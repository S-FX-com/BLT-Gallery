<?php

declare( strict_types=1 );

namespace ZymGallery\Aws;

use Aws\CloudFront\CloudFrontClient;
use ZymGallery\Models\Image;

/**
 * Generates CloudFront URLs for images stored in S3.
 *
 * Settings (stored within 'zymgallery_aws_settings' option):
 *   cloudfront_domain    – e.g. 'd1234abcd.cloudfront.net'
 *   cloudfront_key_id    – Key-pair ID for signed URLs (optional)
 *   cloudfront_pem_path  – Absolute path to the private key PEM (optional)
 *   cloudfront_signed_urls – boolean; generate signed URLs (default false)
 */
class CloudFrontCDN {

	private array $settings;

	public function __construct() {
		$this->settings = $this->load_settings();
	}

	// ------------------------------------------------------------------
	// URL generation
	// ------------------------------------------------------------------

	/**
	 * Decorate an Image model with CloudFront URLs.
	 * This mutates meta.thumbs[*].url and sets cloudfront_url on the model.
	 */
	public function apply_to_image( Image $image ): Image {
		if ( ! $this->is_configured() ) {
			return $image;
		}

		if ( $image->s3_key ) {
			$image->cloudfront_url = $this->url_for_key( $image->s3_key );
		}

		foreach ( ( $image->meta['thumbs'] ?? [] ) as $size => $thumb ) {
			if ( ! empty( $thumb['s3_key'] ) ) {
				$image->meta['thumbs'][ $size ]['url'] = $this->url_for_key( $thumb['s3_key'] );
			}
		}

		return $image;
	}

	/**
	 * Build a CloudFront URL for the given S3 key.
	 * If signed URLs are enabled and credentials are present, returns a signed URL.
	 */
	public function url_for_key( string $s3_key, int $expires_in = 86400 ): string {
		$domain = rtrim( $this->settings['cloudfront_domain'] ?? '', '/' );
		$path   = '/' . ltrim( $s3_key, '/' );

		if ( $this->signed_urls_enabled() ) {
			return $this->signed_url( $domain, $path, $expires_in );
		}

		return "https://{$domain}{$path}";
	}

	// ------------------------------------------------------------------
	// Invalidation
	// ------------------------------------------------------------------

	/**
	 * Invalidate a list of paths in the CloudFront distribution.
	 */
	public function invalidate( array $paths, string $distribution_id ): void {
		if ( ! $this->is_configured() || ! $distribution_id ) {
			return;
		}

		try {
			$client = new CloudFrontClient( [
				'version'     => 'latest',
				'region'      => $this->settings['region'] ?? 'us-east-1',
				'credentials' => [
					'key'    => $this->settings['access_key_id'],
					'secret' => $this->settings['secret_access_key'],
				],
			] );

			$client->createInvalidation( [
				'DistributionId'    => $distribution_id,
				'InvalidationBatch' => [
					'CallerReference' => uniqid( 'zym_', true ),
					'Paths'           => [
						'Quantity' => count( $paths ),
						'Items'    => $paths,
					],
				],
			] );
		} catch ( \Exception $e ) {
			error_log( 'ZymGallery CloudFront invalidation failed: ' . $e->getMessage() );
		}
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	public function is_configured(): bool {
		return ! empty( $this->settings['cloudfront_domain'] );
	}

	private function signed_urls_enabled(): bool {
		return ! empty( $this->settings['cloudfront_signed_urls'] )
			&& ! empty( $this->settings['cloudfront_key_id'] )
			&& ! empty( $this->settings['cloudfront_pem_path'] )
			&& file_exists( $this->settings['cloudfront_pem_path'] );
	}

	private function signed_url( string $domain, string $path, int $expires_in ): string {
		$expires = time() + $expires_in;
		$url     = "https://{$domain}{$path}";

		$policy = json_encode( [
			'Statement' => [ [
				'Resource'  => $url,
				'Condition' => [
					'DateLessThan' => [ 'AWS:EpochTime' => $expires ],
				],
			] ],
		] );

		$key_id  = $this->settings['cloudfront_key_id'];
		$pem     = file_get_contents( $this->settings['cloudfront_pem_path'] );
		$key     = openssl_pkey_get_private( $pem );

		if ( ! $key ) {
			return $url; // Fall back to unsigned if key invalid.
		}

		openssl_sign( $policy, $signature, $key, OPENSSL_ALGO_SHA1 );

		$encoded_signature = str_replace(
			[ '+', '=', '/' ],
			[ '-', '_', '~' ],
			base64_encode( $signature )
		);

		return "{$url}?Policy=" . urlencode( base64_encode( $policy ) )
			. "&Signature={$encoded_signature}"
			. "&Key-Pair-Id={$key_id}";
	}

	private function load_settings(): array {
		$raw = get_option( 'zymgallery_aws_settings', [] );
		return is_array( $raw ) ? $raw : [];
	}
}
