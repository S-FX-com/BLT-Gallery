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
 * attachment's file via get_attached_file() and copies it into BLT Gallery's own
 * upload directory through ImageProcessor. The original Modula posts, meta, and
 * media-library files are never modified or removed — unlike the NextGEN
 * importer, there is no on-disk cleanup step because the files belong to the
 * WordPress media library, not to Modula.
 */
class ModulaImporter implements SourceImporter {

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
	 * Import Modula galleries into BLT Gallery in a single pass.
	 *
	 * Kept for the legacy synchronous REST route and WP-CLI style callers;
	 * the admin UI drives ImportRunner instead so large collections can run
	 * in the background with progress reporting.
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
			$results['errors'][] = $this->unavailable_message();
			return $results;
		}

		$processor = new ImageProcessor();

		foreach ( $this->plan_galleries( $gallery_ids ) as $planned ) {
			try {
				$target = $this->create_target_gallery( $planned['source_id'] );
			} catch ( \Throwable $e ) {
				$results['errors'][] = $e->getMessage();
				continue;
			}

			$results['galleries_imported']++;

			$offset = 0;
			while ( $offset < $planned['total'] ) {
				$slice = $this->import_slice( $planned['source_id'], $target, $offset, 25, $processor );

				$results['images_imported'] += $slice['imported'];
				$results['images_skipped']  += $slice['skipped'];
				$results['errors']           = array_merge( $results['errors'], $slice['errors'] );

				if ( $slice['processed'] < 1 ) {
					break; // Defensive: never spin when a slice yields nothing.
				}
				$offset += $slice['processed'];
			}
		}

		return $results;
	}

	// ------------------------------------------------------------------
	// SourceImporter implementation
	// ------------------------------------------------------------------

	public function source_key(): string {
		return 'modula';
	}

	public function source_label(): string {
		return __( 'Modula', 'bltgallery' );
	}

	public function unavailable_message(): string {
		return __( 'No Modula galleries found.', 'bltgallery' );
	}

	public function id_key(): string {
		return 'id';
	}

	public function target_slug_base( int $source_id ): string {
		$post = get_post( $source_id );

		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			return '';
		}

		return $this->slug_base( $post );
	}

	/**
	 * Build the work queue: one entry per gallery with its image count.
	 *
	 * @param int[]|null $gallery_ids
	 * @return array<int, array{source_id:int,title:string,total:int}>
	 */
	public function plan_galleries( ?array $gallery_ids = null ): array {
		$wanted = null;
		if ( ! empty( $gallery_ids ) ) {
			$wanted = array_map( 'intval', $gallery_ids );
		}

		$plan = [];

		foreach ( $this->get_gallery_posts() as $post ) {
			$id = (int) $post->ID;

			if ( null !== $wanted && ! in_array( $id, $wanted, true ) ) {
				continue;
			}

			$plan[] = [
				'source_id' => $id,
				'title'     => (string) ( $post->post_title ?: __( '(untitled gallery)', 'bltgallery' ) ),
				'total'     => count( $this->get_gallery_images( $id ) ),
			];
		}

		return $plan;
	}

	/**
	 * Create the destination BLT Gallery gallery for one Modula gallery post.
	 *
	 * @throws \RuntimeException When the Modula post has vanished.
	 */
	public function create_target_gallery( int $source_id ): Gallery {
		$post = get_post( $source_id );

		if ( ! $post instanceof \WP_Post || self::POST_TYPE !== $post->post_type ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %d: Modula gallery post ID */
					__( 'Modula gallery #%d no longer exists.', 'bltgallery' ),
					$source_id
				)
			);
		}

		$gallery               = new Gallery();
		$gallery->title        = sanitize_text_field( $post->post_title ?: __( 'Untitled Modula Gallery', 'bltgallery' ) );
		$gallery->slug         = $this->unique_slug( $this->slug_base( $post ) );
		$gallery->description  = sanitize_textarea_field( $post->post_content ?: $post->post_excerpt );
		$gallery->display_type = 'masonry';
		$gallery->author_id    = get_current_user_id();

		return GalleryRepository::save( $gallery );
	}

	/**
	 * Copy one slice of a Modula gallery's images into $target.
	 *
	 * The slice is taken from the same normalised meta list plan_galleries()
	 * counted, so consecutive slices tile the gallery exactly once.
	 *
	 * @return array{processed:int,imported:int,skipped:int,errors:string[]}
	 */
	public function import_slice( int $source_id, Gallery $target, int $offset, int $limit, ImageProcessor $processor ): array {
		$result = [
			'processed' => 0,
			'imported'  => 0,
			'skipped'   => 0,
			'errors'    => [],
		];

		$images = array_slice( $this->get_gallery_images( $source_id ), max( 0, $offset ), max( 1, $limit ) );

		if ( ! $images ) {
			return $result;
		}

		foreach ( $images as $index => $item ) {
			$result['processed']++;

			$attachment_id = (int) ( $item['id'] ?? 0 );

			if ( $attachment_id <= 0 ) {
				$result['skipped']++;
				$result['errors'][] = sprintf(
					/* translators: %s: gallery title */
					__( 'Skipped an image with no attachment ID (gallery: %s).', 'bltgallery' ),
					$target->title
				);
				continue;
			}

			$file_path = get_attached_file( $attachment_id );

			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$result['skipped']++;
				$result['errors'][] = sprintf(
					/* translators: 1: attachment ID, 2: gallery title */
					__( 'File not found for attachment #%1$d (gallery: %2$s).', 'bltgallery' ),
					$attachment_id,
					$target->title
				);
				continue;
			}

			try {
				// process_upload() copies the file – Modula's originals are untouched.
				$image              = $processor->process_upload( $file_path, $target );
				$image->alt_text    = sanitize_text_field( $item['alt'] ?? '' );
				$image->caption     = sanitize_textarea_field( $item['caption'] ?? '' );
				$image->description = sanitize_textarea_field( $item['description'] ?? '' );
				$image->sort_order  = $offset + $index;

				$title = sanitize_text_field( $item['title'] ?? '' );
				if ( '' !== $title ) {
					$image->meta['title'] = $title;
				}

				ImageRepository::save( $image );
				$result['imported']++;
			} catch ( \Throwable $e ) {
				$result['skipped']++;
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

	// ------------------------------------------------------------------
	// Private helpers
	// ------------------------------------------------------------------

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
	 * The destination slug for a Modula gallery, before uniqueness.
	 */
	private function slug_base( \WP_Post $post ): string {
		return sanitize_title( $post->post_name ?: $post->post_title ) . '-from-modula';
	}

	/**
	 * Generate a slug that does not already exist in BLT Gallery.
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
