<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate_plus\data_parser;

use Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\StanfordJson;

/**
 * Class StanfordJsonTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\StanfordJson
 */
class StanfordJsonTest extends DataParserTestBase {

  public function testCurrentUrl() {
    $this->dataFetcherContent = json_encode(['foo' => ['bar']]);

    $configuration = [
      'data_fetcher_plugin' => 'url',
      'urls' => ['http://localhost'],
      'item_selector' => '/foo',
      'fields' => [],
    ];
    $plugin = StanfordJson::create(\Drupal::getContainer(), $configuration, '', []);
    $plugin->next();
    $this->assertEquals('http://localhost', $plugin->getCurrentUrl());
  }

}
