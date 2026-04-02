<?php

namespace Drupal\asu_governance\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;
use Symfony\Component\Process\Process;

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
  #[CLI\Usage(name: 'asu_governance:role-manager (agrm) --action=add --role=administrator --username=jdoe --stack=1 --site-alias=@websparkreleasestable.live', description: 'Adds the administrator role to user jdoe in the live environment for the webspark release stable site on stack 1')]
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

    $process = $this->buildProcess($myOptions, 'role-manager');

    return $this->runProcess($process);
  }

  /**
   * An interactive tool for adding users on Acquia sites.
   */
  #[CLI\Command(name: 'asu_governance:add-user', aliases: ['agau'])]
  #[CLI\Option(name: 'username', description: 'The username (ASURITE Id) to manage')]
  #[CLI\Option(name: 'stack', description: 'Stack number (0 = all stacks, or a specific stack number)')]
  #[CLI\Option(name: 'site-alias', description: 'Site alias to target (format: @alias.env or @self). Use "allsites" to target all sites on the specified stack(s).')]
  #[CLI\Usage(name: 'asu_governance:add-user (agau)', description: 'Wizard mode: interactively prompts for options')]
  #[CLI\Usage(name: 'asu_governance:add-user (agau) --username=jdoe --stack=1 --site-alias=@websparkreleasestable.live', description: 'Adds user jdoe in the live environment for the webspark release stable site on stack 1')]
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

    $process = $this->buildProcess($myOptions, 'add-user');

    return $this->runProcess($process);
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
      $stackValue = (string) $myOptions['stack'];
      // Ensure the raw value is a non-negative integer string before casting.
      if (!ctype_digit($stackValue)) {
        $this->logger()->error('The "stack" option must be a non-negative integer.');
        return false;
      }
    }

    return true;
  }

  /**
   * Build a Symfony Process for the given script and options.
   *
   * Arguments are passed as an array, so they are never interpreted by a shell.
   * This eliminates command-injection risk.
   */
  private function buildProcess(array $myOptions, string $func): Process {
    if (!in_array($func, ['role-manager', 'add-user'], TRUE)) {
      throw new \InvalidArgumentException('Invalid command specified for building the process.');
    }

    $module_path = \Drupal::service('extension.list.module')->getPath('asu_governance');
    $file_dir = DRUPAL_ROOT . '/' . $module_path . '/src/Drush/Commands';

    $command = ['./' . $func];

    foreach ($myOptions as $key => $value) {
      if ($value !== NULL) {
        $command[] = '--' . $key;
        $command[] = (string) $value;
      }
    }

    $process = new Process($command, $file_dir);
    $process->setTimeout(NULL);

    return $process;
  }

  /**
   * Execute a Process, streaming output to the terminal in real time.
   *
   * Uses TTY mode when available (full interactive support), otherwise
   * falls back to streaming stdout/stderr via a callback.
   */
  private function runProcess(Process $process): int {
    if (Process::isTtySupported()) {
      $process->setTty(TRUE);
      $process->run();
    }
    else {
      $process->run(function ($type, $buffer) {
        echo $buffer;
      });
    }

    return $process->isSuccessful() ? self::EXIT_SUCCESS : self::EXIT_FAILURE;
  }

}
