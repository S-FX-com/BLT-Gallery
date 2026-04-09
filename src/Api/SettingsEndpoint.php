<?php

declare( strict_types=1 );

namespace ZymGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZymGallery\Aws\S3Storage;
use ZymGallery\Storage\R2Storage;

/**
 * REST API endpoints for plugin settings.
 *
 * GET  /zymgallery/v1/settings        – get all settings
 * POST /zymgallery/v1/settings        – update general settings
 * GET  /zymgallery/v1/settings/aws    – get AWS settings (keys masked)
 * POST /zymgallery/v1/settings/aws    – update AWS settings
 * POST /zymgallery/v1/settings/aws/test – test S3 connection
 */
class SettingsEndpoint {

	const NAMESPACE = 'zymgallery/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/aws',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_aws_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_aws_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/aws/test',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_s3_connection' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/r2',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_r2_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_r2_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/r2/test',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_r2_connection' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	// ------------------------------------------------------------------
	// General settings
	// ------------------------------------------------------------------

	public function get_settings(): WP_REST_Response {
		$settings = get_option( 'zymgallery_settings', $this->default_settings() );
		return new WP_REST_Response( $settings );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'zymgallery_settings', $this->default_settings() );
		$incoming = $request->get_json_params() ?? [];

		$allowed_keys = array_keys( $this->default_settings() );
		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$current[ $key ] = $this->sanitize_setting( $key, $incoming[ $key ] );
			}
		}

		update_option( 'zymgallery_settings', $current );

		return new WP_REST_Response( $current );
	}

	private function default_settings(): array {
		return [
			'delete_data_on_uninstall' => false,
			'default_display_type'     => 'masonry',
			'lazy_load'                => true,
			'webp_quality'             => 85,
			'thumb_width'              => 320,
			'thumb_height'             => 320,
		];
	}

	private function sanitize_setting( string $key, mixed $value ): mixed {
		return match ( $key ) {
			'default_display_type'     => sanitize_key( (string) $value ),
			'webp_quality'             => min( 100, max( 1, (int) $value ) ),
			'thumb_width', 'thumb_height' => max( 1, (int) $value ),
			'delete_data_on_uninstall', 'lazy_load' => (bool) $value,
			default                    => $value,
		};
	}

	// ------------------------------------------------------------------
	// AWS settings
	// ------------------------------------------------------------------

	public function get_aws_settings(): WP_REST_Response {
		$settings = get_option( 'zymgallery_aws_settings', [] );

		// Mask secrets before sending to the client.
		$masked = $settings;
		if ( ! empty( $masked['secret_access_key'] ) ) {
			$masked['secret_access_key'] = str_repeat( '*', 20 );
		}

		return new WP_REST_Response( $masked );
	}

	public function update_aws_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'zymgallery_aws_settings', [] );
		$incoming = $request->get_json_params() ?? [];

		$allowed = [
			'access_key_id', 'secret_access_key', 'region', 'bucket',
			'path_prefix', 'acl', 'cloudfront_domain', 'cloudfront_key_id',
			'cloudfront_pem_path', 'cloudfront_signed_urls',
			'auto_offload', 'delete_local_after_upload',
			'cloudfront_distribution_id',
		];

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				continue;
			}

			// Preserve existing secret if placeholder is sent back.
			if ( 'secret_access_key' === $key && str_starts_with( (string) $incoming[ $key ], '***' ) ) {
				continue;
			}

			$current[ $key ] = sanitize_text_field( (string) $incoming[ $key ] );
		}

		update_option( 'zymgallery_aws_settings', $current );

		return new WP_REST_Response( $this->get_aws_settings()->get_data() );
	}

	// ------------------------------------------------------------------
	// S3 connection test
	// ------------------------------------------------------------------

	public function test_s3_connection(): WP_REST_Response {
		if ( ! S3Storage::is_configured() ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'AWS credentials are not configured.', 'zymgallery' ),
			] );
		}

		try {
			$s3 = new S3Storage();
			$s3->check_connection();

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'S3 connection successful.', 'zymgallery' ),
			] );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	// ------------------------------------------------------------------
	// Cloudflare R2 settings
	// ------------------------------------------------------------------

	public function get_r2_settings(): WP_REST_Response {
		$settings = get_option( 'zymgallery_r2_settings', [] );

		$masked = $settings;
		if ( ! empty( $masked['secret_access_key'] ) ) {
			$masked['secret_access_key'] = str_repeat( '*', 20 );
		}

		return new WP_REST_Response( $masked );
	}

	public function update_r2_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'zymgallery_r2_settings', [] );
		$incoming = $request->get_json_params() ?? [];

		$allowed = [
			'account_id', 'access_key_id', 'secret_access_key',
			'bucket', 'path_prefix', 'public_url',
			'auto_offload', 'delete_local_after_upload',
		];

		foreach ( $allowed as $key ) {
			if ( ! array_key_exists( $key, $incoming ) ) {
				continue;
			}

			// Preserve existing secret if the masked placeholder is sent back.
			if ( 'secret_access_key' === $key && str_starts_with( (string) $incoming[ $key ], '***' ) ) {
				continue;
			}

			$current[ $key ] = sanitize_text_field( (string) $incoming[ $key ] );
		}

		// Boolean fields.
		foreach ( [ 'auto_offload', 'delete_local_after_upload' ] as $bool_key ) {
			if ( array_key_exists( $bool_key, $incoming ) ) {
				$current[ $bool_key ] = filter_var( $incoming[ $bool_key ], FILTER_VALIDATE_BOOLEAN );
			}
		}

		update_option( 'zymgallery_r2_settings', $current );

		return new WP_REST_Response( $this->get_r2_settings()->get_data() );
	}

	public function test_r2_connection(): WP_REST_Response {
		if ( ! R2Storage::is_configured() ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Cloudflare R2 credentials are not configured.', 'zymgallery' ),
			] );
		}

		try {
			$r2 = new R2Storage();
			$r2->check_connection();

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'R2 connection successful.', 'zymgallery' ),
			] );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	// ------------------------------------------------------------------

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
