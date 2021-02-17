<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipProcessException;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\UrlCheck;
use Drupal\Tests\UnitTestCase;

/**
 * Class UrlCheckTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\UrlCheck
 */
class UrlCheckTest extends UnitTestCase {

  /**
   * Skipping process throws an exception.
   */
  public function testProcess() {
    $plugin = new UrlCheck(['method' => 'process'], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = $plugin->transform('https://google.com', $migrate, $row, NULL);
    $this->assertEquals('https://google.com', $value);

    $this->expectException(MigrateSkipProcessException::class);
    $plugin->transform('Foo Bar', $migrate, $row, NULL);
  }

  /**
   * Skipping a row throws an exception.
   */
  public function testRow() {
    $plugin = new UrlCheck(['method' => 'row'], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = $plugin->transform('https://google.com', $migrate, $row, NULL);
    $this->assertEquals('https://google.com', $value);

    $this->expectException(MigrateSkipRowException::class);
    $plugin->transform('Foo Bar', $migrate, $row, NULL);
  }

}
