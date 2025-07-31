<?php

namespace Drupal\asusf_installer_forms\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\simple_sitemap\Manager\Generator;
use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Provides a multi-step AJAX form for initial configuration.
 */
class MultiStepAjaxForm extends FormBase {

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The simple sitemap generator service.
   *
   * @var \Drupal\simple_sitemap\Manager\Generator
   */
  protected $sitemapGenerator;

  /**
   * The installation profile.
   *
   * @var string
   */
  protected $installProfile;

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * Constructs a MultiStepAjaxForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\simple_sitemap\Manager\Generator $sitemap_generator
   *   The simple sitemap generator service.
   * @param string $install_profile
   *   The active installation profile.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, Generator $sitemap_generator, string $install_profile, ModuleInstallerInterface $module_installer) {
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->sitemapGenerator = $sitemap_generator;
    $this->installProfile = $install_profile;
    $this->moduleInstaller = $module_installer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('entity_type.manager'),
      $container->get('simple_sitemap.generator'),
      $container->getParameter('install_profile'),
      $container->get('module_installer')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multi_step_ajax_form';
  }

  /**
   * Builds the multi-step form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The complete form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'asusf_installer_forms/installer_forms';
    $form['#attributes']['class'][] = 'uds-form';
    $step = $form_state->get('step') ?? 1;
    $form_state->set('step', $step);

    $form['#prefix'] = '<div id="ajax-form-wrapper" class="' . $this->installProfile . '">';
    $form['#suffix'] = '</div>';
    $form['#markup'] = $this->t('<h1>Initial Configuration</h1>');
    $form['steps'] = [
      '#type' => 'container',
      '#attributes' => [
        'id' => 'ajax-form-wrapper',
        'class' => ['my-4', 'py-4', 'px-3', 'border'],
      ],
    ];

    $total_steps = $this->installProfile === 'webspark' ? 5 : 3;

    $this->buildStepFields($form['steps'], $step, $this->installProfile);

    $form['steps']['actions'] = [
      '#type' => 'actions',
    ];

    if ($step < $total_steps) {
      $form['steps']['actions']['next'] = [
        '#type' => 'submit',
        '#value' => $this->t('Next'),
        '#submit' => ['::goToNextStep'],
        '#ajax' => [
          'callback' => '::ajaxCallback',
          'wrapper' => 'ajax-form-wrapper',
        ],
      ];
    }
    else {
      $form['steps']['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
      ];
    }

    return $form;
  }

  /**
   * Returns the step map based on the active profile.
   *
   * @param string $profile
   *   The active installation profile.
   *
   * @return array
   *   An array mapping step numbers to form building methods.
   */
  private function getStepMap(string $profile): array {
    $step_map = [
      1 => '\Drupal\asusf_installer_forms\Form\AsusfConfigureSiteInfoForm::buildSiteInfoFields',
      2 => '\Drupal\asusf_installer_forms\Form\AsusfConfigureSitemapXMLForm::buildBaseUrlFields',
      3 => $profile === 'webspark'
        ? '\Drupal\asusf_installer_forms\Form\AsusfConfigureParentUnitForm::buildParentUnitFields'
        : '\Drupal\asusf_installer_forms\Form\AsusfConfigurePurgerForm::buildPurgerConfigFields',
      4 => $profile === 'webspark'
        ? '\Drupal\asusf_installer_forms\Form\AsusfConfigureGAForm::buildAnalyticsFields'
        : NULL,
      5 => $profile === 'webspark'
        ? '\Drupal\asusf_installer_forms\Form\AsusfConfigurePurgerForm::buildPurgerConfigFields'
        : NULL,
    ];

    // Unset steps 4 and 5 if the profile is not 'webspark'.
    if ($profile !== 'webspark') {
      unset($step_map[4], $step_map[5]);
    }

    return $step_map;
  }

  /**
   * Builds the fields for the current step.
   *
   * @param array $steps
   *   The steps array to populate.
   * @param int $step
   *   The current step number.
   * @param string $profile
   *   The active installation profile.
   */
  private function buildStepFields(array &$steps, int $step, string $profile) {
    $step_map = $this->getStepMap($profile);

    // Skip the step if it is not defined or explicitly set to null.
    if (!isset($step_map[$step]) || $step_map[$step] === NULL) {
      return;
    }

    $method = $step_map[$step];
    $steps['step_' . $step] = ['#type' => 'container'];
    $method($steps['step_' . $step]);
  }

  /**
   * Handles the transition to the next step in the multi-step form.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function goToNextStep(array &$form, FormStateInterface $form_state) {
    $step = $form_state->get('step');

    if (!$form_state->hasAnyErrors()) {
      $this->processStepSubmission($step, $form, $form_state, $this->installProfile);

      $step_map = $this->getStepMap($this->installProfile);

      do {
        $step++;
      } while (!isset($step_map[$step]) || $step_map[$step] === NULL);

      $form_state->set('step', $step);
      $form_state->setRebuild();
    }
  }

  /**
   * Processes the submission for the current step.
   *
   * @param int $step
   *   The current step number.
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param string $profile
   *   The active installation profile.
   */
  private function processStepSubmission(int $step, array &$form, FormStateInterface $form_state, string $profile) {
    $submission_map = [
      1 => '\Drupal\asusf_installer_forms\Form\AsusfConfigureSiteInfoForm::submitSiteInfo',
      2 => function () use ($step, $form, $form_state) {
        AsusfConfigureSitemapXMLForm::validateBaseUrl($form['steps']['step_' . $step], $form_state);
        if (!$form_state->hasAnyErrors()) {
          $this->configFactory->getEditable('simple_sitemap.settings')
            ->set('base_url', $form_state->getValue('simplexml_base_url'))
            ->save();
          $this->sitemapGenerator->generate('cron');
        }
      },
      3 => $profile === 'webspark'
        ? '\Drupal\asusf_installer_forms\Form\AsusfConfigureParentUnitForm::submitParentUnit'
        : NULL,
      4 => $profile === 'webspark'
        ? '\Drupal\asusf_installer_forms\Form\AsusfConfigureGAForm::submitAnalyticsSettings'
        : NULL,
    ];

    // Unset steps 3 and 4 if the profile is not 'webspark'.
    if ($profile !== 'webspark') {
      unset($submission_map[3], $submission_map[4]);
    }

    // Skip processing if the handler is null or not set.
    if (!isset($submission_map[$step])) {
      return;
    }

    $handler = $submission_map[$step];
    if (is_callable($handler)) {
      $handler($form_state);
    }
    elseif (is_string($handler)) {
      $handler($form_state);
    }
  }

  /**
   * Ajax callback for the multi-step form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The updated form array.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Submits the form and finalizes the configuration.
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (!$form_state->hasAnyErrors()) {
      AsusfConfigurePurgerForm::submitPurgerConfiguration();
      // Set the config to show that the installer forms have been completed.
      $this->configFactory->getEditable('asusf_installer_forms.settings')
        ->set('installer_forms_completed', TRUE)
        ->save();
      // Remove the custom block if it exists.
      $block_storage = $this->entityTypeManager->getStorage('block');
      $block = $block_storage->load('multi_step_form_instance');
      if ($block) {
        $block->delete();
      }
      // Enable the toolbar and admin_toolbar modules.
      $this->moduleInstaller->install(['toolbar', 'admin_toolbar']);
      // Get list of all roles.
      $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
      // Remove anonymous and authenticated roles from the roles array.
      unset($roles['anonymous'], $roles['authenticated']);
      // Remove the 'employee' role if it exists.
      if (isset($roles['employee'])) {
        unset($roles['employee']);
      }
      // Grant the 'access toolbar' permission to all remaining roles.
      foreach ($roles as $role) {
        $role->grantPermission('access toolbar');
        $role->save();
      }
      // Uninstall the asusf_installer_forms module.
      $this->moduleInstaller->uninstall(['asusf_installer_forms']);
    }
  }

}
