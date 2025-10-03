<?php

namespace Drupal\asu_governance\Services;

use Symfony\Component\Routing\Route;
use Drupal\Core\Access\AccessCheckInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Custom access check for config.import_full route.
 */
class ConfigImportFullAccessCheck implements AccessCheckInterface {

  /**
   * {@inheritdoc}
   */
  public function applies(Route $route): bool {
    // Only apply to config.import_full.
    return $route->getDefault('_route') === 'config.import_full';
  }

  /**
   * {@inheritdoc}
   */
  public function access(RouteMatchInterface $route_match, AccountInterface $account) {
    // Allow administrators.
    if ($account->hasRole('administrator')) {
      return AccessResult::allowed();
    }

    // Forbid everyone else.
    return AccessResult::forbidden();
  }

}
