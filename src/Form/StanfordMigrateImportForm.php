<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Drupal\stanford_migrate\StanfordMigrateBatchExecutable;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\migrate\MigrateMessage;
use Drupal\migrate\Plugin\MigrationInterface;

/**
 * Class StanfordMigrateImportForm.
 *
 * @package Drupal\stanford_migrate\Form
 */
class StanfordMigrateImportForm extends FormBase {

  /**
   * Array of migration plugin objects.
   *
   * @var \Drupal\migrate\Plugin\MigrationInterface[]
   */
  protected $migrations;

  /**
   * Key Value collection of last migrations.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreInterface
   */
  protected $lastMigrations;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter'),
      $container->get('current_user'),
      $container->get('plugin.manager.migration'),
      $container->get('keyvalue'),
      $container->get('datetime.time'),
      $container->get('string_translation')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected DateFormatterInterface $dateFormatter,
    protected AccountProxyInterface $account,
    protected MigrationPluginManagerInterface $migrationManager,
    protected KeyValueFactoryInterface $keyValue,
    protected TimeInterface $time,
    protected TranslationManager $translation,
  ) {
    $this->lastMigrations = $this->keyValue->get('migrate_last_imported');

    $migrations = $this->migrationManager->createInstances([]);
    $this->migrations = $migrations;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stanford_migrate_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = [];
    $form['table'] = [
      '#type' => 'table',
      '#header' => $this->buildHeader(),
      '#empty' => $this->t('No migrations found'),
    ];
    // Remove migrations that the user doesn't have access to.
    $migrations = array_filter($this->migrations, fn($migration_id) => $this->account->hasPermission("import $migration_id migration"), ARRAY_FILTER_USE_KEY);
    foreach ($migrations as $migration_id => $migration) {
      $form['table'][$migration_id] = $this->buildRow($migration);
    }

    return $form;
  }

  /**
   * Build the table header labels.
   *
   * @return array
   *   Array of table headers.
   */
  protected function buildHeader() {
    return [
      $this->t('Importer'),
      $this->t('Status'),
      $this->t('Imported Items'),
      $this->t('Last Imported'),
      $this->t('Import'),
    ];
  }

  /**
   * Build the form row for the given migration object.
   *
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   Migration plugin object.
   *
   * @return array
   *   Form render array.
   */
  protected function buildRow(MigrationInterface $migration) {
    $row['label']['#markup'] = sprintf('%s (%s)', $migration->label(), $migration->id());
    $row['status']['#markup'] = $migration->getStatusLabel();
    $row['imported']['#markup'] = $migration->getIdMap()->importedCount();

    if ($last_imported = $this->lastMigrations->get($migration->id(), FALSE)) {
      $row['last_imported']['#markup'] = $this->dateFormatter->format($last_imported / 1000, 'custom', 'M j Y g:i a');
    }
    else {
      $row['last_imported']['#markup'] = $this->t('Unknown');
    }

    $row['operations']['data'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#name' => $migration->id(),
    ];

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $migration_id = $form_state->getTriggeringElement()['#name'];
    $migration = $this->migrations[$migration_id];

    $migration->interruptMigration(MigrationInterface::RESULT_STOPPED);
    $migration->setStatus(MigrationInterface::STATUS_IDLE);

    $migrateMessage = new MigrateMessage();
    $options = [
      'limit' => 0,
      'update' => 0,
      'force' => 0,
    ];
    $this->migrationManager->clearCachedDefinitions();
    Cache::invalidateTags(['migration_plugins']);

    $executable = new StanfordMigrateBatchExecutable($migration, $migrateMessage, $this->keyValue, $this->time, $this->translation, $this->migrationManager, $options);
    $executable->batchImport();
  }

  /**
   * Check if the current user has permission to any migration objects.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Current user.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   Access result.
   */
  public function access(AccountInterface $account) {
    foreach (array_keys($this->migrations) as $migration_id) {
      if ($account->hasPermission("import $migration_id migration")) {
        return AccessResult::allowed();
      }
    }
    return AccessResult::forbidden();
  }

}
