<?php

declare( strict_types=1 );

namespace ZymGallery\Display;

use ZymGallery\Models\Gallery;
use ZymGallery\Models\Image;

/**
 * Lightbox display type.
 *
 * Renders a thumbnail grid where every image opens a full-screen lightbox
 * modal driven by the lightweight zymgallery-lightbox frontend module.
 * No Galleria.js or jQuery dependency.
 */
class LightboxDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'lightbox';
	}

	public function render( Gallery $gallery, array $images ): void {
		$this->open_container( $gallery );

		if ( empty( $images ) ) {
			$this->no_images_notice();
			$this->close_container();
			return;
		}

		$columns = (int) ( $gallery->settings['columns'] ?? 4 );
		$gutter  = (int) ( $gallery->settings['gutter'] ?? 8 );

		// Thumbnail grid.
		printf(
			'<ul class="zymgallery-lightbox__grid" style="--zym-cols:%d; --zym-gutter:%dpx;" data-lightbox="1">',
			$columns,
			$gutter
		);

		foreach ( $images as $idx => $image ) {
			$this->render_thumb( $image, $idx );
		}

		echo '</ul>';

		// Hidden lightbox modal – populated by JS with full-resolution images.
		$this->render_modal( $gallery, $images );

		$this->close_container();
	}

	private function render_thumb( Image $image, int $idx ): void {
		printf(
			'<li class="zymgallery-lightbox__thumb">'
			. '<button class="zymgallery-lightbox__trigger" data-index="%d" data-image-id="%d" aria-label="%s">'
			. '%s'
			. '</button>'
			. '</li>',
			$idx,
			(int) $image->id,
			esc_attr( $image->alt_text ?: $image->filename ),
			$this->img_tag( $image, 'thumb' )
		);
	}

	private function render_modal( Gallery $gallery, array $images ): void {
		// Embed image data as JSON for the JS lightbox to consume.
		$image_data = array_map(
			function ( Image $img ) {
				return [
					'id'      => $img->id,
					'src'     => $img->get_url(),
					'thumb'   => $img->get_thumb_url( 'thumb' ),
					'alt'     => $img->alt_text ?: $img->filename,
					'caption' => $img->caption,
					'w'       => $img->width,
					'h'       => $img->height,
				];
			},
			$images
		);

		printf(
			'<template class="zymgallery-lightbox__data" data-gallery="%d">%s</template>',
			(int) $gallery->id,
			esc_html( wp_json_encode( $image_data ) )
		);

		// The modal shell – JS will wire it up.
		echo '<div class="zymgallery-lightbox__modal" role="dialog" aria-modal="true" '
			. 'aria-label="' . esc_attr__( 'Image lightbox', 'zymgallery' ) . '" hidden>';
		echo '<button class="zymgallery-lightbox__close" aria-label="' . esc_attr__( 'Close lightbox', 'zymgallery' ) . '">&times;</button>';
		echo '<button class="zymgallery-lightbox__prev"  aria-label="' . esc_attr__( 'Previous image', 'zymgallery' ) . '">&#8249;</button>';
		echo '<button class="zymgallery-lightbox__next"  aria-label="' . esc_attr__( 'Next image', 'zymgallery' ) . '">&#8250;</button>';
		echo '<figure class="zymgallery-lightbox__figure">';
		echo '<img class="zymgallery-lightbox__img" src="" alt="">';
		echo '<figcaption class="zymgallery-lightbox__caption"></figcaption>';
		echo '</figure>';
		echo '</div>';
	}
}
