<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Core\StorageOffloader;

/**
 * Handles multipart image uploads.
 *
 * POST /bltgallery/v1/galleries/{gallery_id}/upload
 *
 * Accepts:
 *   - file : the uploaded file (multipart/form-data)
 *
 * Where the file ends up is decided by StorageOffloader from the plugin's
 * settings, the same way an imported image is.
 */
class UploadEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	/** Maximum upload size in bytes (50 MB). Servers may enforce a lower limit. */
	const MAX_UPLOAD_SIZE = 52_428_800;

	/** Allowed MIME types. */
	const ALLOWED_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ];

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handler
	// ------------------------------------------------------------------

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery_id = (int) $request->get_param( 'gallery_id' );

		$gallery = GalleryRepository::find( $gallery_id );
		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'bltgallery' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate the upload.
		$validation = $this->validate_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Process: resize, strip EXIF, generate thumbs.
		try {
			$processor = new ImageProcessor();
			$image     = $processor->process_upload( $file['tmp_name'], $gallery );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'processing_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		// Hand it to whichever remote store is configured. Local storage is a
		// no-op — ImageProcessor has already written the files.
		$image = StorageOffloader::offload( $image );

		// Persist.
		$image = ImageRepository::save( $image );

		return new WP_REST_Response( $image->to_array(), 201 );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function validate_file( array $file ): true|WP_Error {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload error.', 'bltgallery' ), [ 'status' => 400 ] );
		}

		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'File exceeds maximum size of %s MB.', 'bltgallery' ),
					self::MAX_UPLOAD_SIZE / 1_048_576
				),
				[ 'status' => 413 ]
			);
		}

		// Verify MIME by reading file magic bytes, not trusting $_FILES['type'].
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime, self::ALLOWED_TYPES, true ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Only JPEG, PNG, GIF, WebP, and AVIF images are allowed.', 'bltgallery' ),
				[ 'status' => 415 ]
			);
		}

		return true;
	}

	public function permission(): bool {
		return current_user_can( 'upload_files' );
	}
}
