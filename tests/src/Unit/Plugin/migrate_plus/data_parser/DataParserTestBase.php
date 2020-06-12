<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate_plus\data_parser;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\migrate_plus\DataFetcherPluginInterface;
use Drupal\migrate_plus\DataFetcherPluginManager;
use Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\StanfordJson;
use Drupal\Tests\UnitTestCase;

/**
 * Class DataParserTestBase
 *
 * @package Drupal\Tests\stanford_migrate\Unit\Plugin\migrate_plus\data_parser
 */
abstract class DataParserTestBase extends UnitTestCase {

  /**
   * @var mixed
   */
  protected $dataFetcherContent;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();
$this->dataFetcherContent =
    $data_fetcher = $this->createMock(DataFetcherPluginInterface::class);
    $data_fetcher->method('getResponseContent')
      ->willReturnReference($this->dataFetcherContent);
    $data_fetcher_manager = $this->createMock(DataFetcherPluginManager::class);
    $data_fetcher_manager->method('createInstance')->willReturn($data_fetcher);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.migrate_plus.data_fetcher', $data_fetcher_manager);
    \Drupal::setContainer($container);
  }

}
