# ASU Governance

## Introduction
The asu_governance module is intended to provide Enterprise Technology with a way to manage the governance of Drupal sites on the Acquia platform.
This module provides the following:
- A curated "ASU Modules" interface to replace the stock "Extend" modules page in Drupal. This will allow Site Administrators to limit the ability of Site Builders to enable/disable and configure only the modules that have been approved by Enterprise Technology.
- A curated "ASU Themes" interface to replace the stock "Appearance" themes page in Drupal. This will allow Site Administrators to limit the ability of Site Builders to enable/disable and configure only the themes that have been approved by Enterprise Technology.

## Requirements
This module is intended to be used only on ASU Drupal sites hosted on the Acquia platform.

## Installation
Install as you would normally install a contributed Drupal module.
See: https://www.drupal.org/node/895232 for further information.

## Versions
- The 1.x branch is the main branch, and it targets use of Drush ^12.5
- The 2.x branch is the same as 1.x but targets the use of Drush ^13
This is due to the fact that some deployments need different Drush versions.

## Configuration
- Site Administrators will have access to the "ASU Governance settings" page located in the System submenu of the Configuration menu.

## Local development
- Clone the repository to your local machine with git.
- Navigate to the repository's root directory and run `ddev setup-local`.
- After a browser window opens, navigate to the homepage and fill out the site installation form. This will enable and configure the asu_governance module correctly.
- Create an administrator user with `ddev add-admin <username>`.
- Log in as the administrator user you just created: `ddev drush uli --name=<username>`.
- Now you can proceed with development and testing etc.
- This development environment is created using DDEV and the "DDEV Drupal Contrib" add-on.
  - See https://github.com/ddev/ddev-drupal-contrib for the full instructions about how to use this add-on.
