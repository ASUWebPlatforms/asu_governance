<?php

namespace Drupal\asu_governance\Controller;

use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\system\Form\ThemeExperimentalConfirmForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\system\Controller\ThemeController;

/**
 * Controller for curated theme handling.
 */
class CuratedThemeController extends ThemeController {

  /**
   * Uninstalls a theme.
   */
  public function uninstall(Request $request) {
    $theme = $request->query->get('theme');
    $config = $this->config('system.theme');

    if (isset($theme)) {
      // Get current list of themes.
      $themes = $this->themeHandler->listInfo();

      // Check if the specified theme is one recognized by the system.
      if (!empty($themes[$theme])) {
        // Do not uninstall the default or admin theme.
        if ($theme === $config->get('default') || $theme === $config->get('admin')) {
          $this->messenger()->addError($this->t('%theme is the default theme and cannot be uninstalled.', ['%theme' => $themes[$theme]->info['name']]));
        }
        else {
          $this->themeInstaller->uninstall([$theme]);
          $this->messenger()->addStatus($this->t('The %theme theme has been uninstalled.', ['%theme' => $themes[$theme]->info['name']]));
        }
      }
      else {
        $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
      }

      return $this->redirect('asu_governance.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Installs a theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name and a valid token.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirects back to the appearance admin page or the confirmation form
   *   if an experimental theme will be installed.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme or token is set in the request or when
   *   the token is invalid.
   */
  public function install(Request $request) {
    $theme = $request->query->get('theme');

    if (isset($theme)) {
      // Display confirmation form in case of experimental theme.
      if ($this->willInstallExperimentalTheme($theme)) {
        return $this->formBuilder()->getForm(ThemeExperimentalConfirmForm::class, $theme);
      }

      try {
        if ($this->themeInstaller->install([$theme])) {
          $themes = $this->themeHandler->listInfo();
          $this->messenger()->addStatus($this->t('The %theme theme has been installed.', ['%theme' => $themes[$theme]->info['name']]));
        }
        else {
          $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
        }
      }
      catch (PreExistingConfigException $e) {
        $config_objects = $e->flattenConfigObjects($e->getConfigObjects());
        $this->messenger()->addError(
          $this->formatPlural(
            count($config_objects),
            'Unable to install @extension, %config_names already exists in active configuration.',
            'Unable to install @extension, %config_names already exist in active configuration.',
            [
              '%config_names' => implode(', ', $config_objects),
              '@extension' => $theme,
            ])
        );
      }
      catch (UnmetDependenciesException $e) {
        $this->messenger()->addError($e->getTranslatedMessage($this->getStringTranslation(), $theme));
      }
      catch (MissingDependencyException $e) {
        $this->messenger()->addError($this->t('Unable to install @theme due to missing module dependencies.', ['@theme' => $theme]));
      }

      return $this->redirect('asu_governance.themes_page');
    }

    throw new AccessDeniedHttpException();
  }

  /**
   * Checks if the given theme requires the installation of experimental themes.
   *
   * @param string $theme
   *   The name of the theme to check.
   *
   * @return bool
   *   Whether experimental themes will be installed.
   */
  protected function willInstallExperimentalTheme($theme) {
    $all_themes = $this->themeList->getList();
    $dependencies = array_keys($all_themes[$theme]->requires);
    $themes_to_enable = array_merge([$theme], $dependencies);

    foreach ($themes_to_enable as $name) {
      if (isset($all_themes[$name]) && $all_themes[$name]->isExperimental() && $all_themes[$name]->status === 0) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Set the default theme.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   A request object containing a theme name.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   Redirects back to the appearance admin page or the confirmation form
   *   if an experimental theme will be installed.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Throws access denied when no theme is set in the request.
   */
  public function setDefaultTheme(Request $request) {
    $config = $this->configFactory->getEditable('system.theme');
    $theme = $request->query->get('theme');

    if (isset($theme)) {
      // Get current list of themes.
      $themes = $this->themeHandler->listInfo();
      // Display confirmation form if an experimental theme is being installed.
      if ($this->willInstallExperimentalTheme($theme)) {
        return $this->formBuilder()->getForm(ThemeExperimentalConfirmForm::class, $theme, TRUE);
      }

      // Check if the specified theme is one recognized by the system.
      // Or try to install the theme.
      if (isset($themes[$theme]) || $this->themeInstaller->install([$theme])) {
        $themes = $this->themeHandler->listInfo();

        // Set the default theme.
        $config->set('default', $theme)->save();

        // The status message depends on whether an admin theme is currently in
        // use: a value of 0 means the admin theme is set to be the default
        // theme.
        $admin_theme = $config->get('admin');
        if (!empty($admin_theme) && $admin_theme != $theme) {
          $this->messenger()
            ->addStatus($this->t('Note that the administration theme is still set to the %admin_theme theme; consequently, the theme on this page remains unchanged. All non-administrative sections of the site, however, will show the selected %selected_theme theme by default.', [
              '%admin_theme' => $themes[$admin_theme]->info['name'],
              '%selected_theme' => $themes[$theme]->info['name'],
            ]));
        }
        else {
          $this->messenger()->addStatus($this->t('%theme is now the default theme.', ['%theme' => $themes[$theme]->info['name']]));
        }
      }
      else {
        $this->messenger()->addError($this->t('The %theme theme was not found.', ['%theme' => $theme]));
      }

      return $this->redirect('asu_governance.themes_page');

    }
    throw new AccessDeniedHttpException();
  }

}
