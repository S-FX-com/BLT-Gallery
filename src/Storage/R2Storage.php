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

		// Mirror the on-disk gallery folder ("{id}-{slug}") so the bucket layout
		// matches local storage and is easy to browse.
		$folder   = basename( dirname( $image->local_path ) );
		$base_key = "galleries/{$folder}/{$image->filename}";
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

			$thumb_key = "galleries/{$folder}/thumbs/{$size}/" . basename( $thumb['path'] );
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
			error_log( "BLT Gallery R2 delete failed for {$r2_key}: " . $e->getMessage() );
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

	/**
	 * Cloudflare's default `pub-<hash>.r2.dev` hostnames are heavily abused
	 * by phishing campaigns and broadly blocked by Microsoft Defender /
	 * Teams Safe Links, so we require galleries to be served from a custom
	 * domain attached to the bucket.
	 *
	 * An empty value is treated as "not yet set" rather than unsafe.
	 */
	public static function is_public_url_safe( string $public_url ): bool {
		$public_url = trim( $public_url );
		if ( '' === $public_url ) {
			return true;
		}
		$normalized = preg_match( '#^https?://#i', $public_url )
			? $public_url
			: 'https://' . ltrim( $public_url, '/' );
		$host = parse_url( $normalized, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return true;
		}
		$host = strtolower( $host );
		return 'r2.dev' !== $host && ! str_ends_with( $host, '.r2.dev' );
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

	/**
	 * The stored R2 settings, with the BLT family shared store filling in any
	 * field this site has not set locally.
	 *
	 * Per-field precedence is the plugin's own option first, then the shared
	 * store (there is no wp-config constant for these). Nothing here writes
	 * back to `bltgallery_r2_settings`: a shared value is read-only, so the
	 * Settings screen keeps showing an empty field as empty.
	 *
	 * BLT_Family::get() is itself gated on a per-plugin opt-in that defaults
	 * off, so on a site that has not granted it this returns exactly the array
	 * it always did.
	 *
	 * Public because StorageOffloader::driver() resolves the legacy
	 * `auto_offload` flag from the same array and must not read the raw option
	 * behind this accessor's back.
	 */
	public static function load_settings_static(): array {
		$raw      = get_option( 'bltgallery_r2_settings', [] );
		$settings = is_array( $raw ) ? $raw : [];

		if ( ! class_exists( 'BLT_Family' ) ) {
			return $settings;
		}

		/*
		 * account_id comes from the 'cloudflare' group, not 'r2': for R2 it is
		 * whichever Cloudflare account owns the bucket (often the agency's),
		 * which is the same value BLT Secure's account field holds.
		 */
		$shared = [
			'account_id'        => [ 'cloudflare', 'account_id' ],
			'endpoint'          => [ 'r2', 'endpoint' ],
			'region'            => [ 'r2', 'region' ],
			'bucket'            => [ 'r2', 'bucket' ],
			'access_key_id'     => [ 'r2', 'access_key_id' ],
			'secret_access_key' => [ 'r2', 'secret_access_key' ],
			'public_url'        => [ 'r2', 'public_url' ],
		];

		foreach ( $shared as $key => [ $group, $field ] ) {
			if ( '' !== (string) ( $settings[ $key ] ?? '' ) ) {
				continue;
			}

			$value = (string) \BLT_Family::get( 'blt-gallery', $group, $field );

			if ( '' === $value ) {
				continue;
			}

			/*
			 * public_url has to clear the same gate a locally-entered value
			 * does. SettingsEndpoint rejects a save on an *.r2.dev host and
			 * refuses to migrate to one, because Microsoft Defender and Teams
			 * Safe Links block those hostnames. A shared value that skipped the
			 * check would get written into image->cloudfront_url and the
			 * thumbnail meta, and the admin would then have no way to correct it
			 * from this plugin's own Settings screen — the local field it can
			 * edit is empty, so there is nothing there to fix.
			 */
			if ( 'public_url' === $key && ! self::is_public_url_safe( $value ) ) {
				continue;
			}

			$settings[ $key ] = $value;
		}

		return $settings;
	}
}
