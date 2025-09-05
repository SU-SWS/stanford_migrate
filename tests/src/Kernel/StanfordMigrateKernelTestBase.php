<?php

namespace Drupal\Tests\stanford_migrate\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Class StanfordMigrateKernelTestBase.
 */
abstract class StanfordMigrateKernelTestBase extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_stanford_migrate',
    'entity_reference_revisions',
    'stanford_migrate',
    'migrate_plus',
    'migrate',
    'node',
    'user',
    'system',
    'ultimate_cron',
    'migrate_source_csv',
    'readonly_field_widget',
  ];

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('migration');
    $this->installEntitySchema('ultimate_cron_job');
    $this->installConfig('stanford_migrate');
    $this->installConfig(['test_stanford_migrate', 'system']);
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'article'])->save();
  }

}
