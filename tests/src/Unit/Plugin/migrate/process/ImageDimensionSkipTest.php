<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\ImageDimensionSkip;
use Drupal\Tests\UnitTestCase;

/**
 * Class ImageDimensionSkipTest
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\ImageDimensionSkip
 */
class ImageDimensionSkipTest extends UnitTestCase {

  /**
   * If the passed value is not a valid string, it'll trigger a skip.
   */
  public function testNonString(){
    $plugin = new ImageDimensionSkip([
      'width' => 100,
      'method' => 'row',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = ['http://placecorgi.com/260/180'];
    $this->expectException(MigrateSkipRowException::class);
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));
  }

  public function testNonUrl(){
    $plugin = new ImageDimensionSkip([
      'width' => 100,
      'method' => 'row',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $this->expectException(MigrateSkipRowException::class);
    $value = 'foo/bar/baz';
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));
  }

  /**
   * If the image is not the correct dimensions the row should be skipped.
   */
  public function testRowSkip() {
    $plugin = new ImageDimensionSkip([
      'width' => 100,
      'method' => 'row',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = 'http://placecorgi.com/260/180';
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));

    $value = 'http://placecorgi.com/50/50';
    $this->expectException(MigrateSkipRowException::class);
    $plugin->transform($value, $migrate, $row, '');
  }

  /**
   * If the image is not the correct dimensions process plugin should skip.
   */
  public function testProcessSkip() {
    $plugin = new ImageDimensionSkip([
      'width' => 100,
      'method' => 'process',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = 'http://placecorgi.com/260/180';
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));

    $value = 'http://placecorgi.com/50/50';
    $this->expectException(MigrateSkipProcessException::class);
    $plugin->transform($value, $migrate, $row, '');
  }

}
