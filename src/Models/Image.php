<?php

declare( strict_types=1 );

namespace ZymGallery\Models;

/**
 * Value object representing an image row.
 */
class Image {

	public int    $id             = 0;
	public int    $gallery_id     = 0;
	public string $filename       = '';
	public string $alt_text       = '';
	public string $caption        = '';
	public string $description    = '';
	public int    $sort_order     = 0;
	public int    $width          = 0;
	public int    $height         = 0;
	public int    $filesize       = 0;
	public string $mime_type      = '';
	public string $storage_driver = 'local'; // 'local' | 's3'
	public ?string $local_path    = null;
	public ?string $s3_key        = null;
	public ?string $s3_bucket     = null;
	public ?string $cloudfront_url = null;
	public array  $meta           = [];
	public string $created_at     = '';
	public string $updated_at     = '';

	// ------------------------------------------------------------------
	// Factory
	// ------------------------------------------------------------------

	public static function from_row( array $row ): self {
		$img                 = new self();
		$img->id             = (int) ( $row['id'] ?? 0 );
		$img->gallery_id     = (int) ( $row['gallery_id'] ?? 0 );
		$img->filename       = $row['filename'] ?? '';
		$img->alt_text       = $row['alt_text'] ?? '';
		$img->caption        = $row['caption'] ?? '';
		$img->description    = $row['description'] ?? '';
		$img->sort_order     = (int) ( $row['sort_order'] ?? 0 );
		$img->width          = (int) ( $row['width'] ?? 0 );
		$img->height         = (int) ( $row['height'] ?? 0 );
		$img->filesize       = (int) ( $row['filesize'] ?? 0 );
		$img->mime_type      = $row['mime_type'] ?? '';
		$img->storage_driver = $row['storage_driver'] ?? 'local';
		$img->local_path     = $row['local_path'] ?? null;
		$img->s3_key         = $row['s3_key'] ?? null;
		$img->s3_bucket      = $row['s3_bucket'] ?? null;
		$img->cloudfront_url = $row['cloudfront_url'] ?? null;
		$img->meta           = ! empty( $row['meta'] )
			? (array) json_decode( $row['meta'], true )
			: [];
		$img->created_at     = $row['created_at'] ?? '';
		$img->updated_at     = $row['updated_at'] ?? '';

		return $img;
	}

	// ------------------------------------------------------------------
	// URL helpers
	// ------------------------------------------------------------------

	/**
	 * Returns the public-facing URL for the full-size image.
	 * Prefers CloudFront if available, else falls back to a local WP URL.
	 */
	public function get_url(): string {
		if ( $this->cloudfront_url ) {
			return esc_url_raw( $this->cloudfront_url );
		}

		if ( $this->local_path ) {
			// Convert absolute path to URL.
			$uploads   = wp_upload_dir();
			$base_path = $uploads['basedir'];
			$base_url  = $uploads['baseurl'];
			$relative  = str_replace( $base_path, '', $this->local_path );
			return esc_url_raw( $base_url . $relative );
		}

		return '';
	}

	/**
	 * Returns a thumbnail URL if stored as meta (generated during upload).
	 */
	public function get_thumb_url( string $size = 'medium' ): string {
		return $this->meta['thumbs'][ $size ]['url'] ?? $this->get_url();
	}

	// ------------------------------------------------------------------
	// Serialisation
	// ------------------------------------------------------------------

	public function to_array(): array {
		return [
			'id'             => $this->id,
			'gallery_id'     => $this->gallery_id,
			'filename'       => $this->filename,
			'alt_text'       => $this->alt_text,
			'caption'        => $this->caption,
			'description'    => $this->description,
			'sort_order'     => $this->sort_order,
			'width'          => $this->width,
			'height'         => $this->height,
			'filesize'       => $this->filesize,
			'mime_type'      => $this->mime_type,
			'storage_driver' => $this->storage_driver,
			'url'            => $this->get_url(),
			'thumb_url'      => $this->get_thumb_url(),
			'meta'           => $this->meta,
			'created_at'     => $this->created_at,
			'updated_at'     => $this->updated_at,
		];
	}
}
