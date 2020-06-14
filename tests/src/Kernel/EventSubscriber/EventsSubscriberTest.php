<?php

namespace Drupal\Tests\stanford_migrate\Kernel\EventSubscriber;

use Drupal\migrate\MigrateExecutable;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;

class EventsSubscriberTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/../test.xml'])
      ->save();
  }

  /**
   * No orphan action.
   */
  public function testEventSubscriber() {
    $migrate = $this->getMigrateExecutable();
    $this->assertEquals(1, $migrate->import());
    $this->assertEqual(1, $this->getNodeCount());

    $migrate->import();
    $this->assertEqual(1, $this->getNodeCount());
  }

  /**
   * Delete action will delete the imported nodes.
   */
  public function testDeleteAction() {
    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEqual(1, $this->getNodeCount());
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [])
      ->set('source.orphan_action', 'delete')
      ->save();

    drupal_flush_all_caches();

    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEqual(0, $this->getNodeCount());
  }

  /**
   * Unpublish action will import the new node but unpublish the old one.
   */
  public function testUnpublishAction() {
    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEqual(1, $this->getNodeCount());
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/../test2.xml'])
      ->set('source.orphan_action', 'unpublish')
      ->save();

    drupal_flush_all_caches();

    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEqual(2, $this->getNodeCount());

    $unpublished_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['status' => 0]);
    $this->assertCount(1, $unpublished_nodes);
  }

  /**
   * Get the migration executable.
   */
  protected function getMigrateExecutable() {
    $manager = \Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $manager->createInstance('stanford_migrate');
    return new MigrateExecutable($migration);
  }

  /**
   * Get the number of nodes created.
   */
  protected function getNodeCount() {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple();
    return count($nodes);
  }

}
