<?php
/**
 * Versioned upgrades, run by WP Foundation when the plugin version goes up.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Migrations {

	/**
	 * 0.3.0: settings split into General and Store pages. The default button image moves from
	 * the general settings option to its own option, saved with the stores.
	 */
	public static function moveDefaultImage(): void {
		$settings = get_option( Settings::OPTION );
		if ( ! is_array( $settings ) || ! array_key_exists( 'default_image_id', $settings ) ) {
			return;
		}
		update_option( Stores::DEFAULT_IMAGE_OPTION, absint( $settings['default_image_id'] ) );
		unset( $settings['default_image_id'] );
		update_option( Settings::OPTION, $settings );
	}

	/**
	 * 0.2.0: "Picks" became "Where to Buy" items. Move posts and categories to the new
	 * post type and taxonomy, and update Pick blocks saved in post content.
	 */
	public static function renamePicksToItems(): void {
		global $wpdb;

		$wpdb->update( $wpdb->posts, array( 'post_type' => Items::POST_TYPE ), array( 'post_type' => 'wtb_pick' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->update( $wpdb->term_taxonomy, array( 'taxonomy' => Items::TAXONOMY ), array( 'taxonomy' => 'wtb_pick_category' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		// <!-- wp:where-to-buy/pick {"pickId":12} /--> becomes <!-- wp:where-to-buy/item {"itemId":12} /-->.
		$ids = $wpdb->get_col( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%wp:where-to-buy/pick%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $ids as $id ) {
			$post = get_post( (int) $id );
			if ( ! $post ) {
				continue;
			}
			$content = preg_replace_callback(
				'#<!-- wp:where-to-buy/pick(\s+\{[^}]*\})?\s*/-->#',
				static fn ( array $m ): string => '<!-- wp:where-to-buy/item' . str_replace( '"pickId"', '"itemId"', $m[1] ?? '' ) . ' /-->',
				$post->post_content
			);
			if ( is_string( $content ) && $content !== $post->post_content ) {
				$wpdb->update( $wpdb->posts, array( 'post_content' => $content ), array( 'ID' => $post->ID ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_post_cache( $post->ID );
			}
		}
	}
}
