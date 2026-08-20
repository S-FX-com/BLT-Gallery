<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Deletes a gallery along with its images and the files behind them.
 *
 * GalleryRepository::delete() only clears database rows, which left every
 * uploaded file and every S3/R2 object stranded with nothing pointing at it.
 * Everything that removes a gallery goes through here instead.
 *
 * Local files are removed inline — unlinking is cheap. Remote objects are
 * not: a thousand-image gallery in R2 is several thousand HTTP round trips,
 * far more than an admin request should carry. Their keys go to
 * StoragePurgeQueue instead, which works through them on cron once the
 * gallery has already vanished from the screen.
 *
 * Images are removed one at a time — row and files together — which makes the
 * work naturally resumable. If the caller runs out of time partway through,
 * the gallery and its remaining images stay put, and calling this again with
 * the same id carries on where it stopped.
 */
class GalleryDeleter {

	/**
	 * @param int           $gallery_id
	 * @param callable|null $has_time Called before each image; return false
	 *                                to stop and leave the rest for a later
	 *                                call. Omit to run to completion.
	 * @return array{deleted:bool,missing:bool,images:int,files:int,queued:int,remaining:int}
	 */
	public static function delete( int $gallery_id, ?callable $has_time = null ): array {
		$out = [
			'deleted'   => false,
			'missing'   => false,
			'images'    => 0,
			'files'     => 0,
			'queued'    => 0,
			'remaining' => 0,
		];

		$gallery = GalleryRepository::find( $gallery_id );

		if ( ! $gallery ) {
			$out['missing'] = true;
			return $out;
		}

		$folder = $gallery->storage_folder();

		/**
		 * Remote keys, grouped by backend. Collected as we go and handed off
		 * at the end — or on the way out if we run short of time, because the
		 * image rows holding these keys are gone by then and nothing else
		 * knows the objects exist.
		 *
		 * @var array<string, string[]>
		 */
		$remote = [];

		try {
			foreach ( ImageRepository::find_by_gallery( $gallery_id ) as $image ) {
				if ( $has_time && ! $has_time() ) {
					$out['remaining'] = ImageRepository::count_by_gallery( $gallery_id );
					return $out;
				}

				$keys = ImageFiles::remote_keys( $image );
				if ( $keys ) {
					$driver             = $image->storage_driver;
					$remote[ $driver ]  = array_merge( $remote[ $driver ] ?? [], $keys );
					$out['queued']     += count( $keys );
				}

				// Local copies go now — unlinking is cheap, and an offloaded
				// image may still have them if "delete local after upload"
				// was off.
				$out['files'] += ImageFiles::purge_local( $image );

				ImageRepository::delete( $image->id );
				$out['images']++;
			}

			$out['deleted'] = GalleryRepository::delete( $gallery_id );

			if ( $out['deleted'] ) {
				self::remove_empty_folder( $folder );
			}
		} finally {
			foreach ( $remote as $driver => $keys ) {
				StoragePurgeQueue::enqueue( (string) $driver, $keys );
			}
		}

		return $out;
	}

	/**
	 * Tidy up the gallery's upload directory once its files are gone.
	 *
	 * Uses rmdir rather than a recursive delete, so anything unexpected still
	 * sitting in there keeps the directory — and itself — alive.
	 */
	private static function remove_empty_folder( string $folder ): void {
		if ( '' === $folder ) {
			return;
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return;
		}

		$dir = trailingslashit( $uploads['basedir'] ) . 'bltgallery/' . $folder;

		if ( is_dir( $dir ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@rmdir( $dir );
		}
	}
}
