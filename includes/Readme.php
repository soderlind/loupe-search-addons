<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

/**
 * Add-on readme.txt files from GitHub, parsed with Plugin Update Checker's
 * readme parser and cached network-wide.
 */
final class Readme {

	private const CACHE_PREFIX = 'loupe_search_addons_readme_';

	/**
	 * The parsed readme of an add-on.
	 *
	 * @param array<string,string> $addon Catalog entry.
	 * @param bool                 $fetch Download the readme when it is not cached.
	 * @return array<string,mixed> Parsed readme, or [] when unavailable.
	 */
	public static function get( array $addon, bool $fetch = true ): array {
		$cached = get_site_transient( self::CACHE_PREFIX . $addon['slug'] );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		if ( ! $fetch ) {
			return [];
		}

		$response = wp_remote_get(
			$addon['readme_url'],
			[
				'timeout' => 10,
				'headers' => [ 'User-Agent' => 'loupe-search-addons/' . VERSION ],
			]
		);
		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$parsed = self::parse( (string) wp_remote_retrieve_body( $response ) );
		if ( [] !== $parsed ) {
			set_site_transient( self::CACHE_PREFIX . $addon['slug'], $parsed, Releases::FRESH );
		}
		return $parsed;
	}

	public static function clear(): void {
		foreach ( array_keys( Catalog::all() ) as $slug ) {
			delete_site_transient( self::CACHE_PREFIX . $slug );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function parse( string $contents ): array {
		if ( '' === trim( $contents ) ) {
			return [];
		}
		if ( ! class_exists( 'PucReadmeParser', false ) ) {
			$parser = dirname( FILE ) . '/vendor/yahnis-elsts/plugin-update-checker/vendor/PucReadmeParser.php';
			if ( ! is_readable( $parser ) ) {
				return [];
			}
			require_once $parser;
		}
		$parsed = ( new \PucReadmeParser() )->parse_readme_contents( $contents );
		return is_array( $parsed ) ? $parsed : [];
	}
}
