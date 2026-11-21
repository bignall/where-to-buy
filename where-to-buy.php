<?php
/**
 * Plugin Name:       Where to Buy
 * Plugin URI:        https://bignall.github.io/where-to-buy/
 * Description:       Show where to buy the things you recommend: a photo, a short description and a button for each store that sells it. Only stores with a valid link show, and the affiliate disclosure is added for you.
 * Version:           0.9.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            RB Creative Solutions
 * Author URI:        https://rbcreativesolutions.net
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       where-to-buy
 *
 * @package WhereToBuy
 */

defined( 'ABSPATH' ) || exit;

// Keep this file PHP 5.6-compatible so old hosts get a notice instead of a parse error.
if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Where to Buy requires PHP 8.1 or newer.', 'where-to-buy' ) . '</p></div>';
		}
	);
	return;
}

if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Where to Buy is missing its dependencies. Run "composer install" in the plugin folder, or install a release zip.', 'where-to-buy' ) . '</p></div>';
		}
	);
	return;
}

if ( file_exists( __DIR__ . '/vendor-prefixed/autoload.php' ) ) {
	require_once __DIR__ . '/vendor-prefixed/autoload.php';
}
require_once __DIR__ . '/vendor/autoload.php';

define( 'WTB_FILE', __FILE__ );

WhereToBuy\Bootstrap::plugin( __FILE__ )->boot();
