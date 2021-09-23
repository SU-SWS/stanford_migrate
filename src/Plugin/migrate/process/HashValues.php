<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Hash a string or list of values, useful for unique identifiers.
 *
 * Examples:
 *
 * @code
 * process:
 *   plugin: hash_values
 *   source: some_text_field
 * @endcode
 *
 * @code
 * process:
 *   plugin: hash_values
 *   source:
 *     - some_text_field
 *     - an_array_values
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "hash_values",
 *   handle_multiples = TRUE
 * )
 */
class HashValues extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $value = is_string($value) ? $value : json_encode($value);
    return md5($value);
  }

}
