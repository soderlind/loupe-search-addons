<?php
namespace Soderlind\Plugin\LoupeSearchAddons\Tests;

use Brain\Monkey\Functions;
use Soderlind\Plugin\LoupeSearchAddons\Manager;

final class ManagerTest extends TestCase {

	public function test_tab_filter_runs_last_on_a_single_site(): void {
		Functions\when( 'is_multisite' )->justReturn( false );
		Functions\when( 'plugin_basename' )->justReturn( 'loupe-search-addons/loupe-search-addons.php' );

		Manager::register();

		$this->assertSame( PHP_INT_MAX, has_filter( 'loupe_search_settings_tabs', [ Manager::class, 'add_tab' ] ) );
	}

	public function test_add_tab_is_the_last_tab(): void {
		$render = static function (): void {};
		$tabs   = Manager::add_tab(
			[
				'addons'   => [ 'label' => 'Early', 'render' => $render ],
				'semantic' => [ 'label' => 'Semantic', 'render' => $render ],
			]
		);

		$this->assertSame( [ 'semantic', 'addons' ], array_keys( $tabs ) );
		$this->assertSame( 'Add-ons', $tabs['addons']['label'] );
	}
}
