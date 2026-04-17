<?php

declare(strict_types=1);

namespace Drupal\asu_governance\Form;

use Drupal\asu_governance\Services\ModulePermissionHandler;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ThemeHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\Yaml\Yaml;

/**
 * Configure ASU governance settings for this site.
 */
final class GovernanceSettingsForm extends ConfigFormBase {

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The theme handler service.
   *
   * @var \Drupal\Core\Extension\ThemeHandlerInterface
   */
  protected $themeHandler;

  /**
   * The module permission loader service.
   *
   * @var \Drupal\asu_governance\Services\ModulePermissionHandler
   */
  protected $modulePermissionHandler;

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * Disallowed modules.
   *
   * @var string[]
   */
  public const DISALLOWED_MODULES = [
    'asu_governance',
    'php',
  ];

  /**
   * Disallowed themes.
   *
   * @var string[]
   */
  public const DISALLOWED_THEMES = [
    'classy',
    'stable',
  ];

  /**
   * Config name prefix for environment presets.
   */
  public const ENV_CONFIG_PREFIX = 'asu_governance.settings.env_';

  /**
   * Build the form.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration factory.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\Extension\ThemeHandlerInterface $theme_handler
   *   The theme handler service.
   * @param \Drupal\asu_governance\Services\ModulePermissionHandler $modulePermissionHandler
   *   The module permission loader service.
   * @param \Drupal\Core\Database\Connection $connection
   *   The database connection.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler, ThemeHandlerInterface $theme_handler, ModulePermissionHandler $modulePermissionHandler, Connection $connection) {
    parent::__construct($config_factory);
    $this->moduleHandler = $module_handler;
    $this->themeHandler = $theme_handler;
    $this->modulePermissionHandler = $modulePermissionHandler;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('module_handler'),
      $container->get('theme_handler'),
      $container->get('asu_governance.module_permission_handler'),
      $container->get('database'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'asu_governance_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['asu_governance.settings'];
  }

  /**
   * Discover all available environment presets.
   *
   * Merges presets found in the database with those found as YAML files in
   * config/install/. Returns an associative array keyed by preset key (e.g.,
   * 'stack1', 'my_site') with values being arrays containing 'config_name' and
   * 'label'.
   *
   * @return array
   *   Associative array of discovered presets.
   */
  protected function discoverPresets(): array {
    $presets = [];
    $prefix = self::ENV_CONFIG_PREFIX;

    // 1. Discover from active DB config.
    $dbConfigs = $this->configFactory->listAll($prefix);
    foreach ($dbConfigs as $configName) {
      $key = substr($configName, strlen($prefix));
      $data = $this->configFactory->get($configName)->getRawData();
      $presets[$key] = [
        'config_name' => $configName,
        'label' => $data['label'] ?? $key,
      ];
    }

    // 2. Discover from YAML files (only add if not already in DB).
    $modulePath = $this->moduleHandler->getModule('asu_governance')->getPath();
    $installDir = DRUPAL_ROOT . '/' . $modulePath . '/config/install';
    if (is_dir($installDir)) {
      $pattern = $installDir . '/' . $prefix . '*.yml';
      foreach (glob($pattern) as $file) {
        $configName = basename($file, '.yml');
        $key = substr($configName, strlen($prefix));
        if (!isset($presets[$key])) {
          $data = Yaml::parse(file_get_contents($file));
          $presets[$key] = [
            'config_name' => $configName,
            'label' => $data['label'] ?? $key,
          ];
        }
      }
    }

    // Sort by label for consistent display.
    uasort($presets, fn($a, $b) => strcasecmp($a['label'], $b['label']));

    return $presets;
  }

