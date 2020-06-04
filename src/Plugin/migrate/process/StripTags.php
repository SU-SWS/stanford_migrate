<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Strip tags from some markup.
 *
 * Available configuration keys:
 * - allowed: List of allowed tags.
 *
 * Examples:
 *
 * @code
 * process:
 *   plugin: strip_tags
 *   source: some_text_field
 *   allowed: "<p><div>"
 * @endcode
 *
 * This will strip all tags except p & div tags.
 *
 * @MigrateProcessPlugin(
 *   id = "strip_tags"
 * )
 */
class StripTags extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!is_string($value)) {
      return '';
    }
    return strip_tags($value, $this->configuration['allowed'] ?? NULL);
  }

}
