<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Display\SliderDisplay;
use BltGallery\Models\Gallery;
use BltGallery\Models\Slider;

/**
 * [blt_slider] – render an image slider.
 *
 * The primary path renders a slider built in the admin (Blt Gallery →
 * Sliders), referenced by id or slug:
 *
 *   [blt_slider id="3"]
 *   [blt_slider slug="homepage-hero"]
 *
 * Any saved option can be overridden per placement, e.g.
 *   [blt_slider id="3" autoplay="1" speed="6000" height="60vh"]
 *
 * An ad-hoc path is also supported for quick, code-only sliders assembled
 * straight from sources without saving anything:
 *
 *   [blt_slider galleries="5,7"]
 *   [blt_slider attachments="123,456"]
 *   [blt_slider galleries="5" attachments="123" images="44,45"]
 *
 * Supported attributes:
 *   id           – saved slider ID (primary)
 *   slug         – saved slider slug (alternative to id)
 *   galleries    – comma-separated gallery IDs (ad-hoc)
 *   gallery      – alias for `galleries`
 *   slugs        – comma-separated gallery slugs (ad-hoc)
 *   images       – comma-separated Blt image IDs (ad-hoc)
 *   attachments  – comma-separated WP media attachment IDs (ad-hoc)
 *   title        – accessible label for the carousel (ad-hoc)
 *   captions     – "on" | "off"
 *   arrows       – "1" | "0"      show hover nav arrows
 *   dots         – "1" | "0"      show the dot counter
 *   autoplay     – "1" | "0"
 *   speed        – ms between slides when autoplaying
 *   loop         – "1" | "0"      wrap from last slide back to first
 *   height       – CSS max-height for slides, e.g. "70vh" or "480px"
 *   radius       – border-radius in px
 *   limit        – cap the number of slides rendered
 *   order        – "menu" | "random" | "reverse"
 *   class        – extra CSS class on the wrapping div
 *   style        – extra inline style on the wrapping div
 */
class SliderShortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_slider' ): string {
		$atts = shortcode_atts(
			[
				'id'          => '',
				'slug'        => '',
				'galleries'   => '',
				'gallery'     => '',
				'slugs'       => '',
				'images'      => '',
				'attachments' => '',
				'title'       => '',
				'captions'    => '',
				'arrows'      => '',
				'dots'        => '',
				'autoplay'    => '',
				'speed'       => '',
				'loop'        => '',
				'height'      => '',
				'radius'      => '',
				'limit'       => '',
				'order'       => '',
				'class'       => '',
				'style'       => '',
			],
			$atts,
			$tag
		);

		$slider = $this->resolve_slider( $atts );

		// A saved slider supplies its own title/settings; an ad-hoc one is
		// assembled entirely from attributes.
		if ( $slider instanceof Slider ) {
			$items    = $slider->items;
			$settings = $slider->settings;
			$title    = $slider->title;
		} elseif ( null === $slider ) {
			// id/slug was given but no matching slider exists.
			return '<!-- blt_slider: slider not found -->';
		} else {
			$items    = $this->build_adhoc_items( $atts );
			$settings = [];
			$title    = '' !== trim( (string) $atts['title'] ) ? sanitize_text_field( $atts['title'] ) : '';
		}

		$images = SliderResolver::to_images( $items );
		$images = $this->apply_query_modifiers( $images, $atts );

		if ( empty( $images ) ) {
			return '<!-- blt_slider: no images matched -->';
		}

		wp_enqueue_style( 'bltgallery-frontend' );
		wp_enqueue_script( 'bltgallery-frontend' );

		$gallery     = $this->build_virtual_gallery( $title, $settings, $atts );
		$gallery->id = $slider instanceof Slider ? $slider->id : 0;
		$display     = new SliderDisplay();

		ob_start();
		echo $this->open_outer_wrapper( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$display->render( $gallery, $images );
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Locate a saved slider when id/slug is supplied.
	 *
	 * @return Slider|false|null  Slider when found, false when no id/slug was
	 *                            given (ad-hoc mode), null when id/slug given
	 *                            but no slider matched.
	 */
	private function resolve_slider( array $atts ): Slider|false|null {
		if ( '' !== trim( (string) $atts['id'] ) ) {
			return SliderRepository::find( (int) $atts['id'] );
		}
		if ( '' !== trim( (string) $atts['slug'] ) ) {
			return SliderRepository::find_by_slug( sanitize_title( $atts['slug'] ) );
		}
		return false;
	}

	/**
	 * Build slide descriptors from the ad-hoc source attributes, preserving
	 * source order: galleries first, then explicit Blt image IDs, then media
	 * attachments.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function build_adhoc_items( array $atts ): array {
		$items = [];

		foreach ( $this->resolve_gallery_ids( $atts ) as $gallery_id ) {
			$items[] = [ 'source' => 'gallery', 'ref' => $gallery_id ];
		}
		foreach ( $this->parse_ids( $atts['images'] ) as $image_id ) {
			$items[] = [ 'source' => 'image', 'ref' => $image_id ];
		}
		foreach ( $this->parse_ids( $atts['attachments'] ) as $att_id ) {
			$items[] = [ 'source' => 'attachment', 'ref' => $att_id ];
		}

		return $items;
	}

	/**
	 * @return int[]
	 */
	private function resolve_gallery_ids( array $atts ): array {
		$ids = $this->parse_ids( $atts['galleries'] ?: $atts['gallery'] );

		if ( ! empty( $atts['slugs'] ) ) {
			foreach ( array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', (string) $atts['slugs'] ) ) ) ) as $slug ) {
				$gallery = GalleryRepository::find_by_slug( $slug );
				if ( $gallery ) {
					$ids[] = $gallery->id;
				}
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * @param \BltGallery\Models\Image[] $images
	 * @return \BltGallery\Models\Image[]
	 */
	private function apply_query_modifiers( array $images, array $atts ): array {
		switch ( sanitize_key( (string) $atts['order'] ) ) {
			case 'random':
				shuffle( $images );
				break;
			case 'reverse':
				$images = array_reverse( $images );
				break;
		}

		if ( '' !== $atts['limit'] && (int) $atts['limit'] > 0 ) {
			$images = array_slice( $images, 0, (int) $atts['limit'] );
		}

		return $images;
	}

	/**
	 * Merge stored settings with per-placement attribute overrides into the
	 * virtual gallery handed to SliderDisplay.
	 */
	private function build_virtual_gallery( string $title, array $settings, array $atts ): Gallery {
		$gallery               = new Gallery();
		$gallery->display_type = 'slider';
		$gallery->title        = '' !== trim( $title ) ? $title : __( 'Image slider', 'bltgallery' );

		if ( '' !== $atts['captions'] ) {
			$settings['captions'] = ( '0' === (string) $atts['captions'] || 'off' === sanitize_key( (string) $atts['captions'] ) ) ? 'off' : 'on';
		}
		if ( '' !== $atts['arrows'] ) {
			$settings['arrows'] = $atts['arrows'];
		}
		if ( '' !== $atts['dots'] ) {
			$settings['dots'] = $atts['dots'];
		}
		if ( '' !== $atts['autoplay'] ) {
			$settings['autoplay'] = '0' !== (string) $atts['autoplay'];
		}
		if ( '' !== $atts['speed'] ) {
			$settings['speed'] = (int) $atts['speed'];
		}
		if ( '' !== $atts['loop'] ) {
			$settings['loop'] = $atts['loop'];
		}
		if ( '' !== $atts['radius'] ) {
			$settings['radius'] = (int) $atts['radius'];
		}
		// A `height` attribute overrides any saved height. Validated against a
		// CSS length whitelist; SliderDisplay emits it as --blt-slider-height.
		$height = trim( (string) $atts['height'] );
		if ( '' !== $height && preg_match( '/^[0-9.]+(px|vh|vw|rem|em|%)$/', $height ) ) {
			$settings['height'] = $height;
		}

		$gallery->settings = $settings;

		return $gallery;
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

	/**
	 * @return int[]
	 */
	private function parse_ids( string $raw ): array {
		if ( '' === trim( $raw ) ) {
			return [];
		}
		return array_values( array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $raw ) ) ), static fn( $id ) => $id > 0 ) );
	}
}
