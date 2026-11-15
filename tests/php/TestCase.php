<?php
namespace Soderlind\Plugin\LoupeSearchAddons\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;

/**
 * Brain Monkey with in-memory site transients and a fake github.com releases/latest redirect.
 */
abstract class TestCase extends \PHPUnit\Framework\TestCase {

	/** @var array<string,mixed> */
	protected array $transients = [];

	/** @var array<string,string> Plugin file => installed version. */
	protected array $plugins = [];

	/** @var array<string,mixed> URL => response array or \WP_Error. */
	protected array $responses = [];

	/** @var list<string> */
	protected array $requested = [];

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\stubTranslationFunctions();
		Functions\stubEscapeFunctions();
		Functions\when( 'get_site_transient' )->alias( fn ( $key ) => $this->transients[ $key ] ?? false );
		Functions\when( 'set_site_transient' )->alias(
			function ( $key, $value ) {
				$this->transients[ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_site_transient' )->alias(
			function ( $key ) {
				unset( $this->transients[ $key ] );
				return true;
			}
		);
		Functions\when( 'get_plugins' )->alias(
			fn () => array_map( static fn ( $v ) => [ 'Version' => $v ], $this->plugins )
		);
		Functions\when( 'get_bloginfo' )->justReturn( '7.0' );
		Functions\when( 'is_wp_error' )->alias( static fn ( $thing ) => $thing instanceof \WP_Error );
		$remote = function ( $url ) {
			$this->requested[] = $url;
			return $this->responses[ $url ] ?? [ 'response' => [ 'code' => 404 ], 'body' => '' ];
		};
		Functions\when( 'wp_remote_head' )->alias( $remote );
		Functions\when( 'wp_remote_get' )->alias( $remote );
		Functions\when( 'wp_remote_retrieve_response_code' )->alias( static fn ( $r ) => $r['response']['code'] ?? '' );
		Functions\when( 'wp_remote_retrieve_body' )->alias( static fn ( $r ) => $r['body'] ?? '' );
		Functions\when( 'wp_remote_retrieve_header' )->alias( static fn ( $r, $h ) => $r['headers'][ $h ] ?? '' );
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Let github.com redirect $slug's releases/latest to the release tagged $tag.
	 */
	protected function github_release( string $slug, string $tag ): void {
		$this->responses[ 'https://github.com/soderlind/' . $slug . '/releases/latest' ] = [
			'response' => [ 'code' => 302 ],
			'headers'  => [ 'location' => 'https://github.com/soderlind/' . $slug . '/releases/tag/' . $tag ],
		];
	}

	protected function github_all( string $tag ): void {
		foreach ( [ 'loupe-search-block', 'loupe-semantic-search', 'loupe-search-multilingual' ] as $slug ) {
			$this->github_release( $slug, $tag );
		}
	}
}
