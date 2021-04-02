<?php

namespace Drupal\stanford_migrate\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MigrateLocalTasks.
 *
 * @package Drupal\stanford_migrate\Plugin\Derivative
 */
class MigrateLocalTasks extends DeriverBase implements ContainerDeriverInterface {

  /**
   * Core module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * MigrateLocalTasks constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Core module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritDoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    if ($this->moduleHandler->moduleExists('migrate_source_csv')) {
      $this->derivatives['entity.migration.csv_upload'] = $base_plugin_definition;
      $this->derivatives['entity.migration.csv_upload']['title'] = 'CSV Upload';
      $this->derivatives['entity.migration.csv_upload']['route_name'] = 'entity.migration.csv_upload';
      $this->derivatives['entity.migration.csv_upload']['base_route'] = 'entity.migration.overview';
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
