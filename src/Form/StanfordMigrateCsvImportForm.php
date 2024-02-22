<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\stanford_migrate\StanfordMigrateBatchExecutable;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StanfordMigrateCsvImportForm.
 *
 * @package Drupal\stanford_migrate\Form
 */
class StanfordMigrateCsvImportForm extends EntityForm {

  /**
   * Migration plugin instance that matches the migration entity.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migrationPlugin;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('state'),
      $container->get('file.usage'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * StanfordMigrateCsvImportForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migrationManager
   *   Migration plugin manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   Core state service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $fileUsage
   *   File module usage service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   Entity type manager service.
   */
  public function __construct(protected MigrationPluginManagerInterface $migrationManager, protected StateInterface $state, protected FileUsageInterface $fileUsage, EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;

    /** @var \Drupal\migrate_plus\Entity\MigrationInterface $migration */
    $migration = $this->getRequest()->attributes->get('migration');
    $this->migrationPlugin = $this->migrationManager->createInstance($migration->id());
  }

  /**
   * Check if the user should have access to the form.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   Allowed if the migration is a csv importer.
   */
  public function access(AccountInterface $account): AccessResult {
    $source_plugin = $this->migrationPlugin->getSourcePlugin();
    // If the migration doesn't import csv, there's no reason to allow the form.
    if ($source_plugin->getPluginId() != 'csv') {
      return AccessResult::forbidden();
    }
    $migration_id = $this->migrationPlugin->id();
    return AccessResult::allowedIfHasPermission($account, "import $migration_id migration");
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $migration_id = $this->entity->id();
    $template_link = Link::fromTextAndUrl($this->t('empty CSV template'), $this->entity->toUrl('csv-template'))
      ->toString();
    $previously_uploaded_files = $this->state->get("stanford_migrate.csv.$migration_id", []);

    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('%title - CSV File', ['%title' => $this->entity->label()]),
      '#description' => $this->t('Download an @link for %title.', [
        '@link' => $template_link,
        '%title' => $this->entity->label(),
      ]),
      '#required' => TRUE,
      '#upload_location' => 'public://csv/',
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
    ];
    if (!empty($this->entity->get('source')['csv_help'])) {
      $help = $this->entity->get('source')['csv_help'];
      $form['csv_help'] = [
        '#markup' => is_array($help) ? implode('<br>', $help) : $help,
      ];
    }
    unset($form['actions']['submit']);
    $form['actions']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and Import'),
      '#submit' => ['::submitForm', '::save', '::import'],
      '#weight' => 99,
    ];
    if (!count($previously_uploaded_files)) {
      return $form;
    }

    $form['forget'] = [
      '#type' => 'details',
      '#title' => $this->t('Previously Uploaded Files'),
      '#open' => TRUE,
    ];

    // Create render arrays of links to the files.
    array_walk($previously_uploaded_files, function (&$file) {
      $file = [
        '#theme' => 'file_link',
        '#file' => $this->entityTypeManager->getStorage('file')->load($file),
      ];
    });

    $form['forget']['previous_files'] = [
      '#theme' => 'item_list',
      '#list_type' => 'ul',
      '#items' => $previously_uploaded_files,
    ];
    $form['forget']['previous_files_help']['#markup'] = $this->t('<p><strong>DANGER</strong>: To avoid overwriting any of the previously imported content; check the unique ID column(s) (%ids) on the previously uploaded CSV file. Each imported item (Row) should have the unique values in the columns(s).</p>', ['%ids' => implode(', ', $this->migrationPlugin->getSourceConfiguration()['ids'])]);

    $form['forget']['forget_previous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forget previously imported content.'),
      '#description' => $this->t('<strong>DANGER</strong>: Leave this box unchecked to update existing content based on the unique identifier column(s): %ids.', ['%ids' => implode(', ', $this->migrationPlugin->getSourceConfiguration()['ids'])]),
      '#access' => $this->currentUser()->id() == 1,
    ];
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    // When removing the original file, don't go through validating.
    if (
      $form_state->getTriggeringElement()['#name'] == 'csv_remove_button' ||
      empty($form_state->getValue(['csv', 0]))
    ) {
      return;
    }

    /** @var \Drupal\file\FileInterface $file */
    $file = $this->entityTypeManager->getStorage('file')
      ->load($form_state->getValue(['csv', 0]));

    // Make sure the file uploaded successfully.
    if (!$file || !file_exists($file->getFileUri())) {
      $form_state->setError($form['csv'], $this->t('Unable to load file'));
      return;
    }

    $finput = fopen($file->getFileUri(), 'r');
    $header = fgetcsv($finput);
    fclose($finput);

    // Make sure the file isn't empty. fgetcsv will return false if the file is
    // empty.
    if (!$header) {
      $form_state->setError($form['csv'], $this->t('Unable to fetch the header row from the csv file.'));
      return;
    }

    $migration_fields = $this->migrationPlugin->getSourceConfiguration()['fields'];
    array_walk($migration_fields, function (&$field) {
      $field = $field['selector'];
    });

    // Check the uploaded file headers against the migration source fields to
    // compare. The migrate_source_csv doesn't look at the headers and only uses
    // their position.
    foreach ($header as $key => $header_value) {
      $header_value = preg_replace('/ .*?$/', '', $header_value);

      if (!isset($migration_fields[$key]) || $migration_fields[$key] != $header_value) {
        $form_state->setError($form['csv'], $this->t('Invalid headers order.'));
        return;
      }
    }
  }

  /**
   * {@inheritDoc}
   *
   * Don't save the entity, we'll store the value in state and use it in config
   * overrider.
   */
  public function save(array $form, FormStateInterface $form_state) {
    if ($form_state::hasAnyErrors()) {
      return;
    }

    $this->migrationPlugin->getIdMap()->prepareUpdate();
    $migration_id = $this->entity->id();

    // Previous imported content will be forgotten about.
    if ($form_state->getValue('forget_previous')) {
      // Destroy the database tables to forget all imported content. The tables
      // will be re-created on the next import.
      $this->migrationPlugin->getIdMap()->destroy();

      // Remove the file usage tracking on the previously uploaded files.
      $previous_fids = $this->state->get("stanford_migrate.csv.$migration_id", []);
      if ($previous_fids) {
        $previous_files = $this->entityTypeManager->getStorage('file')
          ->loadMultiple($previous_fids);
        foreach ($previous_files as $previous_file) {
          $this->fileUsage->delete($previous_file, 'stanford_migrate', 'migration', $migration_id);
        }
      }
      $this->state->delete("stanford_migrate.csv.$migration_id");
    }

    $file_id = $form_state->getValue(['csv', 0]);
    if ($file_id) {
      // Mark the file as permanent and save it.
      $file = $this->entityTypeManager->getStorage('file')->load($file_id);
      $file->setPermanent();
      $file->save();
      $this->fixLineBreaks($file->getFileUri());

      // Store the file id into state for use in the config overrider.
      $state = $this->state->get("stanford_migrate.csv.$migration_id", []);
      $state[] = $file_id;
      $this->state->set("stanford_migrate.csv.$migration_id", $state);

      // Track the file usage on the migration.
      $this->fileUsage->add($file, 'stanford_migrate', 'migration', $migration_id);
    }
  }

  /**
   * Replace line breaks with break tags since those columns typically are html.
   *
   * @param string $csv_path
   *   Path to the CSV file.
   */
  protected function fixLineBreaks($csv_path) {
    $reader = Reader::createFromPath($csv_path, 'r');
    $data = [];
    foreach ($reader as $row) {
      foreach ($row as &$column) {
        $column = str_replace(["\r\n", "\n\r", "\n", "\r"], '<br />', $column);
        // Convert Microsoft characters and other obscure characters into
        // UTF-8 Characters.
        $column = iconv('UTF-8', 'ASCII//TRANSLIT', $column);
      }
      $data[] = $row;
    }
    $writer = Writer::createFromPath($csv_path);
    $writer->insertAll($data);
  }

  /**
   * Import the uploaded csv file.
   *
   * @param array $form
   *   Complete form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Submitted form state.
   *
   * @codeCoverageIgnore Not really possible to unit test this.
   */
  public function import(array $form, FormStateInterface $form_state) {
    // Invalidate the migration cache since the file is changing.
    $this->migrationManager->clearCachedDefinitions();
    Cache::invalidateTags(['migration_plugins']);

    $migration_id = $this->entity->id();
    $state = $this->state->get("stanford_migrate.csv.$migration_id", []);

    if (!empty($state)) {

      try {
        /** @var \Drupal\migrate\Plugin\MigrationInterface $migration */
        $migration = $this->migrationManager->createInstance($migration_id);
        $definition = $migration->getPluginDefinition();

        $fid = $form_state->getValue(['csv', 0]);
        $file = $this->entityTypeManager->getStorage('file')->load($fid);
        $definition['source']['path'] = $file->getFileUri();

        $options = ['configuration' => $definition];

        $migrateMessage = new MigrateMessage();
        $executable = new StanfordMigrateBatchExecutable($migration, $migrateMessage, $options);
        $executable->batchImport();
      }
      catch (\Exception $e) {
        $this->messenger()
          ->addError($this->t('Unable to run migration import. See logs for more information'));
        $this->logger($e->getMessage());
      }
    }
  }

}
