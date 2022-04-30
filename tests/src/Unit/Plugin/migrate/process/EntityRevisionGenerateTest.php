<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\Container;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityReferenceSelection\SelectionPluginManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\stanford_migrate\Plugin\migrate\process\EntityGenerateNoLookup;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigrateDestinationInterface;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\EntityRevisionGenerate;
use Drupal\Tests\UnitTestCase;

/**
 * Class EntityRevisionGenerateTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\EntityRevisionGenerate
 */
class EntityRevisionGenerateTest extends UnitTestCase {

  /**
   * Test the tranform returns an entity.
   */
  public function testTranform() {
    $container = new Container();

    $entity = $this->createMock(RevisionableInterface::class);
    $entity->method('id')->willReturn(123);
    $entity->method('getRevisionId')->willReturn(321);

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('create')
      ->willReturn($entity);
    $entity_storage->method('load')->willReturn($entity);

    $entity_definition = $this->createMock(EntityTypeInterface::class);
    $entity_definition->method('getKey')->willReturn('foo');

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')
      ->willReturn($entity_storage);
    $entity_type_manager->method('getDefinition')
      ->willReturn($entity_definition);

    $field_manager = $this->createMock(EntityFieldManagerInterface::class);

    $container->set('entity_field.manager', $field_manager);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('plugin.manager.entity_reference_selection', $this->createMock(SelectionPluginManagerInterface::class));
    $container->set('plugin.manager.migrate.process', $this->createMock(MigratePluginManager::class));

    $destination_plugin = $this->createMock(MigrateDestinationInterface::class);
    $destination_plugin->method('getPluginId')->willReturn('foo:bar');

    $migration = $this->createMock(MigrationInterface::class);
    $migration->method('getDestinationPlugin')
      ->willReturn($destination_plugin);
    $configuration = [
      'entity_type' => 'type',
      'value_key' => 'key',
    ];
    $definition = [];
    $plugin = EntityRevisionGenerate::create($container, $configuration, 'entity_generate_no_lookup', $definition, $migration);

    $this->assertInstanceOf(EntityGenerateNoLookup::class, $plugin);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $info = $plugin->transform('string', $migrate_executable, $row, 'field_stuff');
    $this->assertEquals(123, $info['target_id']);
    $this->assertEquals(321, $info['target_revision_id']);
  }

}
