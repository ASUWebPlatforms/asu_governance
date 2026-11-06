<?php

namespace Drupal\asu_governance\Form;

use Drupal\Core\Extension\ThemeExtensionList;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\system\Form\ThemeExperimentalConfirmForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Builds a confirmation form for enabling experimental themes.
 *
 * @internal
 */
class CuratedThemeExperimentalConfirmForm extends ThemeExperimentalConfirmForm {

  /**
   * Constructs a CuratedThemeExperimentalConfirmForm object.
   *
   * @param \Drupal\Core\Extension\ThemeExtensionList $theme_list
   *   The theme extension list.
   * @param \Drupal\Core\Extension\ThemeInstallerInterface $theme_installer
   *   The theme installer.
   */
  public function __construct(ThemeExtensionList $theme_list, ThemeInstallerInterface $theme_installer) {
    parent::__construct($theme_list, $theme_installer);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return parent::create($container);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('asu_governance.themes_page');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
  }

}
