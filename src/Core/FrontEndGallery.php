<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Gallery;

/**
 * Policy + lookup for the front-end gallery feature: logged-in visitors in
 * an admin-chosen role uploading into a gallery of their own.
 *
 * Every visitor gets at most one such gallery, found (or created) by a
 * deterministic slug — `user-{wp_user_id}` — rather than a new "owner"
 * column. That slug is also the security boundary the REST layer relies on:
 * FrontEndGalleryEndpoint never accepts a gallery id from the client, it
 * only ever resolves "the current user's gallery" through here.
 */
class FrontEndGallery {

	const SLUG_PREFIX = 'user-';

	// ------------------------------------------------------------------
	// Settings
	// ------------------------------------------------------------------

	public static function is_enabled(): bool {
		$settings = get_option( 'bltgallery_settings', [] );
		return is_array( $settings ) && ! empty( $settings['enable_front_end_gallery'] );
	}

	/**
	 * @return string[] Role slugs allowed to use the feature.
	 */
	public static function allowed_roles(): array {
		$settings = get_option( 'bltgallery_settings', [] );
		$roles    = is_array( $settings ) ? ( $settings['front_end_gallery_roles'] ?? [] ) : [];
		return is_array( $roles ) ? array_values( array_filter( $roles ) ) : [];
	}

	public static function image_limit(): int {
		$settings = get_option( 'bltgallery_settings', [] );
		$limit    = is_array( $settings ) ? (int) ( $settings['front_end_gallery_limit'] ?? 20 ) : 20;
		return max( 1, $limit );
	}

	/**
	 * Enabled, logged in, and in one of the admin-chosen roles. Fails closed
	 * if the admin turned the feature on but hasn't picked any roles yet.
	 */
	public static function current_user_allowed(): bool {
		if ( ! self::is_enabled() || ! is_user_logged_in() ) {
			return false;
		}

		$allowed = self::allowed_roles();
		if ( ! $allowed ) {
			return false;
		}

		return (bool) array_intersect( $allowed, (array) wp_get_current_user()->roles );
	}

	// ------------------------------------------------------------------
	// Gallery lookup
	// ------------------------------------------------------------------

	public static function slug_for_user( int $user_id ): string {
		return self::SLUG_PREFIX . $user_id;
	}

	/**
	 * Find the current user's front-end gallery, creating it on first use
	 * when $create is true. Never touches any other gallery — a user's own
	 * row is always looked up by their own deterministic slug, never by an
	 * id supplied by the request.
	 */
	public static function resolve_gallery_for_current_user( bool $create = false ): ?Gallery {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return null;
		}

		$slug    = self::slug_for_user( $user_id );
		$gallery = GalleryRepository::find_by_slug( $slug );
		if ( $gallery || ! $create ) {
			return $gallery;
		}

		$user = get_userdata( $user_id );

		// user_login (the username), not display_name — a visitor can change
		// their display name at any time from their own profile, which would
		// otherwise make the gallery title drift out of sync with who it
		// actually is; the login name is stable.
		$gallery                = new Gallery();
		$gallery->title         = sprintf(
			/* translators: %s: the visitor's username */
			__( "%s's Gallery", 'bltgallery' ),
			$user ? $user->user_login : __( 'My', 'bltgallery' )
		);
		$gallery->slug          = $slug;
		$gallery->display_type  = 'masonry';
		$gallery->author_id     = $user_id;
		// Purely descriptive — lets the admin Galleries list recognise these
		// later without needing a slug-prefix convention to leak into the UI.
		$gallery->settings      = [ 'front_end' => true ];

		$gallery = GalleryRepository::save( $gallery );

		// Two first-ever requests from the same user racing each other both
		// pass the find above, then both try to insert the same unique slug;
		// the loser's insert fails and leaves $gallery->id at 0. Rather than
		// error, just use the winner's row — it's the same gallery either way.
		if ( ! $gallery->id ) {
			$gallery = GalleryRepository::find_by_slug( $slug );
		}

		return $gallery;
	}

	public static function remaining_uploads( Gallery $gallery ): int {
		return max( 0, self::image_limit() - ImageRepository::count_by_gallery( $gallery->id ) );
	}
}
