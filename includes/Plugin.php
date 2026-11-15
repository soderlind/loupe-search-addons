<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		add_action( 'init', [ self::class, 'load_textdomain' ] );
		Updates::register();

		if ( ! is_admin() ) {
			return;
		}
		Manager::register();

		if ( ! is_multisite() && ! defined( 'LOUPE_SEARCH_FILE' ) ) {
			add_action( 'admin_notices', [ self::class, 'missing_core_notice' ] );
		}
	}

	/**
	 * Load translations. The plugin is not on WordPress.org, so WordPress does not load them by itself.
	 */
	public static function load_textdomain(): void {
		load_plugin_textdomain( 'loupe-search-addons', false, dirname( plugin_basename( FILE ) ) . '/languages' );
	}

	/**
	 * On a single site the manager lives in Loupe Search's settings, so say so when Loupe Search is not active.
	 */
	public static function missing_core_notice(): void {
		$screen = get_current_screen();
		if ( ! $screen || 'plugins' !== $screen->id || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Loupe Search Add-ons lives in Settings → Loupe Search. Activate Loupe Search to manage its add-ons.', 'loupe-search-addons' ) . '</p></div>';
	}
}
