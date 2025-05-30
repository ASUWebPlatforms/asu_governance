<?php

namespace Drupal\asu_governance\Services;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;

/**
 * Checks access for the config management.
 */
class PermsRolesAccessCheck implements AccessInterface {

  /**
   * Constructs a new ConfigAccessCheck object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerChannelFactory
   *   The logger channel factory.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory interface.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly MessengerInterface $messenger,
    private readonly LoggerChannelFactoryInterface $loggerChannelFactory,
    private readonly ConfigFactoryInterface $configFactory
  ) {
  }

  /**
   * Handles the access checking.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account to check access for.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException|\Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown when the user entity cannot be loaded.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(AccountInterface $account): AccessResult {
    if ($account->isAnonymous()) {
      return AccessResult::forbidden();
    }

    try {
      /** @var \Drupal\user\Entity\User $user */
      $user = $this->entityTypeManager->getStorage('user')
        ->load($account->id());
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
      $this->messenger->addError($e->getMessage());
      $this->loggerChannelFactory->get('asu_governance')->error($e->getMessage() . PHP_EOL . '<pre>' . $e->getTrace()->toString() . '</pre>');
    }
    // Get the editable configuration for asu_governance.settings.
    $config = $this->configFactory->getEditable('asu_governance.settings');
    if ($user->hasRole('administrator') || ($user->hasRole('site_builder') && $config->get('allow_roles_perms_admin') && in_array($user->get('name')->value, $config->get('permissions_users'), TRUE))) {
      return AccessResult::allowed();
    }

    return AccessResult::forbidden();
  }

}
