<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Slider;

/**
 * Data-access layer for sliders.
 */
class SliderRepository {

	// ------------------------------------------------------------------
	// Read
	// ------------------------------------------------------------------

	public static function find( int $id ): ?Slider {
		global $wpdb;
		$table = Database::sliders_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id ),
			ARRAY_A
		);

		return $row ? Slider::from_row( $row ) : null;
	}

	public static function find_by_slug( string $slug ): ?Slider {
		global $wpdb;
		$table = Database::sliders_table();

		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug ),
			ARRAY_A
		);

		return $row ? Slider::from_row( $row ) : null;
	}

	/**
	 * @return Slider[]
	 */
	public static function all( int $per_page = 100, int $page = 1 ): array {
		global $wpdb;
		$table  = Database::sliders_table();
		$offset = max( 0, ( $page - 1 ) * $per_page );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$per_page,
				$offset
			),
			ARRAY_A
		);

		return array_map( [ Slider::class, 'from_row' ], $rows ?: [] );
	}

	public static function count(): int {
		global $wpdb;
		$table = Database::sliders_table();
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
	}

	// ------------------------------------------------------------------
	// Write
	// ------------------------------------------------------------------

	public static function save( Slider $slider ): Slider {
		global $wpdb;
		$table = Database::sliders_table();

		$slug = $slider->slug ?: sanitize_title( $slider->title );
		$slug = self::unique_slug( '' !== $slug ? $slug : 'slider', $slider->id );

		$data = [
			'title'     => $slider->title,
			'slug'      => $slug,
			'settings'  => wp_json_encode( (object) $slider->settings ),
			'items'     => wp_json_encode( array_values( $slider->items ) ),
			'author_id' => $slider->author_id ?: get_current_user_id(),
		];

		$format = [ '%s', '%s', '%s', '%s', '%d' ];

		if ( $slider->id ) {
			$wpdb->update( $table, $data, [ 'id' => $slider->id ], $format, [ '%d' ] );
		} else {
			$wpdb->insert( $table, $data, $format );
			$slider->id = (int) $wpdb->insert_id;
		}

		$slider->slug = $slug;

		return $slider;
	}

	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( Database::sliders_table(), [ 'id' => $id ], [ '%d' ] );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Guarantee slug uniqueness (the column carries a UNIQUE index), appending
	 * -2, -3, … when needed. Ignores the row currently being saved.
	 */
	private static function unique_slug( string $base, int $ignore_id ): string {
		global $wpdb;
		$table = Database::sliders_table();
		$slug  = $base;
		$i     = 2;

		while ( true ) {
			$existing_id = (int) $wpdb->get_var(
				$wpdb->prepare( "SELECT id FROM {$table} WHERE slug = %s LIMIT 1", $slug )
			);
			if ( 0 === $existing_id || $existing_id === $ignore_id ) {
				return $slug;
			}
			$slug = $base . '-' . $i;
			$i++;
		}
	}
}
