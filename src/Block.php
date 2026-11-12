<?php
/**
 * The Item block (where-to-buy/item): a server-rendered card or buy box.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Block {

	public const NAME = 'where-to-buy/item';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
	}

	public static function register(): void {
		Render::registerStyle();
		register_block_type(
			App::path( 'blocks/item' ),
			array( 'render_callback' => array( self::class, 'render' ) )
		);
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
