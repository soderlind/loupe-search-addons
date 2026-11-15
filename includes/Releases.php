<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

/**
 * Latest GitHub release of each add-on, cached network-wide.
 *
 * Successful lookups are kept for FRESH seconds, failed ones for RETRY
 * seconds, so an unreachable GitHub does not slow down every admin page.
 */
final class Releases {

	public const CACHE_KEY = 'loupe_search_addons_releases';

	public const FRESH = 12 * HOUR_IN_SECONDS;

	public const RETRY = 15 * MINUTE_IN_SECONDS;

	/**
	 * The cached latest release of an add-on, or null when unknown.
	 *
	 * @return array{version:string,package:string,url:string}|null
	 */
	public static function get( string $slug ): ?array {
		$entry = self::cache()[ $slug ] ?? null;
		if ( ! is_array( $entry ) || empty( $entry['version'] ) ) {
			return null;
		}
		return [
			'version' => $entry['version'],
			'package' => $entry['package'],
			'url'     => $entry['url'],
		];
	}

	/**
	 * The last lookup error for an add-on, or '' when the last lookup worked.
	 */
	public static function error( string $slug ): string {
		return (string) ( self::cache()[ $slug ]['error'] ?? '' );
	}

	/**
	 * Look up add-ons whose cached release is missing or out of date.
	 *
	 * @param bool $force Look up every add-on regardless of age.
	 */
	public static function refresh( bool $force = false ): void {
		$cache   = self::cache();
		$now     = time();
		$changed = false;

		foreach ( Catalog::all() as $slug => $addon ) {
			$entry = $cache[ $slug ] ?? null;
			if ( ! $force && is_array( $entry ) ) {
				$ttl = empty( $entry['error'] ) ? self::FRESH : self::RETRY;
				if ( $now - (int) ( $entry['checked'] ?? 0 ) < $ttl ) {
					continue;
				}
			}

			$fetched = self::fetch( $addon );
			if ( '' !== $fetched['error'] && is_array( $entry ) && ! empty( $entry['version'] ) ) {
				// Keep the last known release so a GitHub hiccup does not hide a pending update.
				$fetched = array_merge( $fetched, array_intersect_key( $entry, array_flip( [ 'version', 'package', 'url' ] ) ) );
			}
			$cache[ $slug ] = $fetched + [ 'checked' => $now ];
			$changed        = true;
		}

		if ( $changed ) {
			set_site_transient( self::CACHE_KEY, $cache, WEEK_IN_SECONDS );
		}
	}

	public static function clear(): void {
		delete_site_transient( self::CACHE_KEY );
	}

	/**
	 * Find an add-on's latest release from the redirect of github.com/<repo>/releases/latest.
	 *
	 * Unlike the GitHub REST API, the redirect is not rate limited to 60 requests per hour per IP,
	 * which shared hosts would otherwise exhaust.
	 *
	 * @param array<string,string> $addon Catalog entry.
	 * @return array{version:string,package:string,url:string,error:string}
	 */
	private static function fetch( array $addon ): array {
		$result   = [
			'version' => '',
			'package' => '',
			'url'     => '',
			'error'   => '',
		];
		$response = wp_remote_head(
			$addon['latest_url'],
			[
				'timeout'     => 10,
				'redirection' => 0,
				'headers'     => [ 'User-Agent' => 'loupe-search-addons/' . VERSION ],
			]
		);

		if ( is_wp_error( $response ) ) {
			$result['error'] = $response->get_error_message();
			return $result;
		}

		$code     = (int) wp_remote_retrieve_response_code( $response );
		$location = (string) wp_remote_retrieve_header( $response, 'location' );
		$prefix   = $addon['repo_url'] . '/releases/tag/';
		if ( $code < 300 || $code >= 400 || ! str_starts_with( $location, $prefix ) ) {
			/* translators: %d: HTTP status code. */
			$result['error'] = sprintf( __( 'GitHub answered with HTTP %d.', 'loupe-search-addons' ), $code );
			return $result;
		}

		$tag = rawurldecode( strtok( substr( $location, strlen( $prefix ) ), '?#' ) ?: '' );
		if ( '' === $tag || str_contains( $tag, '/' ) ) {
			$result['error'] = __( 'GitHub did not name a latest release.', 'loupe-search-addons' );
			return $result;
		}

		$result['version'] = ltrim( $tag, 'vV' );
		$result['url']     = $prefix . rawurlencode( $tag );
		$result['package'] = $addon['repo_url'] . '/releases/download/' . rawurlencode( $tag ) . '/' . $addon['slug'] . '.zip';

		return $result;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private static function cache(): array {
		$cache = get_site_transient( self::CACHE_KEY );
		return is_array( $cache ) ? $cache : [];
	}
}
