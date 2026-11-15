<?php
/**
 * Blocks, both rendered on the server:
 *   where-to-buy/item     a chosen item as a card or buy box
 *   where-to-buy/buttons  the buy box for the current item (templates and Query Loops)
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Block {

	public const NAME    = 'where-to-buy/item';
	public const BUTTONS = 'where-to-buy/buttons';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		Render::registerStyle();
		register_block_type(
			App::path( 'blocks/item' ),
			array( 'render_callback' => array( self::class, 'render' ) )
		);
		register_block_type(
			App::path( 'blocks/buttons' ),
			array( 'render_callback' => array( self::class, 'renderButtons' ) )
		);
	}

	/**
	 * @param array<string, mixed> $attrs
	 * @param string               $content Unused.
	 * @param \WP_Block|null       $block   Gives the item from a Query Loop or template.
	 */
	public static function renderButtons( array $attrs, $content = '', $block = null ): string {
		$id = $block instanceof \WP_Block ? absint( $block->context['postId'] ?? 0 ) : 0;
		if ( ! $id ) {
			$id = (int) get_the_ID();
		}
		if ( ! $id || Items::POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			return '';
		}
		$args = array();
		if ( empty( $attrs['showHeading'] ) && isset( $attrs['showHeading'] ) ) {
			$args['heading'] = '';
		}
		if ( empty( $attrs['showNotes'] ) && isset( $attrs['showNotes'] ) ) {
			$args['notes'] = false;
		}
		$html = Render::box( $id, $args );
		return '' === $html ? '' : '<div ' . get_block_wrapper_attributes( array( 'class' => 'wtb-block' ) ) . '>' . $html . '</div>';
	}

	/** @param array<string, mixed> $attrs */
	public static function render( array $attrs ): string {
		$id = absint( $attrs['itemId'] ?? 0 );
		if ( ! $id || Items::POST_TYPE !== get_post_type( $id ) ) {
			return '';
		}
		$html = 'buttons' === ( $attrs['show'] ?? 'card' )
			? ( 'publish' === get_post_status( $id ) ? Render::box( $id ) : '' )
			: Render::card( $id );
		if ( '' === $html ) {
			return '';
		}
		return '<div ' . get_block_wrapper_attributes( array( 'class' => 'wtb-block' ) ) . '>' . $html . '</div>';
	}
}
