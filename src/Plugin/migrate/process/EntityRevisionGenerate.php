<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\RevisionableInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;

/**
 * Similar to entity generate plugin, but returns a keyed array with revision.
 *
 * @MigrateProcessPlugin(
 *   id = "entity_revision_generate"
 * )
 *
 * @see \Drupal\stanford_migrate\Plugin\migrate\process\EntityGenerateNoLookup
 *
 * All the configuration from the entity generate plugin applies here.
 *
 * Example usage with default_values configuration:
 * @code
 * destination:
 *   plugin: 'entity:node'
 * process:
 *   type:
 *     plugin: default_value
 *     default_value: page
 *   field_tags:
 *     plugin: entity_revision generate
 *     source: tags
 *     default_values:
 *       description: Default description
 *       field_long_description: Default long description
 * @endcode
 */
class EntityRevisionGenerate extends EntityGenerateNoLookup {

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    $configuration['id_key'] = $configuration['id_key'] ?? 'target_id';
    $configuration['vid_key'] = $configuration['vid_key'] ?? 'target_revision_id';
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrateExecutable, Row $row, $destinationProperty) {
    $result = parent::transform($value, $migrateExecutable, $row, $destinationProperty);
    $entity = $this->entityTypeManager->getStorage($this->lookupEntityType)
      ->load($result);
    return [
      $this->configuration['id_key'] => $result,
      $this->configuration['vid_key'] => $entity instanceof RevisionableInterface ? $entity->getRevisionId() : NULL,
    ];
  }

}
