<?php

declare( strict_types=1 );

namespace ZymGallery\Storage;

/**
 * Minimal S3-compatible HTTP client using AWS Signature Version 4.
 *
 * No SDK dependency – uses WordPress wp_remote_request() for all HTTP calls.
 * Compatible with AWS S3 and Cloudflare R2 (path-style endpoints).
 */
class S3HttpClient {

	private string $access_key;
	private string $secret_key;
	private string $region;
	private string $endpoint; // base URL, no trailing slash

	public function __construct(
		string $access_key,
		string $secret_key,
		string $region,
		string $endpoint
	) {
		$this->access_key = $access_key;
		$this->secret_key = $secret_key;
		$this->region     = $region;
		$this->endpoint   = rtrim( $endpoint, '/' );
	}

	// ------------------------------------------------------------------
	// Public operations
	// ------------------------------------------------------------------

	/**
	 * Upload a local file to S3/R2 via PUT.
	 *
	 * @throws \RuntimeException on failure.
	 */
	public function put_object(
		string $bucket,
		string $key,
		string $local_path,
		string $content_type,
		string $cache_control = 'max-age=31536000',
		string $acl = ''
	): void {
		$body = file_get_contents( $local_path );
		if ( $body === false ) {
			throw new \RuntimeException( "Cannot read file: {$local_path}" );
		}

		$body_hash = hash( 'sha256', $body );
		$url       = $this->build_url( $bucket, $key );

		$headers = [
			'Content-Type'         => $content_type,
			'Content-Length'       => (string) strlen( $body ),
			'x-amz-content-sha256' => $body_hash,
		];

		if ( $cache_control ) {
			$headers['Cache-Control'] = $cache_control;
		}

		if ( $acl ) {
			$headers['x-amz-acl'] = $acl;
		}

		$signed = $this->sign( 'PUT', $url, $headers, $body_hash );

		$response = wp_remote_request( $url, [
			'method'  => 'PUT',
			'headers' => $signed,
			'body'    => $body,
			'timeout' => 120,
		] );

		$this->assert_success( $response, 'PUT', $url );
	}

