<?php

namespace Drupal\stanford_migrate\EventSubscriber;

use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\Plugin\MigrationInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Class EventsSubscriber.
 */
class EventsSubscriber implements EventSubscriberInterface {

  /**
   * If the migration is configured to delete orphans.
   */
  const ORPHAN_DELETE = 'delete';

  /**
   * If the migration is configured to unpublish orphans.
   */
  const ORPHAN_UNPUBLISH = 'unpublish';

  /**
   * Drupal\Core\Entity\EntityTypeManagerInterface definition.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new MigrateEventsSubscriber object.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
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

      // Look through the current source to see if we can find a match to the
      // existing item.
      foreach ($current_source_ids as $key => $ids) {
        if ($ids == $source_id) {
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
        $destination_ids = $id_map->lookupDestinationIds($id_map->currentSource());
        $destination_ids = array_filter(reset($destination_ids));

        /** @var \Drupal\Core\Entity\ContentEntityInterface[] $entities */
        // $destination_ids should be a single item.
        $entities = $entity_storage->loadMultiple($destination_ids);

        switch ($orphan_action) {
          case self::ORPHAN_DELETE:
            // Delete the entity, then the record in the id map.
            $entity_storage->delete($entities);
            break;

          case self::ORPHAN_UNPUBLISH:
            // Unpublish the orphans.
            foreach ($entities as $entity) {
              if ($entity->hasField($status_key)) {
                $entity->set($status_key, 0)->save();
              }
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
   * @return bool
   *   Delete orphans or not.
   */
  protected function getOrphanAction(MigrationInterface $migration) {
    $source_config = $migration->getSourceConfiguration();

    // The migration entity should have a `delete_orphans` setting in the
    // source config.
    if (isset($source_config['orphan_action']) && $source_config['orphan_action']) {

      // @see \Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl::getAllIds()
      if (method_exists($migration->getSourcePlugin(), 'getAllIds')) {
        return $source_config['orphan_action'];
      }
    }
    return FALSE;
  }

}
