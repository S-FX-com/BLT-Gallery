<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Lightbox display type.
 *
 * Renders a thumbnail grid where every image opens a full-screen lightbox
 * modal driven by the lightweight bltgallery-lightbox frontend module.
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

		$cfg = $this->pagination_config( $gallery );
		[ $slice, $has_more, $total ] = $this->slice_for_pagination( $images, $cfg );

		printf( '<ul class="bltgallery-lightbox__grid" data-lightbox="1" data-total="%d">', (int) $total );

		foreach ( $slice as $idx => $image ) {
			$this->render_thumb( $image, $idx );
		}

		echo '</ul>';

		if ( $has_more ) {
			$this->render_pagination_controls( $gallery, $cfg, $total );
		}

		// Hidden lightbox modal – populated by JS with full-resolution images.
		// We only seed it with the first-page slice; subsequent pages get
		// merged into the modal's data array when they load via AJAX.
		$this->render_modal( $gallery, $slice );

		$this->close_container();
	}

	public function render_thumb( Image $image, int $idx ): void {
		printf(
			'<li class="bltgallery-lightbox__thumb">'
			. '<button class="bltgallery-lightbox__trigger" data-index="%d" data-image-id="%d" aria-label="%s">'
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
			'<template class="bltgallery-lightbox__data" data-gallery="%d">%s</template>',
			(int) $gallery->id,
			esc_html( wp_json_encode( $image_data ) )
		);

		// The modal shell – JS will wire it up.
		echo '<div class="bltgallery-lightbox__modal" role="dialog" aria-modal="true" '
			. 'aria-label="' . esc_attr__( 'Image lightbox', 'bltgallery' ) . '" hidden>';
		echo '<button class="bltgallery-lightbox__close" aria-label="' . esc_attr__( 'Close lightbox', 'bltgallery' ) . '">&times;</button>';
		echo '<button class="bltgallery-lightbox__prev"  aria-label="' . esc_attr__( 'Previous image', 'bltgallery' ) . '">&#8249;</button>';
		echo '<button class="bltgallery-lightbox__next"  aria-label="' . esc_attr__( 'Next image', 'bltgallery' ) . '">&#8250;</button>';
		echo '<figure class="bltgallery-lightbox__figure">';
		echo '<img class="bltgallery-lightbox__img" src="" alt="">';
		echo '<figcaption class="bltgallery-lightbox__caption"></figcaption>';
		echo '</figure>';
		echo '</div>';
	}
}
