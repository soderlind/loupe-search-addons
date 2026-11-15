<?php
/**
 * PHPUnit bootstrap: unit tests with Brain Monkey, no WordPress.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'WEEK_IN_SECONDS', 604800 );
define( 'Soderlind\Plugin\LoupeSearchAddons\VERSION', '0.0.0-test' );
define( 'Soderlind\Plugin\LoupeSearchAddons\FILE', dirname( __DIR__, 2 ) . '/loupe-search-addons.php' );

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

if ( ! class_exists( 'WP_Error' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	class WP_Error {
		public function __construct( public string $code = '', public string $message = '' ) {}
		public function get_error_message(): string {
			return $this->message;
		}
	}
}

// Formatting functions used by Plugin Update Checker's readme parser.
if ( ! function_exists( 'balanceTags' ) ) {
	function balanceTags( $text ) { // phpcs:ignore
		return $text;
	}
	function wp_kses( $text ) { // phpcs:ignore
		return $text;
	}
	function wp_strip_all_tags( $text ) { // phpcs:ignore
		return strip_tags( $text );
	}
}
