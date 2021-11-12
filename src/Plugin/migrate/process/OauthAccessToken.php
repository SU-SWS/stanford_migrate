<?php

namespace Drupal\stanford_migrate\Plugin\migrate\process;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;
use Drupal\migrate_plus\AuthenticationPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Migrate process plugin that will return the bearer token for OAuth2 auth.
 *
 * This requires the migration to be configured for oauth2 authentication, or
 * pass the oauth2 credentials into the process plugin. The schema for both
 * are exactly the same.
 *
 * @see \Drupal\migrate_plus\Plugin\migrate_plus\authentication\OAuth2
 *
 * @MigrateProcessPlugin(
 *   id = "oauth2_access_token"
 * )
 */
class OauthAccessToken extends ProcessPluginBase implements ContainerFactoryPluginInterface {

  /**
   * Authentication plugin manager service.
   *
   * @var \Drupal\migrate_plus\AuthenticationPluginManager
   */
  protected $authPluginManager;

  /**
   * Authentication plugin.
   *
   * @var \Drupal\migrate_plus\AuthenticationPluginInterface
   */
  protected $authenticationPlugin;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.migrate_plus.authentication')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, AuthenticationPluginManager $auth_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->authPluginManager = $auth_manager;
  }

  /**
   * {@inheritDoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $this->configuration += $row->getSource();
    $options = $this->getAuthenticationPlugin()->getAuthenticationOptions();
    preg_match('/ .*$/', $options['headers']['Authorization'], $bearer_match);
    return trim(reset($bearer_match));
  }

  /**
   * Returns the initialized authentication plugin.
   *
   * @return \Drupal\migrate_plus\AuthenticationPluginInterface
   *   The authentication plugin.
   */
  public function getAuthenticationPlugin() {
    if (!isset($this->authenticationPlugin)) {
      $this->authenticationPlugin = $this->authPluginManager->createInstance($this->configuration['authentication']['plugin'], $this->configuration['authentication']);
    }
    return $this->authenticationPlugin;
  }

}
