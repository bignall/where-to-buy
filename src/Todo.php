<?php
/**
 * Optional link-up with To-do Hub: Where to Buy adds tasks to your to-do list when something
 * needs your attention, and marks them done when it's fixed.
 *
 * - "Fix store links on <item>" for each published item with a store link that doesn't show
 *   (wrong domain, not a full address, a cloaked link the cloaker marks dead), or no store
 *   button at all. High priority when visitors can't buy the item anywhere.
 * - "Review N items waiting for approval" while items are pending review.
 *
 * Checked when an item is saved or changes status, when store settings change, and once a day
 * (a cloaked link can die without anything here being saved). To-do Hub is only called when a
 * task actually changes, so a connected site doesn't call its hub on every page load.
 * Works with To-do Hub as the hub on this site or as a connector to a hub elsewhere.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Todo {

	public const SOURCE    = 'where-to-buy';
	public const CRON_HOOK = 'wtb_todo_check';
	public const CATEGORY  = 'Where to Buy';

	/** On each item: fingerprint of the problems last sent, so unchanged problems aren't resent. */
	public const META_SENT = '_wtb_todo_sent';

	/** Pending-review count last sent. */
	public const REVIEW_OPTION = 'wtb_todo_review_count';

	/** @var array<int, true> Items to re-check at the end of this request. */
	private static array $dirty = array();

	private static bool $review_dirty = false;

	public static function init(): void {
		add_action( 'wp_after_insert_post', array( self::class, 'onSaved' ), 20, 2 );
		add_action( 'transition_post_status', array( self::class, 'onTransition' ), 10, 3 );
		add_action( 'before_delete_post', array( self::class, 'onDelete' ) );
		add_action( 'update_option_' . Stores::OPTION, array( self::class, 'onStoresChanged' ) );
		add_action( self::CRON_HOOK, array( self::class, 'checkAll' ) );
		add_action( 'todo_hub_task_completed_' . self::SOURCE, array( self::class, 'onCompleted' ) );
		add_action( 'shutdown', array( self::class, 'flush' ) );
	}

	/** Whether To-do Hub is active and the setting is on. */
	public static function enabled(): bool {
		return function_exists( 'todo_hub_add' ) && (bool) Settings::get( 'todo_tasks' );
	}

	public static function itemKey( int $item_id ): string {
		return self::SOURCE . ':links:' . $item_id;
	}

	public static function reviewKey(): string {
		return self::SOURCE . ':review';
	}

	public static function onSaved( int $post_id, \WP_Post $post ): void {
		if ( Items::POST_TYPE === $post->post_type && ! wp_is_post_revision( $post_id ) && ! wp_is_post_autosave( $post_id ) ) {
			self::$dirty[ $post_id ] = true;
		}
	}

	public static function onTransition( string $new_status, string $old_status, \WP_Post $post ): void {
		if ( Items::POST_TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		self::$dirty[ $post->ID ] = true;
		if ( 'pending' === $new_status || 'pending' === $old_status ) {
			self::$review_dirty = true;
		}
	}

	public static function onDelete( int $post_id ): void {
		if ( get_post_type( $post_id ) === Items::POST_TYPE ) {
			self::check( $post_id, true );
			self::$review_dirty = true;
		}
	}

	/** Store settings decide which links show, so check every item soon (in the background). */
	public static function onStoresChanged(): void {
		if ( self::enabled() && ! wp_next_scheduled( self::CRON_HOOK, array( 'stores' ) ) ) {
			wp_schedule_single_event( time() + MINUTE_IN_SECONDS, self::CRON_HOOK, array( 'stores' ) );
		}
	}

	/** Re-check what this request changed, once, after everything is saved. */
	public static function flush(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$dirty       = array_keys( self::$dirty );
		self::$dirty = array();
		foreach ( $dirty as $item_id ) {
			self::check( (int) $item_id );
		}
		if ( self::$review_dirty ) {
			self::$review_dirty = false;
			self::checkReview();
		}
	}

	/** Daily, and after store settings change: check every published item and the review queue. */
	public static function checkAll(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$ids = get_posts(
			array(
				'post_type'        => Items::POST_TYPE,
				'post_status'      => 'any',
				'numberposts'      => -1,
				'fields'           => 'ids',
			)
		);
		foreach ( $ids as $id ) {
			self::check( (int) $id );
		}
		self::checkReview();
	}

	/**
	 * Why a published item's links don't all show, one line per problem. Empty when all is well.
	 *
	 * @return list<string>
	 */
	public static function problems( int $item_id ): array {
		$links    = Items::links( $item_id );
		$problems = array();
		foreach ( Stores::enabled() as $store ) {
			$entry = $links[ (string) $store['id'] ] ?? null;
			if ( null === $entry ) {
				continue;
			}
			$result = Items::check( $store, $entry, $item_id );
			if ( ! $result['ok'] ) {
				$problems[] = (string) $store['name'] . ': ' . $result['reason'];
			}
		}
		if ( ! Items::buttons( $item_id ) ) {
			$problems[] = __( 'No store button shows, so visitors can’t buy it from this page.', 'where-to-buy' );
		}
		return $problems;
	}

	/**
	 * Add, update or close the item's task to match its links now.
	 *
	 * @param bool $gone The item is being deleted: close its task.
	 */
	public static function check( int $item_id, bool $gone = false ): void {
		if ( ! self::enabled() ) {
			return;
		}
		$sent     = (string) get_post_meta( $item_id, self::META_SENT, true );
		$live     = ! $gone && 'publish' === get_post_status( $item_id );
		$problems = $live ? self::problems( $item_id ) : array();

		if ( ! $problems ) {
			if ( '' !== $sent ) {
				// Fixed: done. Unpublished or deleted: it no longer needs doing.
				if ( $live ) {
					todo_hub_complete( self::itemKey( $item_id ) );
				} else {
					todo_hub_remove( self::itemKey( $item_id ) );
				}
				delete_post_meta( $item_id, self::META_SENT );
			}
			return;
		}

		$fingerprint = md5( (string) wp_json_encode( $problems ) );
		if ( $fingerprint === $sent ) {
			return;
		}
		$none_show = ! Items::buttons( $item_id );
		$title     = (string) get_the_title( $item_id );
		$result    = todo_hub_add(
			array(
				/* translators: %s: item title. */
				'title'    => sprintf( __( 'Fix store links on “%s”', 'where-to-buy' ), $title ),
				'notes'    => '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $problems ) ) . '</li></ul>',
				'category' => self::CATEGORY,
				'priority' => $none_show ? 'high' : 'normal',
				'source'   => self::SOURCE,
				'key'      => self::itemKey( $item_id ),
				'link'     => (string) get_edit_post_link( $item_id, 'raw' ),
				'notify'   => true,
				'data'     => array( 'item_id' => $item_id ),
			)
		);
		if ( ! is_wp_error( $result ) ) {
			update_post_meta( $item_id, self::META_SENT, $fingerprint );
		}
	}

	/** Keep "Review N items" in step with the number of items pending review. */
	public static function checkReview(): void {
		if ( ! self::enabled() ) {
			return;
		}
		$counts = wp_count_posts( Items::POST_TYPE );
		$count  = isset( $counts->pending ) ? (int) $counts->pending : 0;
		$sent   = (int) get_option( self::REVIEW_OPTION, 0 );
		if ( $count === $sent ) {
			return;
		}
		if ( 0 === $count ) {
			todo_hub_complete( self::reviewKey() );
		} else {
			$result = todo_hub_add(
				array(
					/* translators: %s: number of items. */
					'title'    => sprintf( _n( 'Review %s Where to Buy item waiting for approval', 'Review %s Where to Buy items waiting for approval', $count, 'where-to-buy' ), number_format_i18n( $count ) ),
					'category' => self::CATEGORY,
					'source'   => self::SOURCE,
					'key'      => self::reviewKey(),
					'link'     => admin_url( 'edit.php?post_status=pending&post_type=' . Items::POST_TYPE ),
				)
			);
			if ( is_wp_error( $result ) ) {
				return;
			}
		}
		update_option( self::REVIEW_OPTION, $count, false );
	}

	/**
	 * A "fix links" task was ticked off on the to-do list. If the links are still broken, the
	 * next check adds the task again: forget what was sent so it isn't skipped as unchanged.
	 *
	 * @param array<string, mixed> $task The completed task.
	 */
	public static function onCompleted( array $task ): void {
		$item_id = (int) ( $task['data']['item_id'] ?? 0 );
		if ( $item_id > 0 && self::itemKey( $item_id ) === ( $task['key'] ?? '' ) ) {
			delete_post_meta( $item_id, self::META_SENT );
		}
	}
}
