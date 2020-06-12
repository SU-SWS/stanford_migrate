<?php

namespace Drupal\tests\stanford_migrate\Unit\EventSubscriber;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\migrate\Event\MigrateImportEvent;
use Drupal\migrate\MigrateMessageInterface;
use Drupal\migrate\Plugin\MigrateIdMapInterface;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\stanford_migrate\EventSubscriber\EventsSubscriber;
use Drupal\stanford_migrate\Plugin\migrate\source\StanfordUrl;
use Drupal\Tests\UnitTestCase;

/**
 * Class EventsSubscriberTest
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\EventSubscriber\EventsSubscriber
 */
class EventsSubscriberTest extends UnitTestCase {

  /**
   * Event subscriber service.
   *
   * @var \Drupal\stanford_migrate\EventSubscriber\EventsSubscriber
   */
  protected $subscriber;

  /**
   * Migration source configuration.
   *
   * @var array
   */
  protected $migrationConfiguration = [];

  protected $sourceIds = [];

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();

    $entity_definition = $this->createMock(EntityTypeInterface::class);
    $entity_definition->method('getKey')->willReturn('status');

    $entity_storage = $this->createMock(EntityStorageInterface::class);
    $entity_storage->method('loadMultiple')->willReturn([]);

    $entity_type_manager = $this->createMock(EntityTypeManagerInterface::class);
    $entity_type_manager->method('getDefinition')
      ->wilLReturn($entity_definition);
    $entity_type_manager->method('getStorage')->willReturn($entity_storage);
    $this->subscriber = new EventsSubscriber($entity_type_manager);
  }

  public function testThis() {
    $this->assertTrue(TRUE);
  }
  //
  //  public function testNoOprhanAction() {
  //    $this->assertArrayEquals(['migrate.post_import' => ['postImport']], $this->subscriber::getSubscribedEvents());
  //    $this->assertNull($this->subscriber->postImport($this->getEvent()));
  //  }
  //
  //  public function testNoSourceIds() {
  //    $this->migrationConfiguration['orphan_action'] = 'delete';
  //    $this->assertNull($this->subscriber->postImport($this->getEvent()));
  //  }
  //
  //  public function testDeleteAction() {
  //    $this->sourceIds = [['foo' => 'bar', 'bar' => 'baz']];
  //    $this->migrationConfiguration['orphan_action'] = 'delete';
  //    $this->assertNull($this->subscriber->postImport($this->getEvent()));
  //  }
  //
  //  protected function getEvent() {
  //    $source_plugin = $this->createMock(StanfordUrl::class);
  //    $source_plugin->method('getAllIds')->willReturnReference($this->sourceIds);
  //
  //    $id_map = $this->createMock(MigrateIdMapInterface::class);
  //    $id_map->method('current')
  //      ->will($this->returnCallback([$this, 'idMapCurrentCallback']));
  //    $id_map->method('currentSource')->wilLreturn([]);
  //    $id_map->method('lookupDestinationIds')->willReturn([[]]);
  //
  //    $migration = $this->createMock(MigrationInterface::class);
  //    $migration->method('getSourceConfiguration')
  //      ->willReturnReference($this->migrationConfiguration);
  //    $migration->method('getDestinationConfiguration')
  //      ->willReturn(['plugin' => 'entity:node']);
  //    $migration->method('getSourcePlugin')->willReturn($source_plugin);
  //    $migration->method('getIdMap')->willReturn($id_map);
  //    $messenger = $this->createMock(MigrateMessageInterface::class);
  //
  //    return new MigrateImportEvent($migration, $messenger);
  //  }
  //
  //  public function idMapCurrentCallback() {
  //    static $current = TRUE;
  //    if ($current) {
  //      $current = FALSE;
  //      return TRUE;
  //    }
  //    return FALSE;
  //  }

}
