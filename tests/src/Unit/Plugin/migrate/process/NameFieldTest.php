<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\NameField;
use Drupal\Tests\UnitTestCase;

/**
 * Class NameFieldTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\NameField
 */
class NameFieldTest extends UnitTestCase {

  /**
   * Test the transform returns a structured array.
   */
  public function testTranform() {
    $configuration = [
      'entity_type' => 'type',
      'value_key' => 'key',
    ];
    $definition = [];
    $plugin = new NameField($configuration, 'entity_generate_no_lookup', $definition);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $name_info = $plugin->transform('Mr John Doe', $migrate_executable, $row, 'field_stuff');
    $expected = [
      'title' => 'Mr.',
      'given' => 'John',
      'family' => 'Doe',
    ];
    $this->assertEquals($expected, $name_info);

    $name_info = $plugin->transform('John Doe', $migrate_executable, $row, 'field_stuff');
    $expected = [
      'title' => '',
      'given' => 'John',
      'family' => 'Doe',
    ];
    $this->assertEquals($expected, $name_info);

    $name_info = $plugin->transform('J. Doe', $migrate_executable, $row, 'field_stuff');
    $expected = [
      'title' => '',
      'given' => 'J.',
      'family' => 'Doe',
    ];
    $this->assertEquals($expected, $name_info);

    $name_info = $plugin->transform('Drupal', $migrate_executable, $row, 'field_stuff');
    $expected = [
      'title' => '',
      'given' => '',
      'family' => 'Drupal',
    ];
    $this->assertEquals($expected, $name_info);
  }

}
