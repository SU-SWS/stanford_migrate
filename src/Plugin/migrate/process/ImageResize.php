<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileExists;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Attribute\MigrateProcess;
use Drupal\migrate\MigrateException;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Resize an image to a max height and width, replacing the original.
 *
 * This is used when the incoming image size could be very large. The field
 * display of the image might never be more than X, so during the import, resize
 * the image to something more functional, saving file storage and reducing
 * memory when producing image style derivatives.
 *
 * Examples:
 *
 * @code
 * process:
 *   uri:
 *     -
 *      plugin: download
 *      source:
 *        - source
 *        - destination
 *     -
 *       plugin: image_resize
 *       source: '@image'
 *       max_width: 2000
 * @endcode
 *
 * @code
 * process:
 *   field_image:
 *     plugin: file_import
 *     id: only_true
 *   resize:
 *     -
 *       plugin: entity_value
 *       entity_type: file
 *       field_name: uri
 *     -
 *       plugin: extract
 *       index:
 *         - 0
 *         - value
 *     -
 *       plugin: image_resize
 *       max_width: 1000
 *       max_height: 1000
 * @endcode
 */
#[MigrateProcess(id: 'image_resize')]
class ImageResize extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('file_system'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, protected FileSystemInterface $fileSystem, protected EntityTypeManagerInterface $entityTypeManager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    if (!file_exists($value)) {
      throw new MigrateException(sprintf('Image does not exist at path %s', $value));
    }

    if (!str_starts_with(mime_content_type($value), 'image')) {
      throw new MigrateException('Path to file is not an image.');
    }

    $image_style = $this->entityTypeManager->getStorage('image_style')
      ->create(['name' => 'temp', 'label' => 'temp']);
    $effect = [
      'id' => 'image_scale',
      'data' => [
        'width' => $this->configuration['max_width'] ?? NULL,
        'height' => $this->configuration['max_height'] ?? NULL,
      ],
      'weight' => 0,
    ];
    $image_style->addImageEffect($effect);

    $temp = 'temporary://' . basename($value);
    if ($image_style->createDerivative($value, $temp)) {
      $this->fileSystem->move($temp, $value, FileExists::Replace);

      // Resave any file entities using the image to reset the file size data.
      // This should only be 1 file entity at most.
      $files = $this->entityTypeManager->getStorage('file')
        ->loadByProperties(['uri' => $value]);
      foreach ($files as $file) {
        $file->save();
      }
    }
    return $value;
  }

}
