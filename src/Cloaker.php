<?php
/**
 * Optional link-up with Affiliate Link Cloaker: an item's store can use one of its cloaked
 * /go/ links instead of a pasted URL. Clicks are then counted by the cloaker, with the item's
 * slug as the sub-ID, and links the cloaker marks as dead are hidden.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Cloaker {

	private const LINKS_CLASS = 'AffLinkCloaker\\Links';

	public static function active(): bool {
		return class_exists( self::LINKS_CLASS );
	}

	/**
	 * @return array{href: string, target: string, reason: string}
	 */
	public static function resolve( int $link_id, int $item_id = 0 ): array {
		$fail = static fn ( string $reason ): array => array( 'href' => '', 'target' => '', 'reason' => $reason );
		$cls  = self::LINKS_CLASS;

		$post = get_post( $link_id );
		if ( ! $post || $cls::POST_TYPE !== $post->post_type ) {
			return $fail( __( 'The cloaked link it uses was deleted.', 'where-to-buy' ) );
		}
		if ( 'publish' !== $post->post_status ) {
			return $fail( __( 'The cloaked link it uses isn’t published.', 'where-to-buy' ) );
		}
		if ( $cls::isDead( $link_id ) ) {
			return $fail( __( 'The cloaker says this offer no longer pays (paused, banned or ended).', 'where-to-buy' ) );
		}

		$subid = $item_id > 0 ? (string) get_post_field( 'post_name', $item_id ) : '';
		return array(
			'href'   => (string) $cls::cloakedUrl( $link_id, $subid ),
			'target' => (string) $cls::meta( $link_id, 'target_url' ),
			'reason' => '',
		);
	}

	/** @return array<int, string> Published cloaked links, id => title, for the item editor. */
	public static function choices(): array {
		if ( ! self::active() ) {
			return array();
		}
		$cls   = self::LINKS_CLASS;
		$posts = get_posts(
			array(
				'post_type'        => $cls::POST_TYPE,
				'post_status'      => 'publish',
				'numberposts'      => 500,
				'orderby'          => 'title',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[ $p->ID ] = '' !== $p->post_title ? $p->post_title : '/' . $p->post_name . '/';
		}
		return $out;
	}
}
