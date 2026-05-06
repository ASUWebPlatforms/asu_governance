<?php

namespace Drupal\asu_governance\Form;

use Drupal\asu_governance\Services\GovernanceConfigResolver;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Update\UpdateHookRegistry;
use Drupal\system\Form\ModulesUninstallForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for uninstalling modules.
 */
class CuratedModulesUninstallForm extends ModulesUninstallForm {

  /**
   * Allowed modules from the configuration.
   *
   * @var array
   */
  protected $allowableModules;

  /**
   * The governance config resolver.
   *
   * @var \Drupal\asu_governance\Services\GovernanceConfigResolver
   */
  protected GovernanceConfigResolver $configResolver;

  /**
   * {@inheritdoc}
   */
  public function __construct(ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer, KeyValueStoreExpirableInterface $key_value_expirable, ModuleExtensionList $extension_list_module, UpdateHookRegistry $versioning_update_registry, GovernanceConfigResolver $config_resolver) {
    parent::__construct($module_handler, $module_installer, $key_value_expirable, $extension_list_module, $versioning_update_registry);
    $this->configResolver = $config_resolver;
    $this->allowableModules = $this->configResolver->get('allowable_modules') ?? [];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('keyvalue.expirable')->get('module_list'),
      $container->get('extension.list.module'),
      $container->get('update.update_hook_registry'),
      $container->get('asu_governance.config_resolver'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    // Only include ASU modules.
    $uninstallable = array_filter($form['modules'], function ($module, $name) {
      if (in_array($name, $this->allowableModules, TRUE)) {
        return $module;
      }
      return FALSE;
    }, ARRAY_FILTER_USE_BOTH);
    $form['modules'] = $uninstallable;

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // Redirect to the confirm form.
    $form_state->setRedirect('asu_governance.modules_uninstall_confirm');
  }

}
