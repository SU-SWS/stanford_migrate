<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Config;

use Drupal\file\Entity\File;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;

/**
 * Class MigrationConfigOverridesTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Config\MigrationConfigOverrides
 */
class MigrationConfigOverridesTest extends StanfordMigrateKernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'test_stanford_migrate',
    'stanford_migrate',
    'migrate_plus',
    'migrate',
    'node',
    'user',
    'system',
    'ultimate_cron',
    'file'
  ];

  /**
   * {@inheritDoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
  }

  /**
   * After setting the state, the config overrides should set the path data.
   */
  public function testConfigOverrides() {
    $source = \Drupal::configFactory()
      ->get('migrate_plus.migration.stanford_migrate')
      ->get('source');
    $this->assertArrayNotHasKey('path', $source);

    \Drupal::service('file_system')->copy($this->root . '/core/misc/druplicon.png', 'public://example.jpg');
    $file = File::create([
      'uri' => 'public://example.jpg',
    ]);
    $file->save();

    \Drupal::state()->set('stanford_migrate.csv.stanford_migrate', [$file->id()]);
    \Drupal::configFactory()->clearStaticCache();

    $source = \Drupal::configFactory()
      ->get('migrate_plus.migration.stanford_migrate')
      ->get('source');
    $this->assertEquals('public://example.jpg', $source['path']);
  }

}
