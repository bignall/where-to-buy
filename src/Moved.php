<?php
/**
 * After moving Where to Buy to another site: send visitors from this site's item pages, list
 * page and category pages to the same place on the new site, with a permanent (301) redirect,
 * so old links and search results keep working.
 *
 * Set under General Settings → Web address → Moved to. Items keep their names (slugs) when
 * imported, so /where-to-buy/blue-harness/ here goes to <new address>/blue-harness/.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Moved {

	public static function init(): void {
		add_action( 'template_redirect', array( self::class, 'redirect' ), 1 );
		add_action( 'admin_notices', array( self::class, 'notice' ) );
	}

	/** The new address, with a trailing slash, or ''. */
	public static function target(): string {
		$url = (string) Settings::get( 'moved_to' );
		return '' !== $url ? trailingslashit( $url ) : '';
	}

	public static function redirect(): void {
		$base = self::target();
		if ( '' === $base ) {
			return;
		}
		$to = '';
		if ( is_singular( Items::POST_TYPE ) ) {
			$to = $base . get_post_field( 'post_name', get_queried_object_id() ) . '/';
		} elseif ( is_tax( Items::TAXONOMY ) ) {
			$term = get_queried_object();
			$to   = $term instanceof \WP_Term ? $base . 'category/' . $term->slug . '/' : $base;
		} elseif ( is_post_type_archive( Items::POST_TYPE ) ) {
			$to = $base;
		}
		if ( '' !== $to ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- the address the owner set.
			wp_redirect( $to, 301, 'Where to Buy' );
			exit;
		}
	}

	public static function notice(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( '' === self::target() || ! $screen || ! str_contains( (string) $screen->id, 'wtb' ) && Items::POST_TYPE !== $screen->post_type ) {
			return;
		}
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: new address. */
					__( 'Where to Buy has moved: visitors to item pages here are sent to %s.', 'where-to-buy' ),
					self::target()
				)
			)
		);
	}
}
