<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides the Header configuration form.
 */
class AsusfConfigureHeaderForm extends ConfigFormBase {

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
    return 'asusf_install_configure_header_form';
  }

  /**
   * Builds just the header-related form fields.
   *
   * @param array &$form
   *   The form structure to modify.
   */
  public static function buildParentUnitFields(array &$form) {
    $form['#markup'] = \Drupal::translation()->translate('<h2>Configure Parent Unit</h2>');

    $form['explanation'] = [
      '#markup' => '<h3>Add parent unit</h3><p>If this site is for a department/college/unit ' .
        'that has a parent unit to be displayed in the site\'s header, enter that information below. ' .
        'You can also add it later in the ASU brand header configuration.' .
        '<h4>Header example with Parent unit:</h4>' .
        '<img src="/modules/contrib/asu_governance/modules/asusf_installer_forms/img/parent-unit-header.jpg" ' .
        'alt="Parent unit example" style="margin-top: 1rem; opacity: 0.6;" /></p>',
    ];

    $form['parent_unit_name'] = [
      '#type' => 'textfield',
      '#title' => \Drupal::translation()->translate('Parent unit name'),
      '#maxlength' => 50,
      '#size' => 50,
      '#default_value' => '',
      '#states' => [
        'required' => [
          ':input[name="parent_department_url"]' => ['filled' => TRUE],
        ],
      ],
    ];

    $form['parent_department_url'] = [
      '#type' => 'url',
      '#title' => \Drupal::translation()->translate('Parent Department URL'),
      '#maxlength' => 255,
      '#size' => 100,
      '#default_value' => '',
      '#attributes' => [
        'class' => ['mb-2'],
      ],
      '#states' => [
        'required' => [
          ':input[name="parent_unit_name"]' => ['filled' => TRUE],
        ],
        'visible' => [
          ':input[name="parent_unit_name"]' => ['filled' => TRUE],
        ],
      ],
    ];
  }

  /**
   * Saves the parent unit info to the ASU brand header block config.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public static function submitParentUnit(FormStateInterface $form_state) {
    $config_factory = \Drupal::configFactory();
    $block = $config_factory->getEditable('block.block.asubrandheader');

    $block->set('settings.asu_brand_header_block_parent_org', $form_state->getValue('parent_unit_name'));
    $block->set('settings.asu_brand_header_block_parent_org_url', $form_state->getValue('parent_department_url'));

    $block->save(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    self::buildParentUnitFields($form);

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
    self::submitParentUnit($form_state);
  }

}
