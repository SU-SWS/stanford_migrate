<?php

namespace Drupal\Tests\stanford_migrate\Kernel;

use Drupal\stanford_migrate\StanfordMigrateBatchExecutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the batch executable imports in configured sized chunks.
 */
#[Group('stanford_migrate')]
#[RunTestsInSeparateProcesses]
class StanfordMigrateBatchExecutableTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->config('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/test_batch.xml'])
      ->save();
    $this->config('stanford_migrate.settings')
      ->set('batch_limit', 1)
      ->save();
  }

  /**
   * Running the batch to completion imports every item and tallies results.
   */
  public function testBatchProcessImport(): void {
    $context = [];
    $iterations = 0;
    do {
      StanfordMigrateBatchExecutable::batchProcessImport('stanford_migrate', [], $context);
      $iterations++;
    } while ($context['finished'] < 1 && $iterations < 10);

    $this->assertEquals(3, $context['sandbox']['total']);
    $this->assertEquals(1, $context['sandbox']['batch_limit']);
    $this->assertEquals(1, $context['finished']);
    $this->assertEquals([
      '@numItems' => 3,
      '@created' => 3,
      '@updated' => 0,
      '@failures' => 0,
      '@ignored' => 0,
      '@name' => 'Stanford Migrate importer',
    ], $context['results']['stanford_migrate']);
    $this->assertEquals(3, $this->getNodeCount());
  }

  /**
   * The item limit is reduced by the items processed in prior iterations.
   */
  public function testBatchProcessImportLimit(): void {
    // Simulate the batch API calling a second iteration after one item was
    // already processed.
    $context = [
      'finished' => 1 / 3,
      'sandbox' => [
        'total' => 3,
        'counter' => 1,
        'batch_limit' => 1,
        'operation' => StanfordMigrateBatchExecutable::BATCH_IMPORT,
      ],
      'results' => [
        'stanford_migrate' => [
          '@numItems' => 1,
          '@created' => 1,
          '@updated' => 0,
          '@failures' => 0,
          '@ignored' => 0,
          '@name' => 'Stanford Migrate importer',
        ],
      ],
    ];
    StanfordMigrateBatchExecutable::batchProcessImport('stanford_migrate', ['limit' => 2], $context);

    // Only one more item should be imported to reach the overall limit of 2.
    $this->assertEquals(1, $this->getNodeCount());
    $this->assertEquals(2, $context['results']['stanford_migrate']['@numItems']);
    $this->assertEquals(2, $context['results']['stanford_migrate']['@created']);
    $this->assertEquals(1, $context['finished']);
  }

  /**
   * Get the number of nodes that have been imported.
   *
   * @return int
   *   Node count.
   */
  protected function getNodeCount(): int {
    return (int) $this->container->get('entity_type.manager')
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->count()
      ->execute();
  }

}
