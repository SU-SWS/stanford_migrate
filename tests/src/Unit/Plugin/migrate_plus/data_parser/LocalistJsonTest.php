<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate_plus\data_parser;

use Drupal\stanford_migrate\Plugin\migrate_plus\data_parser\LocalistJson;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class LocalistJsonTest extends DataParserTestBase {

  protected function setUp(): void {
    parent::setUp();
    $client = $this->createMock(ClientInterface::class);
    $client->method('request')
      ->will($this->returnCallback([$this, 'getClientResponse']));
    $container = new ContainerBuilder();
    $container->set('http_client', $client);
    \Drupal::setContainer($container);
  }

  public function testJsonParser() {
    $plugin = new TestLocalistJson(['urls' => []], '', []);
    $this->assertEmpty($plugin->getUrls());

    $plugin = new TestLocalistJson([
      'urls' => [
        'foobar',
        'barbaz',
        'bazbar',
      ],
    ], '', []);
    $expected = [
      'foobar?pp=100&page=1',
      'barbaz?pp=100&page=1',
      'barbaz?pp=100&page=2',
      'barbaz?pp=100&page=3',
      'barbaz?pp=100&page=4',
      'bazbar',
    ];
    $this->assertEquals($expected, $plugin->getUrls());
  }

  public function getClientResponse($method, $uri, array $options = []) {
    $data = [];

    switch ($uri) {
      case 'foobar?pp=1':
        $data = ['page' => ['total' => 10]];
        break;

      case 'barbaz?pp=1':
        $data = ['page' => ['total' => 314]];
        break;

      default:
        throw new ClientException('bad data', $this->createMock(RequestInterface::class));
    }

    $guzzle_response = $this->createMock(ResponseInterface::class);
    $guzzle_response->method('getBody')->willReturn(json_encode($data));
    return $guzzle_response;
  }

}

class TestLocalistJson extends LocalistJson {

  public function getUrls() {
    $urls = [];
    foreach ($this->urls as $url) {
      $urls = [...$urls, ...self::getPagedUrls($url)];
    }
    return $urls;
  }

}
