<?php
/**
 * The stores an item can link to: name, image, allowed domains, affiliate tag and extra
 * disclosure. Stored in order as one option; each store has a stable id that items use.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Stores {

	public const OPTION               = 'wtb_stores';
	public const GROUP                = 'wtb-stores';
	public const DEFAULT_IMAGE_OPTION = 'wtb_default_image_id';

	/** Image used for stores that don't have their own (0 = none: text buttons). */
	public static function defaultImageId(): int {
		return absint( get_option( self::DEFAULT_IMAGE_OPTION, 0 ) );
	}

	/** @return array<string, string|int> One empty store, with every field. */
	public static function blank(): array {
		return array(
			'id'        => '',
			'name'      => '',
			'enabled'   => 1,
			'image_id'  => 0,
			'domains'   => '',
			'tag_param' => '',
			'tag_value' => '',
			'note'      => '',
		);
	}

	/** @return list<array<string, string|int>> Stores a new install starts with. */
	public static function defaults(): array {
		$seed = array(
			array(
				'id'        => 'amazon',
				'name'      => 'Amazon',
				'domains'   => 'amazon.com, amzn.to, amzn.com, a.co',
				'tag_param' => 'tag',
				'note'      => 'As an Amazon Associate I earn from qualifying purchases.',
			),
			array(
				'id'      => 'walmart',
				'name'    => 'Walmart',
				'domains' => 'walmart.com, goto.walmart.com, walmrt.us',
			),
			array(
				'id'      => 'best-buy',
				'name'    => 'Best Buy',
				'domains' => 'bestbuy.com, bby.us, bestbuy.7tiv.net',
			),
			array(
				'id'      => 'target',
				'name'    => 'Target',
				'domains' => 'target.com, goto.target.com',
			),
			array(
				'id'      => 'chewy',
				'name'    => 'Chewy',
				'domains' => 'chewy.com',
			),
		);
		return array_map( static fn ( array $s ): array => array_merge( self::blank(), $s ), $seed );
	}

	/** @return list<array<string, string|int>> All stores in display order. */
	public static function all(): array {
		$saved = get_option( self::OPTION, null );
		if ( ! is_array( $saved ) ) {
			return self::defaults();
		}
		$out = array();
		foreach ( $saved as $store ) {
			if ( is_array( $store ) && ! empty( $store['id'] ) ) {
				$out[] = array_merge( self::blank(), $store );
			}
		}
		return $out;
	}

	/** @return list<array<string, string|int>> Stores that are switched on. */
	public static function enabled(): array {
		return array_values( array_filter( self::all(), static fn ( array $s ): bool => ! empty( $s['enabled'] ) ) );
	}

	/** @return array<string, string|int>|null */
	public static function find( string $id ): ?array {
		foreach ( self::all() as $store ) {
			if ( $store['id'] === $id ) {
				return $store;
			}
		}
		return null;
	}

	/** @return list<string> Lower-case domains from the comma- or space-separated setting. */
	public static function domains( array $store ): array {
		$parts = preg_split( '/[\s,]+/', strtolower( (string) $store['domains'] ) ) ?: array();
		$parts = array_map( static fn ( string $d ): string => ltrim( preg_replace( '#^https?://#', '', $d ) ?? '', '.' ), $parts );
		return array_values( array_filter( $parts ) );
	}

	/**
	 * @param mixed $input Raw form rows, in the order shown on the settings page.
	 * @return list<array<string, string|int>>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = array();
		$used  = array();

		foreach ( $input as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$name = sanitize_text_field( (string) ( $row['name'] ?? '' ) );
			if ( '' === $name ) {
				continue; // An empty row is how a store is removed.
			}

			// Keep the saved id when a store is renamed, so items keep their links.
			$id   = sanitize_key( (string) ( $row['id'] ?? '' ) );
			$id   = '' !== $id ? $id : sanitize_title( $name );
			$id   = '' !== $id ? $id : 'store';
			$base = $id;
			for ( $n = 2; isset( $used[ $id ] ); $n++ ) {
				$id = $base . '-' . $n;
			}
			$used[ $id ] = true;

			$domains = self::domains( array( 'domains' => (string) ( $row['domains'] ?? '' ) ) );
			$domains = array_filter( $domains, static fn ( string $d ): bool => (bool) preg_match( '/^[a-z0-9.-]+\.[a-z]{2,}$/', $d ) );

			$out[] = array(
				'id'        => $id,
				'name'      => $name,
				'enabled'   => empty( $row['enabled'] ) ? 0 : 1,
				'image_id'  => absint( $row['image_id'] ?? 0 ),
				'domains'   => implode( ', ', $domains ),
				'tag_param' => preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) ( $row['tag_param'] ?? '' ) ) ?? '',
				'tag_value' => sanitize_text_field( (string) ( $row['tag_value'] ?? '' ) ),
				'note'      => sanitize_text_field( (string) ( $row['note'] ?? '' ) ),
			);
		}

		return $out;
	}
}
