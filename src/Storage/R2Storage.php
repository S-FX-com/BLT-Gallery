<?php

declare( strict_types=1 );

namespace BltGallery\Storage;

use BltGallery\Models\Image;

/**
 * Handles uploading images to Cloudflare R2 using the S3-compatible API.
 *
 * Uses S3HttpClient (pure HTTP + AWS SigV4) – no SDK or Composer required.
 *
 * R2 uses path-style endpoints and does not support ACLs; bucket visibility
 * is managed in the Cloudflare dashboard.
 *
 * Settings (stored in 'bltgallery_r2_settings' option):
 *   account_id              – Cloudflare account ID (found in the dashboard)
 *   access_key_id           – R2 API token access key
 *   secret_access_key       – R2 API token secret
 *   bucket                  – R2 bucket name
 *   path_prefix             – optional key prefix, e.g. 'gallery/'
 *   public_url              – public base URL, e.g. 'https://assets.example.com'
 *   auto_offload            – bool; auto-upload to R2 on ingest
 *   delete_local_after_upload – bool; remove local file after successful R2 upload
 */
class R2Storage {

	private S3HttpClient $client;
	private array        $settings;

	public function __construct() {
		$this->settings = $this->load_settings();
		$this->client   = $this->build_client();
	}

	// ------------------------------------------------------------------
	// Upload
	// ------------------------------------------------------------------

	/**
	 * Upload a single local file to R2 and return the R2 object key.
	 *
	 * @throws \RuntimeException On R2 failure.
	 */
	public function upload( string $local_path, string $r2_key ): string {
		if ( ! file_exists( $local_path ) ) {
			throw new \RuntimeException( "File not found: {$local_path}" );
		}

		$prefix = rtrim( $this->settings['path_prefix'] ?? '', '/' );
		$key    = $prefix ? "{$prefix}/{$r2_key}" : $r2_key;
		$bucket = $this->settings['bucket'];
		$mime   = mime_content_type( $local_path ) ?: 'application/octet-stream';

		$this->client->put_object(
			$bucket,
			$key,
			$local_path,
			$mime,
			'max-age=31536000'
			// No ACL – R2 does not support ACLs
		);

		return $key;
	}

	/**
	 * Upload all sizes (original + thumbnails) of an Image to R2 and mutate the model.
	 */
	public function upload_image( Image $image ): Image {
		if ( ! $image->local_path || ! file_exists( $image->local_path ) ) {
			throw new \RuntimeException( 'Image has no local file to upload.' );
		}

		$base_key = "galleries/{$image->gallery_id}/{$image->filename}";
		$key      = $this->upload( $image->local_path, $base_key );

		$image->s3_key         = $key;
		$image->s3_bucket      = $this->settings['bucket'];
		$image->storage_driver = 'r2';
		$image->cloudfront_url = $this->get_public_url( $key );

		// Upload thumbnails.
		foreach ( ( $image->meta['thumbs'] ?? [] ) as $size => $thumb ) {
			if ( empty( $thumb['path'] ) || ! file_exists( $thumb['path'] ) ) {
				continue;
			}

			$thumb_key = "galleries/{$image->gallery_id}/thumbs/{$size}/" . basename( $thumb['path'] );
			$uploaded  = $this->upload( $thumb['path'], $thumb_key );

			$image->meta['thumbs'][ $size ]['s3_key'] = $uploaded;
			$image->meta['thumbs'][ $size ]['url']    = $this->get_public_url( $uploaded );
		}

		// Optionally remove local files after successful upload.
		if ( ! empty( $this->settings['delete_local_after_upload'] ) ) {
			@unlink( $image->local_path );
			$image->local_path = null;
		}

		return $image;
	}

	// ------------------------------------------------------------------
	// Delete
	// ------------------------------------------------------------------

	public function delete( string $r2_key ): void {
		try {
			$this->client->delete_object( $this->settings['bucket'], $r2_key );
		} catch ( \RuntimeException $e ) {
			// Log but do not throw – deletion failure should not block UI.
			error_log( "BltGallery R2 delete failed for {$r2_key}: " . $e->getMessage() );
		}
	}

	// ------------------------------------------------------------------
	// URL helpers
	// ------------------------------------------------------------------

	/**
	 * Build a public URL for an R2 object key using the configured public base URL.
	 * Returns an empty string if no public URL is configured.
	 */
	public function get_public_url( string $r2_key ): string {
		$base = rtrim( $this->settings['public_url'] ?? '', '/' );
		if ( ! $base ) {
			return '';
		}
		return "{$base}/" . ltrim( $r2_key, '/' );
	}

	// ------------------------------------------------------------------
	// Configuration helpers
	// ------------------------------------------------------------------

	/**
	 * Verify credentials and bucket access by sending a HEAD request.
	 *
	 * @throws \RuntimeException with a human-readable message on failure.
	 */
	public function check_connection(): void {
		$this->client->head_bucket( $this->settings['bucket'] ?? '' );
	}

	public static function is_configured(): bool {
		$settings = self::load_settings_static();
		return ! empty( $settings['account_id'] )
			&& ! empty( $settings['access_key_id'] )
			&& ! empty( $settings['secret_access_key'] )
			&& ! empty( $settings['bucket'] );
	}

	private function build_client(): S3HttpClient {
		$account_id = $this->settings['account_id'] ?? '';
		$endpoint   = "https://{$account_id}.r2.cloudflarestorage.com";

		return new S3HttpClient(
			$this->settings['access_key_id']     ?? '',
			$this->settings['secret_access_key'] ?? '',
			'auto',
			$endpoint
		);
	}

	private function load_settings(): array {
		return self::load_settings_static();
	}

	private static function load_settings_static(): array {
		$raw = get_option( 'bltgallery_r2_settings', [] );
		return is_array( $raw ) ? $raw : [];
	}
}
