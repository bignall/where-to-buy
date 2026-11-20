<?php
/**
 * "Move or back up" on General Settings: download everything as one file, or import such a
 * file (see Transfer).
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy\Admin;

use WhereToBuy\Transfer;

final class TransferPage {

	private const RESULT = 'wtb_import_result_';

	public static function init(): void {
		add_action( 'admin_post_wtb_export', array( self::class, 'export' ) );
		add_action( 'admin_post_wtb_import', array( self::class, 'import' ) );
	}

	public static function export(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'where-to-buy' ), 403 );
		}
		check_admin_referer( 'wtb_export' );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$name = 'where-to-buy-' . sanitize_file_name( $host ) . '-' . wp_date( 'Y-m-d' ) . '.json';
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $name . '"' );
		echo wp_json_encode( Transfer::export(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a JSON download.
		exit;
	}

	public static function import(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'where-to-buy' ), 403 );
		}
		check_admin_referer( 'wtb_import' );
		$file   = $_FILES['wtb_file'] ?? null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- checked below; the file is read, not moved.
		$result = array(
			'ok'    => false,
			'error' => __( 'Choose the export file to import.', 'where-to-buy' ),
		);
		if ( is_array( $file ) && UPLOAD_ERR_OK === (int) ( $file['error'] ?? UPLOAD_ERR_NO_FILE ) && is_uploaded_file( (string) $file['tmp_name'] ) ) {
			$data   = json_decode( (string) file_get_contents( (string) $file['tmp_name'] ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the uploaded file.
			$result = is_array( $data )
				? Transfer::import( $data, ! empty( $_POST['wtb_settings'] ) )
				: array(
					'ok'    => false,
					'error' => __( 'That file couldn\'t be read. Is it the .json file Where to Buy exported?', 'where-to-buy' ),
				);
		} elseif ( is_array( $file ) && in_array( (int) ( $file['error'] ?? 0 ), array( UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ), true ) ) {
			$result['error'] = sprintf(
				/* translators: %s: size limit. */
				__( 'The file is bigger than this site accepts (%s). Ask your host to raise the upload limit, or export without the largest images.', 'where-to-buy' ),
				size_format( wp_max_upload_size() )
			);
		}
		set_transient( self::RESULT . get_current_user_id(), $result, 10 * MINUTE_IN_SECONDS );
		wp_safe_redirect( SettingsPage::url() . '#wtb-transfer' );
		exit;
	}

	/** The section under the settings form. */
	public static function render(): void {
		$result = get_transient( self::RESULT . get_current_user_id() );
		if ( is_array( $result ) ) {
			delete_transient( self::RESULT . get_current_user_id() );
		}
		?>
		<hr />
		<h2 id="wtb-transfer"><?php esc_html_e( 'Move or back up', 'where-to-buy' ); ?></h2>
		<?php if ( is_array( $result ) ) : ?>
			<div class="notice <?php echo $result['ok'] ? 'notice-success' : 'notice-error'; ?> inline"><p>
				<?php
				if ( $result['ok'] ) {
					echo esc_html(
						sprintf(
							/* translators: 1: items, 2: stores, 3: categories, 4: images. */
							__( 'Imported %1$s items, %2$s new stores, %3$s categories and %4$s images.', 'where-to-buy' ),
							number_format_i18n( (int) $result['items'] ),
							number_format_i18n( (int) $result['stores'] ),
							number_format_i18n( (int) $result['categories'] ),
							number_format_i18n( (int) $result['images'] )
						)
					);
					if ( (int) $result['skipped'] > 0 ) {
						echo ' ' . esc_html(
							sprintf(
								/* translators: %s: number of items. */
								_n( '%s item was already here, so it was skipped.', '%s items were already here, so they were skipped.', (int) $result['skipped'], 'where-to-buy' ),
								number_format_i18n( (int) $result['skipped'] )
							)
						);
					}
				} else {
					echo esc_html( (string) $result['error'] );
				}
				?>
			</p>
			<?php if ( ! empty( $result['warnings'] ) ) : ?>
				<ul style="list-style:disc;margin-left:2em">
					<?php foreach ( (array) $result['warnings'] as $warning ) : ?>
						<li><?php echo esc_html( (string) $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			</div>
		<?php endif; ?>
		<p><?php esc_html_e( 'Moving Where to Buy to another site? Export here, then import the file on the new site. The file has your stores (affiliate tags, disclosures and button images), these settings, your categories and every item with its links and images. It also makes a good backup.', 'where-to-buy' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=wtb_export' ), 'wtb_export' ) ); ?>"><?php esc_html_e( 'Export everything', 'where-to-buy' ); ?></a></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<input type="hidden" name="action" value="wtb_import" />
			<?php wp_nonce_field( 'wtb_import' ); ?>
			<p>
				<label for="wtb_file"><strong><?php esc_html_e( 'Import a file', 'where-to-buy' ); ?></strong></label><br />
				<input type="file" id="wtb_file" name="wtb_file" accept=".json,application/json" />
			</p>
			<p><label><input type="checkbox" name="wtb_settings" value="1" checked /> <?php esc_html_e( 'Also use its settings (headings, disclosure texts and switches). This site\'s web address stays as it is.', 'where-to-buy' ); ?></label></p>
			<p class="description">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %s: size limit. */
						__( 'Stores are matched by name, so nothing is doubled up; items already here (same address) are skipped. This site accepts files up to %s.', 'where-to-buy' ),
						size_format( wp_max_upload_size() )
					)
				);
				?>
			</p>
			<?php submit_button( __( 'Import', 'where-to-buy' ), 'secondary', 'submit', false ); ?>
		</form>
		<?php
	}
}
