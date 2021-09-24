<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\HashValues;
use Drupal\Tests\UnitTestCase;

/**
 * Class HashValuesTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\HashValues
 */
class HashValuesTest extends UnitTestCase {

  /**
   * Test the transform returns an expected hash value.
   */
  public function testTranform() {
    $configuration = [
      'entity_type' => 'type',
      'value_key' => 'key',
    ];
    $definition = [];
    $plugin = new HashValues($configuration, 'entity_generate_no_lookup', $definition);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $expected = md5('string');
    $hashed = $plugin->transform('string', $migrate_executable, $row, 'field_stuff');
    $this->assertEquals($expected, $hashed);

    $expected = md5(json_encode(['string']));
    $hashed = $plugin->transform(['string'], $migrate_executable, $row, 'field_stuff');
    $this->assertEquals($expected, $hashed);
  }

}
