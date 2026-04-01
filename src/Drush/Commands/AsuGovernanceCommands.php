<?php

namespace Drupal\asu_governance\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * A Drush commandfile.
 */
final class AsuGovernanceCommands extends DrushCommands {
  /**
   * An interactive tool for managing admin roles on Acquia sites.
   */
  #[CLI\Command(name: 'asu_governance:role-manager', aliases: ['agrm'])]
  #[CLI\Option(name: 'action', description: 'Action to perform: "add" or "remove"')]
  #[CLI\Option(name: 'role', description: 'Role to manage.')]
  #[CLI\Option(name: 'username', description: 'The username (ASURITE Id) to manage')]
  #[CLI\Option(name: 'stack', description: 'Stack number (0 = all stacks, or a specific stack number)')]
  #[CLI\Option(name: 'site-alias', description: 'Site alias to target (format: @alias.env or @self). Use "allsites" to target all sites on the specified stack(s).')]
  #[CLI\Usage(name: 'asu_governance:role-manager (agrm)', description: 'Wizard mode: interactively prompts for options')]
  #[CLI\Usage(name: 'asu_governance:role-manager (agrm) --action=add --role=administrator --username=jdoe --stack=1 site-alias=@websparkreleasestable.live', description: 'Adds the administrator role to user jdoe in the live environment for the webspark release stable site on stack 1')]
  #[CLI\Usage(name: 'asu_governance:role-manager (agrm) --action=remove --role=site_builder --username=jdoe --stack=3 --site-alias=allsites', description: 'Removes the site_builder role from user jdoe on all sites on stack 3')]
  public function roleManager(array $options = ['action' => NULL, 'role' => NULL, 'username' => NULL, 'stack' => NULL, 'site-alias' => NULL]) {

    $myOptions = [
      'action' => $options['action'],
      'role' => $options['role'],
      'username' => $options['username'],
      'stack' => $options['stack'],
      'site_alias' => $options['site-alias'],
    ];

    // Validate options
    if (!$this->validateOptions($myOptions)) {
      return self::EXIT_FAILURE;
    }

    $command = $this->buildCommand($myOptions , 'role-manager');

    // passthru() outputs directly to the terminal and returns the exit status
    $return_status = 0;
    passthru($command, $return_status);
    
    // Return based on the command's exit status
    return $return_status === 0 ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  /**
   * An interactive tool for managing admin roles on Acquia sites.
   */
  #[CLI\Command(name: 'asu_governance:add-user', aliases: ['agau'])]
  #[CLI\Option(name: 'username', description: 'The username (ASURITE Id) to manage')]
  #[CLI\Option(name: 'stack', description: 'Stack number (0 = all stacks, or a specific stack number)')]
  #[CLI\Option(name: 'site-alias', description: 'Site alias to target (format: @alias.env or @self). Use "allsites" to target all sites on the specified stack(s).')]
  #[CLI\Usage(name: 'asu_governance:add-user (agau)', description: 'Wizard mode: interactively prompts for options')]
  #[CLI\Usage(name: 'asu_governance:add-user (agau) --username=jdoe --stack=1 site-alias=@websparkreleasestable.live', description: 'Adds user jdoe in the live environment for the webspark release stable site on stack 1')]
  #[CLI\Usage(name: 'asu_governance:add-user (agau) --username=jdoe --stack=3 --site-alias=allsites', description: 'Adds user jdoe on all sites on stack 3')]
  public function addUser(array $options = ['username' => NULL, 'stack' => NULL, 'site-alias' => NULL]) {

    $myOptions = [
      'username' => $options['username'],
      'stack' => $options['stack'],
      'site_alias' => $options['site-alias'],
    ];

    // Validate options
    if (!$this->validateOptions($myOptions)) {
      return self::EXIT_FAILURE;
    }

    $command = $this->buildCommand($myOptions, 'add-user');

    // passthru() outputs directly to the terminal and returns the exit status
    $return_status = 0;
    passthru($command, $return_status);

    // Return based on the command's exit status
    return $return_status === 0 ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

  private function validateOptions(array $myOptions): bool {
    // Validate 'action' option
    if (isset($myOptions['action']) && $myOptions['action'] !== NULL && !in_array($myOptions['action'], ['add', 'remove'], true)) {
      $this->logger()->error('Invalid action. Allowed values are "add" or "remove".');
      return false;
    }

    // Validate 'role' option
    if (isset($myOptions['role']) && $myOptions['role'] !== NULL) {
      if (!in_array($myOptions['role'], ['administrator', 'site_builder'], true)) {
        $this->logger()
          ->error('The "role" option requires a value of "administrator" or "site_builder".');
        return FALSE;
      }
    }

    // Validate 'username' option
    if ($myOptions['username'] !== NULL) {
      if (!preg_match('/^[a-zA-Z0-9]+$/', $myOptions['username'])) {
        $this->logger()->error('The "username" option must be a valid ASURITE Id.');
        return false;
      }
    }

    // Validate 'stack' option
    if ($myOptions['stack'] !== NULL) {
      // convert to integer for validation
      $myOptions['stack'] = (int)$myOptions['stack'];
      // Ensure 'stack' is an integer or 0.
      if (!is_numeric($myOptions['stack']) || $myOptions['stack'] < 0) {
        $this->logger()->error('The "stack" option must be a non-negative integer.');
        return false;
      }
    }

    return true;
  }

  private function buildCommand(array $myOptions, $func): string {
    $module_path = \Drupal::service('extension.list.module')->getPath('asu_governance');
    $file_dir = DRUPAL_ROOT . '/' . $module_path . '/src/Drush/Commands';
    if ($func === 'role-manager' || $func === 'add-user') {
      $command = "cd {$file_dir} && ./{$func}";
    } else {
      throw new \InvalidArgumentException('Invalid command specified for building the command string.');
    }


    foreach ($myOptions as $key => $value) {
      if (!is_null($value)) {
        $command .= " --$key " . $value;
      }
    }

    return $command;
  }

}