	/**
	 * Check that a bucket exists and credentials are valid via HEAD.
	 *
	 * @throws \RuntimeException if the bucket is unreachable or credentials are wrong.
	 */
	public function head_bucket( string $bucket ): void {
		$empty_hash = hash( 'sha256', '' );
		$url        = $this->endpoint . '/' . ltrim( $bucket, '/' );

		$headers = [
			'x-amz-content-sha256' => $empty_hash,
		];

		$signed = $this->sign( 'HEAD', $url, $headers, $empty_hash );

		$response = wp_remote_request( $url, [
			'method'  => 'HEAD',
			'headers' => $signed,
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException( 'Connection failed: ' . $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code === 403 ) {
			throw new \RuntimeException( 'Access denied – check your credentials and bucket permissions.' );
		}

		if ( $code === 404 ) {
			throw new \RuntimeException( 'Bucket not found – check the bucket name.' );
		}

		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			throw new \RuntimeException( "Unexpected response HTTP {$code}: {$body}" );
		}
	}

	/**
	 * Delete an object from S3/R2.
	 *
	 * Does not throw on 404 (already gone = success).
	 *
	 * @throws \RuntimeException on non-404 failure.
	 */
	public function delete_object( string $bucket, string $key ): void {
		$empty_hash = hash( 'sha256', '' );
		$url        = $this->build_url( $bucket, $key );

		$headers = [
			'x-amz-content-sha256' => $empty_hash,
		];

		$signed = $this->sign( 'DELETE', $url, $headers, $empty_hash );

		$response = wp_remote_request( $url, [
			'method'  => 'DELETE',
			'headers' => $signed,
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				'S3 DELETE request failed: ' . $response->get_error_message()
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code !== 204 && $code !== 200 && $code !== 404 ) {
			$body = wp_remote_retrieve_body( $response );
			throw new \RuntimeException( "S3 DELETE returned HTTP {$code}: {$body}" );
		}
	}

	// ------------------------------------------------------------------
	// AWS Signature Version 4
	// ------------------------------------------------------------------

	/**
	 * Sign a request and return the final headers array (ready for wp_remote_request).
	 */
	private function sign( string $method, string $url, array $headers, string $body_hash ): array {
		$parsed = parse_url( $url );
		$host   = $parsed['host'];
		$path   = $parsed['path'] ?? '/';
		$query  = $parsed['query'] ?? '';

		$now       = new \DateTimeImmutable( 'now', new \DateTimeZone( 'UTC' ) );
		$date_time = $now->format( 'Ymd\THis\Z' ); // 20260409T123456Z
		$date_only = $now->format( 'Ymd' );          // 20260409

		$headers['Host']         = $host;
		$headers['x-amz-date']  = $date_time;

		// Build lowercase-sorted headers map for canonical form.
		$lower = [];
		foreach ( $headers as $k => $v ) {
			$lower[ strtolower( $k ) ] = trim( (string) $v );
		}
		ksort( $lower );

		$canonical_headers_str = '';
		foreach ( $lower as $k => $v ) {
			$canonical_headers_str .= $k . ':' . $v . "\n";
		}

		$signed_headers_list = implode( ';', array_keys( $lower ) );

		// Canonical request.
		$canonical_request = implode( "\n", [
			$method,
			$this->encode_uri_path( $path ),
			$this->canonical_query_string( $query ),
			$canonical_headers_str,
			$signed_headers_list,
			$body_hash,
		] );

		// Credential scope & string to sign.
		$scope = "{$date_only}/{$this->region}/s3/aws4_request";

		$string_to_sign = implode( "\n", [
			'AWS4-HMAC-SHA256',
			$date_time,
			$scope,
			hash( 'sha256', $canonical_request ),
		] );

		// Derive signing key: HMAC chain.
		$signing_key = $this->hmac(
			$this->hmac(
				$this->hmac(
					$this->hmac( 'AWS4' . $this->secret_key, $date_only ),
					$this->region
				),
				's3'
			),
			'aws4_request'
		);

		$signature = bin2hex( $this->hmac( $signing_key, $string_to_sign ) );

		// Add Authorization header to the original (mixed-case) headers.
		$headers['Authorization'] = sprintf(
			'AWS4-HMAC-SHA256 Credential=%s/%s,SignedHeaders=%s,Signature=%s',
			$this->access_key,
			$scope,
			$signed_headers_list,
			$signature
		);
		$headers['x-amz-date'] = $date_time;

		return $headers;
	}

	private function hmac( string $key, string $data ): string {
		return hash_hmac( 'sha256', $data, $key, true );
	}

	/**
	 * URL-encode a path without double-encoding slashes (S3 rule).
	 */
	private function encode_uri_path( string $path ): string {
		$segments = explode( '/', $path );
		return implode( '/', array_map( 'rawurlencode', $segments ) );
	}

	/**
	 * Build a canonically sorted & encoded query string from a raw query.
	 */
	private function canonical_query_string( string $query ): string {
		if ( ! $query ) {
			return '';
		}
		parse_str( $query, $params );
		ksort( $params );
		$parts = [];
		foreach ( $params as $k => $v ) {
			$parts[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}
		return implode( '&', $parts );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function build_url( string $bucket, string $key ): string {
		$key = ltrim( $key, '/' );
		return $this->endpoint . '/' . $bucket . '/' . $key;
	}

	private function assert_success( mixed $response, string $method, string $url ): void {
		if ( is_wp_error( $response ) ) {
			throw new \RuntimeException(
				"S3 {$method} failed: " . $response->get_error_message()
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			throw new \RuntimeException(
				"S3 {$method} returned HTTP {$code} for {$url}: {$body}"
			);
		}
	}
}
