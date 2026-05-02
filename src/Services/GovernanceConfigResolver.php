<?php

declare(strict_types=1);

namespace Drupal\asu_governance\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Resolves governance configuration from the active environment preset.
 *
 * The main config (asu_governance.settings) stores runtime values and is kept
 * in sync on every save. However, when preset-specific fields
 * (allowable_modules,allowable_themes, permissions_blacklist) are modified
 * outside the settings form, this service ensures the active preset config
 * is also updated.
 */
class GovernanceConfigResolver {

  /**
   * Config name prefix for environment presets.
   */
  public const ENV_CONFIG_PREFIX = 'asu_governance.settings.env_';

  /**
   * Fields that are stored per-preset.
   */
  public const PRESET_FIELDS = [
    'allowable_modules',
    'allowable_themes',
    'permissions_blacklist',
    'allow_config_access',
    'allow_roles_perms_admin',
    'permissions_users',
  ];

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected ModuleHandlerInterface $moduleHandler;

  /**
   * Constructs a GovernanceConfigResolver.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ModuleHandlerInterface $module_handler) {
    $this->configFactory = $config_factory;
    $this->moduleHandler = $module_handler;
  }

  /**
   * Get the active environment preset key, or empty string if none.
   *
   * @return string
   *   The active preset key (eg, 'stack1', 'my_site'), or '' if none selected.
   */
  public function getActivePresetKey(): string {
    return $this->configFactory->get('asu_governance.settings')->get('active_environment_preset') ?? '';
  }

  /**
   * Get the config name for the active environment preset.
   *
   * @return string|null
   *   The config name (e.g., 'asu_governance.settings.env_1'), or NULL if no
   *   preset is active.
   */
  public function getActivePresetConfigName(): ?string {
    $key = $this->getActivePresetKey();
    if (empty($key)) {
      return NULL;
    }
    return self::ENV_CONFIG_PREFIX . $key;
  }

  /**
   * Get a preset-aware value from governance configuration.
   *
   * For preset fields (allowable_modules, allowable_themes,
   * permissions_blacklist), reads from the active preset config (DB first,
   * YAML fallback). Falls back to the main config for non-preset fields or
   * when no preset is active.
   *
   * @param string $key
   *   The configuration key.
   *
   * @return mixed
   *   The configuration value.
   */
  public function get(string $key): mixed {
    $presetKey = $this->getActivePresetKey();

    if (!empty($presetKey) && in_array($key, self::PRESET_FIELDS, TRUE)) {
      $data = $this->loadPresetData($presetKey);
      if ($data !== NULL && isset($data[$key])) {
        return $data[$key];
      }
    }

    return $this->configFactory->get('asu_governance.settings')->get($key);
  }

  /**
   * Set a value on both the main config and the active preset (if applicable).
   *
   * @param string $key
   *   The configuration key.
   * @param mixed $value
   *   The value to set.
   */
  public function set(string $key, mixed $value): void {
    // Always update the main config.
    $this->configFactory->getEditable('asu_governance.settings')
      ->set($key, $value)
      ->save();

    // Also update the active preset config for preset-specific fields.
    $presetConfigName = $this->getActivePresetConfigName();
    if ($presetConfigName && in_array($key, self::PRESET_FIELDS, TRUE)) {
      $presetConfig = $this->configFactory->getEditable($presetConfigName);
      if (!$presetConfig->isNew()) {
        $presetConfig->set($key, $value)->save();
      }
    }
  }

  /**
   * Load preset data from active DB config first, falling back to YAML file.
   *
   * @param string $presetKey
   *   The preset key.
   *
   * @return array|null
   *   The preset data array, or NULL if not found.
   */
  public function loadPresetData(string $presetKey): ?array {
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
      return $this->parseYamlFile($file);
    }

    return NULL;
  }

  /**
   * Safely parse a YAML file, returning its data or NULL on failure.
   *
   * Logs an error if the file cannot be read or contains malformed YAML.
   *
   * @param string $file
   *   The absolute path to the YAML file.
   *
   * @return array|null
   *   The parsed data array, or NULL on failure.
   */
  public function parseYamlFile(string $file): ?array {
    $contents = @file_get_contents($file);
    if ($contents === FALSE) {
      \Drupal::logger('asu_governance')->error('Could not read file: @file', ['@file' => $file]);
      return NULL;
    }
    try {
      return Yaml::parse($contents);
    }
    catch (ParseException $e) {
      \Drupal::logger('asu_governance')->error('Malformed YAML in @file: @message', [
        '@file' => $file,
        '@message' => $e->getMessage(),
      ]);
      return NULL;
    }
  }

}
