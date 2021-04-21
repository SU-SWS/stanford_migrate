<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
use Drupal\file\FileUsage\FileUsageInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StanfordMigrateCsvImportForm.
 *
 * @package Drupal\stanford_migrate\Form
 */
class StanfordMigrateCsvImportForm extends EntityForm {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManagerInterface
   */
  protected $migrationManager;

  /**
   * Migration plugin instance that matches the migration entity.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface
   */
  protected $migrationPlugin;

  /**
   * Core state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * File module usage service.
   *
   * @var \Drupal\file\FileUsage\FileUsageInterface
   */
  protected $fileUsage;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('state'),
      $container->get('file.usage')
    );
  }

  /**
   * StanfordMigrateCsvImportForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager
   *   Migration plugin manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   Core state service.
   * @param \Drupal\file\FileUsage\FileUsageInterface $file_usage
   *   File module usage service.
   */
  public function __construct(MigrationPluginManagerInterface $migration_manager, StateInterface $state, FileUsageInterface $file_usage) {
    $this->migrationManager = $migration_manager;
    $this->state = $state;
    $this->fileUsage = $file_usage;

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
      '#title' => $this->t('CSV File'),
      '#description' => $this->t('Download an @link for the importer.', ['@link' => $template_link]),
      '#upload_location' => 'public://csv/',
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#default_value' => array_slice($previously_uploaded_files, -1),
    ];

    if (!count($previously_uploaded_files)) {
      return $form;
    }

    $form['forget'] = [
      '#type' => 'details',
      '#title' => $this->t('Previously Uploaded Files'),
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

    $form['forget']['forget_previous'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Forget previously imported content.'),
      '#description' => $this->t('<strong>DANGER</strong>: Leave this box uncheck to update existing content based on the unique identifier column(s): %ids.', ['%ids' => implode(', ', $this->migrationPlugin->getSourceConfiguration()['ids'])]),
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
    // Invalidate the migration cache since the file is changing.
    Cache::invalidateTags(['migration_plugins']);
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
        foreach ($this->entityTypeManager->getStorage('file')->loadMultiple($previous_fids) as $previous_file) {
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

      // Store the file id into state for use in the config overrider.
      $state = $this->state->get("stanford_migrate.csv.$migration_id", []);
      $state[] = $file_id;
      $this->state->set("stanford_migrate.csv.$migration_id", array_unique($state));

      $link = Link::createFromRoute($this->t('import page'), 'stanford_migrate.list')
        ->toString();
      $this->messenger()
        ->addStatus($this->t('File saved. Import the contents on the @link.', ['@link' => $link]));

      // Track the file usage on the migration.
      $this->fileUsage->add($file, 'stanford_migrate', 'migration', $migration_id);
    }
  }

}
