<?php
namespace Soderlind\Plugin\LoupeSearchAddons\Tests;

use Soderlind\Plugin\LoupeSearchAddons\Releases;

final class ReleasesTest extends TestCase {

	public function test_reads_version_and_zip_from_latest_release_redirect(): void {
		$this->github_all( '1.0.0' );
		$this->github_release( 'loupe-search-block', 'v1.3.0' );

		Releases::refresh();

		$this->assertSame(
			[
				'version' => '1.3.0',
				'package' => 'https://github.com/soderlind/loupe-search-block/releases/download/v1.3.0/loupe-search-block.zip',
				'url'     => 'https://github.com/soderlind/loupe-search-block/releases/tag/v1.3.0',
			],
			Releases::get( 'loupe-search-block' )
		);
	}

	public function test_fresh_cache_is_not_looked_up_again_unless_forced(): void {
		$this->github_all( '1.0.0' );
		Releases::refresh();
		$this->assertCount( 3, $this->requested );

		Releases::refresh();
		$this->assertCount( 3, $this->requested );

		Releases::refresh( true );
		$this->assertCount( 6, $this->requested );
	}

	public function test_stale_entries_are_looked_up_again(): void {
		$this->github_all( '1.0.0' );
		Releases::refresh();
		$this->transients[ Releases::CACHE_KEY ]['loupe-search-block']['checked'] -= Releases::FRESH;

		Releases::refresh();

		$this->assertCount( 4, $this->requested );
		$this->assertStringContainsString( 'loupe-search-block', end( $this->requested ) );
	}

	public function test_failed_lookup_is_retried_sooner_and_keeps_the_last_known_release(): void {
		$this->github_all( '1.0.0' );
		Releases::refresh();

		$this->responses = [];
		Releases::refresh( true );
		$this->assertSame( '1.0.0', Releases::get( 'loupe-search-block' )['version'] ?? null );
		$this->assertSame( 'GitHub answered with HTTP 404.', Releases::error( 'loupe-search-block' ) );

		$count = count( $this->requested );
		Releases::refresh();
		$this->assertCount( $count, $this->requested, 'A failure is not retried immediately.' );

		$this->transients[ Releases::CACHE_KEY ]['loupe-search-block']['checked'] -= Releases::RETRY;
		Releases::refresh();
		$this->assertCount( $count + 1, $this->requested );
	}

	public function test_redirect_that_is_not_a_release_tag_is_an_error(): void {
		$this->responses['https://github.com/soderlind/loupe-search-block/releases/latest'] = [
			'response' => [ 'code' => 302 ],
			'headers'  => [ 'location' => 'https://github.com/soderlind/loupe-search-block/releases' ],
		];

		Releases::refresh();

		$this->assertNull( Releases::get( 'loupe-search-block' ) );
		$this->assertSame( 'GitHub answered with HTTP 302.', Releases::error( 'loupe-search-block' ) );
	}
}
