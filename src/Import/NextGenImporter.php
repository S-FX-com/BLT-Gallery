<?php

declare( strict_types=1 );

namespace ZymGallery\Import;

use ZymGallery\Core\GalleryRepository;
use ZymGallery\Core\ImageProcessor;
use ZymGallery\Core\ImageRepository;
use ZymGallery\Models\Gallery;

/**
 * Imports galleries and images from Imagely NextGEN Gallery.
 *
 * NextGEN stores data in two custom tables:
 *   {prefix}ngg_gallery  – gallery metadata (gid, name, path, title, galdesc, …)
 *   {prefix}ngg_pictures – per-image records (pid, galleryid, filename, alttext, description, …)
 *
 * Images live on disk at ABSPATH . $gallery->path . '/' . $picture->filename.
 * This importer copies files into ZymGallery's own upload directory so that the
 * original NextGEN files are never modified or removed.
 */
class NextGenImporter {

	// ------------------------------------------------------------------
	// Detection
	// ------------------------------------------------------------------

	/**
	 * Returns true if the NextGEN Gallery database tables exist.
	 */
	public function is_available(): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'ngg_gallery';
		// Use a direct SHOW TABLES query; phpcs safe because no user input.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) === $table;
	}

	// ------------------------------------------------------------------
	// Preview
	// ------------------------------------------------------------------

	/**
	 * Return a summary of all NextGEN galleries with image counts.
	 * Used to show the admin a preview before running the import.
	 *
	 * @return array[] Each element: {gid, title, name, path, galdesc, image_count}
	 */
	public function get_galleries(): array {
		global $wpdb;

		if ( ! $this->is_available() ) {
			return [];
		}

		$g = $wpdb->prefix . 'ngg_gallery';
		$p = $wpdb->prefix . 'ngg_pictures';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT g.gid, g.title, g.name, g.path, g.galdesc,
			        COUNT(p.pid) AS image_count
			 FROM {$g} g
			 LEFT JOIN {$p} p ON p.galleryid = g.gid
			 GROUP BY g.gid
			 ORDER BY g.gid ASC",
			ARRAY_A
		);

		return $rows ?: [];
	}

	// ------------------------------------------------------------------
	// Import
	// ------------------------------------------------------------------

	/**
	 * Import NextGEN galleries into ZymGallery.
	 *
	 * @param int[]|null $gallery_ids  Specific NextGEN gallery IDs to import,
	 *                                 or null to import everything.
	 * @return array {
	 *   galleries_imported: int,
	 *   images_imported:    int,
	 *   images_skipped:     int,
	 *   errors:             string[],
	 * }
	 */
	public function import( ?array $gallery_ids = null ): array {
		global $wpdb;

		$results = [
			'galleries_imported' => 0,
			'images_imported'    => 0,
			'images_skipped'     => 0,
			'errors'             => [],
		];

		if ( ! $this->is_available() ) {
			$results['errors'][] = __( 'NextGEN Gallery tables not found.', 'zymgallery' );
			return $results;
		}

		$g_table = $wpdb->prefix . 'ngg_gallery';
		$p_table = $wpdb->prefix . 'ngg_pictures';

		// Build optional WHERE clause for specific gallery IDs.
		if ( ! empty( $gallery_ids ) ) {
			$ids        = array_map( 'intval', $gallery_ids );
			$in         = implode( ',', $ids );
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ngg_galleries = $wpdb->get_results(
				"SELECT * FROM {$g_table} WHERE gid IN ({$in}) ORDER BY gid ASC",
				ARRAY_A
			);
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ngg_galleries = $wpdb->get_results(
				"SELECT * FROM {$g_table} ORDER BY gid ASC",
				ARRAY_A
			);
		}

		if ( ! $ngg_galleries ) {
			return $results;
		}

		$processor = new ImageProcessor();

		foreach ( $ngg_galleries as $ngg ) {
			$gallery_result = $this->import_gallery( $ngg, $p_table, $processor );

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
	 * Import a single NextGEN gallery and its images.
	 *
	 * @param array          $ngg       A row from ngg_gallery.
	 * @param string         $p_table   Fully-qualified ngg_pictures table name.
	 * @param ImageProcessor $processor Shared processor instance.
	 */
	private function import_gallery( array $ngg, string $p_table, ImageProcessor $processor ): array {
		global $wpdb;

		$result = [
			'gallery_imported' => 0,
			'images_imported'  => 0,
			'images_skipped'   => 0,
			'errors'           => [],
		];

		// Create the ZymGallery gallery record.
		$gallery              = new Gallery();
		$gallery->title       = sanitize_text_field( $ngg['title'] ?: $ngg['name'] );
		$gallery->slug        = $this->unique_slug( sanitize_title( $ngg['name'] ) . '-from-nextgen' );
		$gallery->description = sanitize_textarea_field( $ngg['galdesc'] ?? '' );
		$gallery->display_type = 'masonry';
		$gallery->author_id   = get_current_user_id();
		$gallery              = GalleryRepository::save( $gallery );

		$result['gallery_imported'] = 1;

		// Resolve the on-disk path for this NextGEN gallery.
		// NextGEN stores the path relative to ABSPATH, e.g. 'wp-content/gallery/my-gallery'.
		$gallery_disk_path = rtrim( ABSPATH, '/' ) . '/' . trim( $ngg['path'], '/' );

		// Fetch images for this gallery.
		$pictures = $wpdb->get_results(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				"SELECT * FROM {$p_table} WHERE galleryid = %d ORDER BY sortorder ASC, pid ASC",
				(int) $ngg['gid']
			),
			ARRAY_A
		);

		if ( ! $pictures ) {
			return $result;
		}

		foreach ( $pictures as $pic ) {
			$file_path = $gallery_disk_path . '/' . $pic['filename'];

			if ( ! file_exists( $file_path ) ) {
				$result['images_skipped']++;
				$result['errors'][] = sprintf(
					/* translators: 1: filename, 2: gallery title */
					__( 'File not found: %1$s (gallery: %2$s)', 'zymgallery' ),
					$pic['filename'],
					$gallery->title
				);
				continue;
			}

			try {
				// process_upload() copies the file – NextGEN originals are untouched.
				$image             = $processor->process_upload( $file_path, $gallery->id );
				$image->alt_text   = sanitize_text_field( $pic['alttext'] ?? '' );
				$image->description = sanitize_textarea_field( $pic['description'] ?? '' );
				ImageRepository::save( $image );
				$result['images_imported']++;
			} catch ( \Throwable $e ) {
				$result['images_skipped']++;
				$result['errors'][] = sprintf(
					/* translators: 1: filename, 2: error message */
					__( 'Failed to import %1$s: %2$s', 'zymgallery' ),
					$pic['filename'],
					$e->getMessage()
				);
			}
		}

		return $result;
	}

	/**
	 * Generate a slug that does not already exist in ZymGallery.
	 */
	private function unique_slug( string $base ): string {
		$slug  = $base;
		$count = 1;
		while ( GalleryRepository::find_by_slug( $slug ) ) {
			$slug = $base . '-' . $count++;
		}
		return $slug;
	}
}
