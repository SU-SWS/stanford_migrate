<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\State\StateInterface;
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
   * Core date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('state'),
      $container->get('date.formatter')
    );
  }

  /**
   * StanfordMigrateCsvImportForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManagerInterface $migration_manager
   *   Migration plugin manager service.
   * @param \Drupal\Core\State\StateInterface $state
   *   Core state service.
   */
  public function __construct(MigrationPluginManagerInterface $migration_manager, StateInterface $state, DateFormatterInterface $date_formatter) {
    $this->migrationManager = $migration_manager;
    $this->state = $state;
    $this->dateFormatter = $date_formatter;

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
    return AccessResult::allowedIf($source_plugin->getPluginId() == 'csv');
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $migration_id = $this->entity->id();
    $form['csv'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('CSV File'),
      '#upload_location' => 'private://csv/',
      '#upload_validators' => ['file_validate_extensions' => ['csv']],
      '#default_value' => array_filter([$this->state->get("stanford_migrate.csv.$migration_id")]),
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // Don't save the entity, we'll store the value in state and use it in a
    // config overrider.
    $file_id = $form_state->getValue(['csv', 0]);
    $migration_id = $this->entity->id();

    $max_age = $this->configFactory()
      ->get('system.file')
      ->get('temporary_maximum_age');
    $this->state->delete("stanford_migrate.csv.$migration_id");

    if ($file_id) {
      $this->state->set("stanford_migrate.csv.$migration_id", $file_id);
      $this->messenger()
        ->addStatus($this->t('File temporarily saved. It will be retained for %max_age', ['%max_age' => $this->dateFormatter->formatInterval($max_age)]));
    }
  }

}
