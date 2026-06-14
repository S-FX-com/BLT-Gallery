<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Albums-as-taxonomy storage.
 *
 * Albums are stored as a single option, `bltgallery_albums`, holding an
 * array of `[ 'slug' => '…', 'name' => '…', 'description' => '…' ]`. A
 * gallery's membership is stored in its own `settings.albums` array of
 * slugs (multi-select). This keeps album operations cheap (one option
 * read) without needing a join table.
 *
 * Backwards compat: pre-3.2 galleries used `settings.category` (single
 * slug). `Gallery::album_slugs()` normalises both shapes.
 */
final class AlbumRepository {

	private const OPTION = 'bltgallery_albums';

	/**
	 * @return array<int, array{slug:string,name:string,description:string}>
	 */
	public static function all(): array {
		$raw = get_option( self::OPTION, [] );
		if ( ! is_array( $raw ) ) {
			return [];
		}
		// Normalise older shapes.
		$out = [];
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) || empty( $row['slug'] ) ) {
				continue;
			}
			$out[] = [
				'slug'        => (string) $row['slug'],
				'name'        => (string) ( $row['name']        ?? $row['slug'] ),
				'description' => (string) ( $row['description'] ?? '' ),
			];
		}
		usort( $out, static fn( $a, $b ) => strnatcasecmp( $a['name'], $b['name'] ) );
		return $out;
	}

	public static function find( string $slug ): ?array {
		$slug = sanitize_title( $slug );
		foreach ( self::all() as $album ) {
			if ( $album['slug'] === $slug ) {
				return $album;
			}
		}
		return null;
	}

	/**
	 * Create or update an album. Idempotent on slug.
	 */
	public static function save( string $name, string $slug = '', string $description = '' ): array {
		$name = sanitize_text_field( $name );
		$slug = sanitize_title( $slug ?: $name );
		$description = sanitize_textarea_field( $description );

		if ( '' === $name || '' === $slug ) {
			throw new \InvalidArgumentException( __( 'Album name is required.', 'bltgallery' ) );
		}

		$all   = self::all();
		$found = false;
		foreach ( $all as &$album ) {
			if ( $album['slug'] === $slug ) {
				$album['name']        = $name;
				$album['description'] = $description;
				$found = true;
				break;
			}
		}
		unset( $album );

		if ( ! $found ) {
			$all[] = [
				'slug'        => $slug,
				'name'        => $name,
				'description' => $description,
			];
		}

		update_option( self::OPTION, array_values( $all ), false );
		return self::find( $slug ) ?? [ 'slug' => $slug, 'name' => $name, 'description' => $description ];
	}

	public static function delete( string $slug ): bool {
		$slug = sanitize_title( $slug );
		$all  = self::all();
		$next = array_values( array_filter( $all, static fn( $a ) => $a['slug'] !== $slug ) );
		if ( count( $next ) === count( $all ) ) {
			return false;
		}
		update_option( self::OPTION, $next, false );

		// Best-effort: detach this album from every gallery's settings.
		foreach ( GalleryRepository::all( 500, 1 ) as $gallery ) {
			if ( empty( $gallery->settings['albums'] ) || ! is_array( $gallery->settings['albums'] ) ) {
				continue;
			}
			$filtered = array_values( array_filter( $gallery->settings['albums'], static fn( $s ) => $s !== $slug ) );
			if ( $filtered !== $gallery->settings['albums'] ) {
				$gallery->settings['albums'] = $filtered;
				GalleryRepository::save( $gallery );
			}
		}

		return true;
	}

	/**
	 * Replace the set of galleries assigned to an album.
	 *
	 * Galleries whose id appears in $gallery_ids gain this album's slug;
	 * galleries that currently carry the slug but are absent from the list
	 * lose it. Any *other* album each gallery belongs to is left untouched.
	 * Returns the number of galleries whose membership actually changed.
	 *
	 * @param int[]|string[] $gallery_ids
	 */
	public static function set_galleries( string $slug, array $gallery_ids ): int {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return 0;
		}

		$wanted  = array_map( 'intval', $gallery_ids );
		$changed = 0;

		foreach ( GalleryRepository::all( 500, 1 ) as $gallery ) {
			$current = self::gallery_album_slugs( $gallery );
			$has     = in_array( $slug, $current, true );
			$want    = in_array( $gallery->id, $wanted, true );

			if ( $has === $want ) {
				continue;
			}

			if ( $want ) {
				$current[] = $slug;
			} else {
				$current = array_filter( $current, static fn( $s ) => $s !== $slug );
			}

			// Persist on the modern `albums` array and drop the legacy
			// single-slug `category` key so counts don't double up.
			$gallery->settings['albums'] = array_values( array_unique( $current ) );
			unset( $gallery->settings['category'] );
			GalleryRepository::save( $gallery );
			$changed++;
		}

		return $changed;
	}

	/**
	 * Normalise a gallery's album membership across the modern `albums`
	 * array and the legacy single `category` slug.
	 *
	 * @return string[]
	 */
	private static function gallery_album_slugs( \BltGallery\Models\Gallery $gallery ): array {
		$albums = $gallery->settings['albums'] ?? null;
		if ( is_array( $albums ) ) {
			return array_values( array_filter( array_map( 'strval', $albums ) ) );
		}
		$legacy = (string) ( $gallery->settings['category'] ?? '' );
		return '' !== $legacy ? [ $legacy ] : [];
	}

	/**
	 * Auto-register any slug a gallery references but the user hasn't
	 * formally defined yet. Keeps the Albums page complete even after a
	 * NextGEN migration or a shortcode that referenced a new category.
	 */
	public static function ensure_slug( string $slug, string $fallback_name = '' ): void {
		$slug = sanitize_title( $slug );
		if ( '' === $slug || self::find( $slug ) ) {
			return;
		}
		self::save( $fallback_name ?: ucwords( str_replace( '-', ' ', $slug ) ), $slug );
	}
}
