<?php

namespace Drupal\Tests\stanford_migrate\Unit\Hook;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\Display\EntityFormDisplayInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\stanford_migrate\Hook\StanfordMigrateReadonlyFieldHooks;
use Drupal\stanford_migrate\StanfordMigrateInterface;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests the readonly field hooks that don't require a full migration.
 */
#[Group('stanford_migrate')]
class StanfordMigrateReadonlyFieldHooksTest extends UnitTestCase {

  /**
   * Stanford migrate service mock.
   *
   * @var \Drupal\stanford_migrate\StanfordMigrateInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $stanfordMigrate;

  /**
   * Route match mock.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $routeMatch;

  /**
   * Entity type manager mock.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected $entityTypeManager;

  /**
   * Hook class being tested.
   *
   * @var \Drupal\stanford_migrate\Hook\StanfordMigrateReadonlyFieldHooks
   */
  protected $hooks;

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->stanfordMigrate = $this->createMock(StanfordMigrateInterface::class);
    $this->routeMatch = $this->createMock(RouteMatchInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);
    $this->hooks = new StanfordMigrateReadonlyFieldHooks($this->stanfordMigrate, $this->routeMatch, $this->entityTypeManager);
  }

  /**
   * The form display is untouched when the route has no content entity.
   */
  public function testFormDisplayWithoutEntity(): void {
    $this->routeMatch->method('getParameter')->with('node')->willReturn(NULL);
    $this->stanfordMigrate->expects($this->never())->method('getEntityMigration');

    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->expects($this->never())->method('setComponent');

    $this->hooks->entityFormDisplayAlter($form_display, [
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
  }

  /**
   * The form display is untouched when the entity wasn't imported.
   */
  public function testFormDisplayWithoutMigration(): void {
    $entity = $this->createMock(ContentEntityInterface::class);
    $this->routeMatch->method('getParameter')->with('node')->willReturn($entity);
    $this->stanfordMigrate->expects($this->once())
      ->method('getEntityMigration')
      ->with($entity)
      ->willReturn(NULL);
    $this->entityTypeManager->expects($this->never())->method('getStorage');

    $form_display = $this->createMock(EntityFormDisplayInterface::class);
    $form_display->expects($this->never())->method('setComponent');

    $this->hooks->entityFormDisplayAlter($form_display, [
      'entity_type' => 'node',
      'bundle' => 'article',
    ]);
  }

  /**
   * Readonly fields get wrapped with warning classes and the library.
   */
  public function testPreprocessReadonlyField(): void {
    $variables = [
      'element' => ['#third_party_settings' => ['stanford_migrate' => ['readonly' => TRUE]]],
      'attributes' => ['class' => ['field']],
    ];
    $this->hooks->preprocessField($variables);
    $this->assertEquals(['field', 'messages', 'messages--warning', 'messages--readonly'], $variables['attributes']['class']);
    $this->assertEquals(['stanford_migrate/readonly'], $variables['#attached']['library']);
  }

  /**
   * Fields that aren't readonly are left alone.
   */
  public function testPreprocessNormalField(): void {
    $variables = [
      'element' => ['#third_party_settings' => []],
      'attributes' => ['class' => ['field']],
    ];
    $original = $variables;
    $this->hooks->preprocessField($variables);
    $this->assertEquals($original, $variables);
  }

}
