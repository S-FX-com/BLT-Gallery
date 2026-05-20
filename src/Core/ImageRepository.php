<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Image;

/**
 * Data-access layer for images.
 */
class ImageRepository {

	// ------------------------------------------------------------------
	// Read
	// ------------------------------------------------------------------

	public static function find( int $id ): ?Image {
		global $wpdb;
		$table = Database::images_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return $row ? Image::from_row( $row ) : null;
	}

	/**
	 * @return Image[]
	 */
	public static function find_by_gallery( int $gallery_id ): array {
		global $wpdb;
		$table = Database::images_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE gallery_id = %d ORDER BY sort_order ASC, id ASC",
				$gallery_id
			),
			ARRAY_A
		);

		return array_map( [ Image::class, 'from_row' ], $rows ?: [] );
	}

	public static function count_by_gallery( int $gallery_id ): int {
		global $wpdb;
		$table = Database::images_table();
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE gallery_id = %d", $gallery_id )
		);
	}

	// ------------------------------------------------------------------
	// Write
	// ------------------------------------------------------------------

	public static function save( Image $image ): Image {
		global $wpdb;
		$table = Database::images_table();

		$data = [
			'gallery_id'     => $image->gallery_id,
			'filename'       => $image->filename,
			'alt_text'       => $image->alt_text,
			'caption'        => $image->caption,
			'description'    => $image->description,
			'sort_order'     => $image->sort_order,
			'width'          => $image->width,
			'height'         => $image->height,
			'filesize'       => $image->filesize,
			'mime_type'      => $image->mime_type,
			'storage_driver' => $image->storage_driver,
			'local_path'     => $image->local_path,
			's3_key'         => $image->s3_key,
			's3_bucket'      => $image->s3_bucket,
			'cloudfront_url' => $image->cloudfront_url,
			'meta'           => wp_json_encode( $image->meta ),
		];

		$format = [
			'%d', '%s', '%s', '%s', '%s', '%d',
			'%d', '%d', '%d', '%s', '%s', '%s',
			'%s', '%s', '%s', '%s',
		];

		if ( $image->id ) {
			$wpdb->update( $table, $data, [ 'id' => $image->id ], $format, [ '%d' ] );
		} else {
			$wpdb->insert( $table, $data, $format );
			$image->id = (int) $wpdb->insert_id;
		}

		return $image;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Database::images_table(), [ 'id' => $id ], [ '%d' ] );
	}

	public static function reorder( int $gallery_id, array $ordered_ids ): void {
		global $wpdb;
		$table = Database::images_table();

		foreach ( $ordered_ids as $sort_order => $image_id ) {
			$wpdb->update(
				$table,
				[ 'sort_order' => (int) $sort_order ],
				[
					'id'         => (int) $image_id,
					'gallery_id' => $gallery_id,
				],
				[ '%d' ],
				[ '%d', '%d' ]
			);
		}
	}
}
