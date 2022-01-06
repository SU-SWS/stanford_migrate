<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutable;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\SmartDateAllDayAdjust;
use Drupal\Tests\UnitTestCase;

/**
 * Class SmartDateAllDayAdjust.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\SmartDateAllDayAdjust
 */
class SmartDateAllDayAdjustTest extends UnitTestCase {

  /**
   * Test the transform returns an expected hash value.
   */
  public function testTranform() {
    $configuration = [];
    $definition = [];
    $plugin = new SmartDateAllDayAdjust($configuration, 'smartdate_adjust', $definition);

    $migrate_executable = $this->createMock(MigrateExecutable::class);
    $row = $this->createMock(Row::class);
    $start_value = NULL;
    $row->method('get')->willReturnReference($start_value);

    // Some regular values and the start time configuration isn't available.
    $new_value = $plugin->transform(123456, $migrate_executable, $row, '');
    $this->assertEquals(123456, $new_value);

    // The start time configuration is set, but there's no value for it.
    $configuration['start_time'] = 'start';
    $plugin = new SmartDateAllDayAdjust($configuration, 'smartdate_adjust', $definition);
    $new_value = $plugin->transform(123456, $migrate_executable, $row, '');
    $this->assertEquals(123456, $new_value);

    // Now the start time exists, but it's not a midnight value.
    $start_value = 123;
    $new_value = $plugin->transform(123456, $migrate_executable, $row, '');
    $this->assertEquals(123456, $new_value);

    // The start and the end values are the exact same, so the end value should
    // be 23 hours, 59 minutes ahead.
    $timestamp = strtotime('Jan 10 2020 12:00am');
    $start_value = date('c', $timestamp);
    $new_value = $plugin->transform($start_value, $migrate_executable, $row, '');
    $this->assertEquals($timestamp + 60 * 60 * 24 - 60, $new_value);

    // The end value is midnight of the next day, so it should be adjusted to
    // 1 minute prior for it to be "all day"
    $end_timestamp = strtotime('Jan 11 2020 12:00am');
    $end_value = date('c', $end_timestamp);
    $new_value = $plugin->transform($end_value, $migrate_executable, $row, '');
    $this->assertEquals($end_timestamp - 60, $new_value);

    // Start time is midnight, but the end time is at 3PM.
    $end_timestamp = strtotime('Jan 11 2020 3:00pm');
    $end_value = date('c', $end_timestamp);
    $new_value = $plugin->transform($end_value, $migrate_executable, $row, '');
    $this->assertEquals($end_timestamp, $new_value);

    // Start time is not at midnight, but the end time is.
    $start_value = date('c', strtotime('Jan 10 2020 8:00am'));
    $end_timestamp = strtotime('Jan 11 2020 12:00am');
    $end_value = date('c', $end_timestamp);
    $new_value = $plugin->transform($end_value, $migrate_executable, $row, '');
    $this->assertEquals($end_timestamp, $new_value);
  }

}
