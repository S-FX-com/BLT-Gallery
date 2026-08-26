<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * [blt_user_gallery] – display a specific WordPress user's front-end
 * gallery, by their user ID rather than a gallery id/slug.
 *
 * Built for templates that already know *which* user they're showing (a
 * member profile page, a directory), where hard-coding that user's gallery
 * id/slug isn't an option:
 *
 *   [blt_user_gallery user_id="123"]
 *   [blt_user_gallery user_id="123" type="tile" cols="4"]
 *
 * Typically used from PHP with a dynamic id rather than typed into content:
 *
 *   echo do_shortcode( '[blt_user_gallery user_id="' . (int) $profile_user_id . '"]' );
 *
 * Unlike [blt_my_gallery], this is pure display — no upload widget, no
 * login requirement, and no dependency on the front-end gallery feature
 * being currently enabled (disabling new uploads shouldn't blank out
 * already-public profile pages). Shows a visible "No gallery exists for
 * this user." notice when user_id is missing, invalid, or that user has
 * never created a gallery — a template author should be able to tell
 * that from the rendered page, rather than an empty section with no
 * explanation.
 *
 * All of [blt_gallery]'s display-customisation attributes (type, cols,
 * gap, radius, captions, lightbox, pagination, limit, order, class,
 * style, …) work here too — see Shortcode::display_atts().
 */
class UserGalleryShortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_user_gallery' ): string {
		$atts = shortcode_atts(
			array_merge( Shortcode::display_atts(), [ 'user_id' => 0 ] ),
			$atts,
			$tag
		);

		$user_id = (int) $atts['user_id'];
		$gallery = $user_id > 0 ? GalleryRepository::find_by_slug( FrontEndGallery::slug_for_user( $user_id ) ) : null;

		if ( ! $gallery ) {
			return $this->no_gallery_notice();
		}

		return ( new Shortcode() )->render_gallery( $gallery, $atts );
	}

	private function no_gallery_notice(): string {
		wp_enqueue_style( 'bltgallery-frontend' );

		// Same .bltgallery__empty class AbstractDisplay::no_images_notice()
		// uses for "no images in this (existing) gallery" — this is the
		// "no gallery at all" counterpart, so it should look the same.
		return '<p class="bltgallery__empty">' . esc_html__( 'No gallery exists for this user.', 'bltgallery' ) . '</p>';
	}
}
