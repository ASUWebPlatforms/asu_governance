<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Google Analytics configuration form.
 */
class AsusfConfigureGAForm extends ConfigFormBase {

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
    return 'asusf_install_configure_ga_form';
  }

  /**
   * Adds the GA fields to a form.
   *
   * @param array &$form
   *   The form array to modify.
   */
  public static function buildAnalyticsFields(array &$form) {
    $form['#markup'] = \Drupal::translation()->translate('<h2>Configure Google Analytics</h2>');

    $form['has_ga_account'] = [
      '#type' => 'checkbox',
      '#title' => \Drupal::translation()->translate('Do you have a Google Analytics account separate from the main ASU account?'),
    ];

    $form['google_analytics_account'] = [
      '#type' => 'textfield',
      '#title' => \Drupal::translation()->translate('Web Property ID'),
      '#description' => \Drupal::translation()->translate(
        'Enter your custom GA ID (ex. UA-xxxxxxx-yy) to send tracking information to from this site. Entering this ID will not affect or override ASU\'s main Google account.'
      ),
      '#placeholder' => 'UA-',
      '#maxlength' => 20,
      '#size' => 20,
      '#states' => [
        'visible' => [
          ':input[name="has_ga_account"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="has_ga_account"]' => ['checked' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Saves GA config to asu_brand.settings.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state object.
   */
  public static function submitAnalyticsSettings(FormStateInterface $form_state) {
    $ga_id = $form_state->getValue('google_analytics_account');

    \Drupal::configFactory()
      ->getEditable('asu_brand.settings')
      ->set('asu_brand.asu_brand_extra_gtm_id', $ga_id)
      ->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::buildAnalyticsFields($form);

    // Submit button for standalone use
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
    self::submitAnalyticsSettings($form_state);
  }

}
