<?php

declare( strict_types=1 );

namespace ZymGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZymGallery\Core\GalleryRepository;
use ZymGallery\Core\ImageProcessor;
use ZymGallery\Core\ImageRepository;
use ZymGallery\Aws\CloudFrontCDN;
use ZymGallery\Aws\S3Storage;
use ZymGallery\Storage\R2Storage;

/**
 * Handles multipart image uploads.
 *
 * POST /zymgallery/v1/galleries/{gallery_id}/upload
 *
 * Accepts:
 *   - file   : the uploaded file (multipart/form-data)
 *   - offload: 1|0 – whether to push to S3 after local processing (default: per settings)
 */
class UploadEndpoint {

	const NAMESPACE = 'zymgallery/v1';

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
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'zymgallery' ), [ 'status' => 404 ] );
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'zymgallery' ), [ 'status' => 400 ] );
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

		// Optionally offload to a cloud provider.
		// S3 takes priority if both are configured; only one provider runs per upload.
		if ( $this->should_offload_s3( $request ) && S3Storage::is_configured() ) {
			try {
				$s3    = new S3Storage();
				$image = $s3->upload_image( $image );

				$cf = new CloudFrontCDN();
				if ( $cf->is_configured() ) {
					$image = $cf->apply_to_image( $image );
				}
			} catch ( \Throwable $e ) {
				error_log( 'ZymGallery S3 offload failed: ' . $e->getMessage() );
			}
		} elseif ( $this->should_offload_r2( $request ) && R2Storage::is_configured() ) {
			try {
				$r2    = new R2Storage();
				$image = $r2->upload_image( $image );
			} catch ( \Throwable $e ) {
				error_log( 'ZymGallery R2 offload failed: ' . $e->getMessage() );
			}
		}

		// Persist.
		$image = ImageRepository::save( $image );

		return new WP_REST_Response( $image->to_array(), 201 );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function validate_file( array $file ): true|WP_Error {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload error.', 'zymgallery' ), [ 'status' => 400 ] );
		}

		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'File exceeds maximum size of %s MB.', 'zymgallery' ),
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
				__( 'Only JPEG, PNG, GIF, WebP, and AVIF images are allowed.', 'zymgallery' ),
				[ 'status' => 415 ]
			);
		}

		return true;
	}

	private function should_offload_s3( WP_REST_Request $request ): bool {
		$param = $request->get_param( 'offload' );
		if ( null !== $param ) {
			return filter_var( $param, FILTER_VALIDATE_BOOLEAN );
		}
		$settings = get_option( 'zymgallery_aws_settings', [] );
		return ! empty( $settings['auto_offload'] );
	}

	private function should_offload_r2( WP_REST_Request $request ): bool {
		$param = $request->get_param( 'offload' );
		if ( null !== $param ) {
			return filter_var( $param, FILTER_VALIDATE_BOOLEAN );
		}
		$settings = get_option( 'zymgallery_r2_settings', [] );
		return ! empty( $settings['auto_offload'] );
	}

	public function permission(): bool {
		return current_user_can( 'upload_files' );
	}
}
