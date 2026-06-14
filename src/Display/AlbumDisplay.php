<?php

declare( strict_types=1 );

namespace BltGallery\Display;

use BltGallery\Core\ImageRepository;
use BltGallery\Core\Plugin;
use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Renders an "album" – a collection of gallery cards.
 *
 * Layout styles (atts.style):
 *   grid      – uniform card grid (default)
 *   masonry   – CSS-columns masonry layout for variable-height covers
 *   carousel  – horizontally-scrollable strip of cards
 *   accordion – stacked rows that expand inline to show the full gallery
 *
 * This is invoked by AlbumShortcode and not registered as a per-gallery
 * display_type, but it also implements get_id()/render() so it can be
 * dropped into Plugin::make_display() as a regular display when an album-of-one
 * is requested.
 */
class AlbumDisplay extends AbstractDisplay {

	public function get_id(): string {
		return 'album';
	}

	public function render( Gallery $gallery, array $images ): void {
		// Single-gallery fallback: behaves like a tile grid with the gallery's
		// own settings. Used only when [blt_gallery type="album"] is invoked.
		$this->open_container( $gallery );
		if ( empty( $images ) ) {
			$this->no_images_notice();
			$this->close_container();
			return;
		}
		( new TileGridDisplay() )->render( $gallery, $images );
		$this->close_container();
	}

	/**
	 * @param Gallery[] $galleries
	 * @param array     $atts      Already-sanitised shortcode attributes.
	 */
	public function render_album( array $galleries, array $atts ): void {
		$style  = sanitize_key( $atts['style'] ?: 'grid' );
		$cols   = max( 1, min( 8, (int) ( $atts['cols'] ?? 3 ) ) );
		$gap    = max( 0, (int) ( $atts['gap'] ?? 20 ) );
		$radius = max( 0, (int) ( $atts['radius'] ?? 8 ) );

		printf(
			'<div class="bltgallery-album bltgallery-album--%1$s%2$s" style="--blt-cols:%3$d; --blt-gap:%4$dpx; --blt-radius:%5$dpx;%6$s" role="region" aria-label="%7$s" data-style="%1$s">',
			esc_attr( $style ),
			$atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '',
			$cols,
			$gap,
			$radius,
			$atts['style_attr'] ? ' ' . esc_attr( $atts['style_attr'] ) : '',
			esc_attr__( 'Photo album', 'bltgallery' )
		);

		match ( $style ) {
			'accordion' => $this->render_accordion( $galleries, $atts ),
			'carousel'  => $this->render_carousel( $galleries, $atts ),
			'masonry'   => $this->render_masonry( $galleries, $atts ),
			default     => $this->render_grid( $galleries, $atts ),
		};

		echo '</div>';
	}

	// ------------------------------------------------------------------
	// Layout variants
	// ------------------------------------------------------------------

	private function render_grid( array $galleries, array $atts ): void {
		echo '<ul class="bltgallery-album__grid">';
		foreach ( $galleries as $gallery ) {
			$this->render_card( $gallery, $atts );
		}
		echo '</ul>';
	}

	private function render_masonry( array $galleries, array $atts ): void {
		echo '<ul class="bltgallery-album__masonry">';
		foreach ( $galleries as $gallery ) {
			$this->render_card( $gallery, $atts );
		}
		echo '</ul>';
	}

	private function render_carousel( array $galleries, array $atts ): void {
		echo '<div class="bltgallery-album__carousel" role="group" aria-roledescription="carousel" tabindex="0">';
		echo '<ul class="bltgallery-album__carousel-track">';
		foreach ( $galleries as $gallery ) {
			$this->render_card( $gallery, $atts );
		}
		echo '</ul>';
		echo '</div>';
	}

