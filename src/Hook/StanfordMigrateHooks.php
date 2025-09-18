<?php

declare(strict_types=1);

namespace Drupal\stanford_migrate\Hook;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\stanford_migrate\StanfordMigrateInterface;

class StanfordMigrateHooks {

  use StringTranslationTrait;

  /**
   */
  public function __construct(protected StanfordMigrateInterface $stanfordMigrate, protected ModuleHandlerInterface $moduleHandler) {}

  /**
   * Help information.
   *
   * @codeCoverageIgnore
   */
  public function help($route_name, RouteMatchInterface $route_match) {
    // Main module help for the stanford_migrate module.
    if ($route_name == 'help.page.stanford_migrate') {
      $output = '';
      $output .= '<h3>' . $this->t('About') . '</h3>';
      $output .= '<p>' . $this->t('Adds more functionality to migrate and migrate plus modules') . '</p>';
      return $output;
    }
  }

  /**
   * When an entity is manually deleted from the database, we want to remove it
   * from the migration mapping.
   */
  #[Hook('entity_delete')]
  public function entityDelete(EntityInterface $entity) {
    $this->stanfordMigrate->deleteEntityFromMigration($entity);
  }

  #[Hook('entity_update')]
  public function entityUpdate(EntityInterface $entity) {
    if ($entity instanceof ContentEntityInterface) {
      $this->stanfordMigrate->clearEntityMigrationCache($entity);
    }
  }

  /**
   * Implements hook_config_readonly_whitelist_patterns().
   */
  #[Hook('config_readonly_whitelist_patterns')]
  public function configReadonlyWhitelistPatterns() {
    $configs = [];
    foreach ($this->stanfordMigrate->getMigrationList() as $group) {
      foreach ($group as $id => $migration) {
        $source_config = $migration->getSourceConfiguration();
        if ($source_config['plugin'] == 'csv') {
          $configs[] = "migrate_plus.migration.$id";
        }
      }
    }
    return $configs;
  }

  /**
   * Implements hook_entity_type_alter().
   */
  #[Hook('entity_type_alter')]
  public function entityTypeAlter(array &$entity_types) {
    if ($this->moduleHandler->moduleExists('migrate_source_csv')) {
      $entity_types['migration']->setFormClass('csv-upload', 'Drupal\stanford_migrate\Form\StanfordMigrateCsvImportForm');
      $entity_types['migration']->setLinkTemplate('csv-upload', '/admin/structure/migrate/manage/{migration_group}/migrations/{migration}/csv-upload');
      $entity_types['migration']->setLinkTemplate('csv-template', '/admin/structure/migrate/manage/{migration_group}/migrations/{migration}/csv-template');
    }
  }

}
