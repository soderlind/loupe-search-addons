=== Loupe Search Add-ons ===
Contributors: PerS
Tags: search, add-ons, updates, loupe
Requires at least: 7.0
Tested up to: 7.1
Requires PHP: 8.3
Stable tag: 0.1.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Install, activate and update Loupe Search add-ons from GitHub.

== Description ==

Loupe Search Add-ons manages the add-ons for Loupe Search:

* **Loupe Search Block**: a search block with instant results.
* **Loupe Semantic Search**: semantic search and RAG passages.
* **Loupe Search Multilingual**: one index per language for Polylang and WPML.

From one screen you can install, activate, deactivate, update and delete them. The add-ons are downloaded from their latest GitHub release.

Add-on updates also show up where WordPress shows other plugin updates: Dashboard → Updates, the Plugins screen, the update count in the admin menu, and automatic updates.

* **Single site:** Settings → Loupe Search → Add-ons. Requires Loupe Search.
* **Multisite:** Network Admin → Plugins → Loupe Search Add-ons. Add-ons are network activated from there, or activated per site on each site's Plugins screen.

== Installation ==

1. Install and activate Loupe Search, then this plugin. On multisite, network activate this plugin.
2. Open the Add-ons tab (single site) or page (multisite) and install the add-ons you want.

== Frequently Asked Questions ==

= How often does it look for updates? =

Every 12 hours, when WordPress checks for plugin updates. Click **Check for updates** to look right away. If GitHub can't be reached, the last known releases are kept and the lookup is retried after 15 minutes.

= Does it use the GitHub API? =

No. It reads the latest release from the redirect of `github.com/<repo>/releases/latest`, so the GitHub API's limit of 60 requests per hour does not apply.

= Can I update add-ons without it? =

Yes. The add-ons are ordinary plugins. Without this plugin, download the zip from each add-on's GitHub releases.

= Why can't I install anything? =

`DISALLOW_FILE_MODS` is set, or your account cannot install plugins.

== Changelog ==

= 0.1.1 =
* Added: Norwegian Bokmål (nb_NO) translation; the manager loads its translations from `languages/`.
* Fixed: The add-on descriptions can now be translated.

= 0.1.0 =
* Initial release.
