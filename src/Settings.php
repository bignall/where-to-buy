<?php
/**
 * Display settings, stored in one option. Stores live in their own option (see Stores).
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Settings {

	public const OPTION       = 'wtb_settings';
	public const GROUP        = 'wtb-settings';
	public const DEFAULT_SLUG = 'where-to-buy';

	/**
	 * Defaults. Plain strings, not translated: this runs when the plugin file loads (to seed the
	 * option on activation), which is before WordPress allows translations to load.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'slug'             => self::DEFAULT_SLUG,
			'heading'          => 'Where to buy',
			'note'             => 'Note: Commissions may be earned from the links above.',
			'new_tab'          => 1,
			'auto_append'      => 1,
			'shorten_amazon'   => 1,
			'remove_data'      => 0,
		);
	}

	/** @return array<string, mixed> */
	public static function all(): array {
		$saved = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
	}

	public static function get( string $key ): mixed {
		return self::all()[ $key ] ?? null;
	}

	public static function init(): void {
		add_action( 'admin_init', array( self::class, 'register' ) );
		add_action( 'update_option_' . self::OPTION, array( self::class, 'onUpdate' ), 10, 2 );
	}

	public static function register(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
		register_setting(
			Stores::GROUP,
			Stores::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Stores::class, 'sanitize' ),
			)
		);
		register_setting(
			Stores::GROUP,
			Stores::DEFAULT_IMAGE_OPTION,
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 0,
			)
		);
	}

	/**
	 * @param mixed $input Raw form input.
	 * @return array<string, mixed>
	 */
	public static function sanitize( $input ): array {
		$input = is_array( $input ) ? $input : array();
		$out   = self::defaults();

		$slug        = sanitize_title( (string) ( $input['slug'] ?? '' ) );
		$out['slug'] = '' !== $slug ? $slug : self::DEFAULT_SLUG;
		if ( $out['slug'] !== self::get( 'slug' ) && get_page_by_path( $out['slug'] ) ) {
			add_settings_error(
				self::OPTION,
				'wtb_slug_clash',
				sprintf(
					/* translators: %s: URL slug */
					__( 'A page already uses /%s/. The Where to Buy list page will take over that address, so consider a different slug.', 'where-to-buy' ),
					$out['slug']
				),
				'warning'
			);
		}

		$out['heading']          = sanitize_text_field( (string) ( $input['heading'] ?? '' ) );
		$out['note']             = sanitize_textarea_field( (string) ( $input['note'] ?? '' ) );

		foreach ( array( 'new_tab', 'auto_append', 'shorten_amazon', 'remove_data' ) as $flag ) {
			$out[ $flag ] = empty( $input[ $flag ] ) ? 0 : 1;
		}

		return $out;
	}

	/**
	 * Rebuild rewrite rules when the slug changes.
	 *
	 * @param mixed $old Old value.
	 * @param mixed $new New value.
	 */
	public static function onUpdate( $old, $new ): void {
		$old_slug = is_array( $old ) ? ( $old['slug'] ?? self::DEFAULT_SLUG ) : self::DEFAULT_SLUG;
		$new_slug = is_array( $new ) ? ( $new['slug'] ?? self::DEFAULT_SLUG ) : self::DEFAULT_SLUG;
		if ( $old_slug !== $new_slug ) {
			App::plugin()->requestRewriteFlush();
		}
	}
}
