<?php

namespace Drupal\asu_governance\Controller;

use Drupal\asu_governance\Form\CuratedThemeExperimentalConfirmForm;
use Drupal\Core\Batch\BatchStorageInterface;
use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Extension\MissingDependencyException;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\system\Controller\ThemeController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for curated theme handling.
 */
class CuratedThemeController extends ThemeController {

  /**
   * {@inheritdoc}
   */
  #[Route(
    path: '/admin/appearance/install',
    name: 'system.theme_install',
    requirements: [
      '_permission' => 'administer themes',
      '_csrf_token' => 'TRUE',
    ],
  )]
  public function install(#[MapQueryParameter] string $theme) {
    // Display confirmation form in case of experimental theme.
    if ($this->willInstallExperimentalTheme($theme)) {
      return $this->formBuilder()->getForm(CuratedThemeExperimentalConfirmForm::class, $theme);
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
    catch (MissingDependencyException) {
      $this->messenger()->addError($this->t('Unable to install @theme due to missing module dependencies.', ['@theme' => $theme]));
    }

    // Process any batch queued by hook_themes_installed (e.g. locale's
    // langcode rewrite). batch_process() redirects to the batch page and
    // then to $redirect after completion, so it is returned directly.
    $batch = &batch_get();
    if (!empty($batch)) {
      return batch_process(Url::fromRoute('asu_governance.themes_page'));
    }

    return $this->redirect('asu_governance.themes_page');
  }

  /**
   * {@inheritdoc}
   */
  #[Route(
    path: '/admin/appearance/set-default',
    name: 'system.theme_set_default',
    requirements: [
      '_permission' => 'administer themes',
      '_csrf_token' => 'TRUE',
    ],
    defaults: [
      '_title' => new TranslatableMarkup('Set as default theme'),
    ],
  )]
  public function setDefaultTheme(#[MapQueryParameter] string $theme) {
    if ($this->willInstallExperimentalTheme($theme)) {
      return $this->formBuilder()->getForm(CuratedThemeExperimentalConfirmForm::class, $theme, TRUE);
    }

    $response = parent::setDefaultTheme($theme);
    $redirect_url = Url::fromRoute('asu_governance.themes_page');

    // The parent already stored the batch with its post-completion redirect,
    // so point the stored batch at the curated themes page instead.
    $batch = &batch_get();
    if (!empty($batch['id'])) {
      $batch['batch_redirect'] = $redirect_url;
      \Drupal::service(BatchStorageInterface::class)->update($batch);
      return $response;
    }

    if ($response instanceof RedirectResponse) {
      $response->setTargetUrl($redirect_url->setAbsolute()->toString());
    }
    return $response;
  }

}
