<?php
/**
 * Shortcodes:
 *   [where_to_buy id="12"] or [where_to_buy slug="my-harness"]       the item as a card (photo, title, description, buttons)
 *   [where_to_buy id="12" show="buttons"]                    just the buy box
 *   [where_to_buy_buttons]                                   the buy box for the current item (for templates)
 *   [where_to_buy_list category="service-dog-gear" count="6"]    a grid of item cards
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Shortcodes {

	public static function init(): void {
		add_shortcode( 'where_to_buy', array( self::class, 'item' ) );
		add_shortcode( 'where_to_buy_buttons', array( self::class, 'buttons' ) );
		add_shortcode( 'where_to_buy_list', array( self::class, 'grid' ) );
	}

	/** Find an item by id or slug. */
	public static function findId( array $atts ): int {
		$id = absint( $atts['id'] ?? 0 );
		if ( $id > 0 ) {
			return Items::POST_TYPE === get_post_type( $id ) ? $id : 0;
		}
		$slug = sanitize_title( (string) ( $atts['slug'] ?? '' ) );
		if ( '' === $slug ) {
			return 0;
		}
		$post = get_page_by_path( $slug, OBJECT, Items::POST_TYPE );
		return $post ? (int) $post->ID : 0;
	}

	/** @param array<string, string>|string $atts */
	public static function item( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'            => '',
				'slug'          => '',
				'show'          => 'card',
				'heading_level' => '3',
			),
			(array) $atts,
			'where_to_buy'
		);
		$id = self::findId( $atts );
		if ( ! $id ) {
			return '';
		}
		if ( 'buttons' === $atts['show'] ) {
			return 'publish' === get_post_status( $id ) ? Render::box( $id ) : '';
		}
		return Render::card( $id, array( 'heading_level' => (int) $atts['heading_level'] ) );
	}

	/** @param array<string, string>|string $atts */
	public static function buttons( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id'      => '',
				'slug'    => '',
				'heading' => null,
			),
			(array) $atts,
			'where_to_buy_buttons'
		);
		$id = self::findId( $atts );
		if ( ! $id ) {
			$post = get_post();
			$id   = $post && Items::POST_TYPE === $post->post_type ? (int) $post->ID : 0;
		}
		if ( ! $id ) {
			return '';
		}
		$args = null !== $atts['heading'] ? array( 'heading' => (string) $atts['heading'] ) : array();
		return Render::box( $id, $args );
	}

	/** @param array<string, string>|string $atts */
	public static function grid( $atts ): string {
		$atts = shortcode_atts(
			array(
				'category'      => '',
				'count'         => '12',
				'orderby'       => 'date',
				'order'         => 'DESC',
				'buttons'       => '1',
				'heading_level' => '3',
			),
			(array) $atts,
			'where_to_buy_list'
		);

		$query = array(
			'post_type'      => Items::POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, min( 100, (int) $atts['count'] ) ),
			'orderby'        => in_array( $atts['orderby'], array( 'date', 'title', 'menu_order', 'rand', 'modified' ), true ) ? $atts['orderby'] : 'date',
			'order'          => 'ASC' === strtoupper( (string) $atts['order'] ) ? 'ASC' : 'DESC',
			'no_found_rows'  => true,
		);
		$cats = array_filter( array_map( 'sanitize_title', explode( ',', (string) $atts['category'] ) ) );
		if ( $cats ) {
			$query['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => Items::TAXONOMY,
					'field'    => 'slug',
					'terms'    => $cats,
				),
			);
		}

		$ids = get_posts( array_merge( $query, array( 'fields' => 'ids' ) ) );
		if ( ! $ids ) {
			return '';
		}
		$cards = '';
		foreach ( $ids as $id ) {
			$cards .= Render::card(
				(int) $id,
				array(
					'buttons'       => '0' !== (string) $atts['buttons'],
					'heading_level' => (int) $atts['heading_level'],
				)
			);
		}
		return '<div class="wtb-items">' . $cards . '</div>';
	}
}
