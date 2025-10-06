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

## Configuration
- Site Administrators will have access to the "ASU Governance settings" page located in the System submenu of the Configuration menu.

## Local development
- This module is developed using DDEV and the "DDEV Drupal Contrib" add-on.
  - Do the following for an initial vanilla Drupal setup:
      - Run `ddev config --project-type=drupal10 --docroot=web --php-version=8.3`.
      - Run `ddev add-on get ddev/ddev-drupal-contrib`
      - Run `ddev start`
      - Run `ddev poser`
      - Run `ddev symlink-project`
  - See https://github.com/ddev/ddev-drupal-contrib for the full instructions about how to use this add-on.
- After you have built a local DDEV environment, do the following:
  - Run `ddev drush si` to install Drupal.
  - Run `ddev drush en asusf_installer_forms` to install the ASU Installer Forms module.
  - Log into the site as the admin user with `ddev drush uli`.
  - Navigate to the home page and fill out the "Initial Configuration" form that will enable and configure governance on the site.
  - Create a site administator account: `ddev drush ucrt siteadmin`
  - Assign the "Site Administrator" role to the siteadmin user: `ddev drush urol "Administrator" siteadmin`
  - Log in with the siteadmin user: `ddev drush uli siteadmin`
- Now you can proceed with development and testing etc.
