<?php

namespace Drupal\stanford_migrate\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class StanfordMigrateUltimateCronForm.
 *
 * @package Drupal\stanford_migrate\Form
 */
class StanfordMigrateUltimateCronForm extends FormBase {

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
      $container->get('entity_type.manager')
    );
  }

  /**
   * StanfordMigrateUltimateCronForm constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   Entity type manager service.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
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

    $migration_group_configs = $this->configFactory->listAll('migrate_plus.migration_group.');
    foreach ($migration_group_configs as $config_name) {
      $group_config = $this->config($config_name);
      $migration_group = $group_config->get('id');
      if (in_array("ultimate_cron.job.stanford_migrate_$migration_group", $existing_configs)) {
        $existing_migration_jobs[$migration_group] = $group_config->get('label');
        continue;
      }
      $missing_migration_jobs[$migration_group] = $group_config->get('label');
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
    foreach ($form_state->get('missing_cron_jobs') as $group_id => $label) {
      $values = [
        'id' => "stanford_migrate_$group_id",
        'title' => 'Importer: ' . $label,
        'callback' => 'stanford_migrate_ultimate_cron_task',
        'module' => 'stanford_migrate',
      ];
      $this->entityTypeManager->getStorage('ultimate_cron_job')
        ->create($values)->save();
    }
    $this->messenger()
      ->addStatus($this->t('Created cron jobs for the following migration entities: %labels', ['%labels' => implode(', ', $form_state->get('missing_cron_jobs'))]));
  }

}
