<?php

namespace Drupal\asu_governance\Form;

use Drupal\Core\Config\PreExistingConfigException;
use Drupal\Core\Config\UnmetDependenciesException;
use Drupal\Core\Access\AccessManagerInterface;
use Drupal\Core\Extension\Extension;
use Drupal\Core\Extension\ExtensionLifecycle;
use Drupal\Core\Extension\InfoParserException;
use Drupal\Core\Extension\ModuleDependencyMessageTrait;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\Render\Element;
use Drupal\Core\Render\Markup;
use Drupal\Core\Session\AccountInterface;
use Drupal\system\Form\ModulesEnabledTrait;
use Drupal\user\PermissionHandlerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Component\Utility\Xss;

/**
 * Provides module installation interface.
 *
 * The list of curated modules includes all modules marked as allowed in the
 * asu_governance's settings form, except for obsolete modules.
 *
 * @see \Drupal\Core\Extension\InfoParser
 */
class CuratedModulesListForm extends FormBase {

  use ModuleDependencyMessageTrait;
  use ModulesEnabledTrait;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The expirable key value store.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface
   */
  protected $keyValueExpirable;

  /**
   * The module installer.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * The permission handler.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * The access manager.
   *
   * @var \Drupal\Core\Access\AccessManagerInterface
   */
  protected $accessManager;

