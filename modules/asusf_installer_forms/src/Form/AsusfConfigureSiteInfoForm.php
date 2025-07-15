<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the site info configuration form.
 */
class AsusfConfigureSiteInfoForm extends ConfigFormBase {

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
    return 'asusf_install_configure_site_info_form';
  }

  /**
   * Builds just the site info related form fields.
   *
   * @param array &$form
   *   The form structure to modify.
   */
  public static function buildSiteInfoFields(array &$form) {
    $form['#markup'] = \Drupal::translation()->translate('<h2>Basic site information</h2>');

    $form['site_name'] = [
      '#type' => 'textfield',
      '#title' => \Drupal::translation()->translate('Site name'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => '',
      '#required' => TRUE,
      '#description' => \Drupal::translation()->translate('The name of the site, used in the header. Will not display until all configuration forms are completed.'),
    ];

    $form['site_email'] = [
      '#type' => 'email',
      '#title' => \Drupal::translation()->translate('Site email address'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => '',
      '#required' => TRUE,
      '#description' => \Drupal::translation()->translate('<p>The email address associated with the site, used for administrative purposes.</p>'),
    ];

    $form['defaults'] = [
      '#markup' => '<h3 class="mt-6">Default regional configuration</h3>
      <h4>Time zone</h4>
      <p>The <a href="/admin/config/regional/settings">default time zone</a> is configured
       for <strong>"Phoenix"</strong> (technically known as Mountain Standard).
       If you would like to adjust the time zone, you can configure it by clicking the link.</p>
       <h4>Language</h4>
       <p>The default language for an ASU site is <strong>English</strong>.
       To change the default language, you will need to enable Drupal Core\'s
       "Interface Translation" module and adjust configurations accordingly.</p>',
    ];
  }

  /**
   * Saves the site info.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function submitSiteInfo(FormStateInterface $form_state) {
    $site_name = $form_state->getValue('site_name');
    $site_email = $form_state->getValue('site_email');

    $config_factory = \Drupal::configFactory();
    // Set the site name and email.
    $siteconfig = $config_factory->getEditable('system.site');
    $siteconfig->set('name', $site_name)
      ->set('mail', $site_email)
      ->save();

    // Set the site name in the ASU brand settings.
    $block = $config_factory->getEditable('block.block.asubrandheader');
    $block->set('settings.asu_brand_header_block_title', $site_name)->save();

    // Set the regional settings.
    $regional_config = $config_factory->getEditable('system.date');
    $regional_config->set('timezone.default', 'America/Phoenix')
      ->set('country.default', 'US')
      ->save();

    $localeEnabled = \Drupal::moduleHandler()->moduleExists('locale');
    if ($localeEnabled) {
      \Drupal::service('module_installer')->uninstall(['locale']);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::buildSiteInfoFields($form);

    // Only needed when this form is run standalone.
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
    self::submitSiteInfo($form_state);
  }

}
