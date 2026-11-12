<?php
/**
 * The Item post type, its categories, and each item's store links.
 *
 * An item's links are saved as one meta array: store id => [ 'url' => '', 'alc' => 0 ], where
 * `alc` is an Affiliate Link Cloaker link id used instead of a pasted URL.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Items {

	public const POST_TYPE = 'wtb_item';
	public const TAXONOMY  = 'wtb_item_category';
	public const META      = '_wtb_links';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ) );
		add_action( 'rest_api_init', array( self::class, 'restFields' ) );
	}

	public static function register(): void {
		register_post_type(
			self::POST_TYPE,
			array(
				'labels'        => array(
					'name'               => __( 'Where to Buy', 'where-to-buy' ),
					'singular_name'      => __( 'Item', 'where-to-buy' ),
					'add_new_item'       => __( 'Add Item', 'where-to-buy' ),
					'edit_item'          => __( 'Edit Item', 'where-to-buy' ),
					'new_item'           => __( 'New Item', 'where-to-buy' ),
					'view_item'          => __( 'View Item', 'where-to-buy' ),
					'view_items'         => __( 'View Items', 'where-to-buy' ),
					'search_items'       => __( 'Search Items', 'where-to-buy' ),
					'not_found'          => __( 'No items found.', 'where-to-buy' ),
					'all_items'          => __( 'All Items', 'where-to-buy' ),
					'archives'           => __( 'Where to Buy', 'where-to-buy' ),
					'featured_image'     => __( 'Item photo', 'where-to-buy' ),
					'set_featured_image' => __( 'Set item photo', 'where-to-buy' ),
					'menu_name'          => __( 'Where to Buy', 'where-to-buy' ),
					'name_admin_bar'     => __( 'Where to Buy item', 'where-to-buy' ),
				),
				'description'   => __( 'Things I recommend, and where to buy them.', 'where-to-buy' ),
				'public'        => true,
				'has_archive'   => self::slug(),
				'rewrite'       => array( 'slug' => self::slug(), 'with_front' => false ),
				'show_in_rest'  => true,
				'menu_icon'     => 'dashicons-cart',
				'menu_position' => 21,
				'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'revisions' ),
			)
		);

		register_taxonomy(
			self::TAXONOMY,
			self::POST_TYPE,
			array(
				'labels'            => array(
					'name'          => __( 'Item Categories', 'where-to-buy' ),
					'singular_name' => __( 'Item Category', 'where-to-buy' ),
					'menu_name'     => __( 'Categories', 'where-to-buy' ),
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_in_rest'      => true,
				'show_admin_column' => true,
				'rewrite'           => array( 'slug' => self::slug() . '/category', 'with_front' => false ),
			)
		);
	}

	/** URL base for items and their list page, e.g. /where-to-buy/my-harness/. Set in settings. */
	public static function slug(): string {
		$slug = sanitize_title( (string) Settings::get( 'slug' ) );
		/**
		 * Filter the URL base for items and the item list page.
		 *
		 * @param string $slug From settings; default "where-to-buy".
		 */
		$slug = sanitize_title( (string) apply_filters( 'wtb_item_slug', '' !== $slug ? $slug : Settings::DEFAULT_SLUG ) );
		return '' !== $slug ? $slug : Settings::DEFAULT_SLUG;
	}

	/** @return array<string, array{url: string, alc: int}> Saved links keyed by store id. */
	public static function links( int $item_id ): array {
		$raw = get_post_meta( $item_id, self::META, true );
		$out = array();
		if ( is_array( $raw ) ) {
			foreach ( $raw as $store_id => $entry ) {
				if ( is_array( $entry ) ) {
					$out[ (string) $store_id ] = array(
						'url' => (string) ( $entry['url'] ?? '' ),
						'alc' => (int) ( $entry['alc'] ?? 0 ),
					);
				}
			}
		}
		return $out;
	}

	/** @param array<string, array{url: string, alc: int}> $links */
	public static function saveLinks( int $item_id, array $links ): void {
		$links = array_filter( $links, static fn ( array $e ): bool => '' !== $e['url'] || $e['alc'] > 0 );
		if ( $links ) {
			update_post_meta( $item_id, self::META, $links );
		} else {
			delete_post_meta( $item_id, self::META );
		}
	}

	/**
	 * Check one store's link for an item.
	 *
	 * @param array<string, string|int> $store Store from Stores.
	 * @param array{url: string, alc: int} $entry Saved link.
	 * @return array{ok: bool, href: string, reason: string}
	 */
	public static function check( array $store, array $entry, int $item_id = 0 ): array {
		$no = static fn ( string $reason ): array => array( 'ok' => false, 'href' => '', 'reason' => $reason );

		if ( $entry['alc'] > 0 ) {
			if ( ! Cloaker::active() ) {
				return $no( __( 'Uses a cloaked link, but Affiliate Link Cloaker is not active.', 'where-to-buy' ) );
			}
			$cloaked = Cloaker::resolve( $entry['alc'], $item_id );
			if ( '' !== $cloaked['reason'] ) {
				return $no( $cloaked['reason'] );
			}
			$target = $cloaked['target'];
			$href   = $cloaked['href'];
		} elseif ( '' !== $entry['url'] ) {
			$target = $entry['url'];
			$href   = self::withTag( $store, $entry['url'] );
		} else {
			return $no( __( 'No link.', 'where-to-buy' ) );
		}

		if ( ! self::isUrl( $target ) ) {
			return $no( __( 'Not a full web address (it must start with https://).', 'where-to-buy' ) );
		}
		if ( ! self::hostAllowed( $store, $target ) ) {
			return $no(
				sprintf(
					/* translators: 1: store name, 2: domain list */
					__( 'Doesn’t go to %1$s (allowed: %2$s). Add the domain in Where to Buy → Store Settings if it’s right.', 'where-to-buy' ),
					(string) $store['name'],
					implode( ', ', Stores::domains( $store ) )
				)
			);
		}
		return array( 'ok' => true, 'href' => $href, 'reason' => '' );
	}

	/**
	 * The store buttons that should show for an item: enabled stores, in order, with valid links.
	 *
	 * @return list<array{store: array<string, string|int>, href: string}>
	 */
	public static function buttons( int $item_id ): array {
		$links = self::links( $item_id );
		$out   = array();
		foreach ( Stores::enabled() as $store ) {
			$entry = $links[ (string) $store['id'] ] ?? null;
			if ( null === $entry ) {
				continue;
			}
			$result = self::check( $store, $entry, $item_id );
			if ( $result['ok'] ) {
				$out[] = array( 'store' => $store, 'href' => $result['href'] );
			}
		}

		/**
		 * Filter the store buttons shown for an item.
		 *
		 * @param list<array{store: array, href: string}> $out     Buttons.
		 * @param int                                     $item_id Item ID.
		 */
		return apply_filters( 'wtb_buttons', $out, $item_id );
	}

	/** Absolute http(s) URL with a real-looking host and nothing that could break out of an attribute. */
	public static function isUrl( string $url ): bool {
		if ( '' === $url || preg_match( '/[\s<>"]/', $url ) ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		return is_array( $parts )
			&& isset( $parts['scheme'], $parts['host'] )
			&& in_array( strtolower( $parts['scheme'] ), array( 'http', 'https' ), true )
			&& (bool) preg_match( '/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]*[a-z0-9])?)+$/i', $parts['host'] );
	}

	/** True when the URL's host is one of the store's domains or a subdomain of one. No domains = any host. */
	public static function hostAllowed( array $store, string $url ): bool {
		$domains = Stores::domains( $store );
		if ( ! $domains ) {
			return true;
		}
		$host = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		foreach ( $domains as $domain ) {
			if ( $host === $domain || str_ends_with( $host, '.' . $domain ) ) {
				return true;
			}
		}
		return false;
	}

	/** Add the store's affiliate tag (e.g. Amazon's tag=you-20) unless the link already has one. */
	public static function withTag( array $store, string $url ): string {
		$param = (string) $store['tag_param'];
		$value = (string) $store['tag_value'];
		if ( '' === $param || '' === $value ) {
			return $url;
		}
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		parse_str( $query, $args );
		if ( isset( $args[ $param ] ) && '' !== $args[ $param ] ) {
			return $url;
		}
		return add_query_arg( $param, rawurlencode( $value ), $url );
	}

	/** Read-only `wtb_buttons` field on items in the REST API, for templates and headless use. */
	public static function restFields(): void {
		register_rest_field(
			self::POST_TYPE,
			'wtb_buttons',
			array(
				'get_callback' => static function ( array $post ): array {
					return array_map(
						static fn ( array $b ): array => array(
							'store' => (string) $b['store']['id'],
							'name'  => (string) $b['store']['name'],
							'url'   => $b['href'],
							'image' => Render::storeImageUrl( $b['store'] ),
						),
						self::buttons( (int) $post['id'] )
					);
				},
				'schema'       => array(
					'description' => __( 'Store buttons that show for this item.', 'where-to-buy' ),
					'type'        => 'array',
					'context'     => array( 'view', 'edit' ),
					'readonly'    => true,
				),
			)
		);
	}
}
