<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Form;

use Drupal\Core\Session\AccountInterface;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationInterface;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class StanfordMigrateCsvImportFormTest.
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Form\StanfordMigrateCsvImportForm
 */
class StanfordMigrateCsvImportFormTest extends StanfordMigrateKernelTestBase {

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
    'file',
    'migrate_source_csv',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->installEntitySchema('file');
  }

  /**
   * Migrations that aren't csv importers are denied access.
   */
  public function testNonCsvAccess() {
    $this->setMigrationRequest(Migration::load('stanford_migrate'));

    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('migration', 'csv-upload');
    $account = $this->createMock(AccountInterface::class);
    $this->assertFalse($form_object->access($account)->isAllowed());
  }

  /**
   * CSV Importers have permission access.
   */
  public function testCsvPermissionAccess() {
    $migration = Migration::load('stanford_migrate');
    $source = $migration->get('source');
    $source['plugin'] = 'csv';
    $source['path'] = '/tmp/tmp.csv';
    $source['ids'] = ['foo'];
    $migration->set('source', $source)->save();
    $this->setMigrationRequest($migration);

    $account = $this->createMock(AccountInterface::class);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('migration', 'csv-upload');
    $this->assertFalse($form_object->access($account)->isAllowed());

    $account->method('hasPermission')->willReturn(TRUE);
    $this->assertTrue($form_object->access($account)->isAllowed());
  }

  /**
   * Set the current request on the request stack to have a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   */
  protected function setMigrationRequest(MigrationInterface $migration) {
    $attributes = ['migration' => $migration];
    $request = new Request([], [], $attributes);
    \Drupal::requestStack()->push($request);
  }

}
