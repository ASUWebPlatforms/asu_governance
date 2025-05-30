<?php

namespace Drupal\asu_governance\Services;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Discovery\YamlDiscovery;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Controller\ControllerResolverInterface;
use Drupal\user\Entity\Role;
use Drupal\user\PermissionHandlerInterface;

/**
 * Service to handle permissions for all asu_governance allowed modules.
 *
 * Allows to dynamically add/update them to the Site Builder role.
 */
class ModulePermissionHandler {
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
   * Base permissions for the Content Editor role.
   *
   * @var string[]
   */
  public const BASE_CE_PERMISSIONS = [
    'access administration pages',
    'access block library',
    'access content',
    'access content overview',
    'access contextual links',
    'access files overview',
    'access help pages',
    'access media overview',
    'access own webform configuration',
    'access shortcuts',
    'access site in maintenance mode',
    'access taxonomy overview',
    'access toolbar',
    'access webform help',
    'access webform overview',
    'access webform submission user',
    'administer block content',
    'administer menu',
    'administer meta tags',
    'administer redirects',
    'administer url aliases',
    'configure editable article node layout overrides',
    'configure editable page node layout overrides',
    'create accordion block content',
    'create and edit custom blocks',
    'create article content',
    'create audio media',
    'create banner block content',
    'create blockquote block content',
    'create card_and_image block content',
    'create card_arrangement block content',
    'create card_carousel block content',
    'create card_image_and_content block content',
    'create carousel_image block content',
    'create content_image block content',
    'create cropped_image_16_25 media',
    'create cropped_image_rounded_1_1 media',
    'create cropped_image_sqare media',
    'create cropped_image_wide media',
    'create display_list block content',
    'create divider block content',
    'create document media',
    'create donut_chart block content',
    'create events block content',
    'create gallery block content',
    'create grid_links block content',
    'create hero block content',
    'create hover_cards block content',
    'create icon_list block content',
    'create image block content',
    'create image media',
    'create image_and_text_block block content',
    'create image_background_with_cta block content',
    'create image_block_images media',
    'create inset_box block content',
    'create media',
    'create menu_sidebar block content',
    'create news block content',
    'create page content',
    'create remote_video media',
    'create step_list block content',
    'create story_hero media',
    'create tabbed_content block content',
    'create terms in location',
    'create terms in tags',
    'create testimonial block content',
    'create testimonial_carousel block content',
    'create testimonial_on_image_background block content',
    'create text_content block content',
    'create url aliases',
    'create video block content',
    'create video media',
    'create video_hero block content',
    'create video_poster_image_73_100 media',
    'create web_directory block content',
    'create webform',
    'create webform block content',
    'customize shortcut links',
    'delete any accordion block content',
    'delete any accordion block content revisions',
    'delete any article content',
    'delete any audio media',
    'delete any banner block content',
    'delete any banner block content revisions',
    'delete any blockquote block content',
    'delete any blockquote block content revisions',
    'delete any card_and_image block content',
    'delete any card_and_image block content revisions',
    'delete any card_arrangement block content',
    'delete any card_arrangement block content revisions',
    'delete any card_carousel block content',
    'delete any card_carousel block content revisions',
    'delete any card_image_and_content block content',
    'delete any card_image_and_content block content revisions',
    'delete any carousel_image block content',
    'delete any carousel_image block content revisions',
    'delete any content_image block content',
    'delete any content_image block content revisions',
    'delete any cropped_image_16_25 media',
    'delete any cropped_image_rounded_1_1 media',
    'delete any cropped_image_sqare media',
    'delete any cropped_image_wide media',
    'delete any display_list block content',
    'delete any display_list block content revisions',
    'delete any divider block content',
    'delete any divider block content revisions',
    'delete any document media',
    'delete any donut_chart block content',
    'delete any donut_chart block content revisions',
    'delete any events block content',
    'delete any events block content revisions',
    'delete any file',
    'delete any gallery block content',
    'delete any gallery block content revisions',
    'delete any grid_links block content',
    'delete any grid_links block content revisions',
    'delete any hero block content',
    'delete any hero block content revisions',
    'delete any hover_cards block content',
    'delete any hover_cards block content revisions',
    'delete any icon_list block content',
    'delete any icon_list block content revisions',
    'delete any image block content',
    'delete any image block content revisions',
    'delete any image media',
    'delete any image_and_text_block block content',
    'delete any image_and_text_block block content revisions',
    'delete any image_background_with_cta block content',
    'delete any image_background_with_cta block content revisions',
    'delete any image_block_images media',
    'delete any inset_box block content',
    'delete any inset_box block content revisions',
    'delete any media',
    'delete any menu_sidebar block content',
    'delete any menu_sidebar block content revisions',
    'delete any news block content',
    'delete any news block content revisions',
    'delete any page content',
    'delete any remote_video media',
    'delete any step_list block content',
    'delete any step_list block content revisions',
    'delete any story_hero media',
    'delete any tabbed_content block content',
    'delete any tabbed_content block content revisions',
    'delete any testimonial block content',
    'delete any testimonial block content revisions',
    'delete any testimonial_carousel block content',
    'delete any testimonial_carousel block content revisions',
    'delete any testimonial_on_image_background block content',
    'delete any testimonial_on_image_background block content revisions',
    'delete any text_content block content',
    'delete any text_content block content revisions',
    'delete any video block content',
    'delete any video block content revisions',
    'delete any video media',
    'delete any video_hero block content',
    'delete any video_hero block content revisions',
    'delete any video_poster_image_73_100 media',
    'delete any web_directory block content',
    'delete any web_directory block content revisions',
    'delete any webform block content',
    'delete any webform block content revisions',
    'delete article revisions',
    'delete own article content',
    'delete own audio media',
    'delete own cropped_image_16_25 media',
    'delete own cropped_image_rounded_1_1 media',
    'delete own cropped_image_sqare media',
    'delete own cropped_image_wide media',
    'delete own document media',
    'delete own files',
    'delete own image media',
    'delete own image_block_images media',
    'delete own page content',
    'delete own remote_video media',
    'delete own story_hero media',
    'delete own video media',
    'delete own video_poster_image_73_100 media',
    'delete own webform',
    'delete own webform submission',
    'delete page revisions',
    'delete terms in location',
    'delete terms in tags',
    'edit any accordion block content',
    'edit any article content',
    'edit any banner block content',
    'edit any blockquote block content',
    'edit any card_and_image block content',
    'edit any card_arrangement block content',
    'edit any card_carousel block content',
    'edit any card_image_and_content block content',
    'edit any carousel_image block content',
    'edit any content_image block content',
    'edit any display_list block content',
    'edit any divider block content',
    'edit any donut_chart block content',
    'edit any events block content',
    'edit any gallery block content',
    'edit any grid_links block content',
    'edit any hero block content',
    'edit any hover_cards block content',
    'edit any icon_list block content',
    'edit any image block content',
    'edit any image_and_text_block block content',
    'edit any image_background_with_cta block content',
    'edit any inset_box block content',
    'edit any menu_sidebar block content',
    'edit any news block content',
    'edit any page content',
    'edit any step_list block content',
    'edit any tabbed_content block content',
    'edit any testimonial block content',
    'edit any testimonial_carousel block content',
    'edit any testimonial_on_image_background block content',
    'edit any text_content block content',
    'edit any video block content',
    'edit any video_hero block content',
    'edit any web_directory block content',
    'edit any webform block content',
    'edit own article content',
    'edit own audio media',
    'edit own cropped_image_16_25 media',
    'edit own cropped_image_rounded_1_1 media',
    'edit own cropped_image_sqare media',
    'edit own cropped_image_wide media',
    'edit own document media',
    'edit own image media',
    'edit own image_block_images media',
    'edit own page content',
    'edit own remote_video media',
    'edit own story_hero media',
    'edit own video media',
    'edit own video_poster_image_73_100 media',
    'edit own webform',
    'edit own webform submission',
    'edit terms in location',
    'edit terms in tags',
    'revert all revisions',
    'revert any accordion block content revisions',
    'revert any banner block content revisions',
    'revert any blockquote block content revisions',
    'revert any card_and_image block content revisions',
    'revert any card_arrangement block content revisions',
    'revert any card_carousel block content revisions',
    'revert any card_image_and_content block content revisions',
    'revert any carousel_image block content revisions',
    'revert any content_image block content revisions',
    'revert any display_list block content revisions',
    'revert any divider block content revisions',
    'revert any donut_chart block content revisions',
    'revert any events block content revisions',
    'revert any gallery block content revisions',
    'revert any grid_links block content revisions',
    'revert any hero block content revisions',
    'revert any hover_cards block content revisions',
    'revert any icon_list block content revisions',
    'revert any image block content revisions',
    'revert any image_and_text_block block content revisions',
    'revert any image_background_with_cta block content revisions',
    'revert any inset_box block content revisions',
    'revert any menu_sidebar block content revisions',
    'revert any news block content revisions',
    'revert any step_list block content revisions',
    'revert any tabbed_content block content revisions',
    'revert any testimonial block content revisions',
    'revert any testimonial_carousel block content revisions',
    'revert any testimonial_on_image_background block content revisions',
    'revert any text_content block content revisions',
    'revert any video block content revisions',
    'revert any video_hero block content revisions',
    'revert any web_directory block content revisions',
    'revert any webform block content revisions',
    'revert article revisions',
    'revert page revisions',
    'revert term revisions in location',
    'revert term revisions in tags',
    'switch shortcut sets',
    'update media',
    'use text format basic_html',
    'use text format minimal_format',
    'use text format restricted_html',
    'use text format webform_default',
    'view all revisions',
    'view all taxonomy revisions',
    'view any accordion block content history',
    'view any banner block content history',
    'view any blockquote block content history',
    'view any card_and_image block content history',
    'view any card_arrangement block content history',
    'view any card_carousel block content history',
    'view any card_image_and_content block content history',
    'view any carousel_image block content history',
    'view any content_image block content history',
    'view any display_list block content history',
    'view any divider block content history',
    'view any donut_chart block content history',
    'view any events block content history',
    'view any gallery block content history',
    'view any grid_links block content history',
    'view any hero block content history',
    'view any hover_cards block content history',
    'view any icon_list block content history',
    'view any image block content history',
    'view any image_and_text_block block content history',
    'view any image_background_with_cta block content history',
    'view any inset_box block content history',
    'view any menu_sidebar block content history',
    'view any news block content history',
    'view any step_list block content history',
    'view any tabbed_content block content history',
    'view any testimonial block content history',
    'view any testimonial_carousel block content history',
    'view any testimonial_on_image_background block content history',
    'view any text_content block content history',
    'view any video block content history',
    'view any video_hero block content history',
    'view any web_directory block content history',
    'view any webform block content history',
    'view article revisions',
    'view media',
    'view own unpublished content',
    'view own unpublished media',
    'view own webform submission',
    'view page revisions',
    'view term revisions in location',
    'view the administration theme',
    'view vocabulary labels',
  ];