  /**
   * Allowed modules from the configuration.
   *
   * @var array
   */
  protected $allowableModules;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\Messenger
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler'),
      $container->get('module_installer'),
      $container->get('keyvalue.expirable')->get('module_list'),
      $container->get('access_manager'),
      $container->get('current_user'),
      $container->get('user.permissions'),
      $container->get('extension.list.module'),
      $container->get('messenger')

    );
  }

  /**
   * Constructs a CuratedModulesListForm object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleInstallerInterface $module_installer
   *   The module installer.
   * @param \Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface $key_value_expirable
   *   The key value expirable factory.
   * @param \Drupal\Core\Access\AccessManagerInterface $access_manager
   *   Access manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module,
   *   The module extension list.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   */
  public function __construct(ModuleHandlerInterface $module_handler, ModuleInstallerInterface $module_installer, KeyValueStoreExpirableInterface $key_value_expirable, AccessManagerInterface $access_manager, AccountInterface $current_user, PermissionHandlerInterface $permission_handler, ModuleExtensionList $extension_list_module, Messenger $messenger) {
    $this->moduleExtensionList = $extension_list_module;
    $this->moduleHandler = $module_handler;
    $this->moduleInstaller = $module_installer;
    $this->keyValueExpirable = $key_value_expirable;
    $this->accessManager = $access_manager;
    $this->currentUser = $current_user;
    $this->permissionHandler = $permission_handler;
    $this->allowableModules = $this->config('asu_governance.settings')->get('allowable_modules') ?? [];
    $this->messenger = $messenger;
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'asu_governance_curated_modules';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $this->messenger->addMessage($this->t('<p><strong>Please note:</strong> This is a filtered list of all the modules available to you in the site based on your user role/permissions.</p><p>If you do not see a module you previously had access to in Pantheon, please <a href="https://asu.edu/webservices">open a Web Services ticket</a> and we will assist you.</p>'), 'warning');

    require_once DRUPAL_ROOT . '/core/includes/install.inc';
    $distribution = drupal_install_profile_distribution_name();

    // Include system.admin.inc so we can use the sort callbacks.
    $this->moduleHandler->loadInclude('system', 'inc', 'system.admin');

    $form['filters'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['table-filter', 'js-show'],
      ],
    ];

    $form['filters']['text'] = [
      '#type' => 'search',
      '#title' => $this->t('Filter modules'),
      '#title_display' => 'invisible',
      '#size' => 30,
      '#placeholder' => $this->t('Filter by name or description'),
      '#description' => $this->t('Enter a part of the module name or description'),
      '#attributes' => [
        'class' => ['table-filter-text'],
        'data-table' => '#asu-governance-curated-modules',
        'autocomplete' => 'off',
      ],
    ];

    // Sort all modules by their names.
    try {
      // The module list needs to be reset so that it can re-scan and include
      // any new modules that may have been added directly into the filesystem.
      $all_modules = array_filter($this->moduleExtensionList->reset()->getList(), function ($module) {
        return !$module->isObsolete();
      });

      // Only include ASU modules.
      $displayed_modules = array_filter($all_modules, function ($module) {
        if (!$module->isObsolete()
            && (in_array($module->getName(), $this->allowableModules, TRUE)
          )) {
          return $module;
        }
        return FALSE;
      });
      uasort($displayed_modules, [ModuleExtensionList::class, 'sortByName']);
    }
    catch (InfoParserException $e) {
      $this->messenger()->addError($this->t('Modules could not be listed due to an error: %error', ['%error' => $e->getMessage()]));
      $displayed_modules = [];
    }

    // Iterate over each of the modules.
    $form['modules']['#tree'] = TRUE;
    $incompatible_installed = FALSE;
    foreach ($displayed_modules as $filename => $module) {
      if (empty($module->info['hidden'])) {
        $package = $module->info['package'];
        $form['modules'][$package][$filename] = $this->buildRow($all_modules,
          $displayed_modules,
          $module,
          $distribution
        );
        $form['modules'][$package][$filename]['#parents'] = [
          'modules',
          $filename,
        ];
      }
      if (!$incompatible_installed && $module->status && $module->info['core_incompatible']) {
        $incompatible_installed = TRUE;
        $this->messenger()->addWarning($this->t(
          'There are errors with some installed modules. Visit the <a href=":link">status report page</a> for more information.',
          [':link' => Url::fromRoute('system.status')->toString()]
        ));
      }
    }

    // Add a wrapper around every package.
    foreach (Element::children($form['modules']) as $package) {
      $form['modules'][$package] += [
        '#type' => 'details',
        '#title' => Markup::create(Xss::filterAdmin($this->t('@package', ['@package' => $package]))),
        '#open' => TRUE,
        '#theme' => 'system_modules_details',
        '#attributes' => ['class' => ['package-listing']],
        // Ensure that the "Core" package comes first.
        '#weight' => $package == 'Core' ? -10 : NULL,
      ];
    }

    // If testing modules are shown, collapse the corresponding package by
    // default.
    if (isset($form['modules']['Testing'])) {
      $form['modules']['Testing']['#open'] = FALSE;
    }

    // Lastly, sort all packages by title.
    uasort($form['modules'], [
      '\Drupal\Component\Utility\SortArray',
      'sortByTitleProperty',
    ]);

    $form['#attached']['library'][] = 'core/drupal.tableresponsive';
    $form['#attached']['library'][] = 'system/drupal.system.modules';
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Install'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * Builds a table row for the ASU Modules page.
   *
   * @param array $all_modules
   *   The list of active modules.
   * @param array $displayed_modules
   *   The list of displayed modules.
   * @param \Drupal\Core\Extension\Extension $module
   *   The module for which to build the form row.
   * @param string $distribution
   *   The distribution.
   *
   * @return array
   *   The form row for the given module.
   */
  protected function buildRow(array $all_modules, array $displayed_modules, Extension $module, string $distribution) {
    // Set the basic properties.
    $row['#required'] = [];
    $row['#requires'] = [];
    $row['#required_by'] = [];

    $lifecycle = $module->info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER];
    $row['name']['#markup'] = $module->info['name'];
    if ($lifecycle !== ExtensionLifecycle::STABLE && !empty($module->info[ExtensionLifecycle::LIFECYCLE_LINK_IDENTIFIER])) {
      $row['name']['#markup'] .= ' ' . Link::fromTextAndUrl('(' . $this->t('@lifecycle', ['@lifecycle' => ucfirst($lifecycle)]) . ')',
          Url::fromUri($module->info[ExtensionLifecycle::LIFECYCLE_LINK_IDENTIFIER], [
            'attributes' =>
              [
                'class' => ['module-link--non-stable'],
                'aria-label' => $this->t('View information on the @lifecycle status of the module @module', [
                  '@lifecycle' => ucfirst($lifecycle),
                  '@module' => $module->info['name'],
                ]),
              ],
          ])
        )->toString();
    }
    $row['description']['#markup'] = (string) $this->t('@description', ['@description' => $module->info['description']]);
    $row['version']['#markup'] = $module->info['version'];

    // Generate link for module's help page. Assume that if a hook_help()
    // implementation exists then the module provides an overview page, rather
    // than checking to see if the page exists, which is costly.
    if ($this->moduleHandler->moduleExists('help') && $module->status && $this->moduleHandler->hasImplementations('help', $module->getName())) {
      $row['links']['help'] = [
        '#type' => 'link',
        '#title' => $this->t('Help <span class="visually-hidden">for @module</span>', ['@module' => $module->info['name']]),
        '#url' => Url::fromRoute('help.page', ['name' => $module->getName()]),
        '#options' => [
          'attributes' => [
            'class' => [
              'module-link',
              'module-link-help',
            ],
          ],
        ],
      ];
    }

    // Generate link for module's permission, if the user has access to it.
    if ($module->status && $this->currentUser->hasPermission('administer permissions') && $this->permissionHandler->moduleProvidesPermissions($module->getName())) {
      $row['links']['permissions'] = [
        '#type' => 'link',
        '#title' => $this->t('Permissions <span class="visually-hidden">for @module</span>', ['@module' => $module->info['name']]),
        '#url' => Url::fromRoute('user.admin_permissions.module', ['modules' => $module->getName()]),
        '#options' => [
          'attributes' => [
            'class' => [
              'module-link',
              'module-link-permissions',
            ],
          ],
        ],
      ];
    }

    // Generate link for module's configuration page, if it has one.
    if ($module->status && isset($module->info['configure'])) {
      $route_parameters = $module->info['configure_parameters'] ?? [];
      if ($this->accessManager->checkNamedRoute($module->info['configure'], $route_parameters, $this->currentUser)) {
        $row['links']['configure'] = [
          '#type' => 'link',
          '#title' => $this->t('Configure <span class="visually-hidden">@module</span>', ['@module' => $module->info['name']]),
          '#url' => Url::fromRoute($module->info['configure'], $route_parameters),
          '#options' => [
            'attributes' => [
              'class' => ['module-link', 'module-link-configure'],
            ],
          ],
        ];
      }
    }

    // Present a checkbox for installing and indicating the status of a module.
    $row['enable'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Install'),
      '#default_value' => (bool) $module->status,
      '#disabled' => (bool) $module->status,
    ];

    // Disable the checkbox for required modules.
    if (!empty($module->info['required'])) {
      // Used when displaying modules required by the installation profile.
      $row['enable']['#disabled'] = TRUE;
      $row['#required_by'][] = $distribution . (!empty($module->info['explanation']) ? ' (' . $module->info['explanation'] . ')' : '');
    }

    // Check the compatibilities.
    $compatible = TRUE;

    // Initialize an empty array of reasons why the module is incompatible. Add
    // each reason as a separate element of the array.
    $reasons = [];

    // Check the core compatibility.
    if ($module->info['core_incompatible']) {
      $compatible = FALSE;
      $reasons[] = $this->t('This version is not compatible with Drupal @core_version and should be replaced.', [
        '@core_version' => \Drupal::VERSION,
      ]);
      $row['#requires']['core'] = $this->t('Drupal Core (@core_requirement) (<span class="admin-missing">incompatible with</span> version @core_version)', [
        '@core_requirement' => $module->info['core_version_requirement'] ?? $module->info['core'],
        '@core_version' => \Drupal::VERSION,
      ]);
    }

    // Ensure this module is compatible with installed version of PHP.
    if (version_compare(phpversion(), $module->info['php']) < 0) {
      $compatible = FALSE;
      $required = $module->info['php'] . (substr_count($module->info['php'], '.') < 2 ? '.*' : '');
      $reasons[] = $this->t('This module requires PHP version @php_required and is incompatible with PHP version @php_version.', [
        '@php_required' => $required,
        '@php_version' => phpversion(),
      ]);
    }

    // If this module is not compatible, disable the checkbox.
    if (!$compatible) {
      $status = implode(' ', $reasons);
      $row['enable']['#disabled'] = TRUE;
      $row['description']['#markup'] = $status;
      $row['#attributes']['class'][] = 'incompatible';
    }

    // If this module requires other modules, add them to the array.
    /** @var \Drupal\Core\Extension\Dependency $dependency_object */
    foreach ($module->requires as $dependency => $dependency_object) {
      // @todo Add logic for not displaying hidden modules in
      //   https://drupal.org/node/3117829.
      if ($incompatible = $this->checkDependencyMessage($all_modules, $dependency, $dependency_object)) {
        $row['#requires'][$dependency] = $incompatible;
        $row['enable']['#disabled'] = TRUE;
        continue;
      }

      $name = $all_modules[$dependency]->info['name'];
      $row['#requires'][$dependency] = $all_modules[$dependency]->status ? $this->t('@module', ['@module' => $name]) : $this->t('@module (<span class="admin-disabled">disabled</span>)', ['@module' => $name]);
    }

    // If this module is required by other modules, list those, and then make it
    // impossible to disable this one.
    foreach ($module->required_by as $dependent => $version) {
      if (isset($all_modules[$dependent]) && empty($all_modules[$dependent]->info['hidden'])) {
        if ($all_modules[$dependent]->status == 1 && $module->status == 1) {
          $row['#required_by'][$dependent] = $this->t('@module', ['@module' => $all_modules[$dependent]->info['name']]);
          $row['enable']['#disabled'] = TRUE;
        }
        else {
          $row['#required_by'][$dependent] = $this->t('@module (<span class="admin-disabled">disabled</span>)', ['@module' => $all_modules[$dependent]->info['name']]);
        }
      }
    }

    return $row;
  }

  /**
   * Helper function for building a list of modules to install.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   An array of modules to install and their dependencies.
   */
  protected function buildModuleList(FormStateInterface $form_state) {
    // Build a list of modules to install.
    $modules = [
      'install' => [],
      'dependencies' => [],
      'non_stable' => [],
    ];

    $data = $this->moduleExtensionList->getList();
    foreach ($data as $name => $module) {
      // If the module is installed there is nothing to do.
      if ($this->moduleHandler->moduleExists($name)) {
        continue;
      }
      // Required modules have to be installed.
      if (!empty($module->required)) {
        $modules['install'][$name] = $module->info['name'];
      }
      // Selected modules should be installed.
      elseif (($checkbox = $form_state->getValue(['modules', $name], FALSE)) && $checkbox['enable']) {
        $info = $data[$name]->info;
        $modules['install'][$name] = $info['name'];
        // Identify non-stable modules.
        $lifecycle = $info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER];
        if ($lifecycle !== ExtensionLifecycle::STABLE) {
          $modules['non_stable'][$name] = $info['name'];
        }
      }
    }

    // Add all dependencies to a list.
    foreach ($modules['install'] as $module => $value) {
      foreach (array_keys($data[$module]->requires) as $dependency) {
        if (!isset($modules['install'][$dependency]) && !$this->moduleHandler->moduleExists($dependency)) {
          $dependency_info = $data[$dependency]->info;
          $modules['dependencies'][$module][$dependency] = $dependency_info['name'];
          $modules['install'][$dependency] = $dependency_info['name'];

          // Identify non-stable modules.
          $lifecycle = $dependency_info[ExtensionLifecycle::LIFECYCLE_IDENTIFIER];
          if ($lifecycle !== ExtensionLifecycle::STABLE) {
            $modules['non_stable'][$dependency] = $dependency_info['name'];
          }
        }
      }
    }

    // Make sure the install API is available.
    include_once DRUPAL_ROOT . '/core/includes/install.inc';

    // Invoke hook_requirements('install'). If failures are detected, make
    // sure the dependent modules aren't installed either.
    foreach (array_keys($modules['install']) as $module) {
      if (!drupal_check_module($module)) {
        unset($modules['install'][$module]);
        unset($modules['non_stable'][$module]);
        foreach (array_keys($data[$module]->required_by) as $dependent) {
          unset($modules['install'][$dependent]);
          unset($modules['dependencies'][$dependent]);
        }
      }
    }

    return $modules;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve a list of modules to install and their dependencies.
    $modules = $this->buildModuleList($form_state);

    // Redirect to a confirmation form if needed.
    if (!empty($modules['non_stable']) || !empty($modules['dependencies'])) {

      $route_name = !empty($modules['non_stable']) ? 'system.modules_list_non_stable_confirm' : 'asu_governance.modules_list_confirm';
      // Write the list of changed module states into a key value store.
      $account = $this->currentUser()->id();
      $this->keyValueExpirable->setWithExpire($account, $modules, 60);

      // Redirect to the confirmation form.
      $form_state->setRedirect($route_name);

      // We can exit here because at least one module has dependencies
      // which we have to prompt the user for in a confirmation form.
      return;
    }

    // Install the given modules.
    if (!empty($modules['install'])) {
      try {
        $this->moduleInstaller->install(array_keys($modules['install']));
        $this->messenger()
          ->addStatus($this->modulesEnabledConfirmationMessage($modules['install']));
      }
      catch (PreExistingConfigException $e) {
        $this->messenger()->addError($this->modulesFailToEnableMessage($modules, $e));
        return;
      }
      catch (UnmetDependenciesException $e) {
        $this->messenger()->addError(
          $e->getTranslatedMessage($this->getStringTranslation(), $modules['install'][$e->getExtension()])
        );
        return;
      }
    }
  }

}
