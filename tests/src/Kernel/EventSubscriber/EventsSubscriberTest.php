<?php

namespace Drupal\Tests\stanford_migrate\Kernel\EventSubscriber;

use Drupal\KernelTests\KernelTestBase;
use Drupal\migrate\MigrateExecutable;
use Drupal\node\Entity\NodeType;

class EventsSubscriberTest extends KernelTestBase {

  protected static $modules = [
    'test_stanford_migrate',
    'stanford_migrate',
    'migrate_plus',
    'migrate',
    'node',
    'user',
    'system',
  ];

  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installEntitySchema('migration');
    $this->installConfig('test_stanford_migrate');
    $this->installSchema('node', ['node_access']);

    NodeType::create(['type' => 'article'])->save();

    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/test.xml'])
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
      ->set('source.urls', [__DIR__ . '/test2.xml'])
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

  protected function getMigrateExecutable() {
    $manager = \Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $manager->createInstance('stanford_migrate');
    return new MigrateExecutable($migration);
  }

  protected function getNodeCount() {
    $nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadMultiple();
    return count($nodes);
  }

}
