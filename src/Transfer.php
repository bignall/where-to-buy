<?php
/**
 * Move Where to Buy to another site, or keep a backup: export everything to one file, and import
 * that file on the other site.
 *
 * The file holds the stores (with their affiliate tags, disclosures and button images), the
 * settings, the categories and every item (text, status, order, categories, store links,
 * featured image, and images inside its description). Images travel inside the file, so the
 * export works even when the old site isn't public.
 *
 * Importing adds what's missing: stores are matched by id or name, items whose address already
 * exists are skipped, and the web address (slug) of the site you import into is kept. Cloaked
 * links (Affiliate Link Cloaker) are matched by their /go/ name on the new site, or replaced by
 * the address they lead to.
 *
 * @package WhereToBuy
 */

declare(strict_types=1);

namespace WhereToBuy;

final class Transfer {

	public const FORMAT = 'where-to-buy-export';
	public const VERSION = 1;

	/** Images larger than this aren't embedded (they're listed as skipped instead). */
	private const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

	/** @var array<string, array<string, mixed>> Media being exported, by key. */
	private static array $media = array();
	/** @var array<string, string> Path => media key, so each file goes in once. */
	private static array $mediaKeys = array();
	/** @var list<string> */
	private static array $warnings = array();

	/**
	 * Everything, as an array ready for json_encode.
	 *
	 * @return array<string, mixed>
	 */
	public static function export(): array {
		self::$media     = array();
		self::$mediaKeys = array();
		self::$warnings  = array();

		$settings = Settings::all();
		$page     = (int) $settings['disclosure_page'];
		unset( $settings['disclosure_page'] );

		$stores = array();
		foreach ( Stores::all() as $store ) {
			$image = self::embedAttachment( (int) $store['image_id'] );
			unset( $store['image_id'] );
			$store['image'] = $image;
			$stores[]       = $store;
		}

		$categories = array();
		$terms      = get_terms(
			array(
				'taxonomy'   => Items::TAXONOMY,
				'hide_empty' => false,
			)
		);
		foreach ( is_array( $terms ) ? $terms : array() as $term ) {
			$parent       = $term->parent ? get_term( $term->parent, Items::TAXONOMY ) : null;
			$categories[] = array(
				'slug'        => $term->slug,
				'name'        => $term->name,
				'description' => $term->description,
				'parent'      => $parent instanceof \WP_Term ? $parent->slug : '',
			);
		}

		$items = array();
		$posts = get_posts(
			array(
				'post_type'        => Items::POST_TYPE,
				'post_status'      => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => false,
			)
		);
		foreach ( $posts as $post ) {
			$links = array();
			foreach ( Items::links( $post->ID ) as $store_id => $entry ) {
				$link = array( 'url' => $entry['url'] );
				if ( $entry['alc'] > 0 ) {
					$link['cloaked'] = array(
						'slug'   => (string) get_post_field( 'post_name', $entry['alc'] ),
						'target' => Cloaker::active() ? Cloaker::resolve( $entry['alc'] )['target'] : '',
					);
				}
				$links[ $store_id ] = $link;
			}
			$items[] = array(
				'title'      => $post->post_title,
				'slug'       => $post->post_name,
				'status'     => $post->post_status,
				'date'       => $post->post_date,
				'menu_order' => (int) $post->menu_order,
				'content'    => self::embedContentImages( $post->post_content ),
				'excerpt'    => $post->post_excerpt,
				'categories' => wp_get_post_terms( $post->ID, Items::TAXONOMY, array( 'fields' => 'slugs' ) ),
				'links'      => $links,
				'image'      => self::embedAttachment( (int) get_post_thumbnail_id( $post ) ),
			);
		}

		return array(
			'format'          => self::FORMAT,
			'version'         => self::VERSION,
			'exported_at'     => gmdate( 'c' ),
			'site'            => home_url( '/' ),
			'slug'            => Items::slug(),
			'settings'        => $settings,
			'disclosure_page' => $page > 0 ? (string) get_the_title( $page ) : '',
			'stores'          => $stores,
			'default_image'   => self::embedAttachment( Stores::defaultImageId() ),
			'categories'      => $categories,
			'items'           => $items,
			'media'           => self::$media,
			'warnings'        => self::$warnings,
		);
	}

