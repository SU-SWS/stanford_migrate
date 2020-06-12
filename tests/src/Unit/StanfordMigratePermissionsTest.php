<?php

namespace Drupal\tests\stanford_migrate\Unit;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\migrate\Plugin\Migration;
use Drupal\migrate\Plugin\MigrationPluginManager;
use Drupal\stanford_migrate\StanfordMigratePermissions;
use Drupal\Tests\UnitTestCase;

/**
 * Class StanfordMigratePermissionsTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\StanfordMigratePermissions
 */
class StanfordMigratePermissionsTest extends UnitTestCase {

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $migration = $this->createMock(Migration::class);
    $migration->method('getMigrationDependencies')->willReturn([]);
    $migration2 = $this->createMock(Migration::class);
    $migration2->method('getMigrationDependencies')
      ->willReturn(['required' => ['foo']]);

    $migrations = ['foo' => $migration, 'bar' => $migration2];

    $migration_manager = $this->createMock(MigrationPluginManager::class);
    $migration_manager->method('createInstances')->willReturn($migrations);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.migration', $migration_manager);
    $container->set('string_translation', $this->getStringTranslationStub());
    \Drupal::setContainer($container);
  }

  /**
   * Permissions returned should consist of only a subset of migrations.
   */
  public function testPermissions() {
    $permission_service = StanfordMigratePermissions::create(\Drupal::getContainer());
    $permissions = $permission_service->permissions();
    $this->assertCount(1, $permissions);
    $this->assertArrayHasKey('import bar migration', $permissions);
  }

}
