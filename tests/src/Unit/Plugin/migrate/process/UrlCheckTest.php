<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\MigrateSkipRowException;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\UrlCheck;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\TestWith;

/**
 * Class UrlCheckTest.
 *
 */
class UrlCheckTest extends UnitTestCase {

  /**
   * Skipping process throws an exception.
   */
  #[TestWith(['url' => 'https://google.com', 'skipped' => FALSE])]
  #[TestWith(['url' => ' https://google.com', 'skipped' => TRUE])]
  #[TestWith(['url' => 'Foo Bar', 'skipped' => TRUE])]
  #[TestWith(['url' => 'www.google.com', 'skipped' => TRUE])]
  public function testProcess(string $url, bool $skipped) {
    $plugin = new UrlCheck(['method' => 'process'], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $value = $plugin->transform($url, $migrate, $row, NULL);

    if ($skipped) {
      $this->assertTrue($plugin->isPipelineStopped());
    }
    else {
      $this->assertEquals($url, $value);
    }
  }

  /**
   * Skipping a row throws an exception.
   */
  #[TestWith(['url' => 'https://google.com', 'skipped' => FALSE])]
  #[TestWith(['url' => ' https://google.com', 'skipped' => TRUE])]
  #[TestWith(['url' => 'Foo Bar', 'skipped' => TRUE])]
  #[TestWith(['url' => 'www.google.com', 'skipped' => TRUE])]
  public function testRow(string $url, bool $skipped) {
    $plugin = new UrlCheck(['method' => 'row'], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    if ($skipped) {
      $this->expectException(MigrateSkipRowException::class);
      $plugin->transform($url, $migrate, $row, NULL);
    }
    else {
      $value = $plugin->transform($url, $migrate, $row, NULL);
      $this->assertEquals($url, $value);
    }
  }

}
