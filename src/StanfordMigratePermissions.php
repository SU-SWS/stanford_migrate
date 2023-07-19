<?php

namespace Drupal\stanford_migrate;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StanfordMigratePermissions.
 *
 * @package Drupal\stanford_migrate
 */
class StanfordMigratePermissions implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Associative array of migrations ids and labels.
   *
   * @var string[]
   */
  protected $migrationIds = [];

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * StanfordMigratePermissions constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrations_manager
   *   Migration plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity Type Manager Service.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function __construct(MigrationPluginManagerInterface $migrations_manager, EntityTypeManagerInterface $entityTypeManager) {
    /** @var \Drupal\migrate_plus\Entity\Migration[] $migration_entities */
    $migration_entities = $entityTypeManager->getStorage('migration')
      ->loadMultiple();
    foreach ($migration_entities as $id => $entity) {
      $this->migrationIds[$id] = $entity->label();
    }

    $migrations = $migrations_manager->createInstances([]);
    foreach ($migrations as $id => $migration) {
      $this->migrationIds[$id] = $migration->label();
    }

    // Some migrations will be run when its dependent migration is ran.
    foreach ($migrations as $migration) {
      $migration_dependencies = $migration->getMigrationDependencies();
      if (empty($migration_dependencies['required'])) {
        continue;
      }
      foreach ($migration->getMigrationDependencies()['required'] as $dependency) {
        unset($this->migrationIds[$dependency]);
      }
    }
  }

  /**
   * Build a list of permissions for the available migrations.
   *
   * @return array
   *   Keyed array of permission data.
   */
  public function permissions() {
    $permissions = [];

    foreach ($this->migrationIds as $migration_id => $label) {
      $permissions["import $migration_id migration"] = [
        'title' => $this->t('Execute Migration %label', ['%label' => $label]),
        'description' => $this->t('Run importer on /import page'),
      ];
    }

    return $permissions;
  }

}
