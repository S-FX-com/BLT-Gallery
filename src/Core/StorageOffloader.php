<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Aws\CloudFrontCDN;
use BltGallery\Aws\S3Storage;
use BltGallery\Storage\R2Storage;
use BltGallery\Models\Image;

/**
 * Pushes a freshly processed image out to whichever remote store is turned on.
 *
 * This used to live inside UploadEndpoint, which meant only the admin
 * drag-and-drop uploader ever offloaded anything. Galleries brought in by the
 * NextGEN and Modula importers went straight to the database with their files
 * left on local disk, so a site with R2 switched on could migrate thousands of
 * photos and still be looking at an empty bucket. Every path that creates an
 * image goes through here now.
 *
 * Offloading is best effort: a bucket that is unreachable or misconfigured
 * leaves the image local and working rather than failing the upload outright.
 */
class StorageOffloader {

	/**
	 * Which backend new images should be sent to.
	 *
	 * Precedence:
	 *   1. The `enable_s3` / `enable_r2` toggle in general settings, provided
	 *      that backend's credentials are configured
	 *   2. The legacy per-provider `auto_offload` flag (pre-3.1 installs)
	 *   3. Local
	 *
	 * When both S3 and R2 are enabled, S3 wins — it was supported first.
	 */
	public static function driver(): string {
		$general = get_option( 'bltgallery_settings', [] );
		$general = is_array( $general ) ? $general : [];

		if ( ! empty( $general['enable_s3'] ) && S3Storage::is_configured() ) {
			return 's3';
		}
		if ( ! empty( $general['enable_r2'] ) && R2Storage::is_configured() ) {
			return 'r2';
		}

		$aws = get_option( 'bltgallery_aws_settings', [] );
		if ( is_array( $aws ) && ! empty( $aws['auto_offload'] ) && S3Storage::is_configured() ) {
			return 's3';
		}

		// Through the accessor, not get_option(): R2Storage::load_settings_static()
		// is where the BLT family shared-store fallback lives, and a site whose
		// R2 credentials resolve there must not look unconfigured here.
		$r2 = R2Storage::load_settings_static();
		if ( ! empty( $r2['auto_offload'] ) && R2Storage::is_configured() ) {
			return 'r2';
		}

		return 'local';
	}

	/**
	 * Send an image and its thumbnails to whichever store is configured
	 * right now.
	 *
	 * Returns the image either way — mutated with its remote keys and public
	 * URL on success, untouched on failure or when storage is local.
	 */
	public static function offload( Image $image ): Image {
		$driver = self::driver();

		return 'local' === $driver ? $image : self::offload_to( $image, $driver );
	}

	/**
	 * Send an image to a specific backend, bypassing whatever driver()
	 * currently resolves to.
	 *
	 * Used by StorageBackfillRunner: a backfill run pins its target driver
	 * once at start, so an admin flipping Settings mid-run can't send half a
	 * run's images to R2 and the other half to S3.
	 *
	 * @param string $driver 's3' or 'r2'. Anything else is a no-op.
	 */
	public static function offload_to( Image $image, string $driver ): Image {
		try {
			if ( 's3' === $driver ) {
				$image = ( new S3Storage() )->upload_image( $image );

				$cdn = new CloudFrontCDN();
				if ( $cdn->is_configured() ) {
					$image = $cdn->apply_to_image( $image );
				}

				return $image;
			}

			if ( 'r2' === $driver ) {
				return ( new R2Storage() )->upload_image( $image );
			}

			return $image;
		} catch ( \Throwable $e ) {
			error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				sprintf( 'BLT Gallery %s upload failed: %s', strtoupper( $driver ), $e->getMessage() )
			);

			return $image;
		}
	}
}
