<?php
/**
 * Loupe Search Add-ons
 *
 * @package   Soderlind\Plugin\LoupeSearchAddons
 * @author    Per Soderlind
 * @license   GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       Loupe Search Add-ons
 * Plugin URI:        https://github.com/soderlind/loupe-search-addons
 * Description:       Install, activate and update Loupe Search add-ons from GitHub. Add-on updates show up in WordPress' own update screens.
 * Version:           0.2.0
 * Author:            Per Soderlind
 * Author URI:        https://soderlind.no
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       loupe-search-addons
 * Domain Path:       /languages
 * Requires at least: 7.0
 * Requires PHP:      8.3
 * Network:           true
 */

namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

const VERSION = '0.2.0';
const FILE    = __FILE__;

if ( is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( ! str_starts_with( $class, $prefix ) ) {
			return;
		}
		$file = __DIR__ . '/includes/' . str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

if ( class_exists( \Soderlind\WordPress\GitHubUpdater::class ) ) {
	\Soderlind\WordPress\GitHubUpdater::init(
		github_url: 'https://github.com/soderlind/loupe-search-addons',
		plugin_file: __FILE__,
		plugin_slug: 'loupe-search-addons',
		name_regex: '/loupe-search-addons\.zip/',
	);
}

add_action( 'plugins_loaded', [ Plugin::class, 'boot' ] );
