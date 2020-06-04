<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StanfordMigrateUltimateCronForm.
 *
 * @package Drupal\stanford_migrate\Form
 */
class StanfordMigrateUltimateCronForm extends FormBase {

  /**
   * Migration plugin manager service.
   *
   * @var \Drupal\migrate\Plugin\MigrationPluginManager
   */
  protected $migrationManager;

  /**
   * Entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.migration'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * StanfordMigrateUltimateCronForm constructor.
   *
   * @param \Drupal\migrate\Plugin\MigrationPluginManager $migration_manager
   *   Migration plugin manager service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(MigrationPluginManager $migration_manager, EntityTypeManagerInterface $entity_type_manager) {
    $this->migrationManager = $migration_manager;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'stanford_migrate_ultimate_cron';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $existing_configs = $this->configFactory()
      ->listAll('ultimate_cron.job.stanford_migrate_');

    $existing_migration_jobs = [];
    $missing_migration_jobs = [];
    foreach ($this->migrationManager->getDefinitions() as $migration_id => $definition) {
      if (in_array("ultimate_cron.job.stanford_migrate_$migration_id", $existing_configs)) {
        $existing_migration_jobs[$migration_id] = $definition['label'];
        continue;
      }
      $missing_migration_jobs[$migration_id] = $definition['label'];
    }
    $form_state->set('missing_cron_jobs', $missing_migration_jobs);

    $form['existing'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Existing Cron Jobs'),
      '#items' => $existing_migration_jobs,
    ];
    $form['missing'] = [
      '#theme' => 'item_list',
      '#title' => $this->t('Missing Cron Jobs'),
      '#items' => $missing_migration_jobs,
    ];

    if (!empty($missing_migration_jobs)) {
      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Create missing cron jobs'),
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    foreach ($form_state->get('missing_cron_jobs') as $migration_id => $label) {
      $values = [
        'id' => "stanford_migrate_$migration_id",
        'title' => 'Importer: ' . $label,
        'callback' => 'stanford_migrate_ultimate_cron_task',
        'module' => 'stanford_migrate',
      ];
      $this->entityTypeManager->getStorage('ultimate_cron_job')
        ->create($values)->save();
    }
    $this->messenger()
      ->addStatus($this->t('Created cron jobs for the following migration entities: %labels', ['%labels' => implode(',', $form_state->get('missing_cron_jobs'))]));
  }

}
