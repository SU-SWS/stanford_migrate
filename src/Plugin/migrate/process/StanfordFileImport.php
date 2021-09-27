<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\Component\Utility\Bytes;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_file\Plugin\migrate\process\FileImport;

/**
 * Override the migrate_file module's plugin to add a max_size configuration.
 */
class StanfordFileImport extends FileImport {

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!empty($this->configuration['max_size'])) {
      $allowed = FALSE;
      $headers = get_headers($value, TRUE);

      if (isset($headers['Content-Length'])) {
        $size = is_array($headers['Content-Length']) ? end($headers['Content-Length']) : $headers['Content-Length'];

        if ((int) $size <= Bytes::toNumber($this->configuration['max_size'])) {
          $allowed = TRUE;
        }
      }

      $value = $allowed ? $value : NULL;
    }
    return parent::transform($value, $migrate_executable, $row, $destination_property);
  }

}
