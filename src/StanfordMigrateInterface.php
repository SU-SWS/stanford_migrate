<?php

namespace Drupal\stanford_migrate;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\node\NodeInterface;

interface StanfordMigrateInterface {

  /**
   * Flag to execute any migrations using a batch process.
   *
   * @param bool $batch_execution
   *   Boolean if to run batch execution.
   *
   * @return $this
   *   Self.
   */
  public function setBatchExecution(bool $batch_execution): self;

  /**
   * Execute a migration by its migration ID.
   *
   * @param string $migration_id
   *   Migration entity ID.
   */
  public function executeMigrationId(string $migration_id): void;

  /**
   * Executes a single migration, taken from drush command in migrate_tools.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration to execute.
   * @param string $migration_id
   *   The migration ID (not used, just an artifact of array_walk()).
   * @param array $options
   *   Array of options to pass into the migration import.
   *
   * @see \Drupal\migrate_tools\Drush\Commands\MigrateToolsCommands::executeMigration()
   */
  public function executeMigration(MigrationInterface $migration, string $migration_id, array $options = []): void;

  /**
   * Retrieve a list of active migrations, partially taken from migrate_tools.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface[][]
   *   An array keyed by migration group, each value containing an array of
   *   migrations or an empty array if no migrations match the input criteria.
   *
   * @see \Drupal\migrate_tools\Drush\Commands\MigrateToolsCommands::migrationsList()
   */
  public function getMigrationList(): array;

  /**
   * Get the migration that imported the given node.
   *
   * @deprecated in stanford_media:9.0.5 and is removed from 10.0.0. Use getEntityMigration().
   *
   * @param \Drupal\node\NodeInterface $node
   *   Node entity.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface|null
   *   Migration entity or null if none found.
   */
  public function getNodesMigration(NodeInterface $node): ?MigrationInterface;

  /**
   * Get the migration that imported the given entity.
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   Content entity.
   *
   * @return \Drupal\migrate\Plugin\MigrationInterface|null
   *   Migration entity or null if none found.
   */
  public function getEntityMigration(ContentEntityInterface $entity): ?MigrationInterface;

  /**
   * Remove the record that the given entity was imported from a migration.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   Imported entity.
   */
  public function deleteEntityFromMigration(EntityInterface $entity): void;

}
