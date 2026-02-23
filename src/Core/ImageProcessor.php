<?php

declare( strict_types=1 );

namespace ZymGallery\Core;

use ZymGallery\Models\Image;

/**
 * Handles image upload processing: dimension extraction, thumbnail generation,
 * WebP/AVIF conversion, and EXIF stripping.
 *
 * Only the WordPress WP_Image_Editor API is used so that the host's preferred
 * image library (GD or Imagick) is respected automatically.
 */
class ImageProcessor {

	/**
	 * Thumbnail sizes generated for every uploaded image.
	 * Keys are size names; values are [width, height, crop] triples.
	 */
	const THUMBNAIL_SIZES = [
		'thumb'  => [ 320, 320, true ],
		'medium' => [ 800, 600, false ],
		'large'  => [ 1600, 1200, false ],
	];

	/**
	 * Process an uploaded file and populate an Image model.
	 *
	 * @param string $tmp_path Absolute path to the uploaded temp file.
	 * @param int    $gallery_id
	 * @return Image  Populated model (not yet persisted).
	 * @throws \RuntimeException On processing failure.
	 */
	public function process_upload( string $tmp_path, int $gallery_id ): Image {
		// Determine destination directory inside WP uploads.
		$upload_dir = wp_upload_dir();
		$dest_dir   = trailingslashit( $upload_dir['basedir'] ) . 'zymgallery/' . $gallery_id . '/';

		if ( ! wp_mkdir_p( $dest_dir ) ) {
			throw new \RuntimeException( 'Could not create upload directory.' );
		}

		// Sanitise filename and copy to destination.
		$original_name = sanitize_file_name( basename( $tmp_path ) );
		$dest_path     = $dest_dir . $original_name;
		$dest_path     = $this->unique_path( $dest_path );

		if ( ! copy( $tmp_path, $dest_path ) ) {
			throw new \RuntimeException( 'Could not move uploaded file.' );
		}

		// Read dimensions via WP image editor.
		$editor = wp_get_image_editor( $dest_path );
		if ( is_wp_error( $editor ) ) {
			throw new \RuntimeException( 'Image editor error: ' . $editor->get_error_message() );
		}

		$size     = $editor->get_size();
		$width    = (int) ( $size['width'] ?? 0 );
		$height   = (int) ( $size['height'] ?? 0 );
		unset( $editor ); // No longer needed; thumbnails open their own instances.

		$filesize = (int) filesize( $dest_path );
		$mime     = wp_check_filetype( $dest_path )['type'] ?: 'image/jpeg';

		// Generate thumbnails and WebP variants.
		$thumbs = $this->generate_thumbnails( $dest_dir, basename( $dest_path ) );

		// Build the Image model.
		$image                 = new Image();
		$image->gallery_id     = $gallery_id;
		$image->filename       = basename( $dest_path );
		$image->width          = $width;
		$image->height         = $height;
		$image->filesize       = $filesize;
		$image->mime_type      = $mime;
		$image->storage_driver = 'local';
		$image->local_path     = $dest_path;
		$image->meta           = [ 'thumbs' => $thumbs ];

		return $image;
	}

	/**
	 * Generate thumbnail images and, when supported, WebP versions.
	 *
	 * @param \WP_Image_Editor $editor
	 * @param string           $dest_dir  Absolute path ending with /
	 * @param string           $base_name  Original file name (with extension)
	 * @return array  Keyed by size name; each value has 'path', 'url', 'width', 'height'.
	 */
	private function generate_thumbnails( string $dest_dir, string $base_name ): array {
		$upload_dir       = wp_upload_dir();
		$src_path         = $dest_dir . $base_name;
		$name_without_ext = pathinfo( $base_name, PATHINFO_FILENAME );

		// Detect WebP support via wp_image_editor_supports() – public WP API.
		$webp_supported = wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] );

		$thumbs = [];

		foreach ( self::THUMBNAIL_SIZES as $size_name => [ $w, $h, $crop ] ) {
			// Fresh editor instance per size so resize calls don't stack.
			$thumb_editor = wp_get_image_editor( $src_path );
			if ( is_wp_error( $thumb_editor ) ) {
				continue;
			}

			$result = $thumb_editor->resize( $w, $h, $crop );
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$actual_size = $thumb_editor->get_size();

			if ( $webp_supported ) {
				$thumb_name = "{$name_without_ext}-{$size_name}.webp";
				$save_mime  = 'image/webp';
			} else {
				$ext        = pathinfo( $base_name, PATHINFO_EXTENSION );
				$thumb_name = "{$name_without_ext}-{$size_name}.{$ext}";
				$save_mime  = null;
			}

			$saved = $thumb_editor->save( $dest_dir . $thumb_name, $save_mime );
			if ( is_wp_error( $saved ) ) {
				continue;
			}

			$saved_path = $saved['path'] ?? ( $dest_dir . $thumb_name );
			$saved_url  = $upload_dir['baseurl'] . '/' . ltrim(
				str_replace( $upload_dir['basedir'], '', $saved_path ),
				'/\\'
			);

			$thumbs[ $size_name ] = [
				'path'   => $saved_path,
				'url'    => $saved_url,
				'width'  => (int) ( $actual_size['width'] ?? $w ),
				'height' => (int) ( $actual_size['height'] ?? $h ),
			];
		}

		return $thumbs;
	}

	/**
	 * If a file at $path already exists, append a numeric suffix until unique.
	 */
	private function unique_path( string $path ): string {
		if ( ! file_exists( $path ) ) {
			return $path;
		}

		$dir  = dirname( $path );
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );
		$name = pathinfo( $path, PATHINFO_FILENAME );

		$i = 1;
		do {
			$candidate = "{$dir}/{$name}-{$i}.{$ext}";
			++$i;
		} while ( file_exists( $candidate ) );

		return $candidate;
	}
}