	/** A media key for an attachment, with the file added to the export; '' for none. */
	private static function embedAttachment( int $attachment_id ): string {
		if ( $attachment_id <= 0 ) {
			return '';
		}
		$path = (string) get_attached_file( $attachment_id );
		return self::embedFile( $path, (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ), (string) get_the_title( $attachment_id ) );
	}

	private static function embedFile( string $path, string $alt = '', string $title = '' ): string {
		if ( '' === $path || ! is_readable( $path ) ) {
			return '';
		}
		if ( isset( self::$mediaKeys[ $path ] ) ) {
			return self::$mediaKeys[ $path ];
		}
		$size = (int) filesize( $path );
		if ( $size > self::MAX_IMAGE_BYTES ) {
			/* translators: %s: file name. */
			self::$warnings[] = sprintf( __( '%s is over 8 MB, so it was left out. Add it again on the new site.', 'where-to-buy' ), wp_basename( $path ) );
			return '';
		}
		$type = wp_check_filetype( $path );
		$key  = 'm' . ( count( self::$media ) + 1 );
		self::$media[ $key ] = array(
			'name'  => wp_basename( $path ),
			'mime'  => (string) $type['type'],
			'alt'   => $alt,
			'title' => $title,
			'data'  => base64_encode( (string) file_get_contents( $path ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- the image travels inside the export file.
		);
		self::$mediaKeys[ $path ] = $key;
		return $key;
	}

	/** Images from this site's uploads inside a description: embedded, and marked {{wtb-media:mN}}. */
	private static function embedContentImages( string $content ): string {
		$uploads = wp_upload_dir( null, false );
		if ( ! empty( $uploads['error'] ) || '' === $content ) {
			return $content;
		}
		$bases = array_unique( array( $uploads['baseurl'], set_url_scheme( $uploads['baseurl'], 'http' ), set_url_scheme( $uploads['baseurl'], 'https' ) ) );
		foreach ( $bases as $base ) {
			$content = (string) preg_replace_callback(
				'#' . preg_quote( $base, '#' ) . '/([^"\'\s<>?]+\.(?:jpe?g|png|gif|webp|avif|svg))#i',
				static function ( array $m ) use ( $uploads ): string {
					$key = self::embedFile( $uploads['basedir'] . '/' . rawurldecode( $m[1] ) );
					return '' !== $key ? '{{wtb-media:' . $key . '}}' : $m[0];
				},
				$content
			);
		}
		return $content;
	}

	/**
	 * Import an export file's data.
	 *
	 * @param array<string, mixed> $data     Decoded export.
	 * @param bool                 $settings Also use the exported settings (texts and switches).
	 * @return array{ok: bool, stores: int, categories: int, items: int, skipped: int, images: int, warnings: list<string>, error: string}
	 */
	public static function import( array $data, bool $settings = true ): array {
		$report = array(
			'ok'         => false,
			'stores'     => 0,
			'categories' => 0,
			'items'      => 0,
			'skipped'    => 0,
			'images'     => 0,
			'warnings'   => array(),
			'error'      => '',
		);
		if ( self::FORMAT !== ( $data['format'] ?? '' ) || (int) ( $data['version'] ?? 0 ) < 1 ) {
			$report['error'] = __( 'That isn\'t a Where to Buy export file.', 'where-to-buy' );
			return $report;
		}
		if ( (int) $data['version'] > self::VERSION ) {
			$report['error'] = __( 'That file was made by a newer version of Where to Buy. Update the plugin on this site first.', 'where-to-buy' );
			return $report;
		}
		if ( function_exists( 'set_time_limit' ) ) {
			set_time_limit( 300 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged -- large catalogues.
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$media    = is_array( $data['media'] ?? null ) ? $data['media'] : array();
		$imported = array();
		$attach   = static function ( string $key, int $parent = 0 ) use ( $media, &$imported, &$report ): int {
			if ( '' === $key || ! isset( $media[ $key ] ) || ! is_array( $media[ $key ] ) ) {
				return 0;
			}
			if ( isset( $imported[ $key ] ) ) {
				return $imported[ $key ];
			}
			$id                = self::sideload( $media[ $key ], $parent );
			$imported[ $key ] = $id;
			if ( $id > 0 ) {
				++$report['images'];
			} else {
				/* translators: %s: file name. */
				$report['warnings'][] = sprintf( __( 'Couldn\'t add the image %s.', 'where-to-buy' ), (string) ( $media[ $key ]['name'] ?? $key ) );
			}
			return $id;
		};

		// Settings: the texts and switches, never this site's web address.
		if ( $settings && is_array( $data['settings'] ?? null ) ) {
			$new         = array_merge( Settings::all(), array_intersect_key( $data['settings'], Settings::defaults() ) );
			$new['slug'] = Settings::get( 'slug' );
			update_option( Settings::OPTION, Settings::sanitize( $new ) );
			if ( '' !== (string) ( $data['disclosure_page'] ?? '' ) && ! Settings::get( 'disclosure_page' ) ) {
				/* translators: %s: page title. */
				$report['warnings'][] = sprintf( __( 'Choose the disclosure page again under General Settings (it was "%s" on the old site).', 'where-to-buy' ), (string) $data['disclosure_page'] );
			}
		}

		// Stores: matched by id, then by name; new ones are added at the end.
		$stores = Stores::all();
		$map    = array();
		foreach ( is_array( $data['stores'] ?? null ) ? $data['stores'] : array() as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['name'] ?? '' ) ) {
				continue;
			}
			$found = null;
			foreach ( $stores as $i => $store ) {
				if ( (string) $store['id'] === (string) ( $row['id'] ?? '' ) || 0 === strcasecmp( (string) $store['name'], (string) $row['name'] ) ) {
					$found = $i;
					break;
				}
			}
			if ( null === $found ) {
				$row['image_id'] = $attach( (string) ( $row['image'] ?? '' ) );
				unset( $row['image'] );
				$stores[] = array_merge( Stores::blank(), $row );
				$found    = count( $stores ) - 1;
				++$report['stores'];
			} else {
				// Keep this site's store, but fill in what it's missing.
				foreach ( array( 'tag_param', 'tag_value', 'note', 'domains' ) as $field ) {
					if ( '' === (string) $stores[ $found ][ $field ] && '' !== (string) ( $row[ $field ] ?? '' ) ) {
						$stores[ $found ][ $field ] = $row[ $field ];
					}
				}
				if ( ! (int) $stores[ $found ]['image_id'] && '' !== (string) ( $row['image'] ?? '' ) ) {
					$stores[ $found ]['image_id'] = $attach( (string) $row['image'] );
				}
			}
			$map[ (string) ( $row['id'] ?? '' ) ] = $found;
		}
		$stores = Stores::sanitize( $stores );
		update_option( Stores::OPTION, $stores );
		foreach ( $map as $old => $index ) {
			$map[ $old ] = (string) $stores[ $index ]['id'];
		}
		if ( ! Stores::defaultImageId() && '' !== (string) ( $data['default_image'] ?? '' ) ) {
			update_option( Stores::DEFAULT_IMAGE_OPTION, $attach( (string) $data['default_image'] ) );
		}

		// Categories, parents first.
		$cats = is_array( $data['categories'] ?? null ) ? $data['categories'] : array();
		for ( $pass = 0; $pass < 5 && $cats; $pass++ ) {
			$left = array();
			foreach ( $cats as $cat ) {
				if ( term_exists( (string) $cat['slug'], Items::TAXONOMY ) ) {
					continue;
				}
				$parent = 0;
				if ( '' !== (string) $cat['parent'] ) {
					$p = get_term_by( 'slug', (string) $cat['parent'], Items::TAXONOMY );
					if ( ! $p ) {
						$left[] = $cat;
						continue;
					}
					$parent = (int) $p->term_id;
				}
				$made = wp_insert_term(
					(string) $cat['name'],
					Items::TAXONOMY,
					array(
						'slug'        => (string) $cat['slug'],
						'description' => (string) $cat['description'],
						'parent'      => $parent,
					)
				);
				if ( ! is_wp_error( $made ) ) {
					++$report['categories'];
				}
			}
			$cats = $left;
		}

		// Items.
		foreach ( is_array( $data['items'] ?? null ) ? $data['items'] : array() as $row ) {
			if ( ! is_array( $row ) || '' === (string) ( $row['title'] ?? '' ) ) {
				continue;
			}
			$slug = sanitize_title( (string) ( $row['slug'] ?? '' ) );
			if ( '' !== $slug && get_page_by_path( $slug, OBJECT, Items::POST_TYPE ) ) {
				++$report['skipped'];
				continue;
			}
			$status = in_array( $row['status'] ?? '', array( 'publish', 'draft', 'pending', 'private', 'future' ), true ) ? (string) $row['status'] : 'draft';
			$id     = wp_insert_post(
				array(
					'post_type'    => Items::POST_TYPE,
					'post_title'   => (string) $row['title'],
					'post_name'    => $slug,
					'post_status'  => $status,
					'post_date'    => (string) ( $row['date'] ?? '' ),
					'menu_order'   => (int) ( $row['menu_order'] ?? 0 ),
					'post_content' => '',
					'post_excerpt' => (string) ( $row['excerpt'] ?? '' ),
				),
				true
			);
			if ( is_wp_error( $id ) ) {
				/* translators: 1: item title, 2: error. */
				$report['warnings'][] = sprintf( __( 'Couldn\'t add "%1$s": %2$s', 'where-to-buy' ), (string) $row['title'], $id->get_error_message() );
				continue;
			}
			// The description, with its images added here first.
			$content = (string) preg_replace_callback(
				'/\{\{wtb-media:(m\d+)\}\}/',
				static function ( array $m ) use ( $attach, $id ): string {
					$aid = $attach( $m[1], $id );
					return $aid > 0 ? (string) wp_get_attachment_url( $aid ) : '';
				},
				(string) ( $row['content'] ?? '' )
			);
			wp_update_post(
				array(
					'ID'           => $id,
					'post_content' => $content,
				)
			);
			if ( ! empty( $row['categories'] ) && is_array( $row['categories'] ) ) {
				wp_set_object_terms( $id, array_map( 'strval', $row['categories'] ), Items::TAXONOMY );
			}
			$thumb = $attach( (string) ( $row['image'] ?? '' ), $id );
			if ( $thumb > 0 ) {
				set_post_thumbnail( $id, $thumb );
			}
			$links = array();
			foreach ( is_array( $row['links'] ?? null ) ? $row['links'] : array() as $old_store => $link ) {
				$store = $map[ (string) $old_store ] ?? ( Stores::find( (string) $old_store ) ? (string) $old_store : '' );
				if ( '' === $store || ! is_array( $link ) ) {
					continue;
				}
				$entry = array(
					'url' => esc_url_raw( (string) ( $link['url'] ?? '' ) ),
					'alc' => 0,
				);
				if ( is_array( $link['cloaked'] ?? null ) ) {
					$alc = self::cloakedLinkHere( (string) $link['cloaked']['slug'] );
					if ( $alc > 0 ) {
						$entry['alc'] = $alc;
					} elseif ( '' === $entry['url'] && '' !== (string) $link['cloaked']['target'] ) {
						$entry['url'] = esc_url_raw( (string) $link['cloaked']['target'] );
						/* translators: 1: cloaked link name, 2: item title. */
						$report['warnings'][] = sprintf( __( 'No cloaked link /%1$s/ here, so "%2$s" links straight to the store instead.', 'where-to-buy' ), (string) $link['cloaked']['slug'], (string) $row['title'] );
					}
				}
				$links[ $store ] = $entry;
			}
			Items::saveLinks( $id, $links );
			++$report['items'];
		}

		$report['ok'] = true;
		App::plugin()->requestRewriteFlush();
		return $report;
	}

	/** An Affiliate Link Cloaker link with this name on this site, or 0. */
	private static function cloakedLinkHere( string $slug ): int {
		if ( '' === $slug || ! Cloaker::active() ) {
			return 0;
		}
		$cls  = 'AffLinkCloaker\\Links';
		$post = get_page_by_path( $slug, OBJECT, $cls::POST_TYPE );
		return $post instanceof \WP_Post ? (int) $post->ID : 0;
	}

	/**
	 * Add one embedded file to the media library.
	 *
	 * @param array<string, mixed> $file Media entry.
	 */
	private static function sideload( array $file, int $parent ): int {
		$name = sanitize_file_name( (string) ( $file['name'] ?? 'image' ) );
		$data = base64_decode( (string) ( $file['data'] ?? '' ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		if ( false === $data || '' === $data ) {
			return 0;
		}
		$tmp = wp_tempnam( $name );
		if ( ! $tmp || false === file_put_contents( $tmp, $data ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return 0;
		}
		$id = media_handle_sideload(
			array(
				'name'     => $name,
				'tmp_name' => $tmp,
			),
			$parent,
			'' !== (string) ( $file['title'] ?? '' ) ? (string) $file['title'] : null
		);
		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}
		if ( '' !== (string) ( $file['alt'] ?? '' ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( (string) $file['alt'] ) );
		}
		return (int) $id;
	}
}
