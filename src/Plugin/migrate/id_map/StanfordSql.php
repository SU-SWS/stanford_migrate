<?php

namespace Drupal\stanford_migrate\Plugin\migrate\id_map;

use Drupal\migrate\Plugin\migrate\id_map\Sql;

/**
 * SQL Plugin override to modify the way the methods are used.
 */
class StanfordSql extends Sql {

  /**
   * {@inheritdoc}
   */
  public function getRowByDestination(array $destination_id_values) {
    $query = $this->getDatabase()->select($this->mapTableName(), 'map')
      ->fields('map');

    $conditions = [];
    foreach ($this->destinationIdFields() as $field_name => $destination_id) {
      if (!isset($destination_id_values[$field_name])) {
        // In the parent class, if the destination id values doesn't include
        // every field, we get an empty data. We're overridding it to use
        // whatever values & conditions we can.
        continue;
      }
      $conditions["map.$destination_id"] = $destination_id_values[$field_name];
    }
    if (empty($conditions)) {
      return [];
    }
    foreach ($conditions as $key => $value) {
      $query->condition($key, $value, '=');
    }
    $result = $query->execute()->fetchAssoc();
    return $result ? $result : [];
  }

}
