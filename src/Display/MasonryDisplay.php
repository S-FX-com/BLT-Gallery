<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Masonry display type.
 *
 * Uses CSS-only masonry (columns property) with a JS enhancement for
 * infinite scroll. No legacy Masonry.js dependency needed.
 */
class MasonryDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'masonry';
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

		$lightbox = ( $gallery->settings['lightbox'] ?? '1' );
		$lightbox = ( '0' === (string) $lightbox || false === $lightbox ) ? '0' : '1';

		printf(
			'<ul class="bltgallery-masonry__grid" data-lightbox="%s" data-total="%d">',
			esc_attr( $lightbox ),
			(int) $total
		);

		foreach ( $slice as $image ) {
			$this->render_item( $image );
		}

		echo '</ul>';

		if ( $has_more ) {
			$this->render_pagination_controls( $gallery, $cfg, $total );
		}

		$this->close_container();
	}

	public function render_item( Image $image ): void {
		$full_url = esc_url( $image->get_url() );
		$caption  = wp_kses_post( $image->caption );

		echo '<li class="bltgallery-masonry__item">';
		printf(
			'<a href="%s" class="bltgallery__link" data-image-id="%d" aria-label="%s">',
			$full_url,
			(int) $image->id,
			esc_attr( $image->alt_text ?: $image->filename )
		);
		echo $this->img_tag( $image, 'medium' );
		if ( $caption ) {
			echo '<span class="bltgallery__caption">' . $caption . '</span>';
		}
		echo '</a></li>';
	}
}
