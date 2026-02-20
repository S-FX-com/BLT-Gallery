<?php

declare( strict_types=1 );

namespace ZymGallery\Core;

use ZymGallery\Models\Gallery;

/**
 * Handles the [zymgallery] shortcode.
 *
 * Usage:
 *   [zymgallery id="5"]
 *   [zymgallery id="5" type="slideshow"]
 *   [zymgallery slug="my-gallery" type="masonry"]
 */
class Shortcode {

	public function render( array $atts ): string {
		$atts = shortcode_atts(
			[
				'id'   => 0,
				'slug' => '',
				'type' => '',       // override the gallery's stored display_type
			],
			$atts,
			'zymgallery'
		);

		$gallery = null;

		if ( ! empty( $atts['id'] ) ) {
			$gallery = GalleryRepository::find( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$gallery = GalleryRepository::find_by_slug( sanitize_title( $atts['slug'] ) );
		}

		if ( ! $gallery instanceof Gallery ) {
			return '<!-- ZymGallery: gallery not found -->';
		}

		$display_type = ! empty( $atts['type'] ) ? sanitize_key( $atts['type'] ) : $gallery->display_type;
		$display      = \ZymGallery\Core\Plugin::make_display( $display_type );

		if ( null === $display ) {
			return '<!-- ZymGallery: unknown display type "' . esc_html( $display_type ) . '" -->';
		}

		// Enqueue the assets required by this display type.
		wp_enqueue_style( 'zymgallery-frontend' );
		wp_enqueue_script( 'zymgallery-frontend' );

		$images = ImageRepository::find_by_gallery( $gallery->id );

		ob_start();
		$display->render( $gallery, $images );
		return ob_get_clean();
	}
}
