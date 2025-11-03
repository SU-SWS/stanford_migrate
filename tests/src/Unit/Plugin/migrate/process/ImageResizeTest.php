<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\ImageResize;
use Drupal\Tests\UnitTestCase;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;

/**
 * Tests the ImageResize process plugin.
 */
class ImageResizeTest extends UnitTestCase {

  /**
   * Virtual file system.
   *
   * @var \org\bovigo\vfs\vfsStreamDirectory
   */
  protected vfsStreamDirectory $fileSystem;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Set up a virtual file system.
    $this->fileSystem = vfsStream::setup('root');
  }

  /**
   * Tests that the plugin throws an exception for non-existent files.
   */
  public function testNonExistentFile(): void {
    $file_system = $this->createMock(FileSystemInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $container = new ContainerBuilder();
    $container->set('file_system', $file_system);
    $container->set('entity_type.manager', $entity_type_manager);

    $configuration = ['image_style' => 'large'];
    $plugin = ImageResize::create($container, $configuration, 'image_resize', []);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('Image does not exist at path');
    $plugin->transform('/path/to/nonexistent/file.jpg', $migrate_executable, $row, 'field_image');
  }

  /**
   * Tests that the plugin throws an exception for non-image files.
   */
  public function testNonImageFile(): void {
    // Create a text file in the virtual file system.
    $file = vfsStream::newFile('test.txt')
      ->withContent('This is a text file')
      ->at($this->fileSystem);

    $file_system = $this->createMock(FileSystemInterface::class);
    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);

    $configuration = ['image_style' => 'large'];
    $plugin = new ImageResize($configuration, 'image_resize', [], $file_system, $entity_type_manager);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $this->expectException(MigrateException::class);
    $this->expectExceptionMessage('Path to file is not an image');
    $plugin->transform($file->url(), $migrate_executable, $row, 'field_image');
  }

  /**
   * Tests that the plugin returns original value for non-existent image style.
   */
  public function testNonExistentImageStyle(): void {
    // Create a PNG image in the virtual file system.
    $image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    $file = vfsStream::newFile('test.png')
      ->withContent($image_data)
      ->at($this->fileSystem);

    $image_style = $this->createMock(ImageStyleInterface::class);

    $file_system = $this->createMock(FileSystemInterface::class);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('create')
      ->willReturn($image_style);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('image_style')
      ->willReturn($storage);

    $container = new ContainerBuilder();
    $container->set('file_system', $file_system);
    $container->set('entity_type.manager', $entity_type_manager);

    $configuration = ['max_width' => 2000];
    $plugin = ImageResize::create($container, $configuration, 'image_resize', []);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $result = $plugin->transform($file->url(), $migrate_executable, $row, 'field_image');
    $this->assertEquals($file->url(), $result);
  }

  /**
   * Tests successful image resizing with a valid image style.
   */
  public function testSuccessfulImageResize(): void {
    // Create a PNG image in the virtual file system.
    $image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    $file = vfsStream::newFile('test.png')
      ->withContent($image_data)
      ->at($this->fileSystem);
    $file_url = $file->url();

    $image_style = $this->createMock(ImageStyleInterface::class);
    $image_style->expects($this->once())
      ->method('createDerivative')
      ->with($file_url, $this->stringContains('temporary://'))
      ->willReturn(TRUE);

    $image_storage = $this->createMock(EntityStorageInterface::class);
    $image_storage->expects($this->once())
      ->method('create')
      ->willReturn($image_style);

    $file_entity = $this->createMock(FileInterface::class);
    $file_entity->expects($this->once())
      ->method('save');

    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['uri' => $file_url])
      ->willReturn([$file_entity]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnCallback(function ($entity_type) use ($image_storage, $file_storage) {
        return $entity_type === 'image_style' ? $image_storage : $file_storage;
      });

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())
      ->method('move')
      ->with($this->stringContains('temporary://'), $file_url, FileExists::Replace);

    $configuration = ['image_style' => 'test_style'];
    $plugin = new ImageResize($configuration, 'image_resize', [], $file_system, $entity_type_manager);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $result = $plugin->transform($file_url, $migrate_executable, $row, 'field_image');
    $this->assertEquals($file_url, $result);
  }

  /**
   * Tests that the plugin handles image derivative creation failure.
   */
  public function testImageResizeDerivativeFailure(): void {
    // Create a PNG image in the virtual file system.
    $image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    $file = vfsStream::newFile('test.png')
      ->withContent($image_data)
      ->at($this->fileSystem);
    $file_url = $file->url();

    $image_style = $this->createMock(ImageStyleInterface::class);
    $image_style->expects($this->once())
      ->method('createDerivative')
      ->willReturn(FALSE);

    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->expects($this->once())
      ->method('create')
      ->willReturn($image_style);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->once())
      ->method('getStorage')
      ->with('image_style')
      ->willReturn($storage);

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->never())
      ->method('move');

    $configuration = ['image_style' => 'test_style'];
    $plugin = new ImageResize($configuration, 'image_resize', [], $file_system, $entity_type_manager);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $result = $plugin->transform($file_url, $migrate_executable, $row, 'field_image');
    $this->assertEquals($file_url, $result);
  }

  /**
   * Tests resizing when no file entities exist for the URI.
   */
  public function testImageResizeNoFileEntities(): void {
    // Create a PNG image in the virtual file system.
    $image_data = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg==');
    $file = vfsStream::newFile('test.png')
      ->withContent($image_data)
      ->at($this->fileSystem);
    $file_url = $file->url();

    $image_style = $this->createMock(ImageStyleInterface::class);
    $image_style->expects($this->once())
      ->method('createDerivative')
      ->willReturn(TRUE);

    $image_storage = $this->createMock(EntityStorageInterface::class);
    $image_storage->expects($this->once())
      ->method('create')
      ->willReturn($image_style);

    $file_storage = $this->createMock(EntityStorageInterface::class);
    $file_storage->expects($this->once())
      ->method('loadByProperties')
      ->with(['uri' => $file_url])
      ->willReturn([]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->expects($this->exactly(2))
      ->method('getStorage')
      ->willReturnCallback(function ($entity_type) use ($image_storage, $file_storage) {
        return $entity_type === 'image_style' ? $image_storage : $file_storage;
      });

    $file_system = $this->createMock(FileSystemInterface::class);
    $file_system->expects($this->once())
      ->method('move')
      ->with($this->stringContains('temporary://'), $file_url, FileExists::Replace);

    $configuration = ['image_style' => 'test_style'];
    $plugin = new ImageResize($configuration, 'image_resize', [], $file_system, $entity_type_manager);

    $migrate_executable = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $result = $plugin->transform($file_url, $migrate_executable, $row, 'field_image');
    $this->assertEquals($file_url, $result);
  }

}
