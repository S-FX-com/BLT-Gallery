<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Image;

/**
 * [blt_my_gallery] – the current visitor's own front-end gallery: an
 * upload widget plus the images they've already added, each removable.
 *
 * No id/slug attributes — unlike [blt_gallery], this shortcode always shows
 * the logged-in visitor their own gallery (see FrontEndGallery), never one
 * chosen by the placement. Renders nothing when the feature is off, a
 * sign-in prompt when the visitor isn't logged in, a short explanation when
 * they're logged in but not in an allowed role, and the gallery + uploader
 * otherwise.
 *
 * Supported attributes:
 *   class – extra CSS class on the wrapping div
 *   style – extra inline style on the wrapping div
 */
class FrontEndGalleryShortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_my_gallery' ): string {
		if ( ! FrontEndGallery::is_enabled() ) {
			return '<!-- blt_my_gallery: disabled -->';
		}

		$atts = shortcode_atts(
			[
				'class' => '',
				'style' => '',
			],
			$atts,
			$tag
		);

		if ( ! is_user_logged_in() ) {
			return $this->message(
				$atts,
				sprintf(
					'<a href="%s">%s</a>',
					esc_url( wp_login_url( self::redirect_url() ) ),
					esc_html__( 'Log in to manage your gallery.', 'bltgallery' )
				)
			);
		}

		if ( ! FrontEndGallery::current_user_allowed() ) {
			return $this->message( $atts, esc_html__( "You don't have access to a front-end gallery on this site.", 'bltgallery' ) );
		}

		$gallery = FrontEndGallery::resolve_gallery_for_current_user( true );
		if ( ! $gallery ) {
			return '<!-- blt_my_gallery: could not resolve your gallery -->';
		}

		wp_enqueue_style( 'bltgallery-my-gallery' );
		wp_enqueue_script( 'bltgallery-my-gallery' );

		$images    = ImageRepository::find_by_gallery( $gallery->id );
		$limit     = FrontEndGallery::image_limit();
		$remaining = FrontEndGallery::remaining_uploads( $gallery );

		ob_start();
		$this->render_widget( $atts, $gallery->id, $images, $limit, $remaining );
		return (string) ob_get_clean();
	}

	// ------------------------------------------------------------------
	// Rendering
	// ------------------------------------------------------------------

	private function message( array $atts, string $inner_html ): string {
		return sprintf(
			'<div class="bltgallery-my-gallery bltgallery-my-gallery--message%s"%s><p>%s</p></div>',
			$this->extra_class( $atts ),
			$this->extra_style( $atts ),
			$inner_html // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- callers pass esc_html()/esc_url()'d markup, see call sites above.
		);
	}

	/**
	 * @param Image[] $images
	 */
	private function render_widget( array $atts, int $gallery_id, array $images, int $limit, int $remaining ): void {
		// bltgallery--lightbox is what tells frontend.js's generic lightbox
		// initialiser to wire this container up — see initLightbox() and
		// LightboxDisplay, which this mirrors (modal + JSON <template> +
		// .bltgallery-lightbox__trigger buttons). Reusing that module instead
		// of writing a second one gets focus trapping, keyboard/swipe nav,
		// and image preloading for free.
		printf(
			'<div class="bltgallery-my-gallery bltgallery--lightbox" data-limit="%d" data-remaining="%d"%s%s>',
			$limit,
			$remaining,
			$this->extra_class( $atts ),
			$this->extra_style( $atts )
		);

		printf( '<div class="bltgallery-my-gallery__uploader"%s>', $remaining > 0 ? '' : ' hidden' );
		printf(
			'<input type="file" accept="%s" multiple hidden>',
			esc_attr( implode( ',', UploadValidator::ALLOWED_TYPES ) )
		);
		printf(
			'<div class="bltgallery-my-gallery__dropzone" tabindex="0" role="button" aria-label="%1$s">
				<p>%2$s <strong>%3$s</strong></p>
				<p class="bltgallery-my-gallery__hint">%4$s</p>
			</div>',
			esc_attr__( 'Drop images here or click to upload', 'bltgallery' ),
			esc_html__( 'Drag & drop images here, or', 'bltgallery' ),
			esc_html__( 'click to browse', 'bltgallery' ),
			esc_html(
				sprintf(
					/* translators: %s: max file size in MB, e.g. "50" */
					__( 'JPEG, PNG, GIF, WebP, AVIF · Max %s MB each', 'bltgallery' ),
					(string) ( UploadValidator::MAX_UPLOAD_SIZE / 1_048_576 )
				)
			)
		);
		echo '<ul class="bltgallery-my-gallery__progress"></ul>';
		echo '</div>';

		printf(
			'<p class="bltgallery-my-gallery__quota">%s</p>',
			esc_html( $this->quota_text( count( $images ), $limit ) )
		);

		echo '<ul class="bltgallery-my-gallery__grid">';
		if ( ! $images ) {
			echo '<li class="bltgallery-my-gallery__empty">' . esc_html__( "You haven't added any images yet.", 'bltgallery' ) . '</li>';
		}
		foreach ( $images as $idx => $image ) {
			$this->render_item( $image, $idx );
		}
		echo '</ul>';

		$this->render_lightbox_modal( $gallery_id, $images );

		echo '</div>';
	}

	private function render_item( Image $image, int $idx ): void {
		$alt = esc_attr( $image->alt_text ?: $image->filename );

		printf(
			'<li class="bltgallery-my-gallery__item" data-id="%1$d">
				<button type="button" class="bltgallery-lightbox__trigger" data-index="%6$d" data-image-id="%1$d" aria-label="%3$s">
					<img src="%2$s" alt="%3$s" loading="lazy" width="%4$d" height="%5$d">
				</button>
				<button type="button" class="bltgallery-my-gallery__delete" data-id="%1$d" aria-label="%7$s">&times;</button>
			</li>',
			$image->id,
			esc_url( $image->get_thumb_url( 'thumb' ) ),
			$alt,
			(int) ( $image->meta['thumbs']['thumb']['width'] ?? $image->width ),
			(int) ( $image->meta['thumbs']['thumb']['height'] ?? $image->height ),
			$idx,
			esc_attr__( 'Delete this image', 'bltgallery' )
		);
	}

	/**
	 * Hidden lightbox modal + its image data, in the exact shape
	 * LightboxDisplay::render_modal() uses — same JSON <template> convention,
	 * same modal markup — so frontend.js's shared lightbox module drives both
	 * without knowing which one it's looking at.
	 *
	 * @param Image[] $images
	 */
	private function render_lightbox_modal( int $gallery_id, array $images ): void {
		$image_data = array_map(
			static fn( Image $img ) => [
				'id'      => $img->id,
				'src'     => $img->get_url(),
				'thumb'   => $img->get_thumb_url( 'thumb' ),
				'alt'     => $img->alt_text ?: $img->filename,
				'caption' => $img->caption,
				'w'       => $img->width,
				'h'       => $img->height,
			],
			$images
		);

		printf(
			'<template class="bltgallery-lightbox__data" data-gallery="%d">%s</template>',
			$gallery_id,
			esc_html( wp_json_encode( $image_data ) )
		);

		echo '<div class="bltgallery-lightbox__modal" role="dialog" aria-modal="true" aria-label="'
			. esc_attr__( 'Image lightbox', 'bltgallery' ) . '" hidden>';
		echo '<button class="bltgallery-lightbox__close" aria-label="' . esc_attr__( 'Close lightbox', 'bltgallery' ) . '">&times;</button>';
		echo '<button class="bltgallery-lightbox__prev"  aria-label="' . esc_attr__( 'Previous image', 'bltgallery' ) . '">&#8249;</button>';
		echo '<button class="bltgallery-lightbox__next"  aria-label="' . esc_attr__( 'Next image', 'bltgallery' ) . '">&#8250;</button>';
		echo '<figure class="bltgallery-lightbox__figure">';
		echo '<img class="bltgallery-lightbox__img" src="" alt="">';
		echo '<figcaption class="bltgallery-lightbox__caption"></figcaption>';
		echo '</figure>';
		echo '</div>';
	}

	private function quota_text( int $count, int $limit ): string {
		if ( $count >= $limit ) {
			return sprintf(
				/* translators: %d: the maximum number of images allowed */
				__( "You've used all %d of your image slots.", 'bltgallery' ),
				$limit
			);
		}

		return sprintf(
			/* translators: 1: images used so far, 2: the maximum allowed */
			__( '%1$d of %2$d images used.', 'bltgallery' ),
			$count,
			$limit
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function extra_class( array $atts ): string {
		$class = trim( (string) ( $atts['class'] ?? '' ) );
		return $class ? ' ' . esc_attr( $class ) : '';
	}

	private function extra_style( array $atts ): string {
		$style = trim( (string) ( $atts['style'] ?? '' ) );
		return $style ? ' style="' . esc_attr( $style ) . '"' : '';
	}

	private static function redirect_url(): string {
		return get_permalink() ?: home_url( '/' );
	}
}
