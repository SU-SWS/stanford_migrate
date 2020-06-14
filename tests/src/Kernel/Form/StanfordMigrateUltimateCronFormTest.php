<?php

namespace Drupal\Tests\stanford_migrate\Kernel\Form;

use Drupal\Core\Form\FormState;
use Drupal\stanford_migrate\Form\StanfordMigrateUltimateCronForm;
use Drupal\Tests\stanford_migrate\Kernel\StanfordMigrateKernelTestBase;

/**
 * Class StanfordMigrateUltimateCronFormTest
 *
 * @group stanford_migrate
 * @coversDefaultClass \Drupal\stanford_migrate\Form\StanfordMigrateUltimateCronForm
 */
class StanfordMigrateUltimateCronFormTest extends StanfordMigrateKernelTestBase {

  /**
   * The form will display missing cron jobs and create them when submitted.
   */
  public function testForm(){
    $configs = \Drupal::configFactory()->listAll('ultimate_cron');
    $this->assertFalse(in_array('ultimate_cron.job.stanford_migrate_stanford_migrate', $configs));

    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(StanfordMigrateUltimateCronForm::class, $form_state);
    $this->assertNotEmpty($form['missing']['#items']);
    $this->assertEmpty($form['existing']['#items']);
    $this->assertArrayHasKey('stanford_migrate', $form_state->get('missing_cron_jobs'));
    \Drupal::formBuilder()->submitForm(StanfordMigrateUltimateCronForm::class, $form_state);

    $configs = \Drupal::configFactory()->listAll('ultimate_cron');
    $this->assertTrue(in_array('ultimate_cron.job.stanford_migrate_stanford_migrate', $configs));

    $form_state = new FormState();
    $form = \Drupal::formBuilder()->buildForm(StanfordMigrateUltimateCronForm::class, $form_state);
    $this->assertEmpty($form['missing']['#items']);
    $this->assertNotEmpty($form['existing']['#items']);
  }

}
