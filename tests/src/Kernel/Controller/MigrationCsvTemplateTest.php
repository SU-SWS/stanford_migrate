<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Controller;

use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationGroup;
use Drupal\stanford_migrate\Controller\MigrationCsvTemplate;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class MigrationCsvTemplateTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Controller\MigrationCsvTemplate
 */
class MigrationCsvTemplateTest extends StanfordMigrateKernelTestBase {

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
    'migrate_source_csv',
  ];

  /**
   * Migrations that aren't csv importers throw 404 response.
   */
  public function testNotFound() {
    $migration = Migration::load('stanford_migrate');
    $controller = MigrationCsvTemplate::create(\Drupal::getContainer());

    $this->expectException(NotFoundHttpException::class);
    $controller->getEmptyTemplate($migration);
  }

  /**
   * The controller should give a csv file with an expected header.
   */
  public function testController() {
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.plugin', 'csv')
      ->set('source.path', '/tmp/foo.csv')
      ->set('source.ids', ['guid'])
      ->save();

    $migration = Migration::load('stanford_migrate');
    $controller = MigrationCsvTemplate::create(\Drupal::getContainer());
    $response = $controller->getEmptyTemplate($migration);
    $this->assertInstanceOf(BinaryFileResponse::class, $response);
    $contents = file_get_contents($response->getFile()->getRealPath());

    $this->assertStringContainsString('"guid (GUID)","title (Title)"', $contents);
  }

}
