<?php

namespace Drupal\Tests\stanford_migrate\Unit\Plugin\Derivative;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\stanford_migrate\Plugin\Derivative\MigrateLocalTasks;
use Drupal\Tests\UnitTestCase;

/**
 * Class MigrateLocalTasksTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\Derivative\MigrateLocalTasks
 */
class MigrateLocalTasksTest extends UnitTestCase {

  /**
   * If the migrate_source_csv module exists.
   *
   * @var bool
   */
  protected $moduleExists = FALSE;

  /**
   * {@inheritDoc}
   */
  public function setup(): void {
    parent::setUp();

    $module_handler = $this->createMock(ModuleHandlerInterface::class);
    $module_handler->method('moduleExists')
      ->willReturnReference($this->moduleExists);

    $container = new ContainerBuilder();
    $container->set('module_handler', $module_handler);

    \Drupal::setContainer($container);
  }

  /**
   * Local tasks are added when the migrate_source_csv module exists.
   */
  public function testLocalTasks() {
    $deriver = MigrateLocalTasks::create(\Drupal::getContainer(), 'foo');
    $derivatives = $deriver->getDerivativeDefinitions([]);
    $this->assertEmpty($derivatives);

    $this->moduleExists = TRUE;
    $derivatives = $deriver->getDerivativeDefinitions([]);
    $this->assertArrayHasKey('entity.migration.csv_upload', $derivatives);
  }

}
