# Loupe Search Add-ons

Install, activate and update [Loupe Search](https://github.com/soderlind/loupe-search) add-ons
from GitHub.

| Add-on | What it does |
| --- | --- |
| [Loupe Search Block](https://github.com/soderlind/loupe-search-block) | Search block with instant results |
| [Loupe Semantic Search](https://github.com/soderlind/loupe-semantic-search) | Semantic search and RAG passages |
| [Loupe Search Multilingual](https://github.com/soderlind/loupe-search-multilingual) | One index per language for Polylang and WPML |

- **One screen for all add-ons**: install, activate, deactivate, update and delete, with the
  readme of each add-on in a details window.
- **Updates where WordPress shows them**: Dashboard → Updates, the Plugins screen, the update
  count in the admin menu, and automatic updates.
- **No GitHub API rate limit**: the latest release is read from the redirect of
  `github.com/<repo>/releases/latest`. If GitHub can't be reached, the last known releases are
  kept and the lookup is retried after 15 minutes.

## Where to find it

- **Single site:** Settings → Loupe Search → Add-ons.
- **Multisite:** Network Admin → Plugins → Loupe Search Add-ons. "Network Activate" activates
  an add-on on all sites; add-ons can also be activated per site on each site's Plugins screen.

## Requirements

- WordPress 7.0 or later, PHP 8.3 or later
- [Loupe Search](https://github.com/soderlind/loupe-search) (single site)
- Network activation on multisite

## Installation

Download `loupe-search-addons.zip` from the
[latest release](https://github.com/soderlind/loupe-search-addons/releases/latest) and install it
under Plugins → Add New → Upload Plugin. On multisite, network activate it. The plugin updates
itself from GitHub releases.

## How updates work

Releases are cached for 12 hours. When WordPress checks for plugin updates, stale releases are
looked up and newer ones are added to the `update_plugins` site transient, so core's update
screens and automatic updates pick them up. **Check for updates** looks up all add-ons right away.

Updates use `Plugin_Upgrader::bulk_upgrade()`, like core's automatic updates, so active add-ons
stay active.

## Development

```sh
composer install
composer test
composer run i18n   # Regenerate languages/*.pot and compile the translations (needs WP-CLI)
```

Translations are in `languages/`. Norwegian Bokmål (nb_NO) is included.

## License

GPL-2.0-or-later. Copyright Per Soderlind.
