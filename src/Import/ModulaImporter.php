<?php

declare( strict_types=1 );

namespace BltGallery\Import;

use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Models\Gallery;

/**
 * Imports galleries and images from Modula (modula-best-grid-gallery).
 *
 * Modula stores each gallery as a `modula-gallery` custom post:
 *   - the post title / content become the gallery title / description;
 *   - the image list lives in the `modula-images` post meta as an array of
 *     items, each carrying a WordPress media-library attachment `id` plus
 *     per-image `title`, `caption`, `description`, and `alt` fields.
 *
 * Because Modula's images are ordinary attachments, this importer reads each
 * attachment's file via get_attached_file() and copies it into BltGallery's own
 * upload directory through ImageProcessor. The original Modula posts, meta, and
 * media-library files are never modified or removed — unlike the NextGEN
 * importer, there is no on-disk cleanup step because the files belong to the
 * WordPress media library, not to Modula.
 */
class ModulaImporter {

	/**
	 * Modula's custom post type for galleries.
	 */
	const POST_TYPE = 'modula-gallery';

	/**
	 * Post meta key holding the gallery's image list.
	 */
	const IMAGES_META = 'modula-images';

	// ------------------------------------------------------------------
	// Detection
	// ------------------------------------------------------------------

	/**
	 * Returns true when at least one Modula gallery post exists.
	 *
	 * Queries the posts table directly (rather than relying on the post type
	 * being registered) so migration still works even if the Modula plugin
	 * has since been deactivated.
	 */
	public function is_available(): bool {
		global $wpdb;

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status != 'trash'",
				self::POST_TYPE
			)
		);

		return (int) $count > 0;
	}

	// ------------------------------------------------------------------
	// Preview
	// ------------------------------------------------------------------

	/**
	 * Return a summary of all Modula galleries with image counts.
	 * Used to show the admin a preview before running the import.
	 *
	 * @return array[] Each element: {id, title, description, image_count}
	 */
	public function get_galleries(): array {
		$out = [];

		foreach ( $this->get_gallery_posts() as $post ) {
			$out[] = [
				'id'          => (int) $post->ID,
				'title'       => (string) ( $post->post_title ?: __( '(untitled gallery)', 'bltgallery' ) ),
				'description' => (string) ( $post->post_content ?: $post->post_excerpt ),
				'image_count' => count( $this->get_gallery_images( (int) $post->ID ) ),
			];
		}

		return $out;
	}

	// ------------------------------------------------------------------
	// Import
	// ------------------------------------------------------------------

	/**
	 * Import Modula galleries into BltGallery.
	 *
	 * @param int[]|null $gallery_ids Specific Modula post IDs to import,
	 *                                or null to import everything.
	 * @return array {
	 *   galleries_imported: int,
	 *   images_imported:    int,
	 *   images_skipped:     int,
	 *   errors:             string[],
	 * }
	 */
	public function import( ?array $gallery_ids = null ): array {
		$results = [
			'galleries_imported' => 0,
			'images_imported'    => 0,
			'images_skipped'     => 0,
			'errors'             => [],
		];

		if ( ! $this->is_available() ) {
			$results['errors'][] = __( 'No Modula galleries found.', 'bltgallery' );
			return $results;
		}

		$wanted = null;
		if ( ! empty( $gallery_ids ) ) {
			$wanted = array_map( 'intval', $gallery_ids );
		}

		$processor = new ImageProcessor();

		foreach ( $this->get_gallery_posts() as $post ) {
			if ( null !== $wanted && ! in_array( (int) $post->ID, $wanted, true ) ) {
				continue;
			}

			$gallery_result = $this->import_gallery( $post, $processor );

			$results['galleries_imported'] += $gallery_result['gallery_imported'];
			$results['images_imported']    += $gallery_result['images_imported'];
			$results['images_skipped']     += $gallery_result['images_skipped'];
			$results['errors']              = array_merge( $results['errors'], $gallery_result['errors'] );
		}

		return $results;
	}

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

	/**
	 * Import a single Modula gallery post and its images.
	 *
	 * @param \WP_Post       $post      A modula-gallery post.
	 * @param ImageProcessor $processor Shared processor instance.
	 */
	private function import_gallery( \WP_Post $post, ImageProcessor $processor ): array {
		$result = [
			'gallery_imported' => 0,
			'images_imported'  => 0,
			'images_skipped'   => 0,
			'errors'           => [],
		];

		// Create the BltGallery gallery record.
		$gallery               = new Gallery();
		$gallery->title        = sanitize_text_field( $post->post_title ?: __( 'Untitled Modula Gallery', 'bltgallery' ) );
		$gallery->slug         = $this->unique_slug( sanitize_title( $post->post_name ?: $post->post_title ) . '-from-modula' );
		$gallery->description  = sanitize_textarea_field( $post->post_content ?: $post->post_excerpt );
		$gallery->display_type = 'masonry';
		$gallery->author_id    = get_current_user_id();
		$gallery               = GalleryRepository::save( $gallery );

		$result['gallery_imported'] = 1;

		$images = $this->get_gallery_images( (int) $post->ID );
		if ( ! $images ) {
			return $result;
		}

		$sort_order = 0;

		foreach ( $images as $item ) {
			$attachment_id = (int) ( $item['id'] ?? 0 );

			if ( $attachment_id <= 0 ) {
				$result['images_skipped']++;
				$result['errors'][] = sprintf(
					/* translators: %s: gallery title */
					__( 'Skipped an image with no attachment ID (gallery: %s).', 'bltgallery' ),
					$gallery->title
				);
				continue;
			}

			$file_path = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$result['images_skipped']++;
				$result['errors'][] = sprintf(
					/* translators: 1: attachment ID, 2: gallery title */
					__( 'File not found for attachment #%1$d (gallery: %2$s).', 'bltgallery' ),
					$attachment_id,
					$gallery->title
				);
				continue;
			}

			try {
				// process_upload() copies the file – Modula's originals are untouched.
				$image              = $processor->process_upload( $file_path, $gallery );
				$image->alt_text    = sanitize_text_field( $item['alt'] ?? '' );
				$image->caption     = sanitize_textarea_field( $item['caption'] ?? '' );
				$image->description = sanitize_textarea_field( $item['description'] ?? '' );
				$image->sort_order  = $sort_order++;

				$title = sanitize_text_field( $item['title'] ?? '' );
				if ( '' !== $title ) {
					$image->meta['title'] = $title;
				}

				ImageRepository::save( $image );
				$result['images_imported']++;
			} catch ( \Throwable $e ) {
				$result['images_skipped']++;
				$result['errors'][] = sprintf(
					/* translators: 1: attachment ID, 2: error message */
					__( 'Failed to import attachment #%1$d: %2$s', 'bltgallery' ),
					$attachment_id,
					$e->getMessage()
				);
			}
		}

		return $result;
	}

	/**
	 * Fetch every Modula gallery post, oldest first.
	 *
	 * @return \WP_Post[]
	 */
	private function get_gallery_posts(): array {
		if ( ! $this->is_available() ) {
			return [];
		}

		return get_posts(
			[
				'post_type'        => self::POST_TYPE,
				'post_status'      => [ 'publish', 'draft', 'pending', 'private', 'future' ],
				'numberposts'      => -1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'suppress_filters' => true,
			]
		);
	}

	/**
	 * Read and normalise a Modula gallery's image list.
	 *
	 * @return array[] Each item is an associative array as stored by Modula
	 *                 (id, title, caption, description, alt, …).
	 */
	private function get_gallery_images( int $post_id ): array {
		$images = get_post_meta( $post_id, self::IMAGES_META, true );

		if ( ! is_array( $images ) ) {
			return [];
		}

		// Keep only well-formed items that reference an attachment.
		return array_values(
			array_filter(
				$images,
				static fn( $item ) => is_array( $item ) && ! empty( $item['id'] )
			)
		);
	}

	/**
	 * Generate a slug that does not already exist in BltGallery.
	 */
	private function unique_slug( string $base ): string {
		$base  = '' !== $base ? $base : 'modula-gallery';
		$slug  = $base;
		$count = 1;
		while ( GalleryRepository::find_by_slug( $slug ) ) {
			$slug = $base . '-' . $count++;
		}
		return $slug;
	}
}
