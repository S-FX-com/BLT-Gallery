<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use WP_Error;

/**
 * Shared validation for anything that becomes a gallery image via
 * ImageProcessor::process_upload() — a fresh multipart upload, a front-end
 * user's own upload, and a media-library import all funnel through the same
 * MIME whitelist and size cap here, so the two can't quietly drift apart
 * across upload paths.
 */
class UploadValidator {

	/** Maximum upload size in bytes (50 MB). Servers may enforce a lower limit. */
	const MAX_UPLOAD_SIZE = 52_428_800;

	/** Allowed MIME types. */
	const ALLOWED_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ];

	/**
	 * Validate a $_FILES-shaped upload entry.
	 */
	public static function validate( array $file ): true|WP_Error {
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
}
