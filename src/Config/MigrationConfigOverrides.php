<?php

namespace Drupal\stanford_migrate\Config;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Config\ConfigFactoryOverrideInterface;
use Drupal\Core\Config\StorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\State\StateInterface;

/**
 * Class MigrationConfigOverrides.
 *
 * @package Drupal\stanford_migrate\Config
 */
class MigrationConfigOverrides implements ConfigFactoryOverrideInterface {

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Drupal core state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * MigrationConfigOverrides constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   Drupal core state service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, StateInterface $state) {
    $this->entityTypeManager = $entity_type_manager;
    $this->state = $state;
  }

  /**
   * {@inheritDoc}
   */
  public function loadOverrides($names) {
    $overrides = [];
    foreach ($names as $name) {

      // Only override migration entities.
      if (substr($name, 0, 23) == 'migrate_plus.migration.') {
        $migration_id = pathinfo($name, PATHINFO_EXTENSION);

        // If the state value is not set, don't do any overriding.
        if ($file_ids = $this->state->get("stanford_migrate.csv.$migration_id", [])) {
          $file_storage = $this->entityTypeManager->getStorage('file');

          /** @var \Drupal\file\FileInterface $file */
          // Make sure the file actually exists.
          if ($file = $file_storage->load(end($file_ids))) {
            $overrides[$name]['source']['path'] = $file->getFileUri();
          }
        }
      }
    }
    return $overrides;
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheSuffix() {
    return 'MigrationConfigOverrides';
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheableMetadata($name) {
    return new CacheableMetadata();
  }

  /**
   * {@inheritDoc}
   */
  public function createConfigObject($name, $collection = StorageInterface::DEFAULT_COLLECTION) {
    return NULL;
  }

}
