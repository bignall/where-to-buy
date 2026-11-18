<?php
/**
 * Wires the plugin's runtime services once WP Foundation says it's ready.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

use WhereToBuy\Vendor\Bignall\WPFoundation\Plugin;

final class App {

	private static ?Plugin $plugin = null;

	public static function boot( Plugin $plugin ): void {
		self::$plugin = $plugin;

		load_plugin_textdomain( 'where-to-buy', false, dirname( $plugin->basename() ) . '/languages' );

		Items::init();
		Settings::init();
		Render::init();
		Shortcodes::init();
		Block::init();
		Templates::init();
		Todo::init();

		if ( is_admin() ) {
			Admin\ItemEditor::init();
			Admin\SettingsPage::init( $plugin );
		}
	}

	public static function plugin(): Plugin {
		if ( null === self::$plugin ) {
			throw new \LogicException( 'Where to Buy is not ready yet.' );
		}
		return self::$plugin;
	}

	public static function url( string $path = '' ): string {
		return plugins_url( $path, WTB_FILE );
	}

	public static function path( string $path = '' ): string {
		return plugin_dir_path( WTB_FILE ) . ltrim( $path, '/' );
	}

	public static function version(): string {
		return null !== self::$plugin ? self::$plugin->currentVersion() : '0';
	}
}
