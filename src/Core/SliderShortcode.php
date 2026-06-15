<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Display\SliderDisplay;
use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * [blt_slider] – build a lightweight image slider from any mix of sources and
 * drop it anywhere on the site. Images can come from existing galleries, from
 * specific gallery images, and/or directly from the WordPress media library —
 * all delivered through the same Cloudflare optimisation pipeline the rest of
 * the plugin uses.
 *
 * Examples:
 *   [blt_slider galleries="5"]
 *   [blt_slider galleries="5,7" autoplay="1" speed="6000"]
 *   [blt_slider attachments="123,456,789"]
 *   [blt_slider galleries="5" attachments="123" images="44,45" order="menu"]
 *   [blt_slider galleries="5" arrows="0" dots="1" captions="off" loop="0"]
 *   [blt_slider attachments="12,13" radius="12" height="70vh" class="my-hero"]
 *
 * Supported attributes (all optional; supply at least one source):
 *   galleries    – comma-separated gallery IDs whose images feed the slider
 *   gallery      – alias for `galleries`
 *   slugs        – comma-separated gallery slugs (alternative to galleries)
 *   images       – comma-separated Blt image IDs (specific gallery images)
 *   attachments  – comma-separated WordPress media library attachment IDs
 *   title        – accessible label for the carousel
 *   captions     – "on" (default) | "off"
 *   arrows       – "1" (default) | "0"   show hover nav arrows
 *   dots         – "1" (default) | "0"   show the dot counter
 *   autoplay     – "1" | "0" (default)
 *   speed        – ms between slides when autoplaying (default 5000)
 *   loop         – "1" (default) | "0"   wrap from last slide back to first
 *   height       – CSS max-height for slides, e.g. "70vh" or "480px"
 *   radius       – border-radius in px
 *   limit        – cap the number of slides rendered
 *   order        – "menu" (default) | "random" | "reverse"
 *   class        – extra CSS class on the wrapping div
 *   style        – extra inline style on the wrapping div
 */
class SliderShortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_slider' ): string {
		$atts = shortcode_atts(
			[
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

		$images = $this->resolve_images( $atts );
		$images = $this->apply_query_modifiers( $images, $atts );

		if ( empty( $images ) ) {
			return '<!-- blt_slider: no images matched -->';
		}

		wp_enqueue_style( 'bltgallery-frontend' );
		wp_enqueue_script( 'bltgallery-frontend' );

		$gallery = $this->build_virtual_gallery( $atts );
		$display = new SliderDisplay();

		ob_start();
		echo $this->open_outer_wrapper( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$display->render( $gallery, $images );
		echo '</div>';
		return (string) ob_get_clean();
	}

	/**
	 * Collect images from every requested source, preserving source order:
	 * galleries first, then explicit Blt image IDs, then media attachments.
	 * Blt images are de-duplicated by ID so overlapping sources don't repeat.
	 *
	 * @return Image[]
	 */
	private function resolve_images( array $atts ): array {
		$images = [];
		$seen   = []; // Blt image IDs already added.

		// 1. Whole galleries (by ID, then by slug).
		foreach ( $this->resolve_gallery_ids( $atts ) as $gallery_id ) {
			foreach ( ImageRepository::find_by_gallery( $gallery_id ) as $image ) {
				if ( ! isset( $seen[ $image->id ] ) ) {
					$seen[ $image->id ] = true;
					$images[]           = $image;
				}
			}
		}

		// 2. Specific Blt gallery images.
		foreach ( $this->parse_ids( $atts['images'] ) as $image_id ) {
			if ( isset( $seen[ $image_id ] ) ) {
				continue;
			}
			$image = ImageRepository::find( $image_id );
			if ( $image ) {
				$seen[ $image_id ] = true;
				$images[]          = $image;
			}
		}

		// 3. Direct WordPress media library attachments.
		foreach ( $this->parse_ids( $atts['attachments'] ) as $att_id ) {
			$image = $this->image_from_attachment( $att_id );
			if ( $image ) {
				$images[] = $image;
			}
		}

		return $images;
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
	 * Wrap a WordPress media library attachment in an Image value object so it
	 * flows through the same render path (and Cloudflare URL rewriting) as
	 * gallery images. The full-size URL is stored as `cloudfront_url` purely so
	 * Image::get_url() returns it; thumbnail sizes are mapped from WordPress's
	 * generated sizes for a working srcset when Cloudflare resizing is off.
	 */
	private function image_from_attachment( int $att_id ): ?Image {
		if ( $att_id <= 0 || ! wp_attachment_is_image( $att_id ) ) {
			return null;
		}

		$full = wp_get_attachment_image_url( $att_id, 'full' );
		if ( ! $full ) {
			return null;
		}

		$meta = wp_get_attachment_metadata( $att_id );

		$image                 = new Image();
		$image->cloudfront_url = $full;
		$image->alt_text       = (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true );
		$image->caption        = (string) wp_get_attachment_caption( $att_id );
		$image->width          = (int) ( $meta['width'] ?? 0 );
		$image->height         = (int) ( $meta['height'] ?? 0 );
		$image->mime_type      = (string) get_post_mime_type( $att_id );
		$image->filename       = wp_basename( (string) ( get_attached_file( $att_id ) ?: $full ) );

		// Map our three logical sizes onto the best available WP size, falling
		// back to the plugin's registered sizes and finally the full image.
		$thumbs    = [];
		$size_map  = [
			'thumb'  => [ 'bltgallery-thumb', 'medium' ],
			'medium' => [ 'bltgallery-medium', 'large' ],
			'large'  => [ 'bltgallery-large', 'full' ],
		];
		foreach ( $size_map as $logical => $candidates ) {
			foreach ( $candidates as $wp_size ) {
				$src = wp_get_attachment_image_src( $att_id, $wp_size );
				if ( $src ) {
					$thumbs[ $logical ] = [
						'url'    => $src[0],
						'width'  => (int) $src[1],
						'height' => (int) $src[2],
					];
					break;
				}
			}
		}
		$image->meta = [ 'thumbs' => $thumbs ];

		return $image;
	}

	/**
	 * @param Image[] $images
	 * @return Image[]
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

	private function build_virtual_gallery( array $atts ): Gallery {
		$gallery               = new Gallery();
		$gallery->display_type = 'slider';
		$gallery->title        = '' !== trim( (string) $atts['title'] )
			? sanitize_text_field( $atts['title'] )
			: __( 'Image slider', 'bltgallery' );

		$settings = [];
		if ( '' !== $atts['captions'] ) {
			$settings['captions'] = '0' === (string) $atts['captions'] || 'off' === sanitize_key( (string) $atts['captions'] ) ? 'off' : 'on';
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

		$gallery->settings = $settings;

		return $gallery;
	}

	private function open_outer_wrapper( array $atts ): string {
		$extra_class = trim( (string) $atts['class'] );
		$extra_style = trim( (string) $atts['style'] );

		// A `height` attribute drives the per-slide max-height via a custom prop.
		$height = trim( (string) $atts['height'] );
		if ( '' !== $height && preg_match( '/^[0-9.]+(px|vh|vw|rem|em|%)$/', $height ) ) {
			$prop        = '--blt-slider-height:' . $height;
			$extra_style = '' === $extra_style ? $prop : rtrim( $extra_style, ';' ) . '; ' . $prop;
		}

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