  /**
   * Load preset data from active DB config first, falling back to YAML file.
   *
   * @param string $presetKey
   *   The preset key (e.g., '1', 'my_site').
   *
   * @return array|null
   *   The preset data array, or NULL if not found.
   */
  protected function loadPresetData(string $presetKey): ?array {
    $configName = self::ENV_CONFIG_PREFIX . $presetKey;

    // Try active DB config first.
    $dbConfig = $this->configFactory->get($configName);
    if (!$dbConfig->isNew()) {
      return $dbConfig->getRawData();
    }

    // Fall back to YAML file.
    $modulePath = $this->moduleHandler->getModule('asu_governance')->getPath();
    $file = DRUPAL_ROOT . '/' . $modulePath . '/config/install/' . $configName . '.yml';
    if (file_exists($file)) {
      return Yaml::parse(file_get_contents($file));
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('asu_governance.settings');

    // Discover all available environment presets dynamically.
    $discoveredPresets = $this->discoverPresets();

    // Build the select options.
    $presetOptions = ['' => $this->t('— Select —')];
    foreach ($discoveredPresets as $key => $preset) {
      $presetOptions[$key] = $preset['label'];
    }
    $presetOptions['_new'] = $this->t('+ New site');

    // Remember the last saved preset selection.
    $savedPreset = $config->get('active_environment_preset') ?? '';

    $form['environment_preset'] = [
      '#type' => 'select',
      '#title' => $this->t('Please indicate your website or stack'),
      '#options' => $presetOptions,
      '#default_value' => $savedPreset,
      '#required' => TRUE,
      '#ajax' => [
        'wrapper' => 'governance-fields-wrapper',
        'callback' => '::updatePresetFields',
      ],
    ];

    // Use Ajax-selected value if present, otherwise use the saved preset.
    $selectedPreset = $form_state->hasValue('environment_preset')
      ? $form_state->getValue('environment_preset')
      : $savedPreset;

    // When the preset selection changes via Ajax, clear the user input for the
    // preset fields so that #default_value is used instead of stale input.
    if ($form_state->hasValue('environment_preset')) {
      $input = &$form_state->getUserInput();
      unset($input['allowable_modules']);
      unset($input['allowable_themes']);
      unset($input['permissions_blacklist']);
      unset($input['new_site_name']);
      unset($input['allow_config_access']);
      unset($input['allow_roles_perms_admin']);
      unset($input['permissions_users']);
    }

    // Determine if a valid selection has been made.
    $isNewSite = ($selectedPreset === '_new');
    $isExistingPreset = !empty($selectedPreset) && isset($discoveredPresets[$selectedPreset]);
    $hasSelection = $isNewSite || $isExistingPreset;

    if (!$hasSelection) {
      // Show an empty wrapper so Ajax has a target to replace.
      $form['preset_fields'] = [
        '#type' => 'container',
        '#attributes' => ['id' => 'governance-fields-wrapper'],
      ];
      return parent::buildForm($form, $form_state);
    }

    // Load preset data for existing presets; new sites start with empty fields.
    if ($isExistingPreset) {
      $data = $this->loadPresetData($selectedPreset);
      $modulesInput = $data['allowable_modules'] ?? [];
      $themesInput = $data['allowable_themes'] ?? [];
      $blacklistInput = $data['permissions_blacklist'] ?? [];
      $allowConfigAccess = $data['allow_config_access'] ?? FALSE;
      $allowRolesPermsAdmin = $data['allow_roles_perms_admin'] ?? FALSE;
      $usersInput = $data['permissions_users'] ?? [];
    }
    else {
      $modulesInput = [];
      $themesInput = [];
      $blacklistInput = [];
      $allowConfigAccess = FALSE;
      $allowRolesPermsAdmin = FALSE;
      $usersInput = [];
    }

    $baseBlacklist = $this->modulePermissionHandler::BASE_BLACKLIST;

    // Wrapper for Ajax-replaced fields.
    $form['preset_fields'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'governance-fields-wrapper'],
    ];

    // Show a name field for new sites.
    if ($isNewSite) {
      $form['preset_fields']['new_site_name'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Site name'),
        '#description' => $this->t('Enter a name for this site or environment. This will be used as the label in the preset selector.'),
        '#required' => TRUE,
      ];
    }

    $form['preset_fields']['allowable_modules'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowable Modules with Permissions'),
      '#description' => $this->t('<p>Add modules, <strong>one per line, <u>by machine name</u></strong>, that Site Builders will be able to enable/disable and configure.</p>
        <p><strong>Please note:</strong> ALL associated permissions for the modules listed above will be automatically updated on the <strong>Site Builder</strong> role when this form is saved or the module is enabled.</p>'),
      '#default_value' => implode("\n", $modulesInput),
      '#required' => TRUE,
    ];

    $form['preset_fields']['allowable_themes'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Allowable Themes'),
      '#description' => $this->t('<p>Add themes, <strong>one per line, <u>by machine name</u></strong>, that Site Builders will be able to enable/disable and configure.</p>'),
      '#default_value' => implode("\n", $themesInput),
      '#required' => TRUE,
    ];

    $form['preset_fields']['permissions_blacklist'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Permissions Blacklist'),
      '#description' => $this->t('<p>Add permissions, <strong>one per line, <u>by machine name</u></strong>, that should not be granted to non-Administrator roles.</p>'),
      '#default_value' => !empty($blacklistInput) ? implode("\n", $blacklistInput) : implode("\n", $baseBlacklist),
      '#required' => TRUE,
    ];

    $form['preset_fields']['allow_config_access'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Allow config sync access</strong>'),
      '#description' => $this->t('If checked, users with the <strong>Site Builder</strong> role will be able to import/export/sync configurations via the <a href = "/admin/config/development/configuration">config sync page</a>.'),
      '#default_value' => $allowConfigAccess,
    ];
    $form['preset_fields']['allow_roles_perms_admin'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('<strong>Allow roles and permissions administration</strong>'),
      '#description' => $this->t('If checked, specified users with the <strong>Site Builder</strong> role will be able to manage roles and permissions.'),
      '#default_value' => $allowRolesPermsAdmin,
    ];
    $form['preset_fields']['permissions_users'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Permissions Users'),
      '#description' => $this->t('<p>Please list usernames (usually ASURITE IDs) of users with the <strong>Site Builder</strong> role, <strong>one per line</strong>, that should be allowed to have limited permissions/roles administrative access.</p>'),
      '#default_value' => implode("\n", $usersInput),
      '#states' => [
        'visible' => [
          ':input[name="allow_roles_perms_admin"]' => ['checked' => TRUE],
        ],
        'required' => [
          ':input[name="allow_roles_perms_admin"]' => ['checked' => TRUE],
        ],
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Ajax callback for the environment preset selector.
   */
  public function updatePresetFields(array $form, FormStateInterface $form_state): array {
    return $form['preset_fields'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    // Skip validation if env preset is not selected (fields are hidden).
    $selectedPreset = $form_state->getValue('environment_preset') ?? '';
    if (empty($selectedPreset)) {
      return;
    }

    // Validate new site name.
    if ($selectedPreset === '_new') {
      $newSiteName = trim($form_state->getValue('new_site_name') ?? '');
      if (empty($newSiteName)) {
        $form_state->setErrorByName('new_site_name', $this->t('Please enter a name for the new site.'));
        return;
      }
      // Generate a machine name and check for collisions.
      $machineKey = preg_replace('/[^a-z0-9_]/', '_', strtolower($newSiteName));
      $machineKey = preg_replace('/_+/', '_', trim($machineKey, '_'));
      $configName = self::ENV_CONFIG_PREFIX . $machineKey;
      $existing = $this->configFactory->get($configName);
      if (!$existing->isNew()) {
        $form_state->setErrorByName('new_site_name', $this->t('A preset with the key "@key" already exists. Please choose a different name.', ['@key' => $machineKey]));
        return;
      }
    }

    $modulesInput = array_filter(array_map('trim', explode("\n", $form_state->getValue('allowable_modules') ?? '')));
    $badModules = [];
    $themesInput = array_filter(array_map('trim', explode("\n", $form_state->getValue('allowable_themes') ?? '')));
    $badThemes = [];
    $currentTheme = $this->themeHandler->getDefault();
    $adminTheme = $this->config('system.theme')->get('admin');
    $includesDefault = in_array($currentTheme, $themesInput, TRUE);
    $includesAdmin = in_array($adminTheme, $themesInput, TRUE);
    $baseBlacklist = $this->modulePermissionHandler::BASE_BLACKLIST;
    $blacklistInput = array_filter(array_map('trim', explode("\n", $form_state->getValue('permissions_blacklist') ?? '')));
    $allPermissions = array_keys(\Drupal::service('user.permissions')->getPermissions());
    $missingPermissions = array_diff($baseBlacklist, $blacklistInput);

    foreach ($modulesInput as $module) {
      if (in_array($module, self::DISALLOWED_MODULES, TRUE)) {
        $badModules[] = $module;
      }
    }

    foreach ($themesInput as $theme) {
      if (in_array($theme, self::DISALLOWED_THEMES, TRUE)) {
        $badThemes[] = $theme;
      }
    }

    if (!empty($badModules)) {
      $form_state->setErrorByName('allowable_modules', $this->t('The following modules are not allowed: <strong>@modules</strong>', ['@modules' => implode(', ', array_map(fn($module) => "\"$module\"", $badModules))]));
    }

    if (!empty($badThemes)) {
      $form_state->setErrorByName('allowable_themes', $this->t('The following themes are not allowed: <strong>@themes</strong>', ['@themes' => implode(', ', array_map(fn($theme) => "\"$theme\"", $badThemes))]));
    }

    if (!$includesDefault) {
      $form_state->setErrorByName('allowable_themes', $this->t('The current default theme (@theme) must be included in the list of allowable themes.', ['@theme' => $currentTheme]));
    }

    if (!$includesAdmin) {
      $form_state->setErrorByName('allowable_themes', $this->t('The current admin theme (@theme) must be included in the list of allowable themes.', ['@theme' => $adminTheme]));
    }

    if (!empty($missingPermissions)) {
      $form_state->setErrorByName('permissions_blacklist', $this->t('The following required permissions are missing from the blacklist: <strong>@perms</strong>', ['@perms' => implode(', ', array_map(fn($perm) => "\"$perm\"", $missingPermissions))]));
    }

    $badPermissions = [];
    foreach ($blacklistInput as $permission) {
      if (!in_array($permission, $allPermissions, TRUE) && !in_array($permission, $baseBlacklist, TRUE)) {
        $badPermissions[] = $permission;
      }
    }

    if (!empty($badPermissions)) {
      $form_state->setErrorByName('permissions_blacklist', $this->t('The following permissions do not exist on the site: <strong>@perms</strong>. Please remove.', ['@perms' => implode(', ', array_map(fn($perm) => "\"$perm\"", $badPermissions))]));
    }

    if ($form_state->getValue('allow_roles_perms_admin')) {
      $usersInput = array_filter(array_map('trim', explode("\n", $form_state->getValue('permissions_users') ?? '')));
      $query = $this->connection->select('users_field_data', 'u');
      $query->join('user__roles', 'ur', 'u.uid = ur.entity_id');
      $query->fields('u', ['name'])
        ->fields('ur', ['roles_target_id'])
        ->condition('u.uid', 0, '>')
        ->condition('ur.roles_target_id', 'site_builder');
      $allSiteBuilders = $query->execute()->fetchCol(0);

      $badUsers = [];
      foreach ($usersInput as $user) {
        if (!in_array($user, $allSiteBuilders, TRUE)) {
          $badUsers[] = $user;
        }
      }

      if (!empty($badUsers)) {
        $form_state->setErrorByName('permissions_users', $this->t('The following users are not valid or do not have the Site Builder role: <strong>@users</strong>', ['@users' => implode(', ', array_map(fn($user) => "\"$user\"", $badUsers))]));
      }
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $governanceSettings = $this->config('asu_governance.settings');
    $originals = $governanceSettings->get('allowable_modules');

    $selectedPreset = $form_state->getValue('environment_preset') ?? '';

    // If no preset is selected, only save the selection and return.
    if (empty($selectedPreset)) {
      $governanceSettings->set('active_environment_preset', '')->save();
      return;
    }

    // Determine the preset key. For new sites, generate one from the name.
    if ($selectedPreset === '_new') {
      $newSiteName = trim($form_state->getValue('new_site_name') ?? '');
      $presetKey = preg_replace('/[^a-z0-9_]/', '_', strtolower($newSiteName));
      $presetKey = preg_replace('/_+/', '_', trim($presetKey, '_'));
      $presetLabel = $newSiteName;
    }
    else {
      $presetKey = $selectedPreset;
      // Preserve the existing label.
      $existingData = $this->loadPresetData($presetKey);
      $presetLabel = $existingData['label'] ?? $presetKey;
    }

    // Save which preset is active.
    $governanceSettings->set('active_environment_preset', $presetKey)->save();

    // Explode submitted modules textarea into an array and remove duplicates.
    $modulesInput = array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('allowable_modules') ?? ''))));
    $governanceSettings->set('allowable_modules', $modulesInput)->save();

    $modulesDiff = array_diff($originals ?? [], $modulesInput);
    if (!empty($modulesDiff)) {
      $this->modulePermissionHandler->revokeSiteBuilderModulePermissions($modulesDiff);
    }

    $this->modulePermissionHandler->addSiteBuilderModulePermissions($modulesInput);

    $themesInput = array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('allowable_themes') ?? ''))));
    $governanceSettings->set('allowable_themes', $themesInput)->save();

    $governanceSettings->set('allow_config_access', $form_state->getValue('allow_config_access'))->save();

    $blacklistInput = $form_state->getValue('permissions_blacklist') ? array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('permissions_blacklist'))))) : [];
    $governanceSettings->set('permissions_blacklist', $blacklistInput)->save();

    if ($form_state->getValue('allow_roles_perms_admin')) {
      $governanceSettings->set('allow_roles_perms_admin', $form_state->getValue('allow_roles_perms_admin'))->save();
      $permsUsersInput = array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('permissions_users') ?? ''))));
      $governanceSettings->set('permissions_users', $permsUsersInput)->save();
    }
    else {
      $governanceSettings->set('allow_roles_perms_admin', FALSE)->save();
      $governanceSettings->set('permissions_users', NULL)->save();
    }

    // Save the submitted values to the preset's DB config.
    $allowConfigAccess = (bool) $form_state->getValue('allow_config_access');
    $allowRolesPermsAdmin = (bool) $form_state->getValue('allow_roles_perms_admin');
    $permsUsersInput = $allowRolesPermsAdmin
      ? array_values(array_unique(array_filter(array_map('trim', explode("\n", $form_state->getValue('permissions_users') ?? '')))))
      : [];

    $presetConfigName = self::ENV_CONFIG_PREFIX . $presetKey;
    $presetConfig = $this->configFactory->getEditable($presetConfigName);
    $presetConfig->setData([
      'label' => $presetLabel,
      'allowable_modules' => array_values($modulesInput),
      'allowable_themes' => array_values($themesInput),
      'permissions_blacklist' => array_values($blacklistInput),
      'allow_config_access' => $allowConfigAccess,
      'allow_roles_perms_admin' => $allowRolesPermsAdmin,
      'permissions_users' => $permsUsersInput,
    ])->save();
  }

}
