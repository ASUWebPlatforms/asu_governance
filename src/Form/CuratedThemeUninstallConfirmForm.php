<?php

declare(strict_types=1);

namespace Drupal\asu_governance\Form;

use Drupal\Core\Config\ConfigManagerInterface;
use Drupal\Core\Config\Entity\ConfigDependencyDeleteFormTrait;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Extension\ThemeInstallerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\system\Form\ThemeUninstallConfirmForm;

/**
 * Builds a confirmation form to uninstall a theme.
 *
 * @internal
 */
class CuratedThemeUninstallConfirmForm extends ThemeUninstallConfirmForm {

  use AutowireTrait;
  use ConfigDependencyDeleteFormTrait;

  /**
   * The theme label.
   */
  protected string $themeLabel = '';

  public function __construct(
    protected ThemeHandlerInterface $themeHandler,
    protected ThemeInstallerInterface $themeInstaller,
    protected ConfigManagerInterface $configManager,
    protected EntityTypeManagerInterface $entityTypeManager,
    ) {
    parent::__construct(
      $themeHandler,
      $themeInstaller,
      $configManager,
      $entityTypeManager
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return new Url('asu_governance.themes_page');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    parent::submitForm($form, $form_state);
  }

}
