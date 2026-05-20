<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Slideshow display type.
 *
 * Renders an accessible carousel. Navigation is handled by lightweight
 * vanilla JS included in the frontend bundle. No jQuery or third-party
 * carousel library required.
 */
class SlideshowDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'slideshow';
	}

	public function render( Gallery $gallery, array $images ): void {
		$this->open_container( $gallery );

		if ( empty( $images ) ) {
			$this->no_images_notice();
			$this->close_container();
			return;
		}

		$autoplay  = ! empty( $gallery->settings['autoplay'] );
		$speed     = (int) ( $gallery->settings['speed'] ?? 4000 );
		// `arrows`/`dots` (new shortcode names) take precedence over the
		// legacy `show_nav`/`show_dots` settings.
		$show_nav  = isset( $gallery->settings['arrows'] )
			? '0' !== (string) $gallery->settings['arrows']
			: ( ! isset( $gallery->settings['show_nav'] ) || $gallery->settings['show_nav'] );
		$show_dots = isset( $gallery->settings['dots'] )
			? '0' !== (string) $gallery->settings['dots']
			: ( ! isset( $gallery->settings['show_dots'] ) || $gallery->settings['show_dots'] );

		printf(
			'<div class="bltgallery-slideshow" data-autoplay="%s" data-speed="%d" role="group" aria-roledescription="carousel" aria-label="%s">',
			$autoplay ? 'true' : 'false',
			$speed,
			esc_attr( $gallery->title )
		);

		// Slides.
		echo '<ul class="bltgallery-slideshow__track">';
		foreach ( $images as $idx => $image ) {
			$this->render_slide( $image, $idx );
		}
		echo '</ul>';

		// Navigation arrows.
		if ( $show_nav ) {
			echo '<button class="bltgallery-slideshow__prev" aria-label="' . esc_attr__( 'Previous slide', 'bltgallery' ) . '">';
			echo '<span aria-hidden="true">&#8249;</span>';
			echo '</button>';
			echo '<button class="bltgallery-slideshow__next" aria-label="' . esc_attr__( 'Next slide', 'bltgallery' ) . '">';
			echo '<span aria-hidden="true">&#8250;</span>';
			echo '</button>';
		}

		// Dot indicators.
		if ( $show_dots && count( $images ) <= 20 ) {
			echo '<ol class="bltgallery-slideshow__dots" aria-label="' . esc_attr__( 'Slide indicators', 'bltgallery' ) . '">';
			foreach ( $images as $idx => $image ) {
				printf(
					'<li><button class="bltgallery-slideshow__dot%s" data-slide="%d" aria-label="%s"></button></li>',
					0 === $idx ? ' is-active' : '',
					$idx,
					sprintf( esc_attr__( 'Go to slide %d', 'bltgallery' ), $idx + 1 )
				);
			}
			echo '</ol>';
		}

		echo '</div>';
		$this->close_container();
	}

	private function render_slide( Image $image, int $idx ): void {
		$full_url = esc_url( $image->get_url() );

		printf(
			'<li class="bltgallery-slideshow__slide%s" role="group" aria-roledescription="slide" aria-label="%s">',
			0 === $idx ? ' is-active' : '',
			sprintf( esc_attr__( 'Slide %d', 'bltgallery' ), $idx + 1 )
		);

		echo $this->img_tag( $image, 'large', $idx > 0 );

		if ( $image->caption ) {
			printf(
				'<p class="bltgallery-slideshow__caption">%s</p>',
				wp_kses_post( $image->caption )
			);
		}

		echo '</li>';
	}
}
