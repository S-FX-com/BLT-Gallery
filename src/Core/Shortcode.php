<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Gallery;

/**
 * [blt_gallery] – render a single gallery with inline styling overrides.
 *
 * Examples:
 *   [blt_gallery id="5"]
 *   [blt_gallery id="5" type="slideshow"]
 *   [blt_gallery slug="my-gallery" type="masonry" cols="4" gap="16"]
 *   [blt_gallery id="5" type="tile" cols="5" gap="8" captions="hover" lightbox="1" radius="12"]
 *   [blt_gallery id="5" type="slideshow" autoplay="1" speed="4000" arrows="1" dots="1"]
 *   [blt_gallery id="5" class="my-custom-wrap" style="background:#000;padding:24px"]
 *
 * Supported attributes (all optional except id/slug):
 *   id        – numeric gallery ID
 *   slug      – gallery slug (used when id is not given)
 *   type      – masonry | tile | slideshow | lightbox  (overrides stored display_type)
 *   cols      – column count (integer 1-8)             [masonry, tile, lightbox]
 *   gap       – gutter in px between items             [all grid types]
 *   radius    – CSS border-radius in px for each item
 *   captions  – "below" | "hover" | "off"
 *   lightbox  – "1" or "0"
 *   autoplay  – "1" or "0"                             [slideshow]
 *   speed     – ms between slides                      [slideshow]
 *   arrows    – "1" or "0"                             [slideshow]
 *   dots      – "1" or "0"                             [slideshow]
 *   limit     – cap number of images rendered
 *   order     – "menu" | "date" | "random"
 *   class     – additional CSS class on the wrapping div
 *   style     – additional inline style on the wrapping div
 */
class Shortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_gallery' ): string {
		$atts = shortcode_atts(
			[
				'id'       => 0,
				'slug'     => '',
				'type'     => '',
				'cols'     => '',
				'gap'      => '',
				'radius'   => '',
				'size'     => '', // small | medium | large | xlarge
				'thumb_min'=> '', // raw px override
				'captions' => '',
				'lightbox' => '',
				'autoplay' => '',
				'speed'    => '',
				'arrows'   => '',
				'dots'     => '',
				'limit'    => '',
				'order'    => '',
				'class'    => '',
				'style'    => '',
			],
			$atts,
			$tag
		);

		$gallery = null;
		if ( ! empty( $atts['id'] ) ) {
			$gallery = GalleryRepository::find( (int) $atts['id'] );
		} elseif ( ! empty( $atts['slug'] ) ) {
			$gallery = GalleryRepository::find_by_slug( sanitize_title( $atts['slug'] ) );
		}

		if ( ! $gallery instanceof Gallery ) {
			return '<!-- blt_gallery: gallery not found -->';
		}

		$gallery->settings = $this->merge_settings( $gallery->settings, $atts );

		$display_type = ! empty( $atts['type'] )
			? sanitize_key( $atts['type'] )
			: $gallery->display_type;

		$display = Plugin::make_display( $display_type );
		if ( null === $display ) {
			return '<!-- blt_gallery: unknown display type "' . esc_html( $display_type ) . '" -->';
		}

		wp_enqueue_style( 'bltgallery-frontend' );
		wp_enqueue_script( 'bltgallery-frontend' );

		$images = ImageRepository::find_by_gallery( $gallery->id );
		$images = $this->apply_query_modifiers( $images, $atts );

		ob_start();
		echo $this->open_outer_wrapper( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$display->render( $gallery, $images );
		echo '</div>';
		return (string) ob_get_clean();
	}

	private function merge_settings( array $settings, array $atts ): array {
		$map = [
			'cols'      => 'columns',
			'gap'       => 'gutter',
			'radius'    => 'radius',
			'size'      => 'thumbnail_size',
			'thumb_min' => 'thumb_min',
			'captions'  => 'captions',
			'lightbox'  => 'lightbox',
			'autoplay'  => 'autoplay',
			'speed'     => 'speed',
			'arrows'    => 'arrows',
			'dots'      => 'dots',
		];

		foreach ( $map as $att => $key ) {
			if ( '' === $atts[ $att ] || null === $atts[ $att ] ) {
				continue;
			}
			$settings[ $key ] = in_array( $att, [ 'cols', 'gap', 'radius', 'speed', 'thumb_min' ], true )
				? (int) $atts[ $att ]
				: $atts[ $att ];
		}

		return $settings;
	}

	private function apply_query_modifiers( array $images, array $atts ): array {
		if ( ! empty( $atts['order'] ) ) {
			switch ( sanitize_key( $atts['order'] ) ) {
				case 'random':
					shuffle( $images );
					break;
				case 'date':
					usort( $images, static fn( $a, $b ) => strcmp( (string) $b->created_at, (string) $a->created_at ) );
					break;
			}
		}

		if ( '' !== $atts['limit'] && (int) $atts['limit'] > 0 ) {
			$images = array_slice( $images, 0, (int) $atts['limit'] );
		}

		return $images;
	}

	private function open_outer_wrapper( array $atts ): string {
		$extra_class = trim( (string) $atts['class'] );
		$extra_style = trim( (string) $atts['style'] );

		return sprintf(
			'<div class="bltgallery-shortcode%s"%s>',
			$extra_class ? ' ' . esc_attr( $extra_class ) : '',
			$extra_style ? ' style="' . esc_attr( $extra_style ) . '"' : ''
		);
	}
}
