<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\block\Entity\Block;

class MultiStepAjaxForm extends FormBase {
  public function getFormId() {
    return 'multi_step_ajax_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'asusf_installer_forms/installer_forms';
    $form['#attributes']['class'][] = 'uds-form';
    $step = $form_state->get('step');
    if ($step === NULL) {
      $step = 1;
      $form_state->set('step', $step);
    }
    $form['#prefix'] = '<div id="ajax-form-wrapper">';
    $form['#suffix'] = '</div>';
    $form['#markup'] = $this->t('<h1>Initial Configuration</h1>');
    $form['steps'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ajax-form-wrapper',
        'class' => ['my-4', 'py-4', 'px-3', 'border'],
      ],
    ];

    // get active profile
    $active_profile = \Drupal::installProfile();
    if ($active_profile === 'webspark'){
      switch ($step) {
        case 1:
          $form['steps']['site_info'] = ['#type' => 'container'];
          AsusfConfigureSiteInfoForm::buildSiteInfoFields($form['steps']['site_info']);
          break;
        case 2:
          $form['steps']['base_url'] = ['#type' => 'container'];
          AsusfConfigureSitemapXMLForm::buildBaseUrlFields($form['steps']['base_url']);
          break;
        case 3:
          $form['steps']['parent_unit'] = ['#type' => 'container'];
          AsusfConfigureParentUnitForm::buildParentUnitFields($form['steps']['parent_unit']);
          break;
        case 4:
          $form['steps']['analytics'] = ['#type' => 'container'];
          AsusfConfigureGAForm::buildAnalyticsFields($form['steps']['analytics']);
          break;
        case 5:
          $form['steps']['purge'] = ['#type' => 'container'];
          AsusfConfigurePurgerForm::buildPurgerConfigFields($form['steps']['purge']);
          break;
      }

      $form['steps']['actions'] = [
        '#type' => 'actions',
      ];

      if ($step < 5) { // Adjust for total steps
        $form['steps']['actions']['next'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#submit' => ['::goToNextStep'],
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'ajax-form-wrapper',
          ],
        ];
      } else {
        $form['steps']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Submit'),
        ];
      }
    }
    else {
      switch ($step) {
        case 1:
          $form['steps']['site_info'] = ['#type' => 'container'];
          AsusfConfigureSiteInfoForm::buildSiteInfoFields($form['steps']['site_info']);
          break;
        case 2:
          $form['steps']['base_url'] = ['#type' => 'container'];
          AsusfConfigureSitemapXMLForm::buildBaseUrlFields($form['steps']['base_url']);
          break;
        case 3:
          $form['steps']['analytics'] = ['#type' => 'container'];
          AsusfConfigureGAForm::buildAnalyticsFields($form['steps']['analytics']);
          break;
        case 4:
          $form['steps']['purge'] = ['#type' => 'container'];
          AsusfConfigurePurgerForm::buildPurgerConfigFields($form['steps']['purge']);
          break;
      }

      $form['steps']['actions'] = [
        '#type' => 'actions',
      ];

      if ($step < 4) { // Adjust for total steps
        $form['steps']['actions']['next'] = [
          '#type' => 'submit',
          '#value' => $this->t('Next'),
          '#submit' => ['::goToNextStep'],
          '#ajax' => [
            'callback' => '::ajaxCallback',
            'wrapper' => 'ajax-form-wrapper',
          ],
        ];
      } else {
        $form['steps']['actions']['submit'] = [
          '#type' => 'submit',
          '#value' => $this->t('Submit'),
        ];
      }
    }

    return $form;
  }

  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::logger('multi_step')->debug('Form serialized size: @size bytes', [
      '@size' => strlen(serialize($form)),
    ]);
    return $form;
  }

  public function goToNextStep(array &$form, FormStateInterface $form_state) {
    // get active profile
    $active_profile = \Drupal::installProfile();
    if ($active_profile === 'webspark') {
      switch ($form_state->get('step')) {
        case 1:
          if (!$form_state->hasAnyErrors()) {
            AsusfConfigureSiteInfoForm::submitSiteInfo($form_state);
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
        case 2:
          AsusfConfigureSitemapXMLForm::validateBaseUrl($form['steps']['base_url'], $form_state);
          if (!$form_state->hasAnyErrors()) {
            $config_factory = \Drupal::configFactory();
            $config_factory->getEditable('simple_sitemap.settings')
              ->set('base_url', $form_state->getValue('simplexml_base_url'))
              ->save();
            \Drupal::service('simple_sitemap.generator')->generate('cron');
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
        case 3:
          if (!$form_state->hasAnyErrors()) {
            AsusfConfigureParentUnitForm::submitParentUnit($form_state);
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
        case 4:
          if (!$form_state->hasAnyErrors()) {
            AsusfConfigureGAForm::submitAnalyticsSettings($form_state);
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
      }
    }
    else {
      switch ($form_state->get('step')) {
        case 1:
          if (!$form_state->hasAnyErrors()) {
            AsusfConfigureSiteInfoForm::submitSiteInfo($form_state);
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
        case 2:
          AsusfConfigureSitemapXMLForm::validateBaseUrl($form['steps']['base_url'], $form_state);
          if (!$form_state->hasAnyErrors()) {
            $config_factory = \Drupal::configFactory();
            $config_factory->getEditable('simple_sitemap.settings')
              ->set('base_url', $form_state->getValue('simplexml_base_url'))
              ->save();
            \Drupal::service('simple_sitemap.generator')->generate('cron');
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
        case 3:
          if (!$form_state->hasAnyErrors()) {
            AsusfConfigureGAForm::submitAnalyticsSettings($form_state);
            $form_state->set('step', $form_state->get('step') + 1);
            $form_state->setRebuild();
          }
          break;
      }
    }
  }

  /**
   * @throws EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->hasAnyErrors()) {
      // Final step submission logic.
      AsusfConfigurePurgerForm::submitPurgerConfiguration();

      // Set the config to show that the installer forms have been completed.
      $config = \Drupal::configFactory()->getEditable('asusf_installer_forms.settings');
      $config->set('installer_forms_completed', TRUE)->save();

      // Remove the custom block if it exists.
      $block_id = 'multi_step_form_instance';
      $block = Block::load($block_id);
      $block?->delete();
    }
  }
}
