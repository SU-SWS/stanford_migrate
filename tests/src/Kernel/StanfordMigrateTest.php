<?php

namespace Drupal\Tests\stanford_migrate\Kernel;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\node\Entity\Node;

/**
 * Tests for StanfordMigrate service.
 *
 * @coversDefaultClass \Drupal\stanford_migrate\StanfordMigrate
 */
class StanfordMigrateTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/test.xml'])
      ->save();
  }

  /**
   * Test various parts of the service.
   */
  public function testServiceMethods() {
    $migration = Migration::load('stanford_migrate');

    $disabled_migration = $migration->createDuplicate();
    $disabled_migration->set('id', 'disabled_migration')
      ->set('status', false)
      ->save();

    $this->assertCount(0, Node::loadMultiple());

    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = \Drupal::service('stanford_migrate');
    $migration_list = $service->getMigrationList();
    $this->assertArrayHasKey('stanford_migrate', $migration_list['stanford_migrate']);
    $this->assertArrayNotHasKey('disabled_migration', $migration_list['stanford_migrate']);

    $service->executeMigrationId('stanford_migrate');
    $nodes = Node::loadMultiple();
    $this->assertCount(1, $nodes);

    $this->assertEquals('stanford_migrate', $service->getNodesMigration(reset($nodes))->id());
    $this->assertEquals('stanford_migrate', $service->getNodesMigration(reset($nodes))->id());

    $unrelated_node = Node::create(['type' => 'article', 'title' => 'Foo Bar']);
    $unrelated_node->save();
    $this->assertNull($service->getNodesMigration($unrelated_node));
    $unrelated_node->delete();

    $map_count = \Drupal::database()
      ->select('migrate_map_stanford_migrate', 'm')
      ->fields('m')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(count($nodes), $map_count);

    foreach ($nodes as $node) {
      $node->delete();
    }
    $this->assertCount(0, Node::loadMultiple());
    $map_count = \Drupal::database()
      ->select('migrate_map_stanford_migrate', 'm')
      ->fields('m')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $map_count);

    $dependent_migration = $migration->createDuplicate();
    $dependent_migration->set('id', 'cloned_migration')
      ->set('source.urls', [__DIR__ . '/test2.xml'])
      ->save();

    $migration->set('migration_dependencies', ['required' => ['cloned_migration']])
      ->save();
    drupal_flush_all_caches();

    \Drupal::service('stanford_migrate')
      ->executeMigrationId('stanford_migrate');

    $this->assertCount(2, Node::loadMultiple());
  }

}
