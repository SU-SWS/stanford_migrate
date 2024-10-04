<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeRepositoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\StreamWrapper\StreamWrapperManagerInterface;
use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Plugin\MigratePluginManager;
use Drupal\migrate\Plugin\MigrateProcessInterface;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\StanfordFileImport;
use Drupal\Tests\UnitTestCase;

/**
 * Class StanfordFileImportTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\StanfordFileImport
 */
class StanfordFileImportTest extends UnitTestCase {

  /**
   * Test the transform returns an expected hash value.
   */
  public function testTranform() {
    $container = new ContainerBuilder();
    $stream_manager = $this->createMock(StreamWrapperManagerInterface::class);
    $file_system = $this->createMock(FileSystemInterface::class);
    $process_plugin = $this->createMock(MigrateProcessInterface::class);
    $process_plugin->method('transform')->willReturn('public://foo.jpg');
    $process_manager = $this->createMock(MigratePluginManager::class);
    $process_manager->method('createInstance')->willReturn($process_plugin);
    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('create')->willReturn($this->createMock(ContentEntityInterface::class));
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getStorage')->willReturn($entity_storage);
    $entity_type_repo = $this->createMock(EntityTypeRepositoryInterface::class);

    $container->set('stream_wrapper_manager', $stream_manager);
    $container->set('file_system', $file_system);
    $container->set('plugin.manager.migrate.process', $process_manager);
    $container->set('entity_type.manager', $entity_type_manager);
    $container->set('entity_type.repository', $entity_type_repo);
    \Drupal::setContainer($container);

    $configuration = ['max_size' => '1B'];
    $definition = [];
    $plugin = StanfordFileImport::create($container, $configuration, 'entity_generate_no_lookup', $definition);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $this->assertNull($plugin->transform('https://identity.stanford.edu/wp-content/uploads/sites/3/2020/07/block-s-right.png', $migrate_executable, $row, 'field_stuff'));
    $this->assertNull($plugin->transform('https://content.sites.stanford.edu/sites/g/files/sbiybj27591/files/media/image/placeholder.png', $migrate_executable, $row, 'field_stuff'));


    $configuration = ['max_size' => '10MB'];
    $definition = [];
    $plugin = StanfordFileImport::create($container, $configuration, 'entity_generate_no_lookup', $definition);
    $this->assertNotEmpty($plugin->transform('https://identity.stanford.edu/wp-content/uploads/sites/3/2020/07/block-s-right.png', $migrate_executable, $row, 'field_stuff'));
  }

}