  /**
   * Base permissions for the Site Builder role.
   *
   * @var string[]
   */
  public const BASE_SB_PERMISSIONS = [
    'access administration pages',
    'access any webform configuration',
    'access block library',
    'access content',
    'access content overview',
    'access contextual links',
    'access files overview',
    'access fontawesome additional settings',
    'access help pages',
    'access media overview',
    'access node layout reports',
    'access own webform configuration',
    'access shortcuts',
    'access site in maintenance mode',
    'access site reports',
    'access site-wide contact form',
    'access taxonomy overview',
    'access toolbar',
    'access user profiles',
    'access webform help',
    'access webform overview',
    'access webform submission user',
    'administer account settings',
    'administer asu modules',
    'administer asu themes',
    'administer block content',
    'administer block types',
    'administer block_content display',
    'administer block_content fields',
    'administer block_content form display',
    'administer blocks',
    'administer contact forms',
    'administer contact_message display',
    'administer contact_message fields',
    'administer contact_message form display',
    'administer content types',
    'administer crop',
    'administer crop types',
    'administer display modes',
    'administer filters',
    'administer image styles',
    'administer media',
    'administer media display',
    'administer media fields',
    'administer media form display',
    'administer media types',
    'administer menu',
    'administer meta tags',
    'administer node display',
    'administer node fields',
    'administer node form display',
    'administer nodes',
    'administer paragraph display',
    'administer paragraph fields',
    'administer paragraph form display',
    'administer pathauto',
    'administer permissions',
    'administer redirect settings',
    'administer redirects',
    'administer responsive images',
    'administer robots.txt',
    'administer search',
    'administer seckit',
    'administer shortcuts',
    'administer site configuration',
    'administer sitemap settings',
    'administer taxonomy',
    'administer taxonomy_term display',
    'administer taxonomy_term fields',
    'administer taxonomy_term form display',
    'administer url aliases',
    'administer users',
    'administer views',
    'administer webform',
    'administer webform element access',
    'administer webform submission',
    'administer webspark_module_asu_breadcrumb configuration',
    'administer webspark_module_renovation_layouts configuration',
    'bypass node access',
    'configure any layout',
    'create accordion block content',
    'create and edit custom blocks',
    'create banner block content',
    'create blockquote block content',
    'create card_and_image block content',
    'create card_arrangement block content',
    'create card_carousel block content',
    'create card_image_and_content block content',
    'create carousel_image block content',
    'create content_image block content',
    'create display_list block content',
    'create divider block content',
    'create donut_chart block content',
    'create events block content',
    'create gallery block content',
    'create grid_links block content',
    'create hero block content',
    'create hover_cards block content',
    'create icon_list block content',
    'create image block content',
    'create image_and_text_block block content',
    'create image_background_with_cta block content',
    'create inset_box block content',
    'create media',
    'create menu_sidebar block content',
    'create news block content',
    'create step_list block content',
    'create tabbed_content block content',
    'create terms in location',
    'create terms in tags',
    'create testimonial block content',
    'create testimonial_carousel block content',
    'create testimonial_on_image_background block content',
    'create text_content block content',
    'create url aliases',
    'create video block content',
    'create video_hero block content',
    'create web_directory block content',
    'create webform',
    'create webform block content',
    'customize shortcut links',
    'delete all revisions',
    'delete any accordion block content',
    'delete any banner block content',
    'delete any blockquote block content',
    'delete any card_and_image block content',
    'delete any card_arrangement block content',
    'delete any card_carousel block content',
    'delete any card_image_and_content block content',
    'delete any carousel_image block content',
    'delete any content_image block content',
    'delete any display_list block content',
    'delete any divider block content',
    'delete any donut_chart block content',
    'delete any events block content',
    'delete any file',
    'delete any gallery block content',
    'delete any grid_links block content',
    'delete any hero block content',
    'delete any hover_cards block content',
    'delete any icon_list block content',
    'delete any image block content',
    'delete any image_and_text_block block content',
    'delete any image_background_with_cta block content',
    'delete any inset_box block content',
    'delete any menu_sidebar block content',
    'delete any news block content',
    'delete any step_list block content',
    'delete any tabbed_content block content',
    'delete any testimonial block content',
    'delete any testimonial_carousel block content',
    'delete any testimonial_on_image_background block content',
    'delete any text_content block content',
    'delete any video block content',
    'delete any video_hero block content',
    'delete any web_directory block content',
    'delete any webform',
    'delete any webform block content',
    'delete any webform submission',
    'delete any media',
    'delete term revisions in location',
    'delete term revisions in tags',
    'delete terms in location',
    'delete terms in tags',
    'edit any accordion block content',
    'edit any banner block content',
    'edit any blockquote block content',
    'edit any card_and_image block content',
    'edit any card_arrangement block content',
    'edit any card_carousel block content',
    'edit any card_image_and_content block content',
    'edit any carousel_image block content',
    'edit any content_image block content',
    'edit any display_list block content',
    'edit any divider block content',
    'edit any donut_chart block content',
    'edit any events block content',
    'edit any gallery block content',
    'edit any grid_links block content',
    'edit any hero block content',
    'edit any hover_cards block content',
    'edit any icon_list block content',
    'edit any image block content',
    'edit any image_and_text_block block content',
    'edit any image_background_with_cta block content',
    'edit any inset_box block content',
    'edit any menu_sidebar block content',
    'edit any news block content',
    'edit any step_list block content',
    'edit any tabbed_content block content',
    'edit any testimonial block content',
    'edit any testimonial_carousel block content',
    'edit any testimonial_on_image_background block content',
    'edit any text_content block content',
    'edit any video block content',
    'edit any video_hero block content',
    'edit any web_directory block content',
    'edit any webform',
    'edit any webform block content',
    'edit any webform submission',
    'edit terms in location',
    'edit terms in tags',
    'edit webform assets',
    'edit webform source',
    'edit webform twig',
    'edit webform variants',
    'notify of path changes',
    'revert all revisions',
    'revert term revisions in location',
    'revert term revisions in tags',
    'search content',
    'switch shortcut sets',
    'use advanced search',
    'use text format basic_html',
    'use text format full_html',
    'use text format minimal_format',
    'use text format restricted_html',
    'use text format webform_default',
    'update media',
    'update any media',
    'view all revisions',
    'view any accordion block content history',
    'view any banner block content history',
    'view any blockquote block content history',
    'view any card_and_image block content history',
    'view any card_arrangement block content history',
    'view any card_carousel block content history',
    'view any card_image_and_content block content history',
    'view any carousel_image block content history',
    'view any content_image block content history',
    'view any display_list block content history',
    'view any divider block content history',
    'view any donut_chart block content history',
    'view any events block content history',
    'view any gallery block content history',
    'view any grid_links block content history',
    'view any hero block content history',
    'view any hover_cards block content history',
    'view any icon_list block content history',
    'view any image block content history',
    'view any image_and_text_block block content history',
    'view any image_background_with_cta block content history',
    'view any inset_box block content history',
    'view any menu_sidebar block content history',
    'view any news block content history',
    'view any step_list block content history',
    'view any tabbed_content block content history',
    'view any testimonial block content history',
    'view any testimonial_carousel block content history',
    'view any testimonial_on_image_background block content history',
    'view any text_content block content history',
    'view any video block content history',
    'view any video_hero block content history',
    'view any web_directory block content history',
    'view any webform block content history',
    'view any webform submission',
    'view media',
    'view own unpublished content',
    'view own webform submission',
    'view term revisions in location',
    'view term revisions in tags',
    'view the administration theme',
    'view user email addresses',
    'view vocabulary labels',
  ];

