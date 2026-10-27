<?php

namespace Drupal\stanford_migrate\Drush\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl;
use Drush\Attributes as CLI;
use Drush\Boot\DrupalBootLevels;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for Stanford migrations.
 */
#[CLI\Bootstrap(DrupalBootLevels::FULL)]
final class StanfordMigrateCommands extends DrushCommands {

  use AutowireTrait;

  /**
   * Constructs a StanfordMigrateCommands object.
   */
  public function __construct(
    protected readonly MigrationPluginManagerInterface $migrationPluginManager,
  ) {
    parent::__construct();
  }

  /**
   * List the source urls each url based migration will import from.
   *
   * Source plugins can build their urls when the migration runs, so the urls
   * aren't always visible in the migration config.
   */
  #[CLI\Command(name: 'stanford-migrate:urls', aliases: ['sm-urls'])]
  #[CLI\Argument(name: 'migration_ids', description: 'Comma separated list of migration ids. Defaults to all url migrations.')]
  #[CLI\FieldLabels(labels: ['migration' => 'Migration', 'url' => 'Url'])]
  #[CLI\DefaultTableFields(fields: ['migration', 'url'])]
  #[CLI\Usage(name: 'stanford-migrate:urls', description: 'List the urls of all url migrations.')]
  #[CLI\Usage(name: 'stanford-migrate:urls su_stanford_person --format=json', description: 'List the urls of the person importer as json.')]
  public function urls(string $migration_ids = '', array $options = ['format' => 'table']): RowsOfFields {
    $ids = array_filter(array_map('trim', explode(',', $migration_ids)));

    foreach ($ids as $id) {
      if (!$this->migrationPluginManager->hasDefinition($id)) {
        $this->logger()->warning(dt('Migration @id does not exist or is disabled.', ['@id' => $id]));
      }
    }

    $rows = [];
    /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
    foreach ($this->migrationPluginManager->createInstances($ids) as $id => $migration) {
      $source = $migration->getSourcePlugin();
      if (!$source instanceof StanfordUrl) {
        continue;
      }

      try {
        $urls = $source->getSourceUrls();
      }
      catch (\Exception $e) {
        $this->logger()->error(dt('Unable to get the urls for @id: @message', [
          '@id' => $id,
          '@message' => $e->getMessage(),
        ]));
        continue;
      }

      if (empty($urls)) {
        $rows[] = ['migration' => $id, 'url' => ''];
      }
      foreach ($urls as $url) {
        $rows[] = ['migration' => $id, 'url' => $url];
      }
    }
    return new RowsOfFields($rows);
  }

}
