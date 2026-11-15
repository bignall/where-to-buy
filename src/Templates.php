<?php
/**
 * Basic templates for block themes (WordPress 6.7+): an item's own page and the list page.
 *
 * They are registered, not saved, so they cost nothing until used, and a template the site
 * already has (or edits in the Site Editor) always wins. Category pages use the list template
 * too (see Items::categoryTemplates()).
 *
 * Also gives the list page its title and introduction from settings.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Templates {

	public const NAMESPACE = 'where-to-buy';

	public static function init(): void {
		add_action( 'init', array( self::class, 'register' ), 20 );
		add_filter( 'post_type_archive_title', array( self::class, 'archiveTitle' ), 10, 2 );
		add_filter( 'get_the_post_type_description', array( self::class, 'archiveDescription' ), 10, 2 );
	}

	public static function register(): void {
		if ( ! function_exists( 'register_block_template' ) || ! wp_is_block_theme() ) {
			return;
		}
		/**
		 * Whether to register the basic block theme templates.
		 *
		 * @param bool $register Default true.
		 */
		if ( ! apply_filters( 'wtb_block_templates', true ) ) {
			return;
		}
		register_block_template(
			self::NAMESPACE . '//single-' . Items::POST_TYPE,
			array(
				'title'       => __( 'Where to Buy item', 'where-to-buy' ),
				'description' => __( 'An item’s own page: photo, title, short description, store buttons and notes.', 'where-to-buy' ),
				'content'     => self::single(),
				'post_types'  => array( Items::POST_TYPE ),
			)
		);
		register_block_template(
			self::NAMESPACE . '//archive-' . Items::POST_TYPE,
			array(
				'title'       => __( 'Where to Buy list', 'where-to-buy' ),
				'description' => __( 'The list of items, also used for item categories.', 'where-to-buy' ),
				'content'     => self::archive(),
			)
		);
	}

	/** Block markup for an item's own page. */
	public static function single(): string {
		return <<<'HTML'
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"wtb-template wtb-template--single","layout":{"type":"constrained"}} -->
<main class="wp-block-group wtb-template wtb-template--single"><!-- wp:columns {"align":"wide","className":"wtb-template__hero"} -->
<div class="wp-block-columns alignwide wtb-template__hero"><!-- wp:column {"width":"40%"} -->
<div class="wp-block-column" style="flex-basis:40%"><!-- wp:post-featured-image {"className":"wtb-template__photo"} /--></div>
<!-- /wp:column -->

<!-- wp:column {"width":"60%"} -->
<div class="wp-block-column" style="flex-basis:60%"><!-- wp:shortcode -->
[where_to_buy_back]
<!-- /wp:shortcode -->

<!-- wp:post-title {"level":1} /-->

<!-- wp:post-excerpt {"className":"wtb-template__excerpt"} /-->

<!-- wp:where-to-buy/buttons /--></div>
<!-- /wp:column --></div>
<!-- /wp:columns -->

<!-- wp:post-content {"layout":{"type":"constrained"}} /--></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
	}

	/** Block markup for the list page and item categories. */
	public static function archive(): string {
		$none = esc_html__( 'Nothing here yet.', 'where-to-buy' );
		$html = <<<'HTML'
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","className":"wtb-template wtb-template--list","layout":{"type":"constrained"}} -->
<main class="wp-block-group wtb-template wtb-template--list"><!-- wp:query-title {"type":"archive","showPrefix":false} /-->

<!-- wp:term-description /-->

<!-- wp:shortcode -->
[where_to_buy_intro]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[where_to_buy_disclosure]
<!-- /wp:shortcode -->

<!-- wp:shortcode -->
[where_to_buy_categories]
<!-- /wp:shortcode -->

<!-- wp:query {"queryId":1,"query":{"perPage":24,"pages":0,"offset":0,"postType":"wtb_item","order":"desc","orderBy":"date","inherit":true},"align":"wide","className":"wtb-template__list"} -->
<div class="wp-block-query alignwide wtb-template__list"><!-- wp:post-template {"layout":{"type":"grid","columnCount":3}} -->
<!-- wp:post-featured-image {"isLink":true,"aspectRatio":"4/5","scale":"contain","className":"wtb-template__photo"} /-->

<!-- wp:post-title {"level":2,"isLink":true,"className":"wtb-template__title"} /-->

<!-- wp:post-excerpt /-->

<!-- wp:where-to-buy/buttons {"showHeading":false,"showNotes":false} /-->
<!-- /wp:post-template -->

<!-- wp:query-pagination {"layout":{"type":"flex","justifyContent":"center"}} -->
<!-- wp:query-pagination-previous /-->

<!-- wp:query-pagination-numbers /-->

<!-- wp:query-pagination-next /-->
<!-- /wp:query-pagination -->

<!-- wp:query-no-results -->
<!-- wp:paragraph -->
<p>%NONE%</p>
<!-- /wp:paragraph -->
<!-- /wp:query-no-results --></div>
<!-- /wp:query --></main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
HTML;
		return str_replace( '%NONE%', $none, $html );
	}

	/**
	 * The list page title from settings.
	 *
	 * @param string $title     Title.
	 * @param string $post_type Post type.
	 */
	public static function archiveTitle( $title, $post_type = '' ): string {
		return Items::POST_TYPE === $post_type ? Render::archiveTitle() : (string) $title;
	}

	/**
	 * The list page introduction from settings, as the post type archive description
	 * (what themes and Etch's {archive.description} show).
	 *
	 * @param string        $description Description.
	 * @param \WP_Post_Type $type        Post type object.
	 */
	public static function archiveDescription( $description, $type = null ): string {
		if ( $type instanceof \WP_Post_Type && Items::POST_TYPE === $type->name ) {
			$intro = trim( (string) Settings::get( 'archive_intro' ) );
			return '' !== $intro ? $intro : (string) $description;
		}
		return (string) $description;
	}
}
