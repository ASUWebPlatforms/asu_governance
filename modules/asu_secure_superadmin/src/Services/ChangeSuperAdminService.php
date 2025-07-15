<?php

namespace Drupal\asu_secure_superadmin\Services;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\user\Entity\User;
use Drupal\Core\Password\DefaultPasswordGenerator;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleInstallerInterface;

/**
 * Change the SuperAdmin (uid 1) to a new user.
 */
class ChangeSuperAdminService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The password generator.
   *
   * @var \Drupal\Core\Password\DefaultPasswordGenerator
   */
  protected $passwordGenerator;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The module handler.
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
   * The module list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleList;

  /**
   * The module installer service.
   *
   * @var \Drupal\Core\Extension\ModuleInstallerInterface
   */
  protected $moduleInstaller;

  /**
   * An array of ASU Enterprise Technology admins.
   *
   * @var string[]
   */
  public const ETADMINS = [
    'rmlebla1',
    'dornela3',
    'tkaiserb',
    'tlstarr',
    'mmilner6',
    'apersky',
    'mlsamuel',
    'cphill',
    'ddavis35',
    'gamille7',
    'igardun1',
    'dlevy4',
    'abrockha',
    'jmitriat',
    'tbutterf',
    'stwilli2',
    'ddoozan',
    'kdmarks',
    'mjenki10',
    'ikrondo',
  ];

  /**
   * Constructs a new ChangeSuperAdminService object.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    ContainerAwareEventDispatcher $eventDispatcher,
    DefaultPasswordGenerator $passwordGenerator,
    MessengerInterface $messenger,
    ModuleHandlerInterface $moduleHandler,
    ConfigFactoryInterface $configFactory,
    ModuleExtensionList $moduleList,
    ModuleInstallerInterface $moduleInstaller,
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->eventDispatcher = $eventDispatcher;
    $this->passwordGenerator = $passwordGenerator;
    $this->messenger = $messenger;
    $this->moduleHandler = $moduleHandler;
    $this->configFactory = $configFactory;
    $this->moduleList = $moduleList;
    $this->moduleInstaller = $moduleInstaller;
  }

  /**
   * Change the SuperAdmin (uid 1) to a new user.
   *
   * @throws \Exception
   */
  public function changeSuperAdmin() :void {
    /** @var \Drupal\user\Entity\User $user1 */
    $user1 = User::load(1);
    $original_name = $user1->get('name')->value;
    $original_from_config = $this->configFactory->get('asu_secure_superadmin.settings')
      ->get('original_superadmin');

    if ($original_name === 'etsuper' && isset($original_from_config)) {
      $this->messenger->addError('The SuperAdmin account has already been secured.');
      return;
    }

    $casEnabled = $this->moduleHandler->moduleExists('cas');
    $casInCode = $this->moduleList->getPath('cas') !== NULL;
    $casUserManager = $casEnabled ? \Drupal::service('cas.user_manager') : NULL;
    if (!$casEnabled && $casInCode) {
      // Install the CAS module if it exists.
      $this->moduleInstaller->install(['cas']);
      $casUserManager = \Drupal::service('cas.user_manager');
    }

    // Check if this is a new Acquia Stack spinup.
    if ($original_name === 'Site Factory admin') {
      $this->adjustForAcquiaStackNewSpinups($user1, $casUserManager);
      return;
    }

    // Duplicate the user entity and trigger the event to reassign content.
    /** @var \Drupal\user\Entity\User $newUser */
    $newUser = $user1->createDuplicate();
    $newUser->isNew();
    $newUser->set('uid', NULL);
    $user1->set('name', 'etsuper');
    $user1->set('mail', 'DL.WG.ET.WebPlatforms@exchange.asu.edu');
    // Remove roles from the old user.
    $roles = $user1->getRoles();
    foreach ($roles as $role) {
      $user1->removeRole($role);
    }
    $newPassword = $this->passwordGenerator->generate(15);
    $user1->set('pass', $newPassword);

    if ($casUserManager) {
      // Get the CAS username.
      $casUsername = $casUserManager->getCasUsernameForAccount($user1->id());
      // Remove the old CAS username mapping.
      $casUserManager->removeCasUsernameForAccount($user1);
      $user1->save();
      $newUser->save();
      // Allow new user to log in via CAS.
      if ($casUsername) {
        $casUserManager->setCasUsernameForAccount($newUser, $casUsername);
      }
    }
    // Reload the newUser object to get the new uid.
    $newUserReloaded = user_load_by_name($original_name);
    $roles = $newUserReloaded->getRoles();
    if (in_array('administrator', $roles) && !in_array($original_name, self::ETADMINS)) {
      $newUserReloaded->removeRole('administrator');
      $newUserReloaded->save();
    }
    if (!in_array('site_builder', $roles)) {
      $newUserReloaded->addRole('site_builder');
      $newUserReloaded->save();
    }
    $user1->block();
    $user1->save();
  }

  /**
   * Adjust for new spinups on Acquia Stacks.
   *
   * @param \Drupal\user\Entity\User $user1
   *   User entity for uid 1.
   * @param \Drupal\cas\Service\CasUserManager $casUserManager
   *   The CAS user manager service.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function adjustForAcquiaStackNewSpinups(User $user1, CasUserManager $casUserManager) :void {
    // Rename user1 to 'etsuper' and set the email.
    $user1->set('name', 'etsuper');
    $user1->set('mail', 'DL.WG.ET.WebPlatforms@exchange.asu.edu');
    // Remove roles.
    $roles = $user1->getRoles();
    foreach ($roles as $role) {
      $user1->removeRole($role);
    }
    // Change the password.
    $newPassword = $this->passwordGenerator->generate(15);
    $user1->set('pass', $newPassword);
    $user1->block();
    $user1->save();

    // Find the site spinup-associated Admin user.
    $query = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->condition('name', '@asu.edu', 'ENDS_WITH');
    $uids = $query->execute();
    // Remove '@asu.edu' from the associated Admin username.
    if (!empty($uids)) {
      foreach ($uids as $uid) {
        $adminUser = $this->entityTypeManager->getStorage('user')->load($uid);
        if ($adminUser instanceof User) {
          $resetUsername = str_replace('@asu.edu', '', $adminUser->getAccountName());
          $adminUser->set('name', $resetUsername);
          if (in_array($resetUsername, self::ETADMINS)) {
            $adminUser->addRole('administrator');
          }
          $adminUser->save();
        }
        // Allow admin user to log in via CAS.
        if ($casUserManager && $resetUsername) {
          $casUserManager = \Drupal::service('cas.user_manager');
          $casUserManager->setCasUsernameForAccount($adminUser, $resetUsername);
        }

        // Reload the newUser object to get the new uid.
        $newUserReloaded = user_load_by_name($resetUsername);
        $roles = $newUserReloaded->getRoles();
        if (in_array('administrator', $roles) && !in_array($resetUsername, self::ETADMINS)) {
          $newUserReloaded->removeRole('administrator');
          $newUserReloaded->save();
        }
        if (!in_array('site_builder', $roles)) {
          $newUserReloaded->addRole('site_builder');
          $newUserReloaded->save();
        }
      }
    }
  }

}
