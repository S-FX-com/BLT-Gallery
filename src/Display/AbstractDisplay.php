<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Base class every display type extends.
 */
abstract class AbstractDisplay {

	/**
	 * Unique identifier used in CSS classes and data attributes.
	 */
	abstract public function get_id(): string;

	/**
	 * Render the gallery HTML directly (caller uses output buffering).
	 *
	 * @param Gallery $gallery
	 * @param Image[] $images
	 */
	abstract public function render( Gallery $gallery, array $images ): void;

	// ------------------------------------------------------------------
	// Shared helpers
	// ------------------------------------------------------------------

	/**
	 * Build a <img> tag with lazy loading, srcset, and CF-aware sources.
	 *
	 * The first image rendered per request gets fetchpriority="high" to
	 * improve LCP; everything after that gets loading="lazy".
	 */
	protected function img_tag( Image $image, string $size = 'medium', bool $lazy = true ): string {
		static $rendered = 0;
		$rendered++;

		$src    = esc_url( $image->get_thumb_url( $size ) );
		$srcset = $this->build_srcset( $image );
		$alt    = esc_attr( $image->alt_text ?: $image->filename );
		$w      = (int) ( $image->meta['thumbs'][ $size ]['width'] ?? $image->width );
		$h      = (int) ( $image->meta['thumbs'][ $size ]['height'] ?? $image->height );

		$loading       = ( $lazy && $rendered > 1 ) ? ' loading="lazy"' : '';
		$decoding      = ' decoding="async"';
		$fetchpriority = ( 1 === $rendered && ! $lazy ) ? ' fetchpriority="high"' : '';

		$srcset_attr = $srcset ? ' srcset="' . esc_attr( $srcset ) . '"' : '';

		return sprintf(
			'<img src="%s"%s sizes="(max-width: 600px) 100vw, 50vw" alt="%s" width="%d" height="%d"%s%s%s>',
			$src,
			$srcset_attr,
			$alt,
			$w,
			$h,
			$loading,
			$decoding,
			$fetchpriority
		);
	}

	protected function build_srcset( Image $image ): string {
		// When Cloudflare resizing is on, use its native srcset (more widths,
		// no need to bake thumbnails).
		if ( \BltGallery\Storage\CloudflareImages::is_enabled() ) {
			return \BltGallery\Storage\CloudflareImages::srcset( $image->get_url() );
		}

		$entries = [];
		foreach ( [ 'thumb' => 320, 'medium' => 800, 'large' => 1600 ] as $size => $width ) {
			$url = $image->get_thumb_url( $size );
			if ( $url ) {
				$entries[] = esc_url( $url ) . " {$width}w";
			}
		}
		return implode( ', ', $entries );
	}

	/**
	 * Wrap the gallery in a standard container div with data attributes the JS picks up.
	 * Inline CSS custom properties expose per-shortcode style overrides.
	 */
	protected function open_container( Gallery $gallery ): void {
		$settings = wp_json_encode( $gallery->settings );

		$style_pairs = [];
		if ( isset( $gallery->settings['radius'] ) ) {
			$style_pairs[] = '--blt-radius:' . (int) $gallery->settings['radius'] . 'px';
		}
		if ( isset( $gallery->settings['gutter'] ) ) {
			$gutter = (int) $gallery->settings['gutter'];
			$style_pairs[] = '--blt-gap:' . $gutter . 'px';
			$style_pairs[] = '--blt-gutter:' . $gutter . 'px';
		}
		if ( isset( $gallery->settings['columns'] ) ) {
			$style_pairs[] = '--blt-cols:' . (int) $gallery->settings['columns'];
		}
		$style_attr = $style_pairs ? ' style="' . esc_attr( implode( '; ', $style_pairs ) ) . '"' : '';

		$captions = sanitize_key( (string) ( $gallery->settings['captions'] ?? '' ) );
		$caption_mod = $captions ? ' bltgallery--captions-' . $captions : '';

		printf(
			'<div class="bltgallery bltgallery--%s%s" id="bltgallery-%d" data-type="%s" data-settings="%s"%s role="region" aria-label="%s">',
			esc_attr( $this->get_id() ),
			esc_attr( $caption_mod ),
			(int) $gallery->id,
			esc_attr( $this->get_id() ),
			esc_attr( $settings ),
			$style_attr, // already escaped
			esc_attr( $gallery->title )
		);
	}

	protected function close_container(): void {
		echo '</div>';
	}

	protected function no_images_notice(): void {
		echo '<p class="bltgallery__empty">' . esc_html__( 'No images found in this gallery.', 'bltgallery' ) . '</p>';
	}
}
