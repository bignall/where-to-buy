<?php
/**
 * Where to Buy → General Settings (web address, buy box) and Where to Buy → Store Settings
 * (the stores, sortable, with images, plus the default button image).
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Admin;

use WhereToBuy\App;
use WhereToBuy\Items;
use WhereToBuy\Settings;
use WhereToBuy\Stores;
use WhereToBuy\Vendor\Bignall\WPFoundation\Plugin;

final class SettingsPage {

	public const SLUG        = 'wtb-settings';
	public const STORES_SLUG = 'wtb-stores';

	public static function init( Plugin $plugin ): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_filter( 'plugin_action_links_' . $plugin->basename(), array( self::class, 'pluginLinks' ) );
	}

	/** URL of the General Settings page. */
	public static function url(): string {
		return admin_url( 'edit.php?post_type=' . Items::POST_TYPE . '&page=' . self::SLUG );
	}

	/** URL of the Store Settings page. */
	public static function storesUrl(): string {
		return admin_url( 'edit.php?post_type=' . Items::POST_TYPE . '&page=' . self::STORES_SLUG );
	}

	public static function menu(): void {
		$parent = 'edit.php?post_type=' . Items::POST_TYPE;
		add_submenu_page( $parent, __( 'Where to Buy: General Settings', 'where-to-buy' ), __( 'General Settings', 'where-to-buy' ), 'manage_options', self::SLUG, array( self::class, 'render' ) );
		add_submenu_page( $parent, __( 'Where to Buy: Store Settings', 'where-to-buy' ), __( 'Store Settings', 'where-to-buy' ), 'manage_options', self::STORES_SLUG, array( self::class, 'renderStores' ) );
	}

	/**
	 * @param array<string, string> $links Action links.
	 * @return array<string, string>
	 */
	public static function pluginLinks( array $links ): array {
		array_unshift( $links, '<a href="' . esc_url( self::url() ) . '">' . esc_html__( 'Settings', 'where-to-buy' ) . '</a>' );
		return $links;
	}

	public static function assets( string $hook ): void {
		$pages = array( Items::POST_TYPE . '_page_' . self::SLUG, Items::POST_TYPE . '_page_' . self::STORES_SLUG );
		if ( ! in_array( $hook, $pages, true ) ) {
			return;
		}
		wp_enqueue_media();
		wp_enqueue_style( 'wtb-admin', App::url( 'assets/admin.css' ), array(), App::version() );
		wp_enqueue_script( 'wtb-settings', App::url( 'assets/settings.js' ), array( 'jquery', 'jquery-ui-sortable' ), App::version(), true );
		wp_localize_script(
			'wtb-settings',
			'wtbSettings',
			array(
				'chooseImage' => __( 'Choose image', 'where-to-buy' ),
				'useImage'    => __( 'Use this image', 'where-to-buy' ),
				'confirmDrop' => __( 'Remove this store? Links to it on your items will stop showing.', 'where-to-buy' ),
				'mediaUrl'    => rest_url( 'wp/v2/media' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'catalog'     => array_map(
					static fn ( array $s ): array => array_intersect_key( $s, array_flip( array( 'id', 'name', 'domains', 'tag_param', 'note', 'logo' ) ) ),
					Stores::catalog()
				),
				'uploading'   => __( 'Uploading…', 'where-to-buy' ),
				'uploadFail'  => __( 'Couldn’t upload %s.', 'where-to-buy' ),
				/* translators: %s: list of store names */
				'logosAdded'  => __( 'Logos added for %s. Save changes to keep them.', 'where-to-buy' ),
				/* translators: %s: list of file names */
				'logosSkipped' => __( 'Not matched to a store, so not uploaded: %s. Name a file after its store (for example amazon.png), or use Upload on that store.', 'where-to-buy' ),
				/* translators: %d: number of files */
				'logosOthers' => __( '%d other versions weren’t needed (each store gets one logo; use Upload on a store to pick a different one).', 'where-to-buy' ),
				'logosNone'   => __( 'None of those files matched a store. Name a file after its store (for example amazon.png), or use Upload on that store.', 'where-to-buy' ),
				'added'       => __( 'added', 'where-to-buy' ),
			)
		);
	}

	/** Image picker: hidden id field, preview, choose and remove buttons. */
	private static function imageField( string $name, int $image_id, string $empty_text ): void {
		$src = $image_id ? wp_get_attachment_image_url( $image_id, 'medium' ) : '';
		?>
		<div class="wtb-image">
			<input type="hidden" class="wtb-image__id" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( (string) $image_id ); ?>" />
			<span class="wtb-image__preview">
				<?php if ( $src ) : ?>
					<img src="<?php echo esc_url( $src ); ?>" alt="" />
				<?php else : ?>
					<span class="wtb-image__empty"><?php echo esc_html( $empty_text ); ?></span>
				<?php endif; ?>
			</span>
			<button type="button" class="button wtb-image__upload"><?php esc_html_e( 'Upload', 'where-to-buy' ); ?></button>
			<button type="button" class="button wtb-image__choose" title="<?php esc_attr_e( 'Choose from the Media Library', 'where-to-buy' ); ?>"><?php esc_html_e( 'Choose', 'where-to-buy' ); ?></button>
			<input type="file" class="wtb-image__file" accept="image/png,image/jpeg,image/gif,image/webp" hidden />
			<button type="button" class="button-link wtb-image__remove" <?php echo $image_id ? '' : 'hidden'; ?>><?php esc_html_e( 'Remove', 'where-to-buy' ); ?></button>
			<span class="wtb-image__status" aria-live="polite"></span>
			<template class="wtb-image__empty-tpl"><span class="wtb-image__empty"><?php echo esc_html( $empty_text ); ?></span></template>
		</div>
		<?php
	}

	/** @param array<string, string|int> $store */
	private static function storeRow( string $index, array $store ): void {
		$n = Stores::OPTION . '[' . $index . ']';
		?>
		<tr class="wtb-store-row">
			<td class="wtb-store-row__handle" title="<?php esc_attr_e( 'Drag to reorder', 'where-to-buy' ); ?>"><span class="dashicons dashicons-menu" aria-hidden="true"></span></td>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $n ); ?>[id]" value="<?php echo esc_attr( (string) $store['id'] ); ?>" />
				<label class="wtb-field"><span><?php esc_html_e( 'Name', 'where-to-buy' ); ?></span>
					<input type="text" class="regular-text wtb-store-row__name" name="<?php echo esc_attr( $n ); ?>[name]" value="<?php echo esc_attr( (string) $store['name'] ); ?>" />
				</label>
				<label class="wtb-field"><span><?php esc_html_e( 'Domains', 'where-to-buy' ); ?></span>
					<input type="text" class="large-text" name="<?php echo esc_attr( $n ); ?>[domains]" value="<?php echo esc_attr( (string) $store['domains'] ); ?>" placeholder="example.com, exm.pl" />
				</label>
				<label class="wtb-field wtb-field--inline"><input type="checkbox" name="<?php echo esc_attr( $n ); ?>[enabled]" value="1" <?php checked( ! empty( $store['enabled'] ) ); ?> /> <?php esc_html_e( 'Show this store', 'where-to-buy' ); ?></label>
				<details class="wtb-store-row__more" <?php echo ( '' !== (string) $store['tag_value'] || '' !== (string) $store['note'] ) ? 'open' : ''; ?>>
					<summary><?php esc_html_e( 'Affiliate tag and disclosure', 'where-to-buy' ); ?></summary>
					<div class="wtb-store-row__tag">
						<label class="wtb-field"><span><?php esc_html_e( 'Tag parameter', 'where-to-buy' ); ?></span>
							<input type="text" class="wtb-field__short" name="<?php echo esc_attr( $n ); ?>[tag_param]" value="<?php echo esc_attr( (string) $store['tag_param'] ); ?>" placeholder="tag" />
						</label>
						<label class="wtb-field"><span><?php esc_html_e( 'Your tag', 'where-to-buy' ); ?></span>
							<input type="text" class="regular-text" name="<?php echo esc_attr( $n ); ?>[tag_value]" value="<?php echo esc_attr( (string) $store['tag_value'] ); ?>" placeholder="yourname-20" />
						</label>
					</div>
					<p class="description"><?php esc_html_e( 'Added to pasted links that don’t already have it. Not added to cloaked links.', 'where-to-buy' ); ?></p>
					<label class="wtb-field"><span><?php esc_html_e( 'Extra disclosure', 'where-to-buy' ); ?></span>
						<input type="text" class="large-text" name="<?php echo esc_attr( $n ); ?>[note]" value="<?php echo esc_attr( (string) $store['note'] ); ?>" />
					</label>
					<p class="description"><?php esc_html_e( 'Shown under the note whenever this store’s button shows. Amazon requires its own sentence.', 'where-to-buy' ); ?></p>
				</details>
			</td>
			<td class="wtb-store-row__image">
				<?php self::imageField( $n . '[image_id]', (int) $store['image_id'], __( 'Default image', 'where-to-buy' ) ); ?>
			</td>
			<td class="wtb-store-row__actions">
				<button type="button" class="button-link button-link-delete wtb-store-row__remove"><?php esc_html_e( 'Remove', 'where-to-buy' ); ?></button>
			</td>
		</tr>
		<?php
	}

	public static function render(): void {
		$s    = Settings::all();
		$name = Settings::OPTION;
		$flag = static function ( string $key, string $label ) use ( $s, $name ): void {
			printf(
				'<label><input type="checkbox" name="%s[%s]" value="1" %s /> %s</label><br>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( (int) $s[ $key ], 1, false ),
				esc_html( $label )
			);
		};
		?>
		<div class="wrap wtb-settings">
			<h1><?php esc_html_e( 'Where to Buy: General Settings', 'where-to-buy' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( Settings::GROUP ); ?>

				<h2><?php esc_html_e( 'Web address', 'where-to-buy' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="wtb_slug"><?php esc_html_e( 'URL slug', 'where-to-buy' ); ?></label></th>
						<td>
							<code><?php echo esc_html( trailingslashit( home_url() ) ); ?></code><input type="text" id="wtb_slug" class="regular-text wtb-slug" name="<?php echo esc_attr( $name ); ?>[slug]" value="<?php echo esc_attr( (string) $s['slug'] ); ?>" /><code>/</code>
							<p class="description">
								<?php
								$base = trailingslashit( home_url() ) . Items::slug();
								echo esc_html(
									sprintf(
										/* translators: 1: list URL, 2: example item URL, 3: category URL */
										__( 'List page: %1$s/ · Items: %2$s/your-item/ · Categories: %3$s/category/name/. Changing this breaks links you’ve already shared.', 'where-to-buy' ),
										$base,
										$base,
										$base
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th><label for="wtb_moved_to"><?php esc_html_e( 'Moved to', 'where-to-buy' ); ?></label></th>
						<td>
							<input type="url" id="wtb_moved_to" class="regular-text" placeholder="https://example.com/picks/" name="<?php echo esc_attr( $name ); ?>[moved_to]" value="<?php echo esc_attr( (string) $s['moved_to'] ); ?>" />
							<p class="description"><?php esc_html_e( 'Only after moving Where to Buy to another site (see Move or back up, below): its list page address there. Visitors to item, list and category pages here are then sent to the same item there. Leave empty otherwise.', 'where-to-buy' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'List page', 'where-to-buy' ); ?></h2>
				<p class="description"><?php echo wp_kses_post( __( 'Used by the list page templates. In your own templates: <code>[where_to_buy_intro]</code>, <code>[where_to_buy_disclosure]</code> and <code>[where_to_buy_back]</code>.', 'where-to-buy' ) ); ?></p>
				<table class="form-table">
					<tr>
						<th><label for="wtb_archive_title"><?php esc_html_e( 'Title', 'where-to-buy' ); ?></label></th>
						<td><input type="text" id="wtb_archive_title" class="regular-text" name="<?php echo esc_attr( $name ); ?>[archive_title]" value="<?php echo esc_attr( (string) $s['archive_title'] ); ?>" placeholder="<?php esc_attr_e( 'Where to Buy', 'where-to-buy' ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="wtb_archive_intro"><?php esc_html_e( 'Introduction', 'where-to-buy' ); ?></label></th>
						<td><textarea id="wtb_archive_intro" class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[archive_intro]"><?php echo esc_textarea( (string) $s['archive_intro'] ); ?></textarea></td>
					</tr>
					<tr>
						<th><label for="wtb_disclosure"><?php esc_html_e( 'Disclosure', 'where-to-buy' ); ?></label></th>
						<td>
							<textarea id="wtb_disclosure" class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[disclosure]"><?php echo esc_textarea( (string) $s['disclosure'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown once at the top of the list page, followed by the extra disclosure of each store that is switched on (such as Amazon’s).', 'where-to-buy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><label for="wtb_disclosure_page"><?php esc_html_e( 'Disclosure page', 'where-to-buy' ); ?></label></th>
						<td>
							<?php
							wp_dropdown_pages(
								array(
									'name'              => esc_attr( $name ) . '[disclosure_page]',
									'id'                => 'wtb_disclosure_page',
									'selected'          => (int) $s['disclosure_page'],
									'show_option_none'  => esc_html__( 'None', 'where-to-buy' ),
									'option_none_value' => '0',
								)
							);
							?>
							<p class="description"><?php esc_html_e( 'Adds a “Read the disclosure” link after the disclosure.', 'where-to-buy' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Buy box', 'where-to-buy' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><label for="wtb_heading"><?php esc_html_e( 'Heading', 'where-to-buy' ); ?></label></th>
						<td><input type="text" id="wtb_heading" class="regular-text" name="<?php echo esc_attr( $name ); ?>[heading]" value="<?php echo esc_attr( (string) $s['heading'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="wtb_note"><?php esc_html_e( 'Disclosure note', 'where-to-buy' ); ?></label></th>
						<td>
							<textarea id="wtb_note" class="large-text" rows="2" name="<?php echo esc_attr( $name ); ?>[note]"><?php echo esc_textarea( (string) $s['note'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Shown right under the store buttons, only when at least one button shows.', 'where-to-buy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Links', 'where-to-buy' ); ?></th>
						<td>
							<?php $flag( 'new_tab', __( 'Open store links in a new tab', 'where-to-buy' ) ); ?>
							<p class="description"><?php esc_html_e( 'Links always carry rel="sponsored nofollow", as Google asks for affiliate links.', 'where-to-buy' ); ?></p>
							<?php $flag( 'shorten_amazon', __( 'Shorten Amazon links', 'where-to-buy' ) ); ?>
							<p class="description"><?php esc_html_e( 'When an item is saved, Amazon product links are trimmed to amazon.com/dp/ASIN/?tag=yourtag, dropping the search tracking. The link’s own tag is kept, otherwise your Amazon tag from Store Settings is used. amzn.to and a.co links are followed to find the product.', 'where-to-buy' ); ?></p>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Item pages', 'where-to-buy' ); ?></th>
						<td>
							<?php $flag( 'auto_append', __( 'Add the buy box after the content on each item’s page', 'where-to-buy' ) ); ?>
							<p class="description"><?php echo wp_kses_post( __( 'Turn off if your template places it with the <code>[where_to_buy_buttons]</code> shortcode or the Where to Buy block.', 'where-to-buy' ) ); ?></p>
						</td>
					</tr>
					<?php if ( function_exists( 'todo_hub_add' ) ) : ?>
						<tr>
							<th><?php esc_html_e( 'To-do list', 'where-to-buy' ); ?></th>
							<td>
								<?php $flag( 'todo_tasks', __( 'Add tasks to To-do Hub when items need attention', 'where-to-buy' ) ); ?>
								<p class="description"><?php esc_html_e( 'A task for each published item whose store links don’t all show, and one for items waiting for review. They’re marked done when fixed. Checked when items are saved and once a day.', 'where-to-buy' ); ?></p>
							</td>
						</tr>
					<?php endif; ?>
					<tr>
						<th><?php esc_html_e( 'Uninstall', 'where-to-buy' ); ?></th>
						<td><?php $flag( 'remove_data', __( 'Delete all items, their categories and these settings when the plugin is deleted', 'where-to-buy' ) ); ?></td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<?php TransferPage::render(); ?>
		</div>
		<?php
	}

	public static function renderStores(): void {
		?>
		<div class="wrap wtb-settings">
			<h1><?php esc_html_e( 'Where to Buy: Store Settings', 'where-to-buy' ); ?></h1>
			<?php settings_errors(); ?>
			<form method="post" action="options.php">
				<?php settings_fields( Stores::GROUP ); ?>

								<p class="description"><?php esc_html_e( 'Drag to set the order buttons appear in. An item’s link only shows if it goes to one of the store’s domains (subdomains count). Leave Domains empty to accept any site.', 'where-to-buy' ); ?></p>
				<div class="wtb-logos">
					<p class="description"><?php
						printf(
							/* translators: %s: link to the store logos download */
							esc_html__( 'Need button images? %s for Amazon, Walmart, Best Buy, Target, Chewy and eBay, unzip it, then add them here. Pick as many logos as you like at once: each goes to the store it’s named after (amazon.png goes to Amazon).', 'where-to-buy' ),
							'<a href="https://github.com/bignall/where-to-buy/releases/latest/download/where-to-buy-store-logos.zip">' . esc_html__( 'Download the store logos', 'where-to-buy' ) . '</a>'
						);
					?></p>
					<p>
						<button type="button" class="button wtb-logos__upload"><?php esc_html_e( 'Add logos from your computer', 'where-to-buy' ); ?></button>
						<input type="file" class="wtb-logos__file" accept="image/png,image/jpeg,image/gif,image/webp" multiple hidden />
						<span class="wtb-logos__status" aria-live="polite"></span>
					</p>
				</div>
				<input type="hidden" name="<?php echo esc_attr( Stores::OPTION ); ?>[__keep]" value="" />
				<table class="widefat wtb-stores">
					<thead>
						<tr>
							<th class="wtb-store-row__handle"><span class="screen-reader-text"><?php esc_html_e( 'Order', 'where-to-buy' ); ?></span></th>
							<th><?php esc_html_e( 'Store', 'where-to-buy' ); ?></th>
							<th><?php esc_html_e( 'Button image', 'where-to-buy' ); ?></th>
							<th><span class="screen-reader-text"><?php esc_html_e( 'Actions', 'where-to-buy' ); ?></span></th>
						</tr>
					</thead>
					<tbody class="wtb-stores__rows">
						<?php
						foreach ( Stores::all() as $i => $store ) {
							self::storeRow( (string) $i, $store );
						}
						?>
					</tbody>
				</table>
				<p><button type="button" class="button wtb-stores__add" aria-expanded="false" aria-controls="wtb-store-picker"><?php esc_html_e( 'Add store', 'where-to-buy' ); ?></button></p>
				<div class="wtb-store-picker" id="wtb-store-picker" hidden>
					<p class="wtb-store-picker__title"><strong><?php esc_html_e( 'Which store?', 'where-to-buy' ); ?></strong> <?php esc_html_e( 'These come with their domains (and Amazon with its tag and disclosure) filled in.', 'where-to-buy' ); ?></p>
					<div class="wtb-store-picker__list">
						<?php foreach ( Stores::catalog() as $known ) : ?>
							<button type="button" class="button wtb-store-picker__store" data-store="<?php echo esc_attr( (string) $known['id'] ); ?>"><?php echo esc_html( (string) $known['name'] ); ?></button>
						<?php endforeach; ?>
						<button type="button" class="button button-primary wtb-store-picker__store" data-store=""><?php esc_html_e( 'Blank store (add your own)', 'where-to-buy' ); ?></button>
					</div>
					<p><button type="button" class="button-link wtb-store-picker__cancel"><?php esc_html_e( 'Cancel', 'where-to-buy' ); ?></button></p>
				</div>
				<template id="wtb-store-template"><?php self::storeRow( '__i__', Stores::blank() ); ?></template>

				<h2><?php esc_html_e( 'Default image', 'where-to-buy' ); ?></h2>
				<table class="form-table">
					<tr>
						<th><?php esc_html_e( 'Default image', 'where-to-buy' ); ?></th>
						<td>
							<?php self::imageField( Stores::DEFAULT_IMAGE_OPTION, Stores::defaultImageId(), __( 'None: store name as text', 'where-to-buy' ) ); ?>
							<p class="description"><?php esc_html_e( 'Used for stores without their own image, with the store name beside it. With no default image, those stores show as text buttons.', 'where-to-buy' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
