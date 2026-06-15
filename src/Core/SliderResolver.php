<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Models\Image;

/**
 * Turns a slider's ordered slide descriptors (see {@see \BltGallery\Models\Slider})
 * into renderable Image objects for the front end, and into lightweight metadata
 * rows for the admin builder.
 *
 * Supported item sources:
 *   - "image"      → a Blt gallery image (resolved via ImageRepository)
 *   - "attachment" → a WordPress media library attachment (wrapped in an Image)
 *   - "gallery"    → every image in a gallery, expanded in order (used by the
 *                    [blt_slider galleries="…"] ad-hoc shortcode path)
 *
 * An optional per-item `caption` overrides the source image's own caption,
 * letting editors add a description or photo credit without touching the
 * underlying gallery image.
 */
class SliderResolver {

	/**
	 * Flatten slide descriptors into a list of renderable images.
	 *
	 * @param array $items Ordered slide descriptors.
	 * @return Image[]
	 */
	public static function to_images( array $items ): array {
		$images = [];

		foreach ( $items as $item ) {
			$source  = sanitize_key( (string) ( $item['source'] ?? 'image' ) );
			$ref     = (int) ( $item['ref'] ?? 0 );
			$caption = isset( $item['caption'] ) ? (string) $item['caption'] : '';

			if ( 'gallery' === $source ) {
				foreach ( ImageRepository::find_by_gallery( $ref ) as $image ) {
					$images[] = $image;
				}
				continue;
			}

			$image = self::resolve_single( $source, $ref );
			if ( ! $image ) {
				continue;
			}
			if ( '' !== trim( $caption ) ) {
				$image->caption = $caption;
			}
			$images[] = $image;
		}

		return $images;
	}

	/**
	 * Describe each slide for the admin builder. Always returns flat,
	 * individually-addressable rows (gallery items are expanded to image rows)
	 * so the builder can show one tile per slide, reorder, and re-caption.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function describe_items( array $items ): array {
		$rows = [];

		foreach ( $items as $item ) {
			$source  = sanitize_key( (string) ( $item['source'] ?? 'image' ) );
			$ref     = (int) ( $item['ref'] ?? 0 );
			$caption = isset( $item['caption'] ) ? (string) $item['caption'] : '';

			if ( 'gallery' === $source ) {
				foreach ( ImageRepository::find_by_gallery( $ref ) as $image ) {
					$rows[] = self::describe_image( 'image', $image->id, $image, '' );
				}
				continue;
			}

			$image  = self::resolve_single( $source, $ref );
			$rows[] = self::describe_image( $source, $ref, $image, $caption );
		}

		return $rows;
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	private static function resolve_single( string $source, int $ref ): ?Image {
		if ( $ref <= 0 ) {
			return null;
		}
		return 'attachment' === $source
			? self::image_from_attachment( $ref )
			: ImageRepository::find( $ref );
	}

	/**
	 * @param Image|null $image Resolved source image, or null when missing.
	 */
	private static function describe_image( string $source, int $ref, ?Image $image, string $caption ): array {
		if ( ! $image ) {
			return [
				'source'          => $source,
				'ref'             => $ref,
				'caption'         => $caption,
				'default_caption' => '',
				'thumb_url'       => '',
				'url'             => '',
				'alt'             => '',
				'title'           => '',
				'missing'         => true,
			];
		}

		return [
			'source'          => $source,
			'ref'             => $ref,
			'caption'         => $caption,
			'default_caption' => (string) $image->caption,
			'thumb_url'       => $image->get_thumb_url( 'thumb' ),
			'url'             => $image->get_url(),
			'alt'             => (string) ( $image->alt_text ?: $image->filename ),
			'title'           => $image->get_title(),
			'missing'         => false,
		];
	}

	/**
	 * Wrap a WordPress media library attachment in an Image value object so it
	 * flows through the same render path (and Cloudflare URL rewriting) as
	 * gallery images. The full-size URL is stored as `cloudfront_url` purely so
	 * Image::get_url() returns it; thumbnail sizes are mapped from WordPress's
	 * generated sizes for a working srcset when Cloudflare resizing is off.
	 */
	public static function image_from_attachment( int $att_id ): ?Image {
		if ( $att_id <= 0 || ! wp_attachment_is_image( $att_id ) ) {
			return null;
		}

		$full = wp_get_attachment_image_url( $att_id, 'full' );
		if ( ! $full ) {
			return null;
		}

		$meta = wp_get_attachment_metadata( $att_id );

		$image                 = new Image();
		$image->cloudfront_url = $full;
		$image->alt_text       = (string) get_post_meta( $att_id, '_wp_attachment_image_alt', true );
		$image->caption        = (string) wp_get_attachment_caption( $att_id );
		$image->width          = (int) ( $meta['width'] ?? 0 );
		$image->height         = (int) ( $meta['height'] ?? 0 );
		$image->mime_type      = (string) get_post_mime_type( $att_id );
		$image->filename       = wp_basename( (string) ( get_attached_file( $att_id ) ?: $full ) );

		// Map our three logical sizes onto the best available WP size, falling
		// back to the plugin's registered sizes and finally the full image.
		$thumbs   = [];
		$size_map = [
			'thumb'  => [ 'bltgallery-thumb', 'medium' ],
			'medium' => [ 'bltgallery-medium', 'large' ],
			'large'  => [ 'bltgallery-large', 'full' ],
		];
		foreach ( $size_map as $logical => $candidates ) {
			foreach ( $candidates as $wp_size ) {
				$src = wp_get_attachment_image_src( $att_id, $wp_size );
				if ( $src ) {
					$thumbs[ $logical ] = [
						'url'    => $src[0],
						'width'  => (int) $src[1],
						'height' => (int) $src[2],
					];
					break;
				}
			}
		}
		$image->meta = [ 'thumbs' => $thumbs ];

		return $image;
	}

	/**
	 * Normalise a raw items payload (e.g. from the REST API) into a clean,
	 * storable shape: only known sources, positive integer refs, and a
	 * sanitised caption override.
	 *
	 * @return array<int, array{source: string, ref: int, caption: string}>
	 */
	public static function sanitize_items( array $raw ): array {
		$clean = [];
		foreach ( $raw as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$source = sanitize_key( (string) ( $item['source'] ?? '' ) );
			$ref    = (int) ( $item['ref'] ?? 0 );
			if ( ! in_array( $source, [ 'image', 'attachment', 'gallery' ], true ) || $ref <= 0 ) {
				continue;
			}
			$clean[] = [
				'source'  => $source,
				'ref'     => $ref,
				'caption' => isset( $item['caption'] ) ? sanitize_text_field( (string) $item['caption'] ) : '',
			];
		}
		return $clean;
	}
}
