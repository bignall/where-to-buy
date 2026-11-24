<?php
/**
 * Test bootstrap: Composer's autoloader plus the few WordPress functions plain-PHP tests reach.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

require dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Same as WordPress's wrapper for the URLs these tests use.
	 *
	 * @param string $url       URL.
	 * @param int    $component PHP_URL_* constant, or -1 for all parts.
	 * @return mixed
	 */
	function wp_parse_url( $url, $component = -1 ) {
		return parse_url( $url, $component ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url -- test stand-in.
	}
}
