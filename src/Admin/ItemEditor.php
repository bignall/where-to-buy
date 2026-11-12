<?php
/**
 * The "Where to buy" box on the item editor, and the store count column in the Items list.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Admin;

use WhereToBuy\App;
use WhereToBuy\Cloaker;
use WhereToBuy\Items;
use WhereToBuy\Render;
use WhereToBuy\Stores;

final class ItemEditor {

	private const NONCE = 'wtb_item_links';

	/** Meta box context printed right under the title, above the notes editor. */
	private const TOP = 'wtb_top';

	public static function init(): void {
		add_filter( 'use_block_editor_for_post_type', array( self::class, 'useBlockEditor' ), 10, 2 );
		add_action( 'edit_form_after_title', array( self::class, 'afterTitle' ) );
		add_filter( 'post_updated_messages', array( self::class, 'messages' ) );
		add_action( 'add_meta_boxes_' . Items::POST_TYPE, array( self::class, 'addBox' ) );
		add_action( 'save_post_' . Items::POST_TYPE, array( self::class, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'manage_' . Items::POST_TYPE . '_posts_columns', array( self::class, 'columns' ) );
		add_action( 'manage_' . Items::POST_TYPE . '_posts_custom_column', array( self::class, 'column' ), 10, 2 );
	}

	public static function assets( string $hook ): void {
		$screen = get_current_screen();
		if ( $screen && Items::POST_TYPE === $screen->post_type ) {
			wp_enqueue_style( 'wtb-admin', App::url( 'assets/admin.css' ), array(), App::version() );
		}
	}

	/**
	 * Items use the classic editor: one plain screen with every field visible. The block
	 * editor tucks meta boxes away in a collapsed panel. Posts and pages are unaffected.
	 */
	public static function useBlockEditor( bool $use, string $post_type ): bool {
		/**
		 * Filter whether Where to Buy items use the block editor.
		 *
		 * @param bool $use Default false.
		 */
		return Items::POST_TYPE === $post_type ? (bool) apply_filters( 'wtb_item_block_editor', false ) : $use;
	}

	public static function addBox(): void {
		$classic = ! use_block_editor_for_post_type( Items::POST_TYPE );
		if ( $classic ) {
			// Core's Excerpt box is hidden by default; replace it with a visible, renamed one.
			remove_meta_box( 'postexcerpt', Items::POST_TYPE, 'normal' );
			add_meta_box( 'wtb-description', __( 'Short description', 'where-to-buy' ), array( self::class, 'renderDescription' ), Items::POST_TYPE, self::TOP, 'high' );
		}
		add_meta_box( 'wtb-links', __( 'Where to buy', 'where-to-buy' ), array( self::class, 'render' ), Items::POST_TYPE, $classic ? self::TOP : 'normal', 'high' );
	}

	/**
	 * "Item published. View item" instead of the generic post wording.
	 *
	 * @param array<string, array<int, string>> $messages Messages by post type.
	 * @return array<string, array<int, string>>
	 */
	public static function messages( array $messages ): array {
		$post = get_post();
		$view = $post ? ' <a href="' . esc_url( (string) get_permalink( $post ) ) . '">' . esc_html__( 'View item', 'where-to-buy' ) . '</a>' : '';
		$messages[ Items::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Item updated.', 'where-to-buy' ) . $view,
			4  => __( 'Item updated.', 'where-to-buy' ),
			6  => __( 'Item published.', 'where-to-buy' ) . $view,
			7  => __( 'Item saved.', 'where-to-buy' ),
			8  => __( 'Item submitted.', 'where-to-buy' ),
			9  => __( 'Item scheduled.', 'where-to-buy' ),
			10 => __( 'Item draft updated.', 'where-to-buy' ),
		);
		return $messages;
	}

	/** Print the top boxes under the title, then a label for the notes editor. */
	public static function afterTitle( \WP_Post $post ): void {
		if ( Items::POST_TYPE !== $post->post_type || use_block_editor_for_post_type( Items::POST_TYPE ) ) {
			return;
		}
		echo '<div class="wtb-top">';
		do_meta_boxes( get_current_screen(), self::TOP, $post );
		echo '</div>';
		echo '<h2 class="wtb-notes-heading">' . esc_html__( 'Longer notes', 'where-to-buy' ) . ' <span>' . esc_html__( '(optional, shown on the item’s own page)', 'where-to-buy' ) . '</span></h2>';
	}

	/** The excerpt, as "Short description". Core saves the `excerpt` field itself. */
	public static function renderDescription( \WP_Post $post ): void {
		?>
		<label class="screen-reader-text" for="excerpt"><?php esc_html_e( 'Short description', 'where-to-buy' ); ?></label>
		<textarea rows="3" cols="40" name="excerpt" id="excerpt" class="large-text"><?php echo esc_textarea( $post->post_excerpt ); ?></textarea>
		<p class="description"><?php esc_html_e( 'A sentence or two. Shown with the photo wherever this item is listed or placed in a post.', 'where-to-buy' ); ?></p>
		<?php
	}

	public static function render( \WP_Post $post ): void {
		$links    = Items::links( $post->ID );
		$stores   = Stores::all();
		$cloaked  = Cloaker::choices();
		$settings = SettingsPage::storesUrl();

		wp_nonce_field( self::NONCE, self::NONCE );

		if ( ! $stores ) {
			echo '<p>' . wp_kses_post( sprintf( /* translators: %s: settings URL */ __( 'No stores yet. Add them in <a href="%s">Where to Buy → Store Settings</a>.', 'where-to-buy' ), esc_url( $settings ) ) ) . '</p>';
			return;
		}
		?>
		<p class="description">
			<?php esc_html_e( 'Paste the link for each store that sells this. Only stores with a valid link show on the site.', 'where-to-buy' ); ?>
			<a href="<?php echo esc_url( $settings ); ?>"><?php esc_html_e( 'Manage stores', 'where-to-buy' ); ?></a>
		</p>
		<table class="wtb-links widefat striped">
			<thead>
				<tr>
					<th class="wtb-links__store"><?php esc_html_e( 'Store', 'where-to-buy' ); ?></th>
					<th><?php esc_html_e( 'Link', 'where-to-buy' ); ?></th>
					<th class="wtb-links__status"><?php esc_html_e( 'On the site', 'where-to-buy' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php
			foreach ( $stores as $store ) :
				$id    = (string) $store['id'];
				$entry = $links[ $id ] ?? array( 'url' => '', 'alc' => 0 );
				$field = 'wtb_links[' . $id . ']';
				$img   = Render::storeImageUrl( $store, 'thumbnail' );
				$state = self::status( $store, $entry, $post->ID );
				?>
				<tr class="<?php echo empty( $store['enabled'] ) ? 'wtb-links--off' : ''; ?>">
					<td class="wtb-links__store">
						<?php if ( '' !== $img ) : ?>
							<img src="<?php echo esc_url( $img ); ?>" alt="" />
						<?php endif; ?>
						<strong><?php echo esc_html( (string) $store['name'] ); ?></strong>
					</td>
					<td>
						<input type="url" class="large-text" name="<?php echo esc_attr( $field ); ?>[url]" value="<?php echo esc_attr( $entry['url'] ); ?>" placeholder="https://" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: store */ __( '%s link', 'where-to-buy' ), (string) $store['name'] ) ); ?>" />
						<?php if ( $cloaked || $entry['alc'] > 0 ) : ?>
							<label class="wtb-links__cloaked">
								<?php esc_html_e( 'or use cloaked link', 'where-to-buy' ); ?>
								<select name="<?php echo esc_attr( $field ); ?>[alc]">
									<option value="0"><?php esc_html_e( '— none —', 'where-to-buy' ); ?></option>
									<?php if ( $entry['alc'] > 0 && ! isset( $cloaked[ $entry['alc'] ] ) ) : ?>
										<option value="<?php echo esc_attr( (string) $entry['alc'] ); ?>" selected><?php echo esc_html( sprintf( /* translators: %d: link ID */ __( 'Link #%d (unavailable)', 'where-to-buy' ), $entry['alc'] ) ); ?></option>
									<?php endif; ?>
									<?php foreach ( $cloaked as $link_id => $title ) : ?>
										<option value="<?php echo esc_attr( (string) $link_id ); ?>" <?php selected( $entry['alc'], $link_id ); ?>><?php echo esc_html( $title ); ?></option>
									<?php endforeach; ?>
								</select>
							</label>
						<?php endif; ?>
					</td>
					<td class="wtb-links__status">
						<span class="wtb-status wtb-status--<?php echo esc_attr( $state['class'] ); ?>"><?php echo esc_html( $state['label'] ); ?></span>
						<?php if ( '' !== $state['detail'] ) : ?>
							<span class="wtb-status__detail"><?php echo esc_html( $state['detail'] ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description"><?php esc_html_e( 'A cloaked link, if chosen, is used instead of the pasted link. Status updates when you save.', 'where-to-buy' ); ?></p>
		<?php
	}

	/**
	 * @param array<string, string|int>    $store Store.
	 * @param array{url: string, alc: int} $entry Saved link.
	 * @return array{class: string, label: string, detail: string}
	 */
	private static function status( array $store, array $entry, int $item_id ): array {
		if ( '' === $entry['url'] && 0 === $entry['alc'] ) {
			return array( 'class' => 'none', 'label' => '—', 'detail' => '' );
		}
		$check = Items::check( $store, $entry, $item_id );
		if ( ! $check['ok'] ) {
			return array( 'class' => 'bad', 'label' => __( 'Hidden', 'where-to-buy' ), 'detail' => $check['reason'] );
		}
		if ( empty( $store['enabled'] ) ) {
			return array( 'class' => 'off', 'label' => __( 'Hidden', 'where-to-buy' ), 'detail' => __( 'This store is switched off in settings.', 'where-to-buy' ) );
		}
		return array( 'class' => 'ok', 'label' => __( 'Shows', 'where-to-buy' ), 'detail' => '' );
	}

	public static function save( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized per field below.
		$raw   = isset( $_POST['wtb_links'] ) && is_array( $_POST['wtb_links'] ) ? wp_unslash( $_POST['wtb_links'] ) : array();
		$links = array();
		foreach ( Stores::all() as $store ) {
			$id  = (string) $store['id'];
			$row = isset( $raw[ $id ] ) && is_array( $raw[ $id ] ) ? $raw[ $id ] : array();
			$url = trim( (string) ( $row['url'] ?? '' ) );
			// Keep what was typed (even if invalid) so the editor can show why it's hidden;
			// esc_url_raw would silently drop it. Output is always escaped and checked.
			$url          = '' !== $url ? sanitize_url( $url, array( 'http', 'https' ) ) : '';
			$links[ $id ] = array(
				'url' => $url,
				'alc' => absint( $row['alc'] ?? 0 ),
			);
		}
		Items::saveLinks( $post_id, $links );
	}

	/** @param array<string, string> $cols */
	public static function columns( array $cols ): array {
		$new = array();
		foreach ( $cols as $key => $label ) {
			$new[ $key ] = $label;
			if ( 'title' === $key ) {
				$new['wtb_stores'] = __( 'Where to buy', 'where-to-buy' );
			}
		}
		return $new;
	}

	public static function column( string $col, int $post_id ): void {
		if ( 'wtb_stores' !== $col ) {
			return;
		}
		$names = array_map( static fn ( array $b ): string => (string) $b['store']['name'], Items::buttons( $post_id ) );
		echo $names ? esc_html( implode( ', ', $names ) ) : '<span class="wtb-status wtb-status--bad">' . esc_html__( 'No valid links', 'where-to-buy' ) . '</span>';
	}
}
