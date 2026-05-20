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

		// Target column count — always emitted; CSS uses RAM pattern so
		// it gracefully reflows on narrower screens.
		$columns       = max( 1, min( 8, (int) ( $gallery->settings['columns'] ?? 4 ) ) );
		$style_pairs[] = '--blt-cols:' . $columns;
		$style_pairs[] = '--blt-thumb-min:' . $this->thumb_min_px( $gallery ) . 'px';

		$style_attr = $style_pairs ? ' style="' . esc_attr( implode( '; ', $style_pairs ) ) . '"' : '';

		$captions    = sanitize_key( (string) ( $gallery->settings['captions'] ?? '' ) );
		$caption_mod = $captions ? ' bltgallery--captions-' . $captions : '';

		// Pagination data attrs (read by frontend.js to drive AJAX paging).
		$pcfg = $this->pagination_config( $gallery );
		$pagination_attrs = '';
		if ( 'off' !== $pcfg['mode'] ) {
			$pagination_attrs = sprintf(
				' data-pagination="%s" data-per-page="%d"',
				esc_attr( $pcfg['mode'] ),
				$pcfg['per_page']
			);
		}

		printf(
			'<div class="bltgallery bltgallery--%s%s" id="bltgallery-%d" data-type="%s" data-settings="%s"%s%s role="region" aria-label="%s">',
			esc_attr( $this->get_id() ),
			esc_attr( $caption_mod ),
			(int) $gallery->id,
			esc_attr( $this->get_id() ),
			esc_attr( $settings ),
			$pagination_attrs,  // safe literal (esc_attr applied inside)
			$style_attr,        // already escaped
			esc_attr( $gallery->title )
		);

		// Optional dated header.
		$this->render_gallery_header( $gallery );
	}

	/**
	 * Resolve the responsive grid's minimum thumbnail width in pixels.
	 *
	 * Order of precedence:
	 *   1. Per-gallery setting `thumb_min` (raw px value)
	 *   2. Per-gallery setting `thumbnail_size` ("small" | "medium" | "large")
	 *   3. Global setting `bltgallery_settings.thumb_width`
	 *   4. Default 200px
	 */
	private function thumb_min_px( Gallery $gallery ): int {
		if ( ! empty( $gallery->settings['thumb_min'] ) ) {
			return max( 80, (int) $gallery->settings['thumb_min'] );
		}

		$size = sanitize_key( (string) ( $gallery->settings['thumbnail_size'] ?? '' ) );
		$presets = [ 'small' => 140, 'medium' => 200, 'large' => 280, 'xlarge' => 360 ];
		if ( isset( $presets[ $size ] ) ) {
			return $presets[ $size ];
		}

		$general = get_option( 'bltgallery_settings', [] );
		if ( is_array( $general ) && ! empty( $general['thumb_width'] ) ) {
			return max( 80, (int) $general['thumb_width'] );
		}

		return 200;
	}

	protected function close_container(): void {
		echo '</div>';
	}

	protected function no_images_notice(): void {
		echo '<p class="bltgallery__empty">' . esc_html__( 'No images found in this gallery.', 'bltgallery' ) . '</p>';
	}

	/**
	 * Read pagination settings on a gallery. Returns:
	 *   [ 'mode' => 'off'|'load-more'|'numbered'|'infinite', 'per_page' => int ]
	 */
	protected function pagination_config( Gallery $gallery ): array {
		$mode = sanitize_key( (string) ( $gallery->settings['pagination'] ?? 'off' ) );
		if ( ! in_array( $mode, [ 'off', 'load-more', 'numbered', 'infinite' ], true ) ) {
			$mode = 'off';
		}
		$per_page = max( 1, min( 200, (int) ( $gallery->settings['per_page'] ?? 24 ) ) );
		return [ 'mode' => $mode, 'per_page' => $per_page ];
	}

	/**
	 * Slice images for the first server-rendered page; returns the slice
	 * plus a flag telling the caller whether more pages exist.
	 *
	 * @param Image[] $images
	 * @return array{0: Image[], 1: bool, 2: int}  [slice, has_more, total]
	 */
	protected function slice_for_pagination( array $images, array $cfg ): array {
		$total = count( $images );
		if ( 'off' === $cfg['mode'] ) {
			return [ $images, false, $total ];
		}
		$slice    = array_slice( $images, 0, $cfg['per_page'] );
		$has_more = $total > $cfg['per_page'];
		return [ $slice, $has_more, $total ];
	}

	/**
	 * Render the pagination controls (load-more button or numbered links).
	 * Wire-up happens client-side via frontend.js, which reads data-*
	 * attributes from the container.
	 */
	protected function render_pagination_controls( Gallery $gallery, array $cfg, int $total ): void {
		if ( 'off' === $cfg['mode'] || $total <= $cfg['per_page'] ) {
			return;
		}

		if ( 'numbered' === $cfg['mode'] ) {
			$pages = (int) ceil( $total / $cfg['per_page'] );
			echo '<nav class="bltgallery-pagination bltgallery-pagination--numbered" aria-label="' . esc_attr__( 'Gallery pages', 'bltgallery' ) . '">';
			echo '<ul class="bltgallery-pagination__list">';
			for ( $i = 1; $i <= $pages; $i++ ) {
				printf(
					'<li><button type="button" class="bltgallery-pagination__page%s" data-page="%d" aria-current="%s">%d</button></li>',
					1 === $i ? ' is-active' : '',
					$i,
					1 === $i ? 'page' : 'false',
					$i
				);
			}
			echo '</ul></nav>';
			return;
		}

		// load-more and infinite both render the same button — infinite mode
		// just causes the JS to click it automatically when it enters view.
		$label = 'infinite' === $cfg['mode']
			? esc_html__( 'Loading more…', 'bltgallery' )
			: esc_html__( 'Load more', 'bltgallery' );

		printf(
			'<div class="bltgallery-pagination bltgallery-pagination--%s">
				<button type="button" class="bltgallery-pagination__load-more" data-page="2">%s</button>
				<span class="bltgallery-pagination__status" aria-live="polite" hidden></span>
			</div>',
			esc_attr( $cfg['mode'] ),
			$label
		);
	}

	/**
	 * Render an optional dated header when settings.gallery_date is set.
	 * The date is rendered with the site's date format so it integrates
	 * with the theme's typography.
	 */
	protected function render_gallery_header( Gallery $gallery ): void {
		$date = trim( (string) ( $gallery->settings['gallery_date'] ?? '' ) );
		if ( '' === $date ) {
			return;
		}

		$ts        = strtotime( $date . ' 00:00:00 UTC' );
		$formatted = $ts ? wp_date( (string) get_option( 'date_format' ), $ts ) : esc_html( $date );

		printf(
			'<header class="bltgallery__header"><time class="bltgallery__date" datetime="%s">%s</time></header>',
			esc_attr( $date ),
			esc_html( $formatted )
		);
	}
}
