<?php
/**
 * Shortens Amazon product links to https://www.amazon.<tld>/dp/ASIN/?tag=TAG.
 *
 * Links copied from Amazon's site carry search tracking (crid, keywords, qid, sprefix, sr…) and a
 * product-title slug. Only the ASIN and the affiliate tag matter, so everything else is dropped.
 * Pure PHP with no WordPress calls, so it can be unit tested on its own.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class AmazonLinks {

	/** Amazon storefront hosts: amazon.com, www.amazon.co.uk, smile.amazon.de, amazon.com.au… */
	private const HOST = '/^(?:[a-z0-9-]+\.)*amazon\.((?:com|co)\.[a-z]{2}|[a-z]{2,3})$/';

	/** Product paths: /dp/ASIN, /Title-Words/dp/ASIN, /gp/product/ASIN, /gp/aw/d/ASIN, /exec/obidos/ASIN/ASIN. */
	private const PATH = '#/(?:dp|gp/product|gp/aw/d|exec/obidos/asin)/([a-z0-9]{10})(?=[/?&]|$)#i';

	/** Short-link hosts that redirect to a product page (amzn.to, a.co) and need resolving first. */
	private const SHORT_HOSTS = array( 'amzn.to', 'www.amzn.to', 'a.co', 'www.a.co' );

	/** True for an Amazon storefront host or one of Amazon's own short-link hosts. */
	public static function isAmazon( string $url ): bool {
		$host = self::host( $url );
		return '' !== $host && ( (bool) preg_match( self::HOST, $host ) || self::isShortLink( $url ) || in_array( $host, array( 'amzn.com', 'www.amzn.com' ), true ) );
	}

	/** True for amzn.to and a.co links, whose product is only known after following the redirect. */
	public static function isShortLink( string $url ): bool {
		return in_array( self::host( $url ), self::SHORT_HOSTS, true );
	}

	/** The product's ASIN (upper case), or '' when the link isn't an Amazon product link. */
	public static function asin( string $url ): string {
		$host = self::host( $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( preg_match( self::HOST, $host ) && preg_match( self::PATH, $path, $m ) ) {
			return strtoupper( $m[1] );
		}
		// amzn.com/ASIN, Amazon's old short form.
		if ( in_array( $host, array( 'amzn.com', 'www.amzn.com' ), true ) && preg_match( '#^/(?:dp/)?([a-z0-9]{10})/?$#i', $path, $m ) ) {
			return strtoupper( $m[1] );
		}
		return '';
	}

	/**
	 * The short form of an Amazon product link, or the link unchanged when it has no ASIN.
	 *
	 * @param string        $url      Link as pasted.
	 * @param string        $tag      Tag to use when the link has none (the store's "Your tag").
	 * @param string        $param    Name of the tag query parameter (Amazon's is `tag`).
	 * @param callable|null $resolve  Follows a short link: fn( string $url ): string, '' on failure.
	 */
	public static function shorten( string $url, string $tag = '', string $param = 'tag', ?callable $resolve = null ): string {
		$param  = '' !== $param ? $param : 'tag';
		$source = $url;
		if ( self::isShortLink( $url ) ) {
			if ( null === $resolve ) {
				return $url;
			}
			$source = (string) $resolve( $url );
			if ( '' === $source ) {
				return $url;
			}
		}

		$asin = self::asin( $source );
		if ( '' === $asin ) {
			return $url;
		}

		parse_str( (string) wp_parse_url( $source, PHP_URL_QUERY ), $args );
		$own = isset( $args[ $param ] ) && is_string( $args[ $param ] ) ? trim( $args[ $param ] ) : '';
		$tag = '' !== $own ? $own : trim( $tag );

		$host = self::host( $source );
		$host = preg_match( self::HOST, $host, $m ) ? 'www.amazon.' . $m[1] : 'www.amazon.com';

		$short = 'https://' . $host . '/dp/' . $asin . '/';
		return '' !== $tag ? $short . '?' . rawurlencode( $param ) . '=' . rawurlencode( $tag ) : $short;
	}

	private static function host( string $url ): string {
		return strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
	}
}
