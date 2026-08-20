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
 * Images are removed one at a time — row and files together — which makes the
 * work naturally resumable. If the caller runs out of time partway through,
 * the gallery and its remaining images stay put, and calling this again with
 * the same id carries on where it stopped. That matters for offloaded
 * galleries, where each image is several HTTP round trips and a
 * thousand-image gallery would otherwise outrun any single request.
 */
class GalleryDeleter {

	/**
	 * @param int           $gallery_id
	 * @param callable|null $has_time Called before each image; return false
	 *                                to stop and leave the rest for a later
	 *                                call. Omit to run to completion.
	 * @return array{deleted:bool,missing:bool,images:int,files:int,remaining:int}
	 */
	public static function delete( int $gallery_id, ?callable $has_time = null ): array {
		$out = [
			'deleted'   => false,
			'missing'   => false,
			'images'    => 0,
			'files'     => 0,
			'remaining' => 0,
		];

		$gallery = GalleryRepository::find( $gallery_id );

		if ( ! $gallery ) {
			$out['missing'] = true;
			return $out;
		}

		$folder = $gallery->storage_folder();

		foreach ( ImageRepository::find_by_gallery( $gallery_id ) as $image ) {
			if ( $has_time && ! $has_time() ) {
				$out['remaining'] = ImageRepository::count_by_gallery( $gallery_id );
				return $out;
			}

			$out['files'] += ImageFiles::purge( $image );
			ImageRepository::delete( $image->id );
			$out['images']++;
		}

		$out['deleted'] = GalleryRepository::delete( $gallery_id );

		if ( $out['deleted'] ) {
			self::remove_empty_folder( $folder );
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
