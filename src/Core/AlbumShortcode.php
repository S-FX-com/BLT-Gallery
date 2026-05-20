<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Display\AlbumDisplay;
use BltGallery\Models\Gallery;

/**
 * [blt_album] – render multiple galleries together as an "album" landing
 * page. Each gallery becomes a clickable card whose cover image links to
 * either an inline expanded gallery (style="accordion") or a separate
 * gallery page (style="grid" + the target URL on the gallery).
 *
 * Examples:
 *   [blt_album ids="3,7,9"]
 *   [blt_album ids="3,7,9" style="grid" cols="3" gap="20" captions="below"]
 *   [blt_album slugs="weddings,nature,travel" style="masonry" cols="4"]
 *   [blt_album ids="3,7,9" style="carousel" autoplay="1" speed="5000"]
 *   [blt_album category="portfolio" limit="12" order="date"]
 *
 * Supported attributes:
 *   ids        – comma-separated gallery IDs to include
 *   slugs      – comma-separated gallery slugs (alternative to ids)
 *   category   – include all galleries whose settings.category matches
 *   style      – grid | masonry | carousel | accordion   (default: grid)
 *   cols       – column count for grid / masonry
 *   gap        – gap in px between album cards
 *   radius     – per-card border-radius (px)
 *   captions   – "below" | "hover" | "off"  (gallery title placement)
 *   show_count – "1" to render "(N photos)" under each card
 *   cover      – "first" | "random"  (which image to use for the card cover)
 *   limit      – cap number of galleries rendered
 *   order      – "menu" | "date" | "random"
 *   gallery_type – override the per-gallery display_type when expanded
 *   class      – extra wrapper class
 *   style_attr – extra wrapper inline style
 */
class AlbumShortcode {

	public function render( array $atts, string $content = '', string $tag = 'blt_album' ): string {
		$atts = shortcode_atts(
			[
				'ids'          => '',
				'slugs'        => '',
				'category'     => '',
				'style'        => 'grid',
				'cols'         => '3',
				'gap'          => '20',
				'radius'       => '8',
				'captions'     => 'below',
				'show_count'   => '1',
				'cover'        => 'first',
				'limit'        => '',
				// Sort key within the album: menu (manual / sort_order),
				// date (settings.gallery_date), or name (title).
				'sort_by'      => 'menu',
				'order'        => 'asc', // asc | desc
				'gallery_type' => '',
				'class'        => '',
				'style_attr'   => '',
			],
			$atts,
			$tag
		);

		$galleries = $this->resolve_galleries( $atts );
		if ( empty( $galleries ) ) {
			return '<!-- blt_album: no galleries matched -->';
		}

		wp_enqueue_style( 'bltgallery-frontend' );
		wp_enqueue_script( 'bltgallery-frontend' );

		$display = new AlbumDisplay();

		ob_start();
		$display->render_album( $galleries, $atts );
		return (string) ob_get_clean();
	}

	/**
	 * @return Gallery[]
	 */
	private function resolve_galleries( array $atts ): array {
		$galleries = [];

		if ( ! empty( $atts['ids'] ) ) {
			$ids = array_filter( array_map( 'intval', array_map( 'trim', explode( ',', $atts['ids'] ) ) ) );
			foreach ( $ids as $id ) {
				$gallery = GalleryRepository::find( $id );
				if ( $gallery ) {
					$galleries[] = $gallery;
				}
			}
		} elseif ( ! empty( $atts['slugs'] ) ) {
			$slugs = array_filter( array_map( 'sanitize_title', array_map( 'trim', explode( ',', $atts['slugs'] ) ) ) );
			foreach ( $slugs as $slug ) {
				$gallery = GalleryRepository::find_by_slug( $slug );
				if ( $gallery ) {
					$galleries[] = $gallery;
				}
			}
		} elseif ( ! empty( $atts['category'] ) ) {
			$category = sanitize_text_field( $atts['category'] );
			foreach ( GalleryRepository::all( 200, 1 ) as $gallery ) {
				if ( isset( $gallery->settings['category'] ) && $gallery->settings['category'] === $category ) {
					$galleries[] = $gallery;
				}
			}
		}

		$sort_by  = sanitize_key( $atts['sort_by'] );
		$reverse  = 'desc' === sanitize_key( $atts['order'] );

		switch ( $sort_by ) {
			case 'random':
				shuffle( $galleries );
				break;
			case 'date':
				usort( $galleries, static function ( $a, $b ) {
					$ad = (string) ( $a->settings['gallery_date'] ?? $a->created_at );
					$bd = (string) ( $b->settings['gallery_date'] ?? $b->created_at );
					return strcmp( $ad, $bd );
				} );
				if ( $reverse ) {
					$galleries = array_reverse( $galleries );
				}
				break;
			case 'name':
			case 'title':
				usort( $galleries, static fn( $a, $b ) => strnatcasecmp( (string) $a->title, (string) $b->title ) );
				if ( $reverse ) {
					$galleries = array_reverse( $galleries );
				}
				break;
			case 'menu':
			default:
				// Manual order = preserve insertion. Only reverse if explicitly asked.
				if ( $reverse ) {
					$galleries = array_reverse( $galleries );
				}
				break;
		}

		if ( '' !== $atts['limit'] && (int) $atts['limit'] > 0 ) {
			$galleries = array_slice( $galleries, 0, (int) $atts['limit'] );
		}

		return $galleries;
	}
}
