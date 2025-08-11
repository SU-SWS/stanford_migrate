<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\stanford_migrate\Plugin\migrate\process\StripTags;
use Drupal\Tests\UnitTestCase;

/**
 * Class StripTagsTest
 *
 */
class StripTagsTest extends UnitTestCase {

  /**
   * Transform should strip appropriate tags.
   */
  public function testTranform() {
    $plugin = new StripTags(['allowed' => '<p> <a>'], '', []);
    $migrate = $this->createMock(MigrateExecutableInterface::class);
    $row = $this->createMock(Row::class);

    $this->assertEquals('', $plugin->transform([], $migrate, $row, ''));

    $value = '<div>Div<p>Paragraph<a>Link</a></p>';
    $this->assertEquals('Div<p>Paragraph<a>Link</a></p>', $plugin->transform($value, $migrate, $row, ''));
  }

}
