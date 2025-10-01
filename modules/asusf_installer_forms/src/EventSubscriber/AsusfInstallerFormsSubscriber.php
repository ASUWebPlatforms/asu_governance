<?php

declare(strict_types=1);

namespace Drupal\asusf_installer_forms\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\cas\Event\CasPreLoginEvent;
use Drupal\cas\Service\CasHelper;

/**
 * Event subscriber to handle user login events for the Asusf Installer Forms.
 */
final class AsusfInstallerFormsSubscriber implements EventSubscriberInterface {

  /**
   * Constructs an AsusfInstallerFormsSubscriber object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * Subscribe to the user login event dispatched.
   *
   * @param \Drupal\cas\Event\CasPreLoginEvent $event
   *   Action to perform before a user logs in via CAS.
   */
  public function onUserPreLogin(CasPreLoginEvent $event) {
    $casAccount = $event->getAccount();
    $casUsername = $event->getCasPropertyBag()->getUsername();
    $config = \Drupal::configFactory()->getEditable('asu_governance.settings');

    if (!$config->get('installer_forms_completed')) {
      // Check if a user with the same username exists
      // Find the site spinup-associated adminUser.
      $query = $this->entityTypeManager->getStorage('user')->getQuery()
        ->accessCheck(FALSE)
        ->condition('name', '@asu.edu', 'ENDS_WITH');
      $result = $query->execute();
      $uid = reset($result);
      $adminUser = $this->entityTypeManager->getStorage('user')->load($uid);
      // If the cas username matches the adminUser's name (without @asu.edu),
      // delete the adminUser and grant the cas user the administrator role.
      if ($adminUser instanceof User && str_replace('@asu.edu', '', $adminUser->getAccountName()) === $casUsername) {
        $adminUser->delete();
        $roles = $casAccount->getRoles();
        if (!in_array('administrator', $roles, TRUE)) {
          $casAccount->addRole('administrator');
          $casAccount->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      // Static class constant => method on this class.
      CasHelper::EVENT_PRE_LOGIN => 'onUserPreLogin',
    ];
  }

}
