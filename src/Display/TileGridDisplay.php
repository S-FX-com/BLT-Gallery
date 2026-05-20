<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Tile grid display type.
 *
 * Renders a uniform grid of equal-sized thumbnails. The grid is built with
 * CSS Grid so no JavaScript layout library is required at render time.
 */
class TileGridDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'tile';
	}

	public function render( Gallery $gallery, array $images ): void {
		$this->open_container( $gallery );

		if ( empty( $images ) ) {
			$this->no_images_notice();
			$this->close_container();
			return;
		}

		$columns       = (int) ( $gallery->settings['columns'] ?? 4 );
		$gutter        = (int) ( $gallery->settings['gutter'] ?? 8 );
		$captions_mode = sanitize_key( (string) ( $gallery->settings['captions'] ?? '' ) );
		$show_captions = $captions_mode
			? 'off' !== $captions_mode
			: ! empty( $gallery->settings['show_captions'] );
		$lightbox      = ( $gallery->settings['lightbox'] ?? '1' );
		$lightbox      = ( '0' === (string) $lightbox || false === $lightbox ) ? '0' : '1';

		printf(
			'<ul class="bltgallery-tile__grid" style="--blt-cols:%d; --blt-gutter:%dpx;" data-lightbox="%s">',
			$columns,
			$gutter,
			esc_attr( $lightbox )
		);

		foreach ( $images as $image ) {
			$this->render_item( $image, $show_captions );
		}

		echo '</ul>';
		$this->close_container();
	}

	private function render_item( Image $image, bool $show_caption ): void {
		$full_url = esc_url( $image->get_url() );

		echo '<li class="bltgallery-tile__item">';
		printf(
			'<a href="%s" class="bltgallery__link" data-image-id="%d" aria-label="%s">',
			$full_url,
			(int) $image->id,
			esc_attr( $image->alt_text ?: $image->filename )
		);

		// Square-crop via the thumb size.
		echo $this->img_tag( $image, 'thumb' );

		if ( $show_caption && $image->caption ) {
			printf(
				'<span class="bltgallery-tile__caption">%s</span>',
				wp_kses_post( $image->caption )
			);
		}

		echo '</a></li>';
	}
}
