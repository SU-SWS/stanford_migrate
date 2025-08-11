<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\ImageDimensionSkip;
use Drupal\Tests\UnitTestCase;

/**
 * Class ImageDimensionSkipTest
 *
 */
class ImageDimensionSkipTest extends UnitTestCase {

  /**
   * If the passed value is not a valid string, it'll trigger a skip.
   */
  public function testNonString() {
    $plugin = new TestImageDimensionSkip([
      'width' => 100,
      'method' => 'row',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = ['260x180'];
    $this->expectException(MigrateSkipRowException::class);
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));
  }

  public function testNonUrl() {
    $plugin = new TestImageDimensionSkip([
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
    $plugin = new TestImageDimensionSkip([
      'width' => 100,
      'method' => 'row',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = '260x180';
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));

    $value = '50x50';
    $this->expectException(MigrateSkipRowException::class);
    $plugin->transform($value, $migrate, $row, '');
  }

  /**
   * If the image is not the correct dimensions process plugin should skip.
   */
  public function testProcessSkip() {
    $plugin = new TestImageDimensionSkip([
      'width' => 100,
      'method' => 'process',
    ], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = '260x180';
    $this->assertEquals($value, $plugin->transform($value, $migrate, $row, ''));

    $value = '50x50';
    $plugin->transform($value, $migrate, $row, '');
    $this->assertTrue($plugin->isPipelineStopped());
  }

}

class TestImageDimensionSkip extends ImageDimensionSkip {

  protected function getImageSize(string $url): bool|array {
    return str_contains($url, 'x') ? explode('x', $url) : FALSE;
  }

}
