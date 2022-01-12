<?php

namespace Drupal\Tests\stanford_migrate\Kernel\EventSubscriber;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;

class EventsSubscriberTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
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
    $this->assertEquals(1, $this->getNodeCount());

    $migrate->import();
    $this->assertEquals(1, $this->getNodeCount());
  }

  /**
   * Delete action will delete the imported nodes.
   */
  public function testDeleteAction() {
    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEquals(1, $this->getNodeCount());
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [])
      ->set('source.orphan_action', 'delete')
      ->save();

    drupal_flush_all_caches();

    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEquals(0, $this->getNodeCount());
  }

  /**
   * Unpublish action will import the new node but unpublish the old one.
   */
  public function testUnpublishAction() {
    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEquals(1, $this->getNodeCount());
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/../test2.xml'])
      ->set('source.orphan_action', 'unpublish')
      ->save();

    drupal_flush_all_caches();

    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEquals(2, $this->getNodeCount());

    $unpublished_nodes = \Drupal::entityTypeManager()
      ->getStorage('node')
      ->loadByProperties(['status' => 0]);
    $this->assertCount(1, $unpublished_nodes);
  }

  /**
   * Forget Orphan action test.
   */
  public function testForgetAction() {
    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    $this->assertEquals(1, $this->getNodeCount());
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/../test2.xml'])
      ->set('source.orphan_action', 'forget')
      ->save();

    drupal_flush_all_caches();

    $migrate = $this->getMigrateExecutable();
    $migrate->import();
    drupal_flush_all_caches();
    $migrate->import();
    $migrate->import();
    $this->assertEquals(2, $this->getNodeCount());

    $manager = \Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $manager->createInstance('stanford_migrate');
    $id_map = $migration->getIdMap();
    $id_map->rewind();

    $number_ignored = 0;
    while ($id_map->current()) {
      $row = $id_map->getRowBySource($id_map->currentSource());
      if ($row['source_row_status'] == MigrateIdMapInterface::STATUS_IGNORED) {
        $number_ignored++;
      }
      $id_map->next();;
    }
    $this->assertGreaterThanOrEqual(2, $id_map->processedCount());
    $this->assertGreaterThanOrEqual(1, $number_ignored);
    $this->assertNotEquals($number_ignored, $id_map->processedCount());
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
