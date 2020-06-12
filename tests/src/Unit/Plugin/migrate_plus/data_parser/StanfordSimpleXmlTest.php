<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate_plus\data_parser;

use Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\StanfordSimpleXml;

/**
 * Class StanfordSimpleXmlTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\StanfordSimpleXml
 */
class StanfordSimpleXmlTest extends DataParserTestBase {

  /**
   * The current url method will return the correct url.
   */
  public function testCurrentUrl() {
    $this->dataFetcherContent = '<foo><bar>baz</bar></foo>';

    $configuration = [
      'data_fetcher_plugin' => 'url',
      'urls' => ['http://localhost', 'http://foo.bar'],
      'item_selector' => 'foo',
      'fields' => [],
    ];
    $plugin = StanfordSimpleXml::create(\Drupal::getContainer(), $configuration, '', []);
    $plugin->next();
    $this->assertEquals('http://foo.bar', $plugin->getCurrentUrl());
  }

}
