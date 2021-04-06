<?php

namespace Drupal\stanford_migrate\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate_plus\Entity\Migration as MigrationEntity;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MigrationCsvTemplate.
 *
 * @package Drupal\stanford_migrate\Controller
 */
class MigrationCsvTemplate extends ControllerBase {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration')
    );
  }

  /**
   * MigrationCsvTemplate constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager
   *   Migration plugin manager service.
   */
  public function __construct(MigrationPluginManagerInterface $migration_manager) {
    $this->migrationManager = $migration_manager;
  }

  /**
   * Get a csv template with a header row for easily populating a datasheet.
   *
   * @param \Drupal\migrate_plus\Entity\Migration $migration
   *   Migration config entity, not the migration plugin.
   *
   * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
   *   The template csv file with only the header row.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getEmptyTemplate(MigrationEntity $migration) {
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration_plugin */
    $migration_plugin = $this->migrationManager->createInstance($migration->id());

    // If the migration is not a CSV importer, run a 404 response.
    if ($migration_plugin->getSourcePlugin()->getPluginId() != 'csv') {
      throw new NotFoundHttpException();
    }

    $csv_headers = [];
    // Build the headers for the csv file.
    foreach ($migration_plugin->getSourceConfiguration()['fields'] as $source_field) {
      $csv_headers[] = sprintf('%s (%s)', $source_field['selector'], $source_field['label']);
    }

    $file_name = $migration->id() . '.csv';
    $template = fopen(sys_get_temp_dir() . '/' . $file_name, 'w+');
    fputcsv($template, $csv_headers);
    fclose($template);

    $headers = [
      'Content-Type' => 'text/csv',
      'Content-Disposition' => 'attachment; filename="' . $file_name . '"',
    ];
    // Return the temporary file as a download.
    return new BinaryFileResponse(sys_get_temp_dir() . '/' . $file_name, 200, $headers, FALSE);
  }

}
