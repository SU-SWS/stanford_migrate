<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\node\Entity\Node;
use Drupal\stanford_migrate\Form\StanfordMigrateImportForm;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;
use Drupal\Tests\user\Traits\UserCreationTrait;

/**
 * Class StanfordMigrateImportFormTest
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Form\StanfordMigrateImportForm
 */
class StanfordMigrateImportFormTest extends StanfordMigrateKernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritDoc}
   */
  protected function setUp() {
    parent::setUp();
    \Drupal::configFactory()
      ->getEditable('migrate_plus.migration.stanford_migrate')
      ->set('source.urls', [__DIR__ . '/../test.xml'])
      ->save();
  }

  /**
   * Users will have access to the form if they have access to any migration.
   */
  public function testAccess() {
    $form_object = StanfordMigrateImportForm::create(\Drupal::getContainer());
    $account = $this->createUser();
    $this->assertFalse($form_object->access($account)->isAllowed());

    $account = $this->createUser(['import stanford_migrate migration']);
    \Drupal::currentUser()->setAccount($account);
    $form_object = StanfordMigrateImportForm::create(\Drupal::getContainer());
    $this->assertTRUE($form_object->access($account)->isAllowed());
  }

  /**
   * Without access, the form is empty.
   */
  public function testNoAccess() {
    $form_State = new FormState();
    $form = \Drupal::formBuilder()
      ->buildForm(StanfordMigrateImportForm::class, $form_State);

    $this->assertArrayNotHasKey('stanford_migrate', $form['table']);
  }

  /**
   * With access, the form will be built and submit to import the migration.
   */
  public function testHasAccess() {
    $account = $this->createUser(['import stanford_migrate migration']);
    \Drupal::currentUser()->setAccount($account);

    $form_State = new FormState();
    $form = \Drupal::formBuilder()
      ->buildForm(StanfordMigrateImportForm::class, $form_State);

    $this->assertArrayHasKey('stanford_migrate', $form['table']);

    $form_State->setTriggeringElement(['#name' => 'stanford_migrate']);
    \Drupal::formBuilder()
      ->submitForm(StanfordMigrateImportForm::class, $form_State);
    $this->assertCount(1, Node::loadMultiple());
  }

}
