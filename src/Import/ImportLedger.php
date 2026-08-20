<?php

declare( strict_types=1 );

namespace BltGallery\Import;

use BltGallery\Core\Database;
use BltGallery\Core\GalleryRepository;
use BltGallery\Models\Gallery;

/**
 * Keeps track of which source galleries have already been migrated.
 *
 * Every gallery BLT Gallery creates during a migration carries a note of
 * where it came from in its own `settings` JSON — the source, the source
 * gallery's id and title, the job that copied it, and, once every image has
 * been dealt with, when it finished and how many landed. Nothing is written
 * back to NextGEN or Modula: the record lives entirely on this side.
 *
 * The Migrate screen reads that back so an already-migrated gallery shows up
 * ticked off rather than queued again — re-importing one does not update the
 * previous copy, it creates a second one.
 *
 * Shape stored at settings['imported_from']:
 *   source        string  'nextgen' | 'modula'
 *   source_id     int     id of the gallery in the source plugin
 *   source_title  string  its title at the time, for display if it is deleted
 *   job_id        string  the migration run that created this gallery
 *   started_at    string  ISO-8601, when the gallery record was created
 *   completed_at  string  ISO-8601, set only once the gallery finished
 *   images        int     images copied in
 *   skipped       int     images that could not be copied
 */
class ImportLedger {

	/**
	 * Key under Gallery::$settings holding the record.
	 */
	const SETTINGS_KEY = 'imported_from';

	// ------------------------------------------------------------------
	// Writing
	// ------------------------------------------------------------------

	/**
	 * Note where a freshly created destination gallery came from.
	 *
	 * Called as soon as the gallery record exists — before any image is
	 * copied — so an interrupted run still leaves a trail. `completed_at`
	 * stays empty until stamp_complete() says otherwise, which is what
	 * separates "half a gallery" from "done".
	 */
	public static function stamp_source(
		Gallery $gallery,
		string $source,
		int $source_id,
		string $source_title,
		string $job_id
	): Gallery {
		$gallery->settings[ self::SETTINGS_KEY ] = [
			'source'       => $source,
			'source_id'    => $source_id,
			'source_title' => $source_title,
			'job_id'       => $job_id,
			'started_at'   => gmdate( 'c' ),
			'completed_at' => '',
			'images'       => 0,
			'skipped'      => 0,
		];

		return GalleryRepository::save( $gallery );
	}

	/**
	 * Tick a gallery off: every image in it has been copied or accounted for.
	 */
	public static function stamp_complete( int $gallery_id, int $images, int $skipped ): void {
		$gallery = GalleryRepository::find( $gallery_id );

		if ( ! $gallery || empty( $gallery->settings[ self::SETTINGS_KEY ] ) ) {
			return;
		}

		$record = (array) $gallery->settings[ self::SETTINGS_KEY ];

		$record['completed_at'] = gmdate( 'c' );
		$record['images']       = $images;
		$record['skipped']      = $skipped;

		$gallery->settings[ self::SETTINGS_KEY ] = $record;

		GalleryRepository::save( $gallery );
	}

	// ------------------------------------------------------------------
	// Reading
	// ------------------------------------------------------------------

