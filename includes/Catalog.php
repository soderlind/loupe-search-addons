<?php
namespace Soderlind\Plugin\LoupeSearchAddons;

defined( 'ABSPATH' ) || exit;

/**
 * The add-ons this manager knows about. Each one is a GitHub repository
 * under soderlind/ whose releases carry a <slug>.zip asset.
 */
final class Catalog {

	private const OWNER = 'soderlind';

	private const ITEMS = [
		'loupe-search-block'        => [
			'title'       => 'Loupe Search Block',
			'description' => 'A search block with instant results as you type, powered by Loupe Search.',
		],
		'loupe-semantic-search'     => [
			'title'       => 'Loupe Semantic Search',
			'description' => 'Adds meaning-based retrieval to Loupe Search, so visitors find posts that match what they mean, not only the words they type.',
		],
		'loupe-search-multilingual' => [
			'title'       => 'Loupe Search Multilingual',
			'description' => 'One Loupe Search index per language for sites translated with Polylang or WPML, with stemming and stop words for each language.',
		],
	];

	/**
	 * All add-ons, keyed by slug.
	 *
	 * @return array<string,array<string,string>>
	 */
	public static function all(): array {
		$addons = [];
		foreach ( array_keys( self::ITEMS ) as $slug ) {
			$addons[ $slug ] = self::get( $slug );
		}
		return $addons;
	}

	/**
	 * One add-on, or null when the slug is not in the catalog.
	 *
	 * @return array<string,string>|null
	 */
	public static function get( string $slug ): ?array {
		if ( ! isset( self::ITEMS[ $slug ] ) ) {
			return null;
		}
		$repo = self::OWNER . '/' . $slug;
		return self::ITEMS[ $slug ] + [
			'slug'        => $slug,
			'plugin_file' => $slug . '/' . $slug . '.php',
			'repo'        => $repo,
			'repo_url'    => 'https://github.com/' . $repo,
			'latest_url'  => 'https://github.com/' . $repo . '/releases/latest',
			'zip_url'     => 'https://github.com/' . $repo . '/releases/latest/download/' . $slug . '.zip',
			'readme_url'  => 'https://raw.githubusercontent.com/' . $repo . '/main/readme.txt',
		];
	}

	/**
	 * The catalog add-on that a plugin file belongs to.
	 *
	 * @return array<string,string>|null
	 */
	public static function for_plugin_file( string $plugin_file ): ?array {
		$addon = self::get( dirname( $plugin_file ) );
		return ( $addon && $addon['plugin_file'] === $plugin_file ) ? $addon : null;
	}
}
