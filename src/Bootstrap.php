<?php
/**
 * Plugin definition: lifecycle handled by WP Foundation.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

use WhereToBuy\Vendor\Bignall\WPFoundation\Plugin;

final class Bootstrap {

	public const PREFIX = 'wtb';

	public static function plugin( string $file ): Plugin {
		return Plugin::create( $file, self::PREFIX )
			->requires( php: '8.1', wp: '6.4' )
			->options(
				array(
					Settings::OPTION => Settings::defaults(),
					Stores::OPTION   => Stores::defaults(),
					Stores::DEFAULT_IMAGE_OPTION => 0,
				),
				merge: false
			)
			->options( array( Todo::REVIEW_OPTION => 0 ) )
			->cron( Todo::CRON_HOOK, 'daily' )
			->postTypes( Items::POST_TYPE )
			->taxonomies( Items::TAXONOMY )
			->flushRewriteRules()
			->migration( '0.2.0', array( Migrations::class, 'renamePicksToItems' ) )
			->migration( '0.3.0', array( Migrations::class, 'moveDefaultImage' ) )
			->deactivationSurvey()
			->deleteDataOnUninstall( static fn (): bool => (bool) Settings::get( 'remove_data' ) )
			->onReady( static fn ( Plugin $plugin ) => App::boot( $plugin ) );
	}
}
