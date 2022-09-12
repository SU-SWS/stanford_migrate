<?php

namespace Drupal\Tests\stanford_migrate\Kernel;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Plugin\MigrationPluginManagerInterface;
use Drupal\migrate\Plugin\RequirementsInterface;
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
   * Test importer service methods and node lookup.
   */
  public function testImporter() {
    $this->assertCount(0, Node::loadMultiple());
    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = \Drupal::service('stanford_migrate');
    $service->executeMigrationId('stanford_migrate');

    $nodes = Node::loadMultiple();
    $this->assertCount(1, $nodes);

    // Run it twice to cover the static variable.
    $service->getNodesMigration(reset($nodes));
    $migration = $service->getNodesMigration(reset($nodes));
    $this->assertEquals('stanford_migrate', $migration->id());

    $unrelated_node = Node::create(['type' => 'article', 'title' => 'Foo Bar']);
    $unrelated_node->save();
    $this->assertNull($service->getNodesMigration($unrelated_node));
    $this->assertNull($service->getNodesMigration($unrelated_node));
    $unrelated_node->delete();
  }

  /**
   * Test the migration list method.
   */
  public function testMigrationList() {
    $migration = Migration::load('stanford_migrate');

    $disabled_migration = $migration->createDuplicate();
    $disabled_migration->set('id', 'disabled_migration')
      ->set('status', FALSE)
      ->save();

    $this->assertCount(0, Node::loadMultiple());

    $migration_list = \Drupal::service('stanford_migrate')->getMigrationList();
    $this->assertArrayHasKey('stanford_migrate', $migration_list['stanford_migrate']);
    $this->assertArrayNotHasKey('disabled_migration', $migration_list['stanford_migrate']);

    $disabled_migration->set('status', TRUE)->save();
    drupal_flush_all_caches();

    $migration_list = \Drupal::service('stanford_migrate')->getMigrationList();
    $this->assertArrayHasKey('stanford_migrate', $migration_list['stanford_migrate']);
    $this->assertArrayHasKey('disabled_migration', $migration_list['stanford_migrate']);
  }

  /**
   * Deleting an entity will remove it from the migration map table.
   */
  public function testEntityDelete() {
    \Drupal::service('stanford_migrate')
      ->executeMigrationId('stanford_migrate');
    $map_count = \Drupal::database()
      ->select('migrate_map_stanford_migrate', 'm')
      ->fields('m')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $map_count);

    foreach (Node::loadMultiple() as $node) {
      $node->delete();
    }
    $map_count = \Drupal::database()
      ->select('migrate_map_stanford_migrate', 'm')
      ->fields('m')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(0, $map_count);
  }

  /**
   * Test running a dependent migration before the called migraiton.
   */
  public function testDependentMigration() {
    $migration = Migration::load('stanford_migrate');

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

  /**
   * Batch importers work similarly.
   */
  public function testBatchExecution() {
    $this->assertCount(0, Node::loadMultiple());

    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = \Drupal::service('stanford_migrate');
    $service->setBatchExecution(TRUE)
      ->executeMigrationId('stanford_migrate');

    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();

    $this->assertCount(1, Node::loadMultiple());
  }

  /**
   * Test a migration plugin that fails to check for requirements.
   */
  public function testRequirementCheck() {
    $source_plugin = $this->createMock(RequirementsInterface::class);
    $source_plugin->method('checkRequirements')
      ->willThrowException(new RequirementsException());

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getSourcePlugin')->willReturn($source_plugin);

    $plugin_manager = $this->createMock(MigrationPluginManagerInterface::class);
    $plugin_manager->method('createInstances')->willReturn([$migration]);
    \Drupal::getContainer()->set('plugin.manager.migration', $plugin_manager);

    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = \Drupal::service('stanford_migrate');
    $this->assertEmpty($service->getMigrationList());
  }

}
