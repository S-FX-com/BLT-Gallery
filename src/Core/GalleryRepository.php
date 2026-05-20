<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Gallery;

/**
 * Data-access layer for galleries.
 */
class GalleryRepository {

	// ------------------------------------------------------------------
	// Read
	// ------------------------------------------------------------------

	public static function find( int $id ): ?Gallery {
		global $wpdb;
		$table = Database::galleries_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return $row ? Gallery::from_row( $row ) : null;
	}

	public static function find_by_slug( string $slug ): ?Gallery {
		global $wpdb;
		$table = Database::galleries_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ),
			ARRAY_A
		);

		return $row ? Gallery::from_row( $row ) : null;
	}

	/**
	 * @return Gallery[]
	 */
	public static function all( int $per_page = 50, int $page = 1 ): array {
		global $wpdb;
		$table  = Database::galleries_table();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array_map( [ Gallery::class, 'from_row' ], $rows ?: [] );
	}

	public static function count(): int {
		global $wpdb;
		$table = Database::galleries_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	// ------------------------------------------------------------------
	// Write
	// ------------------------------------------------------------------

	public static function save( Gallery $gallery ): Gallery {
		global $wpdb;
		$table = Database::galleries_table();

		$data = [
			'title'        => $gallery->title,
			'slug'         => $gallery->slug ?: sanitize_title( $gallery->title ),
			'description'  => $gallery->description,
			'display_type' => $gallery->display_type,
			'settings'     => wp_json_encode( $gallery->settings ),
			'author_id'    => $gallery->author_id ?: get_current_user_id(),
		];

		$format = [ '%s', '%s', '%s', '%s', '%s', '%d' ];

		if ( $gallery->id ) {
			$wpdb->update( $table, $data, [ 'id' => $gallery->id ], $format, [ '%d' ] );
		} else {
			$wpdb->insert( $table, $data, $format );
			$gallery->id = (int) $wpdb->insert_id;
		}

		return $gallery;
	}

	public static function delete( int $id ): bool {
		global $wpdb;

		// Remove images first.
		$wpdb->delete( Database::images_table(), [ 'gallery_id' => $id ], [ '%d' ] );

		$rows = $wpdb->delete( Database::galleries_table(), [ 'id' => $id ], [ '%d' ] );
		return (bool) $rows;
	}
}
