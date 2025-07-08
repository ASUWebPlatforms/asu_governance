<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\UrlHelper;

/**
 * Provides the Google Analytics configuration form.
 */
class AsusfConfigureSitemapXMLForm extends ConfigFormBase {

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
    return 'asusf_install_configure_simplexml_form';
  }

  public static function buildBaseUrlFields(array &$form) {

    $form['#markup'] = \Drupal::translation()->translate('<h2>Configure Base URL</h2>');

    $form['simplexml_base_url'] = [
      '#maxlength' => 64,
      '#placeholder' => 'https://',
      '#size' => 40,
      '#title' => \Drupal::translation()->translate('Base URL'),
      '#type' => 'url',
      '#attributes' => [
        'class' => ['mb-2', 'form-control'],
      ],
    ];

    $form['explanation'] = [
      '#markup' => '<p>Enter the base URL expected to be used with this site when it launches ' .
        '(ex. https://mysite.engineering.asu.edu, https://topleveldomain.asu.edu). ' .
        'This will help with search results and SEO.</p>' .
        '<p>If the base URL is still TBD, leave this blank because it can be added ' .
        'later under the Simple XML Sitemap settings.</p>'
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function validateBaseUrl(array &$form, FormStateInterface $form_state) {
    $base_url = $form_state->getValue('simplexml_base_url');
    $form_state->setValue('simplexml_base_url', rtrim($base_url, '/'));

    if ($base_url !== '' && !UrlHelper::isValid($base_url, TRUE)) {
      $form_state->setErrorByName('simplexml_base_url', t('Not a valid URL.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::buildBaseUrlFields($form);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    self::validateBaseUrl($form, $form_state);
  }

}
