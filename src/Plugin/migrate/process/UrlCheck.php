<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\Component\Utility\UrlHelper;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Skip processing if the provided data is not a url.
 *
 * Examples:
 *
 * @code
 * process:
 *   plugin: url_check
 *   source: some_field
 *   method: process
 * @endcode
 *
 * @MigrateProcessPlugin(
 *   id = "url_check"
 * )
 */
class UrlCheck extends ProcessPluginBase {

  /**
   * SKip processing if the url is not valid.
   *
   * @param mixed $value
   *   Value to check for url.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   Migratable object.
   * @param \Drupal\migrate\Row $row
   *   Migration row.
   * @param string $destination_property
   *   Process Destinatino.
   *
   * @return mixed
   *   Original value.
   *
   * @throws \Drupal\migrate\MigrateSkipProcessException
   */
  public function process($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value) || !UrlHelper::isValid($value)) {
      throw new MigrateSkipProcessException();
    }
    return $value;
  }

  /**
   * SKip current row if the url is not valid.
   *
   * @param mixed $value
   *   Value to check for url.
   * @param \Drupal\migrate\MigrateExecutableInterface $migrate_executable
   *   Migratable object.
   * @param \Drupal\migrate\Row $row
   *   Migration row.
   * @param string $destination_property
   *   Process Destinatino.
   *
   * @return mixed
   *   Original value.
   *
   * @throws \Drupal\migrate\MigrateSkipRowException
   */
  public function row($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (is_array($value) || !UrlHelper::isValid($value)) {
      throw new MigrateSkipRowException();
    }
    return $value;
  }

}
