<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Aws\S3Storage;
use BltGallery\Core\UrlMigrator;
use BltGallery\Storage\R2Storage;

/**
 * REST API endpoints for plugin settings.
 *
 * GET  /bltgallery/v1/settings        – get all settings
 * POST /bltgallery/v1/settings        – update general settings
 * GET  /bltgallery/v1/settings/aws    – get AWS settings (keys masked)
 * POST /bltgallery/v1/settings/aws    – update AWS settings
 * POST /bltgallery/v1/settings/aws/test – test S3 connection
 */
class SettingsEndpoint {

	const NAMESPACE = 'bltgallery/v1';

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

		register_rest_route(
			self::NAMESPACE,
			'/settings/r2/migrate-urls',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_url_migration_status' ],
					'permission_callback' => [ $this, 'permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'run_url_migration' ],
					'permission_callback' => [ $this, 'permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/settings/cf-images',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_cf_images_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'update_cf_images_settings' ],
					'permission_callback' => [ $this, 'permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/updates/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_update_status' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/updates/check',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'check_for_update' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	// ------------------------------------------------------------------
	// Plugin update status
	// ------------------------------------------------------------------

	public function get_update_status(): WP_REST_Response {
		return new WP_REST_Response( $this->build_update_status() );
	}

	public function check_for_update(): WP_REST_Response {
		// Force WordPress to re-poll all update sources on the next read
		// by dropping the cached transient, then trigger an immediate
		// refresh so the result is visible without waiting for a page load.
		delete_site_transient( 'update_plugins' );

		if ( ! function_exists( 'wp_update_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/update.php';
		}
		wp_update_plugins();

		return new WP_REST_Response( $this->build_update_status() );
	}

	private function build_update_status(): array {
		$basename = defined( 'BLT_GALLERY_PLUGIN_BASENAME' ) ? BLT_GALLERY_PLUGIN_BASENAME : plugin_basename( BLT_GALLERY_PLUGIN_FILE );
		$current  = defined( 'BLT_GALLERY_VERSION' ) ? BLT_GALLERY_VERSION : '';

		$transient    = get_site_transient( 'update_plugins' );
		$last_checked = isset( $transient->last_checked ) ? (int) $transient->last_checked : 0;
		$latest       = null;
		$package_url  = null;
		$update_url   = null;

		if ( isset( $transient->response[ $basename ] ) ) {
			$row         = $transient->response[ $basename ];
			$latest      = isset( $row->new_version ) ? (string) $row->new_version : null;
			$package_url = isset( $row->package )     ? (string) $row->package     : null;
			$update_url  = isset( $row->url )         ? (string) $row->url         : null;
		} elseif ( isset( $transient->no_update[ $basename ]->new_version ) ) {
			$latest = (string) $transient->no_update[ $basename ]->new_version;
		}

		return [
			'current_version' => $current,
			'latest_version'  => $latest,
			'update_available' => $latest !== null && version_compare( $latest, $current, '>' ),
			'last_checked'    => $last_checked,
			'last_checked_human' => $last_checked ? human_time_diff( $last_checked ) . ' ago' : null,
			'package_url'     => $package_url,
			'update_url'      => $update_url,
			'plugins_page'    => self_admin_url( 'plugins.php' ),
		];
	}

	// ------------------------------------------------------------------
	// Cloudflare Images settings (URL-based resizing via /cdn-cgi/image/)
	// ------------------------------------------------------------------

	public function get_cf_images_settings(): WP_REST_Response {
		return new WP_REST_Response( get_option( 'bltgallery_cf_images_settings', [] ) );
	}

	public function update_cf_images_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'bltgallery_cf_images_settings', [] );
		$incoming = $request->get_json_params() ?? [];

		$current['enabled']         = ! empty( $incoming['enabled'] );
		$current['zone_url']        = isset( $incoming['zone_url'] )       ? esc_url_raw( (string) $incoming['zone_url'] ) : ( $current['zone_url'] ?? '' );
		$current['default_format']  = isset( $incoming['default_format'] ) ? sanitize_key( (string) $incoming['default_format'] ) : ( $current['default_format'] ?? 'auto' );
		$current['default_quality'] = isset( $incoming['default_quality'] ) ? min( 100, max( 1, (int) $incoming['default_quality'] ) ) : ( $current['default_quality'] ?? 85 );
		$current['default_fit']     = isset( $incoming['default_fit'] )    ? sanitize_key( (string) $incoming['default_fit'] ) : ( $current['default_fit'] ?? 'cover' );
		$current['sharpen']         = isset( $incoming['sharpen'] )        ? max( 0, min( 10, (float) $incoming['sharpen'] ) ) : ( $current['sharpen'] ?? 0 );

		update_option( 'bltgallery_cf_images_settings', $current );

		return new WP_REST_Response( $current );
	}

	// ------------------------------------------------------------------
	// General settings
	// ------------------------------------------------------------------

	public function get_settings(): WP_REST_Response {
		$settings = get_option( 'bltgallery_settings', $this->default_settings() );
		return new WP_REST_Response( $settings );
	}

	public function update_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'bltgallery_settings', $this->default_settings() );
		$incoming = $request->get_json_params() ?? [];

		$allowed_keys = array_keys( $this->default_settings() );
		foreach ( $allowed_keys as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$current[ $key ] = $this->sanitize_setting( $key, $incoming[ $key ] );
			}
		}