  /**
   * Blacklisted permissions.
   *
   * @var string[]
   */
  public const BASE_BLACKLIST = [
    'administer asu governance configuration',
    'administer actions',
    'administer modules',
    'administer permissions',
    'administer software updates',
    'administer themes',
    'view update notifications',
    'export configuration',
    'import configuration',
    'synchronize configuration',
    'use PHP for settings',
    'masquerade as super user',
    'masquerade as any user',
    'masquerade as administrator',
    'switch users',
  ];

  /**
   * The controller resolver.
   *
   * @var \Drupal\Core\Controller\ControllerResolverInterface
   */
  protected $controllerResolver;

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
   * @param \Drupal\Core\Controller\ControllerResolverInterface $controllerResolver
   *   The controller resolver service.
   */
  public function __construct(PermissionHandlerInterface $permission_handler, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, ControllerResolverInterface $controller_resolver) {
    $this->permissionHandler = $permission_handler;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
    $this->entityTypeManager = $entity_type_manager;
    $this->controllerResolver = $controller_resolver;
  }

  /**
   *  Create a role.
   */
  public function createRole($role_id, $role_name) {
    $role_storage = $this->entityTypeManager->getStorage('user_role');
    // Check if the role already exists.
    $loadedRole = $role_storage->load($role_id);
    if ($loadedRole) {
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
    $basePermissions = constant(self::class . '::' . $base_perms_const);
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
   * @param ?string $source
   *   The source of the function call.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function addSiteBuilderModulePermissions(array $modules, $source = NULL) {
    // Load the Site Builder role.
    /** @var \Drupal\user\Entity\Role $role */
    $role = Role::load('site_builder');
    foreach ($modules as $module) {
      // Get the module's permissions.
      $modulePermissions = $this->getModulePermissions($module);
      if (empty($modulePermissions)) {
        if ($source !== 'asu_governance_curated_modules') {
          // Skip the rest of the code if this method is called from anywhere
          // other than the curated modules form.
          continue;
        }
        // Due to an order of operations issue, when enabling permissions within
        // the submit function of the curated modules form, the module may not
        // appear as being enabled yet. This is a workaround to ensure that the
        // permissions are still added. The following code is adapted from the
        // buildPermissionsYaml() method in the PermissionsHandler class.
        // See https://rb.gy/kmidmp
        $moduleExtensionList = \Drupal::service('extension.list.module');
        $path = DRUPAL_ROOT . '/' . $moduleExtensionList->getPath($module);
        $yamlDiscovery = new YamlDiscovery('permissions', [$module => $path]);
        $discoveredPermissions = current($yamlDiscovery->findAll());
        $all_callback_permissions = [];
        if (isset($discoveredPermissions['permission_callbacks'])) {
          foreach ($discoveredPermissions['permission_callbacks'] as $permission_callback) {
            $callback = $this->controllerResolver->getControllerFromDefinition($permission_callback);
            if ($callback_permissions = call_user_func($callback)) {
              // Add any callback permissions to the array of permissions. Any
              // defaults can then get processed below.
              foreach ($callback_permissions as $name => $callback_permission) {
                if (!is_array($callback_permission)) {
                  $callback_permission = [
                    'title' => $callback_permission,
                  ];
                }
                $callback_permission += [
                  'description' => NULL,
                  'provider' => $module,
                ];
                $all_callback_permissions[$name] = $callback_permission;
              }
            }
          }
          unset($discoveredPermissions['permission_callbacks']);
        }
        $discoveredPermissions = !empty($all_callback_permissions) && !empty($discoveredPermissions) ? array_merge($discoveredPermissions, $all_callback_permissions) : $discoveredPermissions;
        if (empty($discoveredPermissions)) {
          continue;
        }
        $modulePermissions = array_keys($discoveredPermissions);
      }
      // Grant permissions not in the blacklist.
      foreach ($modulePermissions as $permission) {
        $permissionBlacklist = $this->configFactory->get('asu_governance.settings')->get('permissions_blacklist');
        if (!in_array($permission, $permissionBlacklist, TRUE)) {
          $role->grantPermission($permission);
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
    $blacklist = $this::BASE_BLACKLIST;
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
