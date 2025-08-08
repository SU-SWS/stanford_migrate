<?php

declare(strict_types=1);

namespace Drupal\stanford_migrate\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\migrate\Plugin\MigrateSourceInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationInterface as MigrationEntityInterface;

class StanfordMigrateMigrationHooks {

  /**
   * Migration hooks constructor
   *
   * @param \Drupal\Core\State\StateInterface $state
   */
  public function __construct(protected StateInterface $state) {}

  /**
   * Implements hook_migrate_process_info_alter().
   */
  #[Hook('migrate_process_info_alter')]
  public function migrateProcessInfoAlter(array &$definitions) {
    if (!empty($definitions['file_import'])) {
      $definitions['file_import']['class'] = '\Drupal\stanford_migrate\Plugin\migrate\process\StanfordFileImport';
    }
  }

  /**
   * Implements hook_migrate_source_info_alter().
   */
  #[Hook('migrate_source_info_alter')]
  public function migrateSourceInfoAlter(array &$definitions) {
    $definitions['url']['class'] = '\Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl';
  }

  /**
   * Implements hook_migrate_id_map_info_alter().
   */
  #[Hook('migrate_id_map_info_alter')]
  public function migrateIdMapInfoAlter(&$definitions) {
    $definitions['sql']['class'] = '\Drupal\stanford_migrate\Plugin\migrate\id_map\StanfordSql';
  }

  /**
   * Implements hook_migrate_prepare_row().
   */
  #[Hook('migrate_prepare_row')]
  public function migratePrepareRow(Row $row, MigrateSourceInterface $source, MigrationInterface $migration) {
    // Oauth2 authentication adds a token query parameter into the data urls that changes frequently. Since it
    // changes, the hash of the row  changes without any data actually changing. Fix the row data so that it doesn't
    // contain those tokens.
    $authentication = $row->getSourceProperty('authentication/plugin');
    $current_url = $row->getSourceProperty('current_feed_url');
    if ($current_url && $authentication == 'oauth2') {
      $row->setSourceProperty('current_feed_url', preg_replace('/access_token=.*?&/', '', $current_url));
    }
    // In case the list of urls changes dynamically, lets just remove it from the source data to avoid unnecessary hash
    // changes.
    $row->setSourceProperty('urls', []);
  }

  /**
   * Implements hook_ENTITY_TYPE_access().
   */
  #[Hook('migration_access')]
  public function migrationAccess(MigrationEntityInterface $entity, $operation, AccountInterface $account) {
    if ($operation != 'csv') {
      return AccessResult::neutral();
    }
    $migration_id = $entity->id();
    return AccessResult::allowedIfHasPermission($account, "import $migration_id migration");
  }

  /**
   * Implements hook_ENTITY_TYPE_delete().
   */
  #[Hook('migration_delete')]
  public function migrateionDelete(Migration $entity) {
    // Clean up the state if the migration is deleted.
    $this->state->delete("stanford_migrate.csv.{$entity->id()}");
  }

}
