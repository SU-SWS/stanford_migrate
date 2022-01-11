<?php

namespace Drupal\stanford_migrate\EventSubscriber;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class EventsSubscriber.
 */
class EventsSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * If the migration is configured to delete orphans.
   */
  const ORPHAN_DELETE = 'delete';

  /**
   * If the migration is configured to unpublish orphans.
   */
  const ORPHAN_UNPUBLISH = 'unpublish';

  /**
   * If the migration is configured to unpublish orphans.
   */
  const ORPHAN_FORGET = 'forget';

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannel|\Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Default cache service.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * Constructs a new MigrateEventsSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Default cache service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, LoggerChannelFactoryInterface $logger_factory, CacheBackendInterface $cache) {
    $this->entityTypeManager = $entity_type_manager;
    $this->logger = $logger_factory->get('stanford_migrate');
    $this->cache = $cache;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_IMPORT] = ['postImport'];
    return $events;
  }

  /**
   * This method is called when the migrate.post_import is dispatched.
   *
   * @param \Drupal\migrate\Event\MigrateImportEvent $event
   *   The dispatched event.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\migrate\MigrateException
   */
  public function postImport(MigrateImportEvent $event) {
    $orphan_action = $this->getOrphanAction($event->getMigration());

    // Migration doesn't have a orphan action, ignore it.
    if (!$orphan_action) {
      return;
    }

    /** @var \Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl $source_plugin */
    $source_plugin = $event->getMigration()->getSourcePlugin();
    $current_source_ids = $source_plugin->getAllIds();

    /** @var \Drupal\migrate\Plugin\migrate\id_map\Sql $id_map */
    $id_map = $event->getMigration()->getIdMap();

    // Get the entity storage handler for the destination entity.
    $destination_config = $event->getMigration()->getDestinationConfiguration();
    // Destination plugin is normally something like `entity:node`.
    [, $type] = explode(':', $destination_config['plugin']);
    $entity_storage = $this->entityTypeManager->getStorage($type);

    // Get the status key from the entity type if we need to unpublish orphans.
    $status_key = $this->entityTypeManager->getDefinition($type)
      ->getKey('status');

    // Start from the beginning.
    $id_map->rewind();

    // Loop through already imported items, find out if they are in the current
    // source, then delete if appropriate.
    while ($id_map->current()) {
      $id_exists_in_source = FALSE;
      // Source key array of the already imported item.
      $source_id = $id_map->currentSource();
      $row = $id_map->getRowBySource($source_id);

      // The current item is already ignored, lets move on to the next one. This
      // is skipped if the migration is ran with `--update` or via the UI with
      // the "Update" checkbox checked.
      if ($row['source_row_status'] == MigrateIdMapInterface::STATUS_IGNORED) {
        $id_map->next();
        continue;
      }

      // Look through the current source to see if we can find a match to the
      // existing item.
      foreach ($current_source_ids as $key => $ids) {
        if (md5(json_encode($ids)) == md5(json_encode($source_id))) {
          // The existing item is in the source, flag it as found and we can
          // reduce the current source ids to make subsequent lookups faster.
          unset($current_source_ids[$key]);
          $id_exists_in_source = TRUE;
          break;
        }
      }

      // The current item was not found in the current source, time to delete
      // it.
      if (!$id_exists_in_source) {
        // Find the entity id from the id map.
        $destination_id = $id_map->lookupDestinationIds($id_map->currentSource());

        // $destination_id should be a single entity id.
        while (is_array($destination_id)) {
          $destination_id = reset($destination_id);
        }

        if (!$destination_id) {
          // Move on to the next existing item.
          $id_map->next();
          continue;
        }

        /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
        $entity = $entity_storage->load($destination_id);
        if (!$entity) {
          // Move on to the next existing item.
          $id_map->next();
          continue;
        }

        switch ($orphan_action) {
          case self::ORPHAN_DELETE:
            $this->logger->notice($this->t('Deleted entity since it no longer exists in the source data. Migration: @migration, Entity Type: @entity_type, Label: @label'), [
              '@migration' => $event->getMigration()->label(),
              '@entity_type' => $type,
              '@label' => $entity->label(),
            ]);

            // Delete the entity, then the record in the id map.
            $entity->delete();
            $id_map->delete($id_map->currentSource());
            break;

          // Tell the migration to ignore the given source ids.
          case self::ORPHAN_FORGET:
            $this->logger->notice($this->t('Entity since it no longer exists in the source data, it will be now be ignored. Migration: @migration, Entity Type: @entity_type, Label: @label'), [
              '@migration' => $event->getMigration()->label(),
              '@entity_type' => $type,
              '@label' => $entity->label(),
            ]);
            $old_row = new Row($id_map->currentSource(), $id_map->currentSource(), TRUE);
            $id_map->saveIdMapping($old_row, [], MigrateIdMapInterface::STATUS_IGNORED);
            break;

          case self::ORPHAN_UNPUBLISH:
            // Unpublish the orphan only if it is currently published.
            if (
              $entity->hasField($status_key) &&
              $entity->get($status_key)->getString()
            ) {
              $entity->setNewRevision();
              if ($entity->hasField('revision_log')) {
                $entity->set('revision_log', 'Unpublished content since it no longer exists in the source data');
              }
              $entity->set($status_key, 0)->save();
              $id_map->setUpdate($id_map->currentSource());

              $this->logger->notice($this->t('Unpublished entity since it no longer exists in the source data. Migration: @migration, Entity Type: @entity_type, Label: @label'), [
                '@migration' => $event->getMigration()->label(),
                '@entity_type' => $type,
                '@label' => $entity->label(),
              ]);
            }
            break;
        }
      }

      // Move on to the next existing item.
      $id_map->next();
    }
  }

  /**
   * Find out if the orphans should be deleted.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   Migration object that finished.
   *
   * @return bool|string
   *   Delete orphans or not.
   */
  protected function getOrphanAction(MigrationInterface $migration) {
    $cid = 'stanford_migrate:' . $migration->id();
    // No need to check the contents of the cache. The cache is just a
    // temporary flag that the orphan action has recently occurred. This
    // will prevent the unnecessary double execution.
    if ($this->cache->get($cid)) {
      return FALSE;
    }

    $source_config = $migration->getSourceConfiguration();

    // The migration entity should have a `delete_orphans` setting in the
    // source config.
    if (isset($source_config['orphan_action']) && $source_config['orphan_action']) {

      // @see \Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl::getAllIds()
      if (method_exists($migration->getSourcePlugin(), 'getAllIds')) {

        // Just set a cache that expires in 5 minutes. This will allow us to
        // just check if the cache exists so that we don't have to run the
        // orphan action more than 1 time.
        $this->cache->set($cid, time(), time() + 60 + 5);
        return $source_config['orphan_action'];
      }
    }
    return FALSE;
  }

}
