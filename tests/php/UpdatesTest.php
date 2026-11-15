<?php
namespace Soderlind\Plugin\LoupeSearchAddons\Tests;

use Soderlind\Plugin\LoupeSearchAddons\Releases;
use Soderlind\Plugin\LoupeSearchAddons\Updates;

final class UpdatesTest extends TestCase {

	private const BLOCK = 'loupe-search-block/loupe-search-block.php';

	private const SEMANTIC = 'loupe-semantic-search/loupe-semantic-search.php';

	private const MULTILINGUAL = 'loupe-search-multilingual/loupe-search-multilingual.php';

	protected function setUp(): void {
		parent::setUp();
		$this->github_all( '1.0.0' );
		$this->github_release( 'loupe-search-block', '1.3.0' );
		Releases::refresh();
		$this->requested = [];
	}

	public function test_newer_release_goes_to_response_and_current_one_to_no_update(): void {
		$this->plugins = [
			self::BLOCK    => '1.2.0',
			self::SEMANTIC => '1.0.0',
			'akismet/akismet.php' => '5.0',
		];

		$transient = Updates::inject( (object) [ 'response' => [], 'no_update' => [] ] );

		$this->assertSame( [ self::BLOCK ], array_keys( $transient->response ) );
		$this->assertSame( [ self::SEMANTIC ], array_keys( $transient->no_update ) );
		$item = $transient->response[ self::BLOCK ];
		$this->assertSame( '1.3.0', $item->new_version );
		$this->assertSame( 'loupe-search-block', $item->slug );
		$this->assertSame( self::BLOCK, $item->plugin );
		$this->assertSame( 'https://github.com/soderlind/loupe-search-block/releases/download/1.3.0/loupe-search-block.zip', $item->package );
		$this->assertSame( [], $this->requested, 'Reading the update data never calls GitHub.' );
	}

	public function test_entries_for_removed_or_updated_add_ons_are_replaced(): void {
		$this->plugins = [ self::BLOCK => '1.3.0' ];
		$stale         = (object) [ 'new_version' => '9.9.9' ];

		$transient = Updates::inject(
			(object) [
				'response'  => [ self::BLOCK => $stale, self::MULTILINGUAL => $stale ],
				'no_update' => [ 'akismet/akismet.php' => $stale ],
			]
		);

		$this->assertSame( [], $transient->response );
		$this->assertSame( [ 'akismet/akismet.php', self::BLOCK ], array_keys( $transient->no_update ) );
	}

	public function test_missing_update_data_is_left_alone(): void {
		$this->assertFalse( Updates::inject( false ) );
	}

	public function test_core_check_looks_up_stale_releases_first(): void {
		$this->plugins = [ self::SEMANTIC => '1.0.0' ];
		$this->transients[ Releases::CACHE_KEY ]['loupe-semantic-search']['checked'] -= Releases::FRESH;
		$this->github_release( 'loupe-semantic-search', '1.1.0' );
		$this->responses['https://raw.githubusercontent.com/soderlind/loupe-semantic-search/main/readme.txt'] = [
			'response' => [ 'code' => 200 ],
			'body'     => "=== Loupe Semantic Search ===\nRequires PHP: 8.3\nStable tag: 1.1.0\n\nShort.\n",
		];

		$transient = Updates::on_core_check( (object) [ 'response' => [], 'no_update' => [] ] );

		$this->assertSame( '1.1.0', $transient->response[ self::SEMANTIC ]->new_version ?? null );
		$this->assertSame( '8.3', $transient->response[ self::SEMANTIC ]->requires_php, 'The readme of a pending update is fetched.' );
		$this->assertNotContains( 'https://raw.githubusercontent.com/soderlind/loupe-search-block/main/readme.txt', $this->requested );
	}

	public function test_count_matches_pending_updates(): void {
		$this->plugins = [ self::BLOCK => '1.2.0', self::SEMANTIC => '1.0.0' ];
		$this->assertSame( 1, Updates::count() );
	}

	public function test_plugin_information_describes_catalog_add_ons_only(): void {
		$this->assertFalse( Updates::plugin_information( false, 'plugin_information', (object) [ 'slug' => 'akismet' ] ) );
		$this->assertFalse( Updates::plugin_information( false, 'query_plugins', (object) [ 'slug' => 'loupe-search-block' ] ) );

		$info = Updates::plugin_information( false, 'plugin_information', (object) [ 'slug' => 'loupe-search-block' ] );

		$this->assertSame( 'loupe-search-block', $info->slug );
		$this->assertSame( '1.3.0', $info->version );
		$this->assertSame( 'https://github.com/soderlind/loupe-search-block/releases/download/1.3.0/loupe-search-block.zip', $info->download_link );
		$this->assertSame( 'Loupe Search Block', $info->name );
		$this->assertArrayHasKey( 'description', $info->sections );
	}

	public function test_plugin_information_uses_the_readme(): void {
		$this->responses['https://raw.githubusercontent.com/soderlind/loupe-search-block/main/readme.txt'] = [
			'response' => [ 'code' => 200 ],
			'body'     => "=== Loupe Search Block ===\nRequires at least: 7.0\nTested up to: 7.0\nRequires PHP: 8.3\nStable tag: 1.3.0\n\nShort.\n\n== Description ==\n\nLong description.\n\n== Changelog ==\n\n= 1.3.0 =\n* Thing.\n",
		];
		\Brain\Monkey\Functions\when( 'get_bloginfo' )->justReturn( '7.0.2' );

		$info = Updates::plugin_information( false, 'plugin_information', (object) [ 'slug' => 'loupe-search-block' ] );

		$this->assertSame( '8.3', $info->requires_php );
		$this->assertSame( '7.0.2', $info->tested, 'Tested up to 7.0 covers 7.0.x.' );
		$this->assertStringContainsString( 'Long description.', $info->sections['description'] );
		$this->assertArrayHasKey( 'changelog', $info->sections );

		$this->plugins = [ self::BLOCK => '1.2.0' ];
		$item          = Updates::inject( (object) [] )->response[ self::BLOCK ];
		$this->assertSame( '8.3', $item->requires_php, 'Update data reuses the cached readme.' );
	}
}
