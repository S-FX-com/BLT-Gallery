<?php

declare( strict_types=1 );

namespace BltGallery\Aws;

use BltGallery\Models\Image;
use BltGallery\Storage\S3HttpClient;

/**
 * Handles uploading images to AWS S3.
 *
 * Uses S3HttpClient (pure HTTP + AWS SigV4) – no SDK or Composer required.
 *
 * Settings (stored in 'bltgallery_aws_settings' option):
 *   access_key_id     – IAM access key
 *   secret_access_key – IAM secret
 *   region            – e.g. 'us-east-1'
 *   bucket            – S3 bucket name
 *   path_prefix       – optional key prefix, e.g. 'gallery/'
 *   acl               – canned ACL, default 'public-read'
 *   delete_local_after_upload – bool
 */
class S3Storage {

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
	 * Upload a local file to S3 and return the S3 key.
	 *
	 * @param string $local_path  Absolute path to the file.
	 * @param string $s3_key      Desired object key (relative to bucket root).
	 * @return string             The S3 key on success.
	 * @throws \RuntimeException  On S3 failure.
	 */
	public function upload( string $local_path, string $s3_key ): string {
		if ( ! file_exists( $local_path ) ) {
			throw new \RuntimeException( "File not found: {$local_path}" );
		}

		$prefix = rtrim( $this->settings['path_prefix'] ?? '', '/' );
		$key    = $prefix ? "{$prefix}/{$s3_key}" : $s3_key;
		$bucket = $this->settings['bucket'];
		$acl    = $this->settings['acl'] ?? 'public-read';
		$mime   = mime_content_type( $local_path ) ?: 'application/octet-stream';

		$this->client->put_object(
			$bucket,
			$key,
			$local_path,
			$mime,
			'max-age=31536000',
			$acl
		);

		return $key;
	}

	/**
	 * Upload all sizes (original + thumbnails) of an Image to S3 and mutate the model.
	 */
	public function upload_image( Image $image ): Image {
		if ( ! $image->local_path || ! file_exists( $image->local_path ) ) {
			throw new \RuntimeException( 'Image has no local file to upload.' );
		}

		$base_key = "galleries/{$image->gallery_id}/{$image->filename}";
		$key      = $this->upload( $image->local_path, $base_key );

		$image->s3_key         = $key;
		$image->s3_bucket      = $this->settings['bucket'];
		$image->storage_driver = 's3';

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

		// Remove local files after successful upload if configured.
		if ( ! empty( $this->settings['delete_local_after_upload'] ) ) {
			@unlink( $image->local_path );
			$image->local_path = null;
		}

		return $image;
	}

	// ------------------------------------------------------------------
	// Delete
	// ------------------------------------------------------------------

	public function delete( string $s3_key ): void {
		try {
			$this->client->delete_object( $this->settings['bucket'], $s3_key );
		} catch ( \RuntimeException $e ) {
			// Log but do not throw – deletion failure should not block UI.
			error_log( "BltGallery S3 delete failed for {$s3_key}: " . $e->getMessage() );
		}
	}

	// ------------------------------------------------------------------
	// URL helpers
	// ------------------------------------------------------------------

	/**
	 * Returns the public URL for an S3 key (requires public-read ACL or public bucket).
	 */
	public function get_public_url( string $s3_key ): string {
		$region = $this->settings['region'] ?? 'us-east-1';
		$bucket = $this->settings['bucket'];
		return "https://{$bucket}.s3.{$region}.amazonaws.com/{$s3_key}";
	}

	// ------------------------------------------------------------------
	// Helpers
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
		return ! empty( $settings['access_key_id'] )
			&& ! empty( $settings['secret_access_key'] )
			&& ! empty( $settings['bucket'] );
	}

	private function build_client(): S3HttpClient {
		$region   = $this->settings['region'] ?? 'us-east-1';
		$endpoint = "https://s3.{$region}.amazonaws.com";

		return new S3HttpClient(
			$this->settings['access_key_id']     ?? '',
			$this->settings['secret_access_key'] ?? '',
			$region,
			$endpoint
		);
	}

	private function load_settings(): array {
		return self::load_settings_static();
	}

	private static function load_settings_static(): array {
		$raw = get_option( 'bltgallery_aws_settings', [] );
		return is_array( $raw ) ? $raw : [];
	}
}
