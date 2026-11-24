<?php
/**
 * Links to the paid add-ons: a "Pro add-on" and "Templates" link on the Plugins screen, and a
 * short Add-ons box at the bottom of General Settings. Each one disappears once that add-on is
 * active. Nothing else in the plugin mentions them.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Admin;

use WhereToBuy\Vendor\Bignall\WPFoundation\Plugin;

final class AddOns {

	private static string $basename = '';

	public static function init( Plugin $plugin ): void {
		self::$basename = $plugin->basename();
		add_filter( 'plugin_row_meta', array( self::class, 'rowMeta' ), 10, 2 );
	}

	/**
	 * The add-ons, keyed by their main plugin file.
	 *
	 * @return array<string, array{name: string, link: string, about: string, url: string}>
	 */
	public static function all(): array {
		return array(
			'where-to-buy-pro/where-to-buy-pro.php'             => array(
				'name'  => __( 'Where to Buy Pro', 'where-to-buy' ),
				'link'  => __( 'Pro add-on', 'where-to-buy' ),
				'about' => __( 'Turns your WarriorPlus and JVZoo offers into items, cloaks store links from the item screen, counts clicks for each item and store, and emails you when a link stops paying.', 'where-to-buy' ),
				'url'   => 'https://rbcreativesolutions.net/where-to-buy-pro/',
			),
			'where-to-buy-templates/where-to-buy-templates.php' => array(
				'name'  => __( 'Where to Buy Templates', 'where-to-buy' ),
				'link'  => __( 'Templates', 'where-to-buy' ),
				'about' => __( 'Ready-made item and list pages in two layouts, for Etch or any block theme.', 'where-to-buy' ),
				'url'   => 'https://rbcreativesolutions.net/where-to-buy-templates/',
			),
		);
	}

	/**
	 * Add-ons that aren't active on this site.
	 *
	 * @return array<string, array{name: string, link: string, about: string, url: string}>
	 */
	public static function missing(): array {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		return array_filter( self::all(), static fn ( string $file ): bool => ! is_plugin_active( $file ), ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Links under the plugin's description on the Plugins screen.
	 *
	 * @param array<int|string, string> $meta Row meta links.
	 * @param string                    $file Plugin file of the row.
	 * @return array<int|string, string>
	 */
	public static function rowMeta( array $meta, string $file ): array {
		if ( $file !== self::$basename ) {
			return $meta;
		}
		foreach ( self::missing() as $addon ) {
			$meta[] = self::link( $addon['url'], $addon['link'] );
		}
		return $meta;
	}

	/** The Add-ons box at the bottom of General Settings. Shows nothing when both are active. */
	public static function render(): void {
		$missing = self::missing();
		if ( array() === $missing ) {
			return;
		}
		?>
		<div class="wtb-addons">
			<h2><?php esc_html_e( 'Add-ons', 'where-to-buy' ); ?></h2>
			<ul>
				<?php foreach ( $missing as $addon ) : ?>
					<li>
						<strong><?php echo esc_html( $addon['name'] ); ?></strong>:
						<?php echo esc_html( $addon['about'] ); ?>
						<?php echo self::link( $addon['url'], __( 'Learn more', 'where-to-buy' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in link(). ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
		<?php
	}

	private static function link( string $url, string $text ): string {
		return sprintf(
			'<a href="%1$s" target="_blank" rel="noopener">%2$s<span class="screen-reader-text"> %3$s</span></a>',
			esc_url( $url ),
			esc_html( $text ),
			esc_html__( '(opens in a new tab)', 'where-to-buy' )
		);
	}
}
