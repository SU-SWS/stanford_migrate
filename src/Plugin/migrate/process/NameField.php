<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Parse a string from common name formats.
 *
 * Examples:
 *
 * @code
 * process:
 *   plugin: name_field
 *   source: some_text_field
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "name_field"
 * )
 */
class NameField extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $parser = new \FullNameParser();
    $info = $parser->parse_name($value);

    return [
      'title' => $info['salutation'] ?? '',
      'given' => $info['fname'] ?? '',
      'middle' => $info['mname'] ?? '',
      'family' => $info['lname'] ?? '',
    ];

  }

}
