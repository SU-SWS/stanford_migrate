<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\migrate\process;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\Row;
use Drupal\migrate_plus\AuthenticationPluginInterface;
use Drupal\migrate_plus\AuthenticationPluginManager;
use Drupal\stanford_migrate\Plugin\migrate\process\OauthAccessToken;
use Drupal\Tests\UnitTestCase;

/**
 * class OauthAccessTokenTest.
 *
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\process\OauthAccessToken
 */
class OauthAccessTokenTest extends UnitTestCase {

  /**
   * Migrate process plugin.
   *
   * @var \Drupal\stanford_migrate\Plugin\migrate\process\OauthAccessToken
   */
  protected $processPlugin;

  /**
   * {@inheritDoc}
   */
  public function setUp() {
    parent::setUp();
    $auth_headers = ['headers' => ['Authorization' => 'Bearer foo-bar-baz']];
    $auth_plugin = $this->createMock(AuthenticationPluginInterface::class);
    $auth_plugin->method('getAuthenticationOptions')->willReturn($auth_headers);

    $auth_manager = $this->createMock(AuthenticationPluginManager::class);
    $auth_manager->method('createInstance')->willReturn($auth_plugin);
    $container = new ContainerBuilder();
    $container->set('plugin.manager.migrate_plus.authentication', $auth_manager);
    $this->processPlugin = OauthAccessToken::create($container, [], '', []);
  }

  /**
   * The process plugin returns the correct string.
   */
  public function testTransform() {
    $migration = $this->createMock(MigrateExecutableInterface::class);
    $row = new Row();
    $row->setSourceProperty('authentication', ['plugin' => 'oauth2']);
    $this->assertEquals('foo-bar-baz', $this->processPlugin->transform('', $migration, $row, ''));
  }

}
