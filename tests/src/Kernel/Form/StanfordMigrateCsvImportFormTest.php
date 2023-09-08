<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\migrate_plus\Entity\Migration;
use Drupal\migrate_plus\Entity\MigrationGroup;
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
  public function setup(): void {
    parent::setUp();
    $this->installEntitySchema('file');
    $this->installSchema('file', ['file_usage']);
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
    $this->setCsvMigrationRequest();

    $account = $this->createMock(AccountInterface::class);
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('migration', 'csv-upload');
    $this->assertFalse($form_object->access($account)->isAllowed());

    $account->method('hasPermission')->willReturn(TRUE);
    $this->assertTrue($form_object->access($account)->isAllowed());
  }

  /**
   * Test the functionality of the form.
   */
  public function testBuildForm() {
    $this->setCsvMigrationRequest();
    $form = [];
    $form_state = new FormState();
    $form_object = \Drupal::entityTypeManager()
      ->getFormObject('migration', 'csv-upload');

    $form_object->setEntity(Migration::load('stanford_migrate'));

    $form = $form_object->buildForm($form, $form_state);
    $this->assertArrayNotHasKey('forget', $form);

    \Drupal::state()->set('stanford_migrate.csv.stanford_migrate', [1, 2, 3]);

    $form = $form_object->buildForm($form, $form_state);
    $this->assertArrayHasKey('forget', $form);

    $form_state->setTriggeringElement(['#name' => 'foo']);
    $form_object->validateForm($form, $form_state);
    $this->assertFalse($form_state::hasAnyErrors());

    $file = File::create(['uri' => 'public://foo.csv']);
    $file->save();

    $form['csv']['#parents'] = [];
    $form_state->setValue(['csv', 0], $file->id());
    $form_object->validateForm($form, $form_state);
    $this->assertTrue($form_state::hasAnyErrors());

    $form_state->clearErrors();
    file_put_contents('public://import.csv', '');
    $file->set('uri','public://import.csv')->save();
    $form_object->validateForm($form, $form_state);
    $this->assertTrue($form_state::hasAnyErrors());

    $f = fopen('public://import.csv', 'w');
    fputcsv($f, ['foo', 'bar']);
    fclose($f);

    $form_state->clearErrors();
    $form_object->validateForm($form, $form_state);
    $this->assertTrue($form_state::hasAnyErrors());

    $f = fopen('public://import.csv', 'w');
    fputcsv($f, ['guid', 'title']);
    fclose($f);

    $form_state->clearErrors();
    $form_object->validateForm($form, $form_state);
    $this->assertFalse($form_state::hasAnyErrors());

    \Drupal::state()->delete('stanford_migrate.csv.stanford_migrate');
    $form_state->setValue('forget_previous', 1);
    $form_object->save($form, $form_state);
    $state = \Drupal::state()->get('stanford_migrate.csv.stanford_migrate');
    $this->assertEquals([$file->id()], $state);
  }

  /**
   * Modify the migration entity and set it on the current request.
   */
  protected function setCsvMigrationRequest() {
    $migration = Migration::load('stanford_migrate');
    $source = $migration->get('source');
    $source['plugin'] = 'csv';
    $source['path'] = '/tmp/tmp.csv';
    $source['ids'] = ['foo'];
    $migration->set('source', $source)->save();
    $this->setMigrationRequest($migration);
  }

  /**
   * Set the current request on the request stack to have a migration entity.
   *
   * @param \Drupal\migrate_plus\Entity\MigrationInterface $migration
   */
  protected function setMigrationRequest(MigrationInterface $migration) {

    $attributes = [
      'migration_group' => MigrationGroup::load('stanford_migrate'),
      'migration' => $migration,
    ];
    $request = new Request([], [], $attributes);
    \Drupal::requestStack()->push($request);
  }

}
