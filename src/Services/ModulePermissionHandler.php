<?php

namespace Drupal\asu_governance\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\user\Entity\Role;
use Drupal\user\PermissionHandlerInterface;

/**
 * Service to handle permissions for all asu_governance allowed modules.
 *
 * Allows to dynamically add/update them to the Site Builder role.
 */
class ModulePermissionHandler {
  use ConstantsTrait;
  use StringTranslationTrait;

  /**
   * The permission handler service.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
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
   * Constructs the ModulePermissionHandler object.
   *
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(PermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager) {
    $this->permissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   *  Create a role.
   */
  public function createRole($role_id, $role_name) {
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    // Check if the role already exists.
    $content_editor = $role_storage->load($role_id);
    if ($content_editor) {
      // Role already exists, no need to create it again.
      return;
    }
    // Create the role if it does not exist.
    $role = $role_storage->create([
      'id' => $role_id,
      'label' => $this->t($role_name),
    ]);
    $role->save();
  }

  /**
   * Add a role's base permissions.
   *
   * @param string $role_id
   *   The role ID to add permissions to.
   * @param string $base_perms_const
   *   The constant name for the base permissions.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addBasePermissions($role_id, $base_perms_const) {
    // Load the role.
    /** @var \Drupal\user\Entity\Role $role */
    $role = Role::load($role_id);

    // Get the available site permissions.
    $allPermissions = $this->permissionHandler->getPermissions();
    if (defined("static::{$base_perms_const}")) {
      $basePermissions = $this::{$base_perms_const};
    } else {
      throw new \InvalidArgumentException("The constant {$base_perms_const} is not defined.");
    }
    // Remove missing permissions from base list.
    $availablePermissions = array_filter(array_keys($allPermissions), function ($permission) use ($basePermissions) {
      if (in_array($permission, $basePermissions, TRUE)) {
        return TRUE;
      }
      return FALSE;
    });

    // Add available base permissions.
    if ($role) {
      foreach ($availablePermissions as $permission) {
        $role->grantPermission($permission);
      }
    }
    $role->save();
  }


  /**
   * Add the Site Builder role's module permissions.
   *
   * @param array $modules
   *   An array of module names.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addSiteBuilderModulePermissions(array $modules) {

    // Load the Site Builder role.
    /** @var \Drupal\user\Entity\Role $role */
    $role = Role::load('site_builder');

    // Add module permissions.
    $allowed_modules = $this->configFactory->get('asu_governance.settings')->get('allowable_modules');
    // Get the role's permissions.
    $siteBuilderPerms = $role->getPermissions();
    foreach ($modules as $module) {
      // Skip modules that are not enabled or not allowed
      // in asu_governance module's settings form.
      if (!$this->moduleHandler->moduleExists($module) || !in_array($module, $allowed_modules, TRUE)) {
        continue;
      }
      // Get the module's permissions.
      $modulePermissions = $this->getModulePermissions($module);
      if (empty($modulePermissions)) {
        continue;
      }
      // Find the difference between the module permissions and
      // the Site Builder role's permissions.
      $diff = array_diff($modulePermissions, $siteBuilderPerms);
      // If there are differences, add them to the Site Builder role.
      if (!empty($diff)) {
        // Grant permission for each role in the diff array.
        foreach ($modulePermissions as $permission) {
          $permissionBlacklist = $this->configFactory->get('asu_governance.settings')->get('permissions_blacklist');
          if (!in_array($permission, $permissionBlacklist, TRUE)) {
            $role->grantPermission($permission);
          }
        }
      }
    }
    $role->save();
  }

  /**
   * Add Site Builder role's access to administrative views.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addSiteBuilderViewsPermissions() {
    // Adjust views display permissions to grant access to Site Builders.
    $view_storage = $this->entityTypeManager->getStorage('view');
    $views = $view_storage->loadMultiple();
    foreach ($views as $view_id => $view) {
      $view_config = $this->configFactory->getEditable('views.view.' . $view_id);
      $display_definitions = $view_config->get('display');
      $config_changed = FALSE;
      foreach ($display_definitions as $display_id => $display_definition) {
        $access_type = $display_definition['display_options']['access']['type'] ?? NULL;
        if ($access_type && $access_type === 'role') {
          if (isset($display_definition['display_options']['access']['options']['role']['administrator'])) {
            $view_config->set('display.' . $display_id . '.display_options.access.options.role.site_builder', 'site_builder');
            $config_changed = TRUE;
          }
        }
      }
      if ($config_changed) {
        $view_config->save();
      }
    }
  }

  /**
   * Revoke the Site Builder role's permissions.
   *
   * @param array $modules
   *   An array of module names to have permissions revoked.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function revokeSiteBuilderModulePermissions(array $modules) {
    foreach ($modules as $module) {
      // Get the module's permissions.
      $modulePermissions = $this->getModulePermissions($module);
      if (empty($modulePermissions)) {
        continue;
      }
      // Load the Site Builder role.
      /** @var \Drupal\user\Entity\Role $role */
      $role = Role::load('site_builder');
      // Revoke permissions from the Site Builder role.
      foreach ($modulePermissions as $permission) {
        $role->revokePermission($permission);
      }
      $role->save();
    }
  }

  /**
   * Gets all permissions provided by a specific module.
   *
   * @param string $module
   *   The machine name of the module.
   *
   * @return array
   *   An array of permissions provided by the module.
   */
  public function getModulePermissions(string $module): array {
    $permissions = $this->permissionHandler->getPermissions();
    $module_permissions = [];

    foreach ($permissions as $permission_id => $permission_info) {
      if (isset($permission_info['provider']) && $permission_info['provider'] === $module) {
        $module_permissions[$permission_id] = $permission_info;
      }
    }

    return array_keys($module_permissions);
  }

  /**
   * Revoke blacklisted permissions for all but administrator and site_builder.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function revokeBlacklistedPermissions() {
    $blacklist = $this->configFactory->get('asu_governance.settings')->get('permissions_blacklist');
    // Get all roles.
    $roles = Role::loadMultiple();
    // Remove the administrator and site_builder roles from the list.
    unset($roles['administrator'], $roles['site_builder']);
    // Loop through each role.
    foreach ($roles as $role) {
      // Get the role's permissions.
      $permissions = $role->getPermissions();
      // Loop through each permission.
      foreach ($permissions as $permission) {
        // If the permission is in the blacklist, revoke it.
        if (in_array($permission, $blacklist)) {
          $role->revokePermission($permission);
        }
      }
      // Save the role.
      $role->save();
    }
  }

}
