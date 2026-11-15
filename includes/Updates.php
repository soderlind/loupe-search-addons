<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

/**
 * Feeds add-on releases into WordPress' own plugin updates, so pending
 * add-on updates show on Dashboard → Updates and Plugins, can be applied
 * there, and can be set to update automatically.
 */
final class Updates {

	public static function register(): void {
		add_filter( 'pre_set_site_transient_update_plugins', [ self::class, 'on_core_check' ] );
		add_filter( 'site_transient_update_plugins', [ self::class, 'inject' ] );
		add_filter( 'plugins_api', [ self::class, 'plugin_information' ], 20, 3 );
	}

	/**
	 * Core is saving the result of its update check: look up stale releases first.
	 *
	 * @param mixed $transient The update_plugins value being saved.
	 * @return mixed
	 */
	public static function on_core_check( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		Releases::refresh();
		$transient = self::inject( $transient );

		// Pending updates show "Tested up to" and "Requires PHP" from the readme, so fetch it while we are online anyway.
		$pending = array_filter( array_map( [ Catalog::class, 'for_plugin_file' ], array_keys( $transient->response ) ) );
		if ( $pending ) {
			foreach ( $pending as $addon ) {
				Readme::get( $addon );
			}
			$transient = self::inject( $transient );
		}
		return $transient;
	}

	/**
	 * Add every installed add-on to the update data: in `response` when a newer
	 * release exists, otherwise in `no_update` (which enables the auto-update toggle).
	 *
	 * Reads the release cache only; never makes HTTP requests.
	 *
	 * @param mixed $transient The update_plugins value.
	 * @return mixed
	 */
	public static function inject( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}
		$transient->response  = is_array( $transient->response ?? null ) ? $transient->response : [];
		$transient->no_update = is_array( $transient->no_update ?? null ) ? $transient->no_update : [];

		$installed = self::installed_versions();
		foreach ( Catalog::all() as $slug => $addon ) {
			$file = $addon['plugin_file'];
			unset( $transient->response[ $file ], $transient->no_update[ $file ] );

			$release = Releases::get( $slug );
			if ( ! isset( $installed[ $file ] ) || null === $release ) {
				continue;
			}

			$item = self::item( $addon, $release );
			if ( version_compare( $release['version'], $installed[ $file ], '>' ) ) {
				$transient->response[ $file ] = $item;
			} else {
				$transient->no_update[ $file ] = $item;
			}
		}

		return $transient;
	}

	/**
	 * Plugin details for the "View details" modal and for core's installer.
	 *
	 * @param false|object|array $result Result so far.
	 * @param string             $action Requested action.
	 * @param object             $args   Request arguments.
	 * @return false|object|array
	 */
	public static function plugin_information( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
			return $result;
		}
		$addon = Catalog::get( (string) ( $args->slug ?? '' ) );
		if ( null === $addon ) {
			return $result;
		}

		Releases::refresh();
		$release = Releases::get( $addon['slug'] );
		$readme  = Readme::get( $addon );

		$sections = (array) ( $readme['sections'] ?? [] );
		unset( $sections['screenshots'] );
		if ( empty( $sections['description'] ) ) {
			$sections = [ 'description' => '<p>' . esc_html( $addon['description'] ) . '</p>' ] + $sections;
		}

		return (object) [
			'name'          => html_entity_decode( (string) ( ( $readme['name'] ?? '' ) ?: $addon['title'] ), ENT_QUOTES, 'UTF-8' ),
			'slug'          => $addon['slug'],
			'version'       => $release['version'] ?? (string) ( $readme['stable_tag'] ?? '' ),
			'author'        => '<a href="https://soderlind.no">Per Soderlind</a>',
			'homepage'      => $addon['repo_url'],
			'requires'      => (string) ( $readme['requires_at_least'] ?? '' ),
			'tested'        => self::normalize_tested( (string) ( $readme['tested_up_to'] ?? '' ) ),
			'requires_php'  => (string) ( $readme['requires_php'] ?? '' ),
			'download_link' => $release['package'] ?? $addon['zip_url'],
			'sections'      => $sections,
			'external'      => true,
		];
	}

	/**
	 * Number of installed add-ons with a newer release.
	 */
	public static function count(): int {
		$count     = 0;
		$installed = self::installed_versions();
		foreach ( Catalog::all() as $slug => $addon ) {
			$release = Releases::get( $slug );
			if ( isset( $installed[ $addon['plugin_file'] ] ) && null !== $release
				&& version_compare( $release['version'], $installed[ $addon['plugin_file'] ], '>' ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Installed catalog add-ons: plugin file => version.
	 *
	 * @return array<string,string>
	 */
	public static function installed_versions(): array {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$versions = [];
		foreach ( get_plugins() as $file => $data ) {
			if ( null !== Catalog::for_plugin_file( $file ) ) {
				$versions[ $file ] = (string) ( $data['Version'] ?? '' );
			}
		}
		return $versions;
	}

	/**
	 * @param array<string,string>                              $addon   Catalog entry.
	 * @param array{version:string,package:string,url:string} $release Latest release.
	 */
	private static function item( array $addon, array $release ): object {
		$readme = Readme::get( $addon, false );
		return (object) [
			'id'           => 'github.com/' . $addon['repo'],
			'slug'         => $addon['slug'],
			'plugin'       => $addon['plugin_file'],
			'new_version'  => $release['version'],
			'url'          => $addon['repo_url'],
			'package'      => $release['package'],
			'tested'       => self::normalize_tested( (string) ( $readme['tested_up_to'] ?? '' ) ),
			'requires_php' => (string) ( $readme['requires_php'] ?? '' ),
			'icons'        => [],
			'banners'      => [],
			'banners_rtl'  => [],
		];
	}

	/**
	 * Treat "Tested up to: 7.0" as tested with 7.0.x, so patch releases of
	 * WordPress do not flag the add-on as untested.
	 */
	private static function normalize_tested( string $tested ): string {
		if ( '' === $tested ) {
			return '';
		}
		$wp = (string) get_bloginfo( 'version' );
		if ( version_compare( $wp, $tested, '<=' ) ) {
			return $tested;
		}
		$minor = static fn ( string $v ): string => implode( '.', array_slice( array_pad( explode( '.', $v ), 2, '0' ), 0, 2 ) );
		return $minor( $wp ) === $minor( $tested ) ? $wp : $tested;
	}
}
