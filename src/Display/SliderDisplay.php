<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Image Slider display type.
 *
 * A compact, "subtle" carousel intended for hero strips and inline image
 * sliders composed via the [blt_slider] shortcode. Unlike SlideshowDisplay
 * (which renders a single gallery), the slider is fed a pre-assembled list of
 * images that can mix several galleries and directly-added media library
 * attachments.
 *
 * Three opinionated, understated UI affordances:
 *   1. A subtle bottom caption (image description / photo credit).
 *   2. Left / right arrows that only fade in on hover or keyboard focus.
 *   3. A dot counter indicating how many slides there are.
 *
 * Navigation is wired up by the shared carousel routine in frontend.js. No
 * jQuery or third-party carousel library required.
 */
class SliderDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'slider';
	}

	public function render( Gallery $gallery, array $images ): void {
		$this->open_container( $gallery );

		if ( empty( $images ) ) {
			$this->no_images_notice();
			$this->close_container();
			return;
		}

		$autoplay = ! empty( $gallery->settings['autoplay'] );
		$speed    = max( 1000, (int) ( $gallery->settings['speed'] ?? 5000 ) );
		$loop     = ! isset( $gallery->settings['loop'] ) || '0' !== (string) $gallery->settings['loop'];

		$show_arrows = ! isset( $gallery->settings['arrows'] ) || '0' !== (string) $gallery->settings['arrows'];
		$show_dots   = ! isset( $gallery->settings['dots'] ) || '0' !== (string) $gallery->settings['dots'];
		$show_caps   = 'off' !== sanitize_key( (string) ( $gallery->settings['captions'] ?? '' ) );

		$count = count( $images );

		printf(
			'<div class="bltgallery-slider" data-autoplay="%s" data-speed="%d" data-loop="%s" role="group" aria-roledescription="carousel" aria-label="%s">',
			$autoplay ? 'true' : 'false',
			$speed,
			$loop ? 'true' : 'false',
			esc_attr( $gallery->title )
		);

		// Slides.
		echo '<ul class="bltgallery-slider__track">';
		foreach ( array_values( $images ) as $idx => $image ) {
			$this->render_slide( $image, $idx, $count );
		}
		echo '</ul>';

		// Navigation arrows (single slide needs none).
		if ( $show_arrows && $count > 1 ) {
			echo '<button type="button" class="bltgallery-slider__prev" aria-label="' . esc_attr__( 'Previous slide', 'bltgallery' ) . '">';
			echo '<span aria-hidden="true">&#8249;</span>';
			echo '</button>';
			echo '<button type="button" class="bltgallery-slider__next" aria-label="' . esc_attr__( 'Next slide', 'bltgallery' ) . '">';
			echo '<span aria-hidden="true">&#8250;</span>';
			echo '</button>';
		}

		// Bottom overlay: subtle caption + dot counter, sharing one gradient
		// scrim so they stay legible over any image.
		$first_caption = $show_caps ? trim( (string) ( array_values( $images )[0]->caption ?? '' ) ) : '';
		$has_footer    = $show_caps || ( $show_dots && $count > 1 );

		if ( $has_footer ) {
			echo '<div class="bltgallery-slider__footer">';

			if ( $show_caps ) {
				printf(
					'<p class="bltgallery-slider__caption" aria-live="polite"%s>%s</p>',
					'' === $first_caption ? ' hidden' : '',
					wp_kses_post( $first_caption )
				);
			}

			if ( $show_dots && $count > 1 && $count <= 30 ) {
				echo '<ol class="bltgallery-slider__dots" aria-label="' . esc_attr__( 'Slide indicators', 'bltgallery' ) . '">';
				for ( $idx = 0; $idx < $count; $idx++ ) {
					printf(
						'<li><button type="button" class="bltgallery-slider__dot%s" data-slide="%d" aria-label="%s"></button></li>',
						0 === $idx ? ' is-active' : '',
						$idx,
						sprintf( esc_attr__( 'Go to slide %d', 'bltgallery' ), $idx + 1 )
					);
				}
				echo '</ol>';
			}

			echo '</div>';
		}

		echo '</div>';
		$this->close_container();
	}

	private function render_slide( Image $image, int $idx, int $total ): void {
		printf(
			'<li class="bltgallery-slider__slide%s" role="group" aria-roledescription="slide" aria-label="%s" data-caption="%s"%s>',
			0 === $idx ? ' is-active' : '',
			sprintf( esc_attr__( 'Slide %1$d of %2$d', 'bltgallery' ), $idx + 1, $total ),
			esc_attr( (string) $image->caption ),
			0 === $idx ? '' : ' aria-hidden="true"'
		);

		echo $this->img_tag( $image, 'large', $idx > 0 );

		echo '</li>';
	}
}
