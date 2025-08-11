<?php

namespace Drupal\Tests\stanford_migrate\Kernel;

use Drupal\user\RoleInterface;

/**
 * Tests for StanfordMigrate service.
 *
 */
class StanfordMigrateTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();
    $this->config('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/test.xml'])
      ->save();
    $this->config('migrate_plus.migration.stanford_migrate_2')
      ->set('source.urls', [__DIR__ . '/test.xml'])
      ->save();

    $this->container->get('entity_type.manager')
      ->getStorage('user_role')
      ->create(['id' => RoleInterface::AUTHENTICATED_ID])
      ->save();
    user_role_grant_permissions(RoleInterface::AUTHENTICATED_ID, ['import stanford_migrate migration']);
  }

  public function testConfigReadonly() {
    $patterns = $this->container->get('module_handler')
      ->invoke('stanford_migrate', 'config_readonly_whitelist_patterns');
    $this->assertEmpty($patterns);

    $migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate');
    $this->assertFalse($migration->access('import'));

    $source_config = $migration->get('source');
    $source_config['plugin'] = 'csv';
    $source_config['path'] = sys_get_temp_dir() . '/foo.csv';
    $source_config['ids'] = ['foo'];
    $migration->set('source', $source_config)->save();

    $user = $this->container->get('entity_type.manager')
      ->getStorage('user')
      ->create([
        'name' => 'admin',
        'roles' => [RoleInterface::AUTHENTICATED_ID],
      ]);
    $user->activate();
    $user->save();
    $this->container->get('current_user')->setAccount($user);

    $this->assertTrue($migration->access('csv'));

    $patterns = $this->container->get('module_handler')
      ->invoke('stanford_migrate', 'config_readonly_whitelist_patterns');
    $this->assertEquals(['migrate_plus.migration.stanford_migrate'], $patterns);

    $this->container->get('state')
      ->set('stanford_migrate.csv.stanford_migrate', ['foo']);
    $this->assertEquals(['foo'], $this->container->get('state')
      ->get('stanford_migrate.csv.stanford_migrate'));
    $migration->delete();

    $this->assertNull($this->container->get('state')
      ->get('stanford_migrate.csv.stanford_migrate'));
  }

  /**
   * Test importer service methods and node lookup.
   */
  public function testImporter() {
    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');

    $this->assertCount(0, $node_storage->loadMultiple());
    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = $this->container->get('stanford_migrate');
    $service->executeMigrationId('stanford_migrate');

    $nodes = $node_storage->loadMultiple();
    $this->assertCount(1, $nodes);

    // Run it twice to cover the static variable.
    $service->getNodesMigration(reset($nodes));
    $migration = $service->getNodesMigration(reset($nodes));
    $this->assertEquals('stanford_migrate', $migration->id());

    $unrelated_node = $node_storage->create([
      'type' => 'article',
      'title' => 'Foo Bar',
    ]);
    $unrelated_node->save();
    $this->assertNull($service->getNodesMigration($unrelated_node));
    $this->assertNull($service->getNodesMigration($unrelated_node));
    $unrelated_node->delete();
  }

  /**
   * Test the migration list method.
   */
  public function testMigrationList() {
    $migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate');

    $disabled_migration = $migration->createDuplicate();
    $disabled_migration->set('id', 'disabled_migration')
      ->set('status', FALSE)
      ->save();

    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $this->assertCount(0, $node_storage->loadMultiple());

    $migration_list = $this->container->get('stanford_migrate')
      ->getMigrationList();
    $this->assertArrayHasKey('stanford_migrate', $migration_list['stanford_migrate']);
    $this->assertArrayNotHasKey('disabled_migration', $migration_list['stanford_migrate']);

    $disabled_migration->set('status', TRUE)->save();
    drupal_flush_all_caches();

    $migration_list = $this->container->get('stanford_migrate')
      ->getMigrationList();
    $this->assertArrayHasKey('stanford_migrate', $migration_list['stanford_migrate']);
    $this->assertArrayHasKey('disabled_migration', $migration_list['stanford_migrate']);
  }

  /**
   * Deleting an entity will remove it from the migration map table.
   */
  public function testEntityDelete() {
    $this->container->get('stanford_migrate')
      ->executeMigrationId('stanford_migrate');
    $map_count = $this->container->get('database')
      ->select('migrate_map_stanford_migrate', 'm')
      ->fields('m')
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals(1, $map_count);

    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    foreach ($node_storage->loadMultiple() as $node) {
      $node->delete();
    }
    $map_count = $this->container->get('database')
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
    $migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate');
    $migration->set('migration_dependencies', ['required' => ['stanford_migrate_2']])
      ->save();

    $service = $this->container->get('stanford_migrate');
    $service->executeMigrationId('stanford_migrate');

    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $this->assertCount(2, $node_storage->loadMultiple());
  }

  /**
   * Batch importers work similarly.
   */
  public function testBatchExecution() {
    $migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate');
    $migration->set('migration_dependencies', ['required' => ['stanford_migrate_2']])
      ->save();

    $node_storage = $this->container->get('entity_type.manager')
      ->getStorage('node');
    $this->assertCount(0, $node_storage->loadMultiple());

    /** @var \Drupal\stanford_migrate\StanfordMigrateInterface $service */
    $service = $this->container->get('stanford_migrate');
    $service->setBatchExecution(TRUE)
      ->executeMigrationId('stanford_migrate');

    $batch = &batch_get();
    $batch['progressive'] = FALSE;
    batch_process();

    $this->assertCount(2, $node_storage->loadMultiple());
  }

  /**
   * Test a migration plugin that fails to check for requirements.
   */
  public function testRequirementCheck() {
    $migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate');
    $migration->set('migration_dependencies', ['required' => ['stanford_migrate_2']])
      ->save();

    $source_config = $migration->get('source');
    $source_config['plugin'] = 'table';
    $source_config['table_name'] = 'foo';
    $source_config['id_fields'] = $source_config['ids'];

    $fail_migration = $this->container->get('entity_type.manager')
      ->getStorage('migration')
      ->load('stanford_migrate_2');
    $fail_migration->set('source', $source_config)
      ->save();

    $this->assertCount(1, $this->container->get('stanford_migrate')
      ->getMigrationList());
  }

}
