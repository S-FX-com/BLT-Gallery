<?php

declare( strict_types=1 );

namespace ZymGallery\Display;

use ZymGallery\Models\Gallery;
use ZymGallery\Models\Image;

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
	 * Build a <img> or <picture> tag with lazy loading and srcset.
	 */
	protected function img_tag( Image $image, string $size = 'medium', bool $lazy = true ): string {
		$src    = esc_url( $image->get_thumb_url( $size ) );
		$srcset = $this->build_srcset( $image );
		$alt    = esc_attr( $image->alt_text ?: $image->filename );
		$w      = (int) ( $image->meta['thumbs'][ $size ]['width'] ?? $image->width );
		$h      = (int) ( $image->meta['thumbs'][ $size ]['height'] ?? $image->height );
		$lazy   = $lazy ? ' loading="lazy" decoding="async"' : '';

		$srcset_attr = $srcset ? ' srcset="' . esc_attr( $srcset ) . '"' : '';

		return sprintf(
			'<img src="%s"%s sizes="(max-width: 600px) 100vw, 50vw" alt="%s" width="%d" height="%d"%s>',
			$src,
			$srcset_attr,
			$alt,
			$w,
			$h,
			$lazy
		);
	}

	protected function build_srcset( Image $image ): string {
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
	 */
	protected function open_container( Gallery $gallery ): void {
		$settings = wp_json_encode( $gallery->settings );
		printf(
			'<div class="zymgallery zymgallery--%s" id="zymgallery-%d" data-type="%s" data-settings="%s" role="region" aria-label="%s">',
			esc_attr( $this->get_id() ),
			(int) $gallery->id,
			esc_attr( $this->get_id() ),
			esc_attr( $settings ),
			esc_attr( $gallery->title )
		);
	}

	protected function close_container(): void {
		echo '</div>';
	}

	protected function no_images_notice(): void {
		echo '<p class="zymgallery__empty">' . esc_html__( 'No images found in this gallery.', 'zymgallery' ) . '</p>';
	}
}
