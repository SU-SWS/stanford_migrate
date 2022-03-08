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
    foreach ($this->destinationIdFields() as $field_name => $destination_id) {
      if (!isset($destination_id_values[$field_name])) {
        // In the parent class, if the destination id values doesn't include
        // every field, we get an empty data. We're overridding it to use
        // whatever values & conditions we can.
        continue;
      }
      $query->condition("map.$destination_id", $destination_id_values[$field_name], '=');
    }
    return count($query->conditions()) > 1 ?
      $query->execute()->fetchAssoc() : [];
  }

}
