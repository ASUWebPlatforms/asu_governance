<?php

declare(strict_types=1);

namespace Drupal\asu_governance\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Route subscriber.
 */
final class AsuGovernanceRouteSubscriber extends RouteSubscriberBase {

  /**
   * Permissions routes.
   *
   * @const array
   */
  public const PERMSROUTES = [
    'user.admin_permissions',
    'entity.user_role.collection',
    'user.role_add',
    'entity.user_role.edit_form',
    'entity.user_role.delete_form',
    'entity.user_role.edit_permissions_form',
  ];

  /**
   * Config routes.
   *
   * @const array
   */
  public const CONFIG_ROUTES = [
    'config.diff',
    'config.diff_collection',
    'config.export_download',
    'config.export_full',
    'config.export_single',
    'config.import_full',
    'config.import_single',
    'config.sync',
  ];

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection): void {
    $configRoutes = $this::CONFIG_ROUTES;

    $permissionsRoutes = $this::PERMSROUTES;

    foreach ($configRoutes as $route_name) {
      /** @var \Symfony\Component\Routing\Route $route */
      $route = $collection->get($route_name);
      if (!$route) {
        continue;
      }

      if ($route->getRequirement('_permission') === 'synchronize configuration' || $route->getRequirement('_permission') === 'import configuration' || $route->getRequirement('_permission') === 'export configuration') {
        $route->setRequirements([]);

        if ($route_name === 'config.import_full' || $route_name === 'config.sync') {
          // Only allow administrator role access.
          $route->setRequirement('_config_admin_access', 'TRUE');
        }
        // Allow access to other config routes based on custom permission check.
        $route->setRequirement('_allow_asu_config_access', 'TRUE');
      }
    }
    foreach ($permissionsRoutes as $routeName) {
      /** @var \Symfony\Component\Routing\Route $route */
      $route = $collection->get($routeName);
      if (!$route) {
        continue;
      }
      $route->setRequirements([]);
      $route->setRequirement('_custom_perms_roles', 'TRUE');
    }
    // Ensure user.role.settings route is only accessible by administrators.
    $roleRoute = 'user.role.settings';
    $route = $collection->get($roleRoute);
    $route->setRequirements([]);
    $route->setRequirement('_role', 'administrator');
  }

}
