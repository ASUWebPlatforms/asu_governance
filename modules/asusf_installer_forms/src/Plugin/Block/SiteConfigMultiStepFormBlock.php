<?php

namespace Drupal\asusf_installer_forms\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\asusf_installer_forms\Form\MultiStepAjaxForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a multi-step AJAX form block.
 *
 * @Block(
 * id = "site_config_multi_step_form_block",
 * admin_label = @Translation("Multi-step AJAX form block")
 * )
 */

class SiteConfigMultiStepFormBlock extends BlockBase implements ContainerFactoryPluginInterface {
  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * Constructs a new SiteConfigMultiStepFormBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm(MultiStepAjaxForm::class);
  }

  public function access(AccountInterface $account, $return_as_object = FALSE) {
    $access = $account->hasPermission('administer site configuration');
    return $return_as_object ? AccessResult::allowedIf($access) : $access;
  }
}
