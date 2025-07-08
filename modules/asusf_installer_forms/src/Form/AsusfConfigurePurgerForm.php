<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Acquia Purge configuration form.
 */
class AsusfConfigurePurgerForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'asusf_install_configure_purger_form';
  }

  /**
   * Builds the static markup for purge setup confirmation.
   *
   * @param array &$form
   *   The form array to modify.
   */
  public static function buildPurgerConfigFields(array &$form) {
    $form['#markup'] = \Drupal::translation()->translate('<h2>Configure Acquia Purger and ASU Governance</h2>');

    $form['description'] = [
      '#type' => 'markup',
      '#markup' => \Drupal::translation()->translate(
        '<p>This task will install and configure the Acquia Purger and ASU Governance modules for your site.</p>
         <p class="small text-muted">The Acquia Purge module will enable the purging of external Varnish caches when your site content is updated.</p>
         <p class="small text-muted">The ASU Governance modules secure the SuperAdmin account and provide a customized interface for managing ASU Drupal sites in Acquia SiteFactory.</p>'
      ),
    ];
  }

  /**
   * Installs and configures the Acquia Purger.
   */
  public static function submitPurgerConfiguration() {
    // Include the purge setup logic from install file.
    include_once DRUPAL_ROOT . '/profiles/webspark/webspark/webspark.install';
    __webspark_setup_purge();

    // Install the ASU Governance modules.
    \Drupal::service('module_installer')->install(['asu_governance']);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::buildPurgerConfigFields($form);

    // Standalone submit button
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save and continue'),
      '#weight' => 15,
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    self::submitPurgerConfiguration();
  }

}