	/**
	 * Work out what has already been migrated from one source.
	 *
	 * @param SourceImporter $importer
	 * @param array[]        $source_galleries Rows from $importer->get_galleries().
	 * @return array<int, array{
	 *     gallery_id:int, title:string, state:string, completed_at:string,
	 *     images:int, skipped:int, matched_by:string
	 * }> Keyed by source gallery id.
	 */
	public static function status_for( SourceImporter $importer, array $source_galleries ): array {
		$destinations = self::destination_rows();

		if ( ! $destinations ) {
			return [];
		}

		$source  = $importer->source_key();
		$status  = [];
		$claimed = [];

		// Galleries that carry a record of where they came from.
		foreach ( $destinations as $row ) {
			$record = $row['settings'][ self::SETTINGS_KEY ] ?? null;

			if ( ! is_array( $record ) || ( $record['source'] ?? '' ) !== $source ) {
				continue;
			}

			$source_id = (int) ( $record['source_id'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$complete = '' !== (string) ( $record['completed_at'] ?? '' );

			// A source gallery can have been imported more than once. Keep
			// the finished copy over a half-finished one; between two of the
			// same kind the later row (higher id) wins.
			$seen = $status[ $source_id ] ?? null;
			if ( $seen && 'complete' === $seen['state'] && ! $complete ) {
				continue;
			}

			$status[ $source_id ] = [
				'gallery_id'   => (int) $row['id'],
				'title'        => (string) $row['title'],
				'state'        => $complete ? 'complete' : 'partial',
				'completed_at' => (string) ( $record['completed_at'] ?? '' ),
				'images'       => (int) ( $record['images'] ?? 0 ),
				'skipped'      => (int) ( $record['skipped'] ?? 0 ),
				'matched_by'   => 'record',
			];

			$claimed[ (int) $row['id'] ] = true;
		}

		// Anything migrated before the plugin kept records has no note to
		// read, so fall back to the slug the importer would have generated.
		$legacy = self::match_by_slug( $importer, $source_galleries, $destinations, $claimed, $status );

		return $status + $legacy;
	}

	/**
	 * Recognise pre-ledger imports by the slug the importer would have given
	 * them ("{name}-from-nextgen", plus any "-2" uniqueness suffix).
	 *
	 * This is a best guess, flagged as such in the response, so the UI can be
	 * honest about the difference between "we recorded this" and "this looks
	 * like it".
	 *
	 * @param array[]              $source_galleries
	 * @param array[]              $destinations
	 * @param array<int,bool>      $claimed  Destination ids already spoken for.
	 * @param array<int,array>     $status   Source ids already resolved.
	 * @return array<int,array>
	 */
	private static function match_by_slug(
		SourceImporter $importer,
		array $source_galleries,
		array $destinations,
		array $claimed,
		array $status
	): array {
		$suffix = '-from-' . $importer->source_key();

		// Cheap guard: no candidate slugs, nothing to match.
		$candidates = [];
		foreach ( $destinations as $row ) {
			if ( isset( $claimed[ (int) $row['id'] ] ) ) {
				continue;
			}
			if ( false !== strpos( (string) $row['slug'], $suffix ) ) {
				$candidates[ (string) $row['slug'] ] = $row;
			}
		}

		if ( ! $candidates ) {
			return [];
		}

		$id_key = $importer->id_key();
		$found  = [];

		foreach ( $source_galleries as $source_row ) {
			$source_id = (int) ( $source_row[ $id_key ] ?? 0 );

			if ( $source_id <= 0 || isset( $status[ $source_id ] ) ) {
				continue;
			}

			$base = $importer->target_slug_base( $source_id );
			if ( '' === $base ) {
				continue;
			}

			foreach ( $candidates as $slug => $row ) {
				if ( $slug !== $base && ! preg_match( '/^' . preg_quote( $base, '/' ) . '-\d+$/', $slug ) ) {
					continue;
				}

				$found[ $source_id ] = [
					'gallery_id'   => (int) $row['id'],
					'title'        => (string) $row['title'],
					'state'        => 'complete',
					'completed_at' => '',
					'images'       => 0,
					'skipped'      => 0,
					'matched_by'   => 'slug',
				];

				unset( $candidates[ $slug ] );
				break;
			}
		}

		return $found;
	}

	/**
	 * Every destination gallery, with its settings decoded.
	 *
	 * One query returning only the four columns this needs — the settings
	 * blob is not indexable, so matching happens in PHP.
	 *
	 * @return array<int, array{id:int,title:string,slug:string,settings:array}>
	 */
	private static function destination_rows(): array {
		global $wpdb;

		$table = Database::galleries_table();

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( "SELECT id, title, slug, settings FROM {$table}", ARRAY_A );

		if ( ! $rows ) {
			return [];
		}

		return array_map(
			static function ( array $row ): array {
				$settings = ! empty( $row['settings'] ) ? json_decode( (string) $row['settings'], true ) : [];

				return [
					'id'       => (int) $row['id'],
					'title'    => (string) $row['title'],
					'slug'     => (string) $row['slug'],
					'settings' => is_array( $settings ) ? $settings : [],
				];
			},
			$rows
		);
	}
}
