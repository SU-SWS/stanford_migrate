<?php

namespace Drupal\stanford_migrate;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Installer\InstallerKernel;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerTrait;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
use Drupal\migrate_tools\MigrateExecutable;
use Drupal\node\NodeInterface;

/**
 * Stanford Migrate service to do various migration actions.
 */
class StanfordMigrate implements StanfordMigrateInterface {

  use MessengerTrait;

  /**
   * Logger channel service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Flag to execute any migrations using batch process.
   *
   * @var bool
   */
  protected $batchExecuteMigrations = FALSE;

  /**
   * Already executed migrations.
   *
   * @var string[]
   */
  protected $executedMigrations = [];

  /**
   * Stanford migrate service constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationPluginManager
   *   Migration plugin manager service.
   * @param \Drupal\Core\KeyValueStore\KeyValueFactoryInterface $keyValue ,
   *   Core key value service.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $translation
   *   Translation provider.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   Logger factory.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache
   *   Default cache service.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected MigrationPluginManagerInterface $migrationPluginManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationManager $translation,
    LoggerChannelFactoryInterface $logger_factory,
    protected CacheBackendInterface $cache,
  ) {
    $this->logger = $logger_factory->get('stanford_migrate');
  }

  /**
   * {@inheritDoc}
   */
  public function setBatchExecution(bool $batch_execution): self {
    $this->batchExecuteMigrations = $batch_execution;
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function executeMigrationId(string $migration_id): void {
    $migrations = $this->getMigrationList();
    foreach ($migrations as $migration_list) {
      if (isset($migration_list[$migration_id])) {
        $this->executeMigration($migration_list[$migration_id], $migration_id);
        return;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function executeMigration(MigrationInterface $migration, string $migration_id, array $options = []): void {
    // Reset migration status so that it can be executed again.
    $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
    $migration->setStatus(MigrationInterface::STATUS_IDLE);

    // Keep track of all migrations run during this command so the same
    // migration is not run multiple times.
    $executed_migrations = &$this->executedMigrations;

    // Execute all the required migrations first before running this one.
    $definition = $migration->getPluginDefinition();
    $required_migrations = $definition['migration_dependencies']['required'] ?? [];

    $required_migrations = array_filter($required_migrations, function($value) use ($executed_migrations) {
      return !isset($executed_migrations[$value]);
    });

    if (!empty($required_migrations)) {
      $required_migrations = $this->migrationPluginManager->createInstances($required_migrations);
      $dependency_options = array_merge($options, ['is_dependency' => TRUE]);
      array_walk($required_migrations, [
        $this,
        'executeMigration',
      ], $dependency_options);
      $executed_migrations += $required_migrations;
    }

    // Finally run this migration.

    $log = new MigrateMessage();

    if ($this->batchExecuteMigrations) {
      $executable = new StanfordMigrateBatchExecutable($migration, $log, $this->keyValue, $this->time, $this->translation, $this->migrationPluginManager, $options);
      $executable->batchImport();
    }
    else {
      $executable = new MigrateExecutable($migration, $log, $this->keyValue, $this->time, $this->translation, $options);
      $executable->import();
    }

    $executed_migrations[$migration_id] = $migration_id;
  }

  /**
   * {@inheritDoc}
   */
  public function getMigrationList(): array {
    $migrations = [];
    // Don't run the migrations when drupal is being installed.
    if (InstallerKernel::installationAttempted()) {
      return $migrations;
    }

    $matched_migrations = $this->migrationPluginManager->createInstances([]);
    // Do not return any migrations which fail to meet requirements.
    foreach ($matched_migrations as $id => $migration) {
      $source_plugin = $migration->getSourcePlugin();
      if ($source_plugin instanceof RequirementsInterface) {
        try {
          $source_plugin->checkRequirements();
        }
        catch (RequirementsException $e) {
          $this->logger->error('Unable to execute migration @name: @message', [
            '@name' => $migration->label(),
            '@message' => $e->getMessage(),
          ]);
          unset($matched_migrations[$id]);
        }
      }
    }

    // Sort the matched migrations by group.
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    foreach ($matched_migrations as $id => $migration) {
      $definition = $migration->getPluginDefinition();
      $configured_group_id = $definition['migration_group'] ?? 'default';
      $migrations[$configured_group_id][$id] = $migration;
    }

    return $migrations;
  }

  /**
   * {@inheritDoc}
   */
  public function clearEntityMigrationCache(ContentEntityInterface $entity): void {
    $cacheKey = sprintf('stanford_migrate:%s:%s', $entity->getEntityTypeId(), $entity->id());
    $this->cache->delete($cacheKey);
  }

  /**
   * {@inheritDoc}
   */
  public function getEntityMigration(ContentEntityInterface $entity): ?MigrationInterface {
    $cacheKey = sprintf('stanford_migrate:%s:%s', $entity->getEntityTypeId(), $entity->id());
    if ($cache = $this->cache->get($cacheKey)) {
      return $cache->data;
    }
    $migrations = $this->migrationPluginManager->createInstances([]);

    $entity_id = $entity->getEntityType()->get('entity_keys')['id'];
    $entityMigration = NULL;

    // Loop through the migration entities, build their migration plugins so
    // that we can dig into their source mapping data.
    foreach ($migrations as $migrate) {
      // CSV Imported content can be ignored since it's normally a one time thing.
      if (!$migrate || $migrate->getSourcePlugin()->getPluginId() == 'csv') {
        continue;
      }

      $destination_ids = $migrate->getDestinationPlugin()->getIds();

      // Ignore any migrate plugin that doesn't map to nodes.
      if (isset($destination_ids[$entity_id])) {
        // If the migrate id map returns something, that means this node is tied
        // to this migration. Set the static variable for later references and
        // get out of here.
        $row_data = $migrate->getIdMap()
          ->getRowByDestination([$entity_id => $entity->id()]);
        if (!empty($row_data) && $row_data['source_row_status'] != MigrateIdMapInterface::STATUS_IGNORED) {
          $entityMigration = $migrate;
        }
      }
    }
    $this->cache->set($cacheKey, $entityMigration, Cache::PERMANENT, ['migration-source']);
    return $entityMigration;
  }

  /**
   * {@inheritDoc}
   *
   * @codeCoverageIgnore
   */
  public function getNodesMigration(NodeInterface $node): ?MigrationInterface {
    @trigger_error('getNodesMigration is deprecated in stanford_media:9.1.0 and is removed from 10.0.0. Use getEntityMigration()', E_USER_DEPRECATED);
    return $this->getEntityMigration($node);
  }

  /**
   * {@inheritDoc}
   */
  public function deleteEntityFromMigration(EntityInterface $entity): void {
    if ($entity instanceof ContentEntityInterface) {
      $this->clearEntityMigrationCache($entity);
    }
    foreach ($this->getMigrationList() as $migrations) {
      foreach ($migrations as $migration) {
        $destination = $migration->getDestinationConfiguration();

        // It should always be set. but this is just a safety valve.
        if (!isset($destination['plugin'])) {
          continue;
        }

        if (
          str_starts_with($destination['plugin'], 'entity:') ||
          str_starts_with($destination['plugin'], 'entity_reference_revisions:')
        ) {
          [, $type] = explode(':', $destination['plugin']);

          if ($type == $entity->getEntityTypeId()) {
            $lookup_values = [];
            $lookup_ids = array_keys($migration->getDestinationPlugin()
              ->getIds());

            foreach ($lookup_ids as $id_key) {
              $lookup_values[$id_key] = $entity->get($id_key)->getString();
            }
            $migration->getIdMap()->deleteDestination($lookup_values);
          }
        }
      }
    }
  }

}
