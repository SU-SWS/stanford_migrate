<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\ContainerBuilder;
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
    $process_manager = $this->createMock(MigratePluginManager::class);
    $process_manager->method('createInstance')->willReturn($process_plugin);
    $container->set('stream_wrapper_manager', $stream_manager);
    $container->set('file_system', $file_system);
    $container->set('plugin.manager.migrate.process', $process_manager);
    $configuration = ['max_size' => '1B'];
    $definition = [];
    $plugin = StanfordFileImport::create($container, $configuration, 'entity_generate_no_lookup', $definition);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $this->assertNull($plugin->transform('https://identity.stanford.edu/wp-content/uploads/2020/06/wordmark-nospace-red.png', $migrate_executable, $row, 'field_stuff'));
    $this->assertNull($plugin->transform('https://placeimg.com/640/480/any', $migrate_executable, $row, 'field_stuff'));
  }

}
