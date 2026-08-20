<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Aws\S3Storage;
use BltGallery\Storage\R2Storage;
use BltGallery\Models\Image;

/**
 * Removes the files behind an image — the original plus every generated
 * thumbnail — from wherever they ended up.
 *
 * Both locations are checked rather than only the one named by
 * `storage_driver`: an image offloaded to S3 or R2 still has its local copies
 * unless "delete local after upload" was on, and skipping them would leave
 * the originals behind on disk.
 *
 * Deletion is best effort. A missing file or an unreachable bucket must not
 * stop a gallery from being removed, so failures are counted rather than
 * thrown — the alternative is a gallery the user cannot delete at all.
 */
class ImageFiles {

	/**
	 * Remote clients are reused across a batch: building one re-reads the
	 * settings and re-signs a client, which adds up over a few thousand
	 * images.
	 *
	 * @var array<string, object|null>
	 */
	private static array $clients = [];

	/**
	 * Delete everything belonging to one image.
	 *
	 * @return int How many files were removed.
	 */
	public static function purge( Image $image ): int {
		return self::purge_remote( $image ) + self::purge_local( $image );
	}

	/**
	 * Drop any cached storage clients. Worth calling between long batches so
	 * a settings change mid-run is picked up.
	 */
	public static function reset(): void {
		self::$clients = [];
	}

	// ------------------------------------------------------------------

	private static function purge_remote( Image $image ): int {
		$client = self::client( $image->storage_driver );

		if ( ! $client ) {
			return 0;
		}

		$removed = 0;

		if ( $image->s3_key ) {
			$client->delete( $image->s3_key );
			$removed++;
		}

		foreach ( (array) ( $image->meta['thumbs'] ?? [] ) as $thumb ) {
			if ( ! empty( $thumb['s3_key'] ) ) {
				$client->delete( (string) $thumb['s3_key'] );
				$removed++;
			}
		}

		return $removed;
	}

	private static function purge_local( Image $image ): int {
		$removed = 0;

		$paths = [];

		if ( $image->local_path ) {
			$paths[] = (string) $image->local_path;
		}

		foreach ( (array) ( $image->meta['thumbs'] ?? [] ) as $thumb ) {
			if ( ! empty( $thumb['path'] ) ) {
				$paths[] = (string) $thumb['path'];
			}
		}

		foreach ( $paths as $path ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( file_exists( $path ) && @unlink( $path ) ) {
				$removed++;
			}
		}

		return $removed;
	}

	/**
	 * The storage client for a driver, or null when the image is local-only
	 * or that backend is no longer configured.
	 */
	private static function client( string $driver ): ?object {
		if ( array_key_exists( $driver, self::$clients ) ) {
			return self::$clients[ $driver ];
		}

		self::$clients[ $driver ] = match ( $driver ) {
			's3'    => S3Storage::is_configured() ? new S3Storage() : null,
			'r2'    => R2Storage::is_configured() ? new R2Storage() : null,
			default => null,
		};

		return self::$clients[ $driver ];
	}
}
