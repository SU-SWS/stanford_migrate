<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Plugin\migrate\id_map;

use Drupal\Core\Cache\Cache;
use Drupal\migrate\MigrateExecutable;
use Drupal\node\Entity\Node;
use Drupal\stanford_migrate\Plugin\migrate\id_map\StanfordSql;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;

/**
 * Class StanfordSqlTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Plugin\migrate\id_map\StanfordSql
 */
class StanfordSqlTest extends StanfordMigrateKernelTestBase {

  /**
   * Ensure the id map returns expected values.
   */
  public function testOverride() {
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('destination.plugin', 'entity_reference_revisions:node')
      ->set('source.urls', [dirname(__FILE__, 4) . '/test.xml'])
      ->save();
    Cache::invalidateTags(['migration_plugins']);

    $manager = \Drupal::service('plugin.manager.migration');
    /** @var \Drupal\migrate\Plugin\Migration $migration */
    $migration = $manager->createInstance('stanford_migrate');
    $migrate_executable = new MigrateExecutable($migration);
    $migrate_executable->import();

    $nodes = Node::loadMultiple();
    $node = reset($nodes);

    $this->assertInstanceOf(StanfordSql::class, $migration->getIdMap());
    $this->assertNotEmpty($migration->getIdMap()
      ->getRowByDestination(['nid' => $node->id()]));
    $this->assertNotEmpty($migration->getIdMap()
      ->getRowByDestination(['nid' => $node->id(), 'vid' => $node->getRevisionId()]));
    $this->assertEmpty($migration->getIdMap()->getRowByDestination([]));
  }

}
