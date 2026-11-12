<?php
/**
 * Front-end output: the buy box (store buttons + disclosure) and the item card used when a
 * item is placed in a post or listed.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Render {

	public static function init(): void {
		add_action( 'wp_enqueue_scripts', array( self::class, 'registerStyle' ) );
		add_action( 'enqueue_block_assets', array( self::class, 'registerStyle' ) );
		add_filter( 'the_content', array( self::class, 'appendToItem' ), 20 );
	}

	public static function registerStyle(): void {
		if ( ! wp_style_is( 'wtb-public', 'registered' ) ) {
			wp_register_style( 'wtb-public', App::url( 'assets/public.css' ), array(), App::version() );
		}
	}

	private static function useStyle(): void {
		self::registerStyle();
		wp_enqueue_style( 'wtb-public' );
	}

	/** Image URL for a store button: its own image, else the default image, else ''. */
	public static function storeImageUrl( array $store, string $size = 'medium' ): string {
		$id = (int) $store['image_id'];
		if ( $id <= 0 ) {
			$id = Stores::defaultImageId();
		}
		return $id > 0 ? (string) wp_get_attachment_image_url( $id, $size ) : '';
	}

	/**
	 * The buy box for an item, or '' when no store has a valid link.
	 *
	 * @param array{heading?: string|null} $args heading: override, '' hides it.
	 */
	public static function box( int $item_id, array $args = array() ): string {
		$buttons = Items::buttons( $item_id );
		if ( ! $buttons ) {
			return '';
		}
		self::useStyle();

		$heading = $args['heading'] ?? (string) Settings::get( 'heading' );
		$new_tab = (bool) Settings::get( 'new_tab' );
		$notes   = array();
		$items   = '';

		foreach ( $buttons as $button ) {
			$store = $button['store'];
			$name  = (string) $store['name'];
			$own   = (int) $store['image_id'] > 0;
			$img   = self::storeImageUrl( $store );
			$label = sprintf( /* translators: %s: store name */ __( 'Buy at %s', 'where-to-buy' ), $name );
			$tab   = $new_tab ? '<span class="wtb-sr">' . esc_html__( '(opens in a new tab)', 'where-to-buy' ) . '</span>' : '';

			if ( '' !== $img && $own ) {
				$kind  = 'image';
				$inner = '<img class="wtb-store__img" src="' . esc_url( $img ) . '" alt="' . esc_attr( $label ) . '" loading="lazy" decoding="async" />' . $tab;
			} elseif ( '' !== $img ) {
				$kind  = 'default';
				$inner = '<img class="wtb-store__img" src="' . esc_url( $img ) . '" alt="" loading="lazy" decoding="async" /><span class="wtb-store__name"><span class="wtb-sr">' . esc_html__( 'Buy at', 'where-to-buy' ) . ' </span>' . esc_html( $name ) . '</span>' . $tab;
			} else {
				$kind  = 'text';
				$inner = '<span class="wtb-store__name"><span class="wtb-sr">' . esc_html__( 'Buy at', 'where-to-buy' ) . ' </span>' . esc_html( $name ) . '</span>' . $tab;
			}

			$items .= sprintf(
				'<li><a class="wtb-store wtb-store--%1$s wtb-store--%2$s" href="%3$s" rel="sponsored nofollow noopener"%4$s>%5$s</a></li>',
				esc_attr( $kind ),
				esc_attr( (string) $store['id'] ),
				esc_url( $button['href'] ),
				$new_tab ? ' target="_blank"' : '',
				$inner
			);

			$note = trim( (string) $store['note'] );
			if ( '' !== $note ) {
				$notes[ $note ] = true;
			}
		}

		$main = trim( (string) Settings::get( 'note' ) );
		$html = '<div class="wtb-box">';
		if ( '' !== $heading ) {
			$html .= '<p class="wtb-box__heading">' . esc_html( $heading ) . '</p>';
		}
		$html .= '<ul class="wtb-box__stores">' . $items . '</ul>';
		if ( '' !== $main || $notes ) {
			$html .= '<div class="wtb-box__notes">';
			if ( '' !== $main ) {
				$html .= '<p class="wtb-box__note">' . nl2br( esc_html( $main ) ) . '</p>';
			}
			foreach ( array_keys( $notes ) as $note ) {
				$html .= '<p class="wtb-box__note">' . esc_html( $note ) . '</p>';
			}
			$html .= '</div>';
		}
		$html .= '</div>';

		/**
		 * Filter the buy box HTML.
		 *
		 * @param string $html    Markup.
		 * @param int    $item_id Item ID.
		 */
		return (string) apply_filters( 'wtb_box_html', $html, $item_id );
	}

	/**
	 * An item as a card: photo, title, short description and the buy box.
	 *
	 * @param array{heading_level?: int, buttons?: bool} $args
	 */
	public static function card( int $item_id, array $args = array() ): string {
		$post = get_post( $item_id );
		if ( ! $post || Items::POST_TYPE !== $post->post_type || 'publish' !== $post->post_status ) {
			return '';
		}
		if ( post_password_required( $post ) ) {
			return '';
		}
		self::useStyle();

		$level = min( 6, max( 2, (int) ( $args['heading_level'] ?? 3 ) ) );
		$link  = get_permalink( $post );
		$title = get_the_title( $post );
		$desc  = has_excerpt( $post ) ? get_the_excerpt( $post ) : wp_trim_words( wp_strip_all_tags( strip_shortcodes( $post->post_content ) ), 30 );
		$photo = get_the_post_thumbnail( $post, 'large', array( 'class' => 'wtb-item__img', 'alt' => '' ) );
		$box   = ( $args['buttons'] ?? true ) ? self::box( $item_id ) : '';

		$html  = '<article class="wtb-item' . ( '' === $photo ? ' wtb-item--no-photo' : '' ) . '">';
		if ( '' !== $photo ) {
			$html .= '<a class="wtb-item__photo" href="' . esc_url( (string) $link ) . '" tabindex="-1" aria-hidden="true">' . $photo . '</a>';
		}
		$html .= '<div class="wtb-item__body">';
		$html .= sprintf( '<h%1$d class="wtb-item__title"><a href="%2$s">%3$s</a></h%1$d>', $level, esc_url( (string) $link ), esc_html( $title ) );
		if ( '' !== trim( $desc ) ) {
			$html .= '<p class="wtb-item__desc">' . esc_html( $desc ) . '</p>';
		}
		$html .= $box . '</div></article>';

		return (string) apply_filters( 'wtb_card_html', $html, $item_id );
	}

	/** Add the buy box after the content on an item's own page, unless the content already places it. */
	public static function appendToItem( string $content ): string {
		if ( is_admin() || is_feed() || doing_filter( 'get_the_excerpt' ) || ! Settings::get( 'auto_append' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || Items::POST_TYPE !== $post->post_type || ! is_singular( Items::POST_TYPE ) || (int) get_queried_object_id() !== $post->ID ) {
			return $content;
		}
		if ( has_shortcode( $post->post_content, 'where_to_buy_buttons' ) || has_block( Block::NAME, $post ) || str_contains( $content, 'class="wtb-box"' ) ) {
			return $content;
		}
		return $content . self::box( $post->ID );
	}
}
