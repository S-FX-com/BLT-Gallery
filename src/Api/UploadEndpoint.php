<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Aws\CloudFrontCDN;
use BltGallery\Aws\S3Storage;
use BltGallery\Storage\R2Storage;

/**
 * Handles multipart image uploads.
 *
 * POST /bltgallery/v1/galleries/{gallery_id}/upload
 *
 * Accepts:
 *   - file   : the uploaded file (multipart/form-data)
 *   - storage: 'local' | 's3' | 'r2' – override the default storage driver for this upload
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

		if ( ! GalleryRepository::find( $gallery_id ) ) {
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
			$image     = $processor->process_upload( $file['tmp_name'], $gallery_id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'processing_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		// Upload to the configured storage driver.
		$driver = $this->resolve_storage_driver( $request );

		if ( 's3' === $driver && S3Storage::is_configured() ) {
			try {
				$s3    = new S3Storage();
				$image = $s3->upload_image( $image );

				$cf = new CloudFrontCDN();
				if ( $cf->is_configured() ) {
					$image = $cf->apply_to_image( $image );
				}
			} catch ( \Throwable $e ) {
				error_log( 'BltGallery S3 upload failed: ' . $e->getMessage() );
			}
		} elseif ( 'r2' === $driver && R2Storage::is_configured() ) {
			try {
				$r2    = new R2Storage();
				$image = $r2->upload_image( $image );
			} catch ( \Throwable $e ) {
				error_log( 'BltGallery R2 upload failed: ' . $e->getMessage() );
			}
		}
		// 'local' driver: image already saved locally by ImageProcessor, nothing else to do.

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

	/**
	 * Determine which storage driver to use for this upload.
	 *
	 * Reads the "Auto-offload" checkbox from each provider's settings.
	 * S3 takes priority if both are enabled.
	 * Falls back to 'local' if neither is enabled.
	 */
	private function resolve_storage_driver( WP_REST_Request $request ): string {
		$aws = get_option( 'bltgallery_aws_settings', [] );
		if ( ! empty( $aws['auto_offload'] ) && S3Storage::is_configured() ) {
			return 's3';
		}

		$r2 = get_option( 'bltgallery_r2_settings', [] );
		if ( ! empty( $r2['auto_offload'] ) && R2Storage::is_configured() ) {
			return 'r2';
		}

		return 'local';
	}

	public function permission(): bool {
		return current_user_can( 'upload_files' );
	}
}