	private function render_accordion( array $galleries, array $atts ): void {
		echo '<ul class="bltgallery-album__accordion">';
		foreach ( $galleries as $gallery ) {
			$cover = $this->pick_cover( $gallery, $atts );
			$count = ImageRepository::count_by_gallery( $gallery->id );
			$type  = ! empty( $atts['gallery_type'] )
				? sanitize_key( $atts['gallery_type'] )
				: $gallery->display_type;

			printf(
				'<li class="bltgallery-album__row"><details><summary class="bltgallery-album__summary"><span class="bltgallery-album__cover">%s</span><span class="bltgallery-album__title">%s</span><span class="bltgallery-album__count">%s</span></summary><div class="bltgallery-album__expanded">',
				$cover ? $this->img_tag( $cover, 'medium', true ) : '',
				esc_html( $gallery->title ),
				'1' === (string) $atts['show_count']
					? sprintf( esc_html__( '%d photos', 'bltgallery' ), $count )
					: ''
			);

			$display = Plugin::make_display( $type ) ?? new TileGridDisplay();
			$images  = ImageRepository::find_by_gallery( $gallery->id );
			$display->render( $gallery, $images );

			echo '</div></details></li>';
		}
		echo '</ul>';
	}

	// ------------------------------------------------------------------
	// Card primitive
	// ------------------------------------------------------------------

	private function render_card( Gallery $gallery, array $atts ): void {
		$cover    = $this->pick_cover( $gallery, $atts );
		$count    = ImageRepository::count_by_gallery( $gallery->id );
		$captions = sanitize_key( $atts['captions'] ?: 'below' );
		$link     = (string) ( $gallery->settings['album_link'] ?? '' );
		$gtype    = ! empty( $atts['gallery_type'] ) ? sanitize_key( (string) $atts['gallery_type'] ) : '';

		echo '<li class="bltgallery-album__item">';

		if ( $link ) {
			// Explicit per-gallery link wins: a normal anchor.
			$open  = '<a href="' . esc_url( $link ) . '" class="bltgallery-album__card bltgallery-album__card--linked">';
			$close = '</a>';
		} else {
			// No link → button that opens the gallery in a modal (JS-driven).
			$open  = sprintf(
				'<button type="button" class="bltgallery-album__card bltgallery-album__card--linked bltgallery-album__card--gallery" data-gallery-id="%d"%s aria-haspopup="dialog">',
				$gallery->id,
				$gtype ? ' data-gallery-type="' . esc_attr( $gtype ) . '"' : ''
			);
			$close = '</button>';
		}

		echo $open; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<figure class="bltgallery-album__figure">';
		if ( $cover ) {
			echo $this->img_tag( $cover, 'medium', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		} else {
			echo '<span class="bltgallery-album__placeholder" aria-hidden="true">&#128247;</span>';
		}

		if ( 'off' !== $captions ) {
			printf(
				'<figcaption class="bltgallery-album__caption bltgallery-album__caption--%s"><span class="bltgallery-album__title">%s</span>',
				esc_attr( $captions ),
				esc_html( $gallery->title )
			);
			if ( '1' === (string) $atts['show_count'] ) {
				printf(
					'<span class="bltgallery-album__count">%s</span>',
					sprintf( esc_html__( '%d photos', 'bltgallery' ), $count )
				);
			}
			echo '</figcaption>';
		}
		echo '</figure>';
		echo $close; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '</li>';
	}

	private function pick_cover( Gallery $gallery, array $atts ): ?Image {
		$images = ImageRepository::find_by_gallery( $gallery->id );
		if ( empty( $images ) ) {
			return null;
		}

		// Explicit cover_image_id wins over everything.
		$cover_id = (int) ( $gallery->settings['cover_image_id'] ?? 0 );
		if ( $cover_id ) {
			foreach ( $images as $image ) {
				if ( $image->id === $cover_id ) {
					return $image;
				}
			}
		}

		return 'random' === sanitize_key( $atts['cover'] ?? 'first' )
			? $images[ array_rand( $images ) ]
			: $images[0];
	}
}