		update_option( 'bltgallery_settings', $current );

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
			// Per-integration enable flags. Drive which panels the admin
			// Settings page reveals and which provider serves uploads.
			'enable_s3'                => false,
			'enable_r2'                => false,
			'enable_cf_images'         => false,
			// Legacy single-value selector kept so old saves don't lose state.
			'storage_driver'           => 'local',
		];
	}

	private function sanitize_setting( string $key, mixed $value ): mixed {
		return match ( $key ) {
			'default_display_type'     => sanitize_key( (string) $value ),
			'storage_driver'           => in_array( (string) $value, [ 'local', 's3', 'r2' ], true ) ? (string) $value : 'local',
			'webp_quality'             => min( 100, max( 1, (int) $value ) ),
			'thumb_width', 'thumb_height' => max( 1, (int) $value ),
			'delete_data_on_uninstall', 'lazy_load', 'enable_s3', 'enable_r2', 'enable_cf_images' => (bool) $value,
			default                    => $value,
		};
	}

	// ------------------------------------------------------------------
	// AWS settings
	// ------------------------------------------------------------------

	public function get_aws_settings(): WP_REST_Response {
		$settings = get_option( 'bltgallery_aws_settings', [] );

		// Mask secrets before sending to the client.
		$masked = $settings;
		if ( ! empty( $masked['secret_access_key'] ) ) {
			$masked['secret_access_key'] = str_repeat( '*', 20 );
		}

		return new WP_REST_Response( $masked );
	}

	public function update_aws_settings( WP_REST_Request $request ): WP_REST_Response {
		$current  = get_option( 'bltgallery_aws_settings', [] );
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

		update_option( 'bltgallery_aws_settings', $current );

		return new WP_REST_Response( $this->get_aws_settings()->get_data() );
	}

	// ------------------------------------------------------------------
	// S3 connection test
	// ------------------------------------------------------------------

	public function test_s3_connection(): WP_REST_Response {
		if ( ! S3Storage::is_configured() ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'AWS credentials are not configured.', 'bltgallery' ),
			] );
		}

		try {
			$s3 = new S3Storage();
			$s3->check_connection();

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'S3 connection successful.', 'bltgallery' ),
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
		$settings = get_option( 'bltgallery_r2_settings', [] );

		$masked = $settings;
		if ( ! empty( $masked['secret_access_key'] ) ) {
			$masked['secret_access_key'] = str_repeat( '*', 20 );
		}

		return new WP_REST_Response( $masked );
	}

	public function update_r2_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$current  = get_option( 'bltgallery_r2_settings', [] );
		$incoming = $request->get_json_params() ?? [];

		if ( array_key_exists( 'public_url', $incoming )
			&& ! R2Storage::is_public_url_safe( (string) $incoming['public_url'] ) ) {
			return new WP_Error(
				'bltgallery_r2_public_url_invalid',
				__( 'Cloudflare\'s default "pub-*.r2.dev" URLs cannot be used. Microsoft Defender, Teams Safe Links, and similar scanners block these hostnames because they are heavily abused by phishing campaigns. Connect a custom domain to your R2 bucket and enter that URL instead (see the instructions above the field).', 'bltgallery' ),
				[ 'status' => 400 ]
			);
		}

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

		update_option( 'bltgallery_r2_settings', $current );

		return new WP_REST_Response( $this->get_r2_settings()->get_data() );
	}

	public function test_r2_connection(): WP_REST_Response {
		if ( ! R2Storage::is_configured() ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => __( 'Cloudflare R2 credentials are not configured.', 'bltgallery' ),
			] );
		}

		try {
			$r2 = new R2Storage();
			$r2->check_connection();

			return new WP_REST_Response( [
				'success' => true,
				'message' => __( 'R2 connection successful.', 'bltgallery' ),
			] );
		} catch ( \Throwable $e ) {
			return new WP_REST_Response( [
				'success' => false,
				'message' => $e->getMessage(),
			] );
		}
	}

	/**
	 * Surfaces what the R2 URL migration tool would do, so the admin UI
	 * can decide whether to show the migration card and prefill the
	 * sample before/after diff. Does not modify anything.
	 */
	public function get_url_migration_status(): WP_REST_Response {
		$r2_settings = get_option( 'bltgallery_r2_settings', [] );
		$target_url  = is_array( $r2_settings ) ? (string) ( $r2_settings['public_url'] ?? '' ) : '';
		$target_safe = '' !== $target_url && R2Storage::is_public_url_safe( $target_url );
		$count       = UrlMigrator::count_unsafe();

		$blocked_reason = null;
		if ( $count === 0 ) {
			$blocked_reason = null;
		} elseif ( '' === $target_url ) {
			$blocked_reason = __( 'Set your Public Base URL to the bucket\'s custom domain first, then return here to migrate.', 'bltgallery' );
		} elseif ( ! $target_safe ) {
			$blocked_reason = __( 'Your Public Base URL is still an r2.dev hostname. Replace it with your custom domain, then return here to migrate.', 'bltgallery' );
		}

		$samples = $target_safe ? UrlMigrator::preview( $target_url )['samples'] : [];

		return new WP_REST_Response( [
			'unsafe_count'     => $count,
			'target_url'       => $target_url,
			'target_safe'      => $target_safe,
			'sample_from_host' => UrlMigrator::sample_from_host(),
			'samples'          => $samples,
			'ready'            => $target_safe && $count > 0,
			'blocked_reason'   => $blocked_reason,
		] );
	}

	/**
	 * Runs one batch of the URL migration. Re-call until `more` is false.
	 */
	public function run_url_migration( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$r2_settings = get_option( 'bltgallery_r2_settings', [] );
		$target_url  = is_array( $r2_settings ) ? (string) ( $r2_settings['public_url'] ?? '' ) : '';

		if ( '' === $target_url || ! R2Storage::is_public_url_safe( $target_url ) ) {
			return new WP_Error(
				'bltgallery_r2_migrate_no_target',
				__( 'Cannot migrate: the saved Public Base URL is missing or is still an r2.dev hostname. Set a custom domain first.', 'bltgallery' ),
				[ 'status' => 400 ]
			);
		}

		$params     = $request->get_json_params() ?? [];
		$batch_size = isset( $params['batch_size'] ) ? (int) $params['batch_size'] : 100;
		$cursor     = isset( $params['cursor'] ) ? max( 0, (int) $params['cursor'] ) : 0;

		$result = UrlMigrator::migrate_batch( $target_url, $batch_size, $cursor );

		return new WP_REST_Response( $result );
	}

	// ------------------------------------------------------------------

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
