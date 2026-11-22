# Changelog

All notable changes to this project are documented in this file.

## [0.2.0] - 2026-11-22

### Fixed

- The Add-ons tab is always the last tab in Settings → Loupe Search, after the tabs other add-ons add.

## [0.1.1] - 2026-11-15

### Added

- Translations: the manager loads its text domain from `languages/`, with a Norwegian Bokmål
  (nb_NO) translation and `composer run i18n` to regenerate them.

### Fixed

- The add-on descriptions can now be translated.

## [0.1.0] - 2026-11-15

### Added

- Add-on manager for Loupe Search Block, Loupe Semantic Search and Loupe Search Multilingual:
  install, activate, deactivate, update and delete.
- Settings → Loupe Search → Add-ons tab on single sites; Network Admin → Plugins → Loupe Search
  Add-ons on multisite.
- Add-on updates in WordPress' own update screens, update count and automatic updates, with
  "View details" from each add-on's readme.
- Latest releases read from the `github.com/<repo>/releases/latest` redirect, which is not
  subject to the GitHub API rate limit.
