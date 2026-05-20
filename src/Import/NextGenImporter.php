<?php

declare( strict_types=1 );

namespace BltGallery\Import;

use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Models\Gallery;

/**
 * Imports galleries and images from Imagely NextGEN Gallery.
 *
 * NextGEN stores data in two custom tables:
 *   {prefix}ngg_gallery  – gallery metadata (gid, name, path, title, galdesc, …)
 *   {prefix}ngg_pictures – per-image records (pid, galleryid, filename, alttext, description, …)
 *
 * Images live on disk at ABSPATH . $gallery->path . '/' . $picture->filename.
 * This importer copies files into BltGallery's own upload directory so that the
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
	 * Import NextGEN galleries into BltGallery.
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
			$results['errors'][] = __( 'NextGEN Gallery tables not found.', 'bltgallery' );
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

		// Create the BltGallery gallery record.
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
					__( 'File not found: %1$s (gallery: %2$s)', 'bltgallery' ),
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
					__( 'Failed to import %1$s: %2$s', 'bltgallery' ),
					$pic['filename'],
					$e->getMessage()
				);
			}
		}

		return $result;
	}

	/**
	 * Generate a slug that does not already exist in BltGallery.
	 */
	private function unique_slug( string $base ): string {
		$slug  = $base;
		$count = 1;
		while ( GalleryRepository::find_by_slug( $slug ) ) {
			$slug = $base . '-' . $count++;
		}
		return $slug;
	}

	// ------------------------------------------------------------------
	// Cleanup – list, zip, delete NextGEN files on disk
	// ------------------------------------------------------------------

	/**
	 * Inspect every NextGEN gallery's on-disk folder and return totals
	 * plus per-gallery breakdown. Used by the cleanup UI to show the
	 * user exactly what would be removed.
	 *
	 * @return array {
	 *   total_files: int,
	 *   total_bytes: int,
	 *   galleries:   array<int, array{gid:int,title:string,path:string,files:int,bytes:int,exists:bool}>,
	 * }
	 */
	public function scan_legacy_files(): array {
		$out = [ 'total_files' => 0, 'total_bytes' => 0, 'galleries' => [] ];

		foreach ( $this->get_galleries() as $ngg ) {
			$abs    = rtrim( ABSPATH, '/' ) . '/' . trim( (string) $ngg['path'], '/' );
			$exists = is_dir( $abs );
			$files  = 0;
			$bytes  = 0;

			if ( $exists ) {
				[ $files, $bytes ] = $this->dir_stats( $abs );
			}

			$out['galleries'][] = [
				'gid'    => (int) $ngg['gid'],
				'title'  => (string) ( $ngg['title'] ?: $ngg['name'] ),
				'path'   => (string) $ngg['path'],
				'files'  => $files,
				'bytes'  => $bytes,
				'exists' => $exists,
			];
			$out['total_files'] += $files;
			$out['total_bytes'] += $bytes;
		}

		return $out;
	}

	/**
	 * Build a ZIP archive of every NextGEN gallery folder under the
	 * WordPress uploads/bltgallery-backups/ directory and return both
	 * the absolute path and the public URL.
	 *
	 * @throws \RuntimeException when ZipArchive is unavailable, the backup
	 *                          directory is unwritable, or the archive
	 *                          can't be opened/closed.
	 */
	public function backup_legacy_files(): array {
		if ( ! class_exists( '\\ZipArchive' ) ) {
			throw new \RuntimeException( __( 'The ZipArchive PHP extension is not available on this server.', 'bltgallery' ) );
		}

		$uploads = wp_upload_dir();
		$dir     = trailingslashit( $uploads['basedir'] ) . 'bltgallery-backups';
		if ( ! wp_mkdir_p( $dir ) ) {
			throw new \RuntimeException( __( 'Could not create the backup directory.', 'bltgallery' ) );
		}

		$filename = 'nextgen-backup-' . gmdate( 'Ymd-His' ) . '.zip';
		$zip_path = $dir . '/' . $filename;
		$zip_url  = trailingslashit( $uploads['baseurl'] ) . 'bltgallery-backups/' . $filename;

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $zip_path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			throw new \RuntimeException( __( 'Could not create the ZIP archive.', 'bltgallery' ) );
		}

		$file_count = 0;
		foreach ( $this->get_galleries() as $ngg ) {
			$abs = rtrim( ABSPATH, '/' ) . '/' . trim( (string) $ngg['path'], '/' );
			if ( ! is_dir( $abs ) ) {
				continue;
			}
			$prefix = trim( (string) $ngg['path'], '/' );

			$iter = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $abs, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::LEAVES_ONLY
			);
			foreach ( $iter as $file ) {
				if ( ! $file->isFile() ) {
					continue;
				}
				$abs_path = $file->getPathname();
				$rel_path = $prefix . '/' . ltrim( substr( $abs_path, strlen( $abs ) ), '/\\' );
				if ( $zip->addFile( $abs_path, $rel_path ) ) {
					$file_count++;
				}
			}
		}

		if ( ! $zip->close() ) {
			throw new \RuntimeException( __( 'Could not finalise the ZIP archive.', 'bltgallery' ) );
		}

		return [
			'path'  => $zip_path,
			'url'   => $zip_url,
			'files' => $file_count,
			'bytes' => (int) ( file_exists( $zip_path ) ? filesize( $zip_path ) : 0 ),
		];
	}

	/**
	 * Recursively delete every NextGEN gallery folder. Returns the
	 * number of files and bytes removed. Does NOT touch the
	 * `ngg_gallery` / `ngg_pictures` database tables — only files on
	 * disk — so re-installing NextGEN would surface the rows as empty
	 * galleries the user can deal with later.
	 *
	 * @return array{files:int,bytes:int,galleries:int}
	 */
	public function delete_legacy_files(): array {
		$out = [ 'files' => 0, 'bytes' => 0, 'galleries' => 0 ];

		foreach ( $this->get_galleries() as $ngg ) {
			$abs = rtrim( ABSPATH, '/' ) . '/' . trim( (string) $ngg['path'], '/' );
			if ( ! is_dir( $abs ) ) {
				continue;
			}

			[ $files, $bytes ] = $this->dir_stats( $abs );
			if ( $this->rrmdir( $abs ) ) {
				$out['files']     += $files;
				$out['bytes']     += $bytes;
				$out['galleries']++;
			}
		}

		return $out;
	}

	/**
	 * @return array{0:int,1:int} files, bytes
	 */
	private function dir_stats( string $dir ): array {
		$files = 0;
		$bytes = 0;
		$iter  = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::LEAVES_ONLY
		);
		foreach ( $iter as $file ) {
			if ( $file->isFile() ) {
				$files++;
				$bytes += (int) $file->getSize();
			}
		}
		return [ $files, $bytes ];
	}

	/**
	 * Recursive rmdir. Refuses to delete anything outside ABSPATH as
	 * a defence-in-depth check against a misconfigured NextGEN `path`.
	 */
	private function rrmdir( string $dir ): bool {
		$real_dir  = realpath( $dir );
		$real_root = realpath( ABSPATH );
		if ( ! $real_dir || ! $real_root || 0 !== strpos( $real_dir, $real_root ) ) {
			return false;
		}

		$it = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $real_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $entry ) {
			$entry->isDir()
				? @rmdir( $entry->getPathname() )
				: @unlink( $entry->getPathname() );
		}
		return @rmdir( $real_dir );
	}
}
