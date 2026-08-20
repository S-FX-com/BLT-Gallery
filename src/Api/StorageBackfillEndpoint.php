<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\StorageBackfillJob;
use BltGallery\Core\StorageBackfillRunner;
use BltGallery\Core\StorageOffloader;

/**
 * REST API endpoints for pushing existing local images to remote storage.
 *
 * GET  /bltgallery/v1/storage/backfill/job     – current run + backlog size
 * POST /bltgallery/v1/storage/backfill/start   – queue a background run
 * POST /bltgallery/v1/storage/backfill/cancel  – stop it
 * POST /bltgallery/v1/storage/backfill/tick    – run one pass in the foreground
 */
class StorageBackfillEndpoint {

	const NAMESPACE = 'bltgallery/v1';
	const BASE      = '/storage/backfill';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/job',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'job' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/start',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'start' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/cancel',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cancel' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/tick',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'tick' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	/**
	 * Current run, or an idle summary carrying today's backlog size so the
	 * Settings page can show "1,847 images not yet in R2" before anyone
	 * presses start.
	 */
	public function job(): WP_REST_Response {
		$job = StorageBackfillJob::get( true );

		if ( ! $job ) {
			return new WP_REST_Response(
				StorageBackfillJob::idle_response( StorageOffloader::driver(), StorageBackfillRunner::pending() )
			);
		}

		$stalled = StorageBackfillRunner::is_stalled( $job );

		if ( $stalled ) {
			$job = StorageBackfillRunner::revive() ?: $job;
		}

		return new WP_REST_Response( StorageBackfillJob::to_response( $job, $stalled ) );
	}

	public function start(): WP_REST_Response|WP_Error {
		try {
			$job = StorageBackfillRunner::start();
		} catch ( \Throwable $e ) {
			return new WP_Error( 'backfill_start_failed', $e->getMessage(), [ 'status' => 409 ] );
		}

		return new WP_REST_Response( StorageBackfillJob::to_response( $job ), 201 );
	}

	public function cancel(): WP_REST_Response {
		$job = StorageBackfillRunner::cancel();

		if ( ! $job ) {
			return new WP_REST_Response(
				StorageBackfillJob::idle_response( StorageOffloader::driver(), StorageBackfillRunner::pending() )
			);
		}

		return new WP_REST_Response( StorageBackfillJob::to_response( $job ) );
	}

	/**
	 * Run one pass inside this request. The Settings page falls back to this
	 * when a run stalls — WP-Cron disabled and loopback requests blocked.
	 */
	public function tick(): WP_REST_Response {
		$job = StorageBackfillRunner::process( 20.0 );

		if ( ! $job ) {
			return new WP_REST_Response(
				StorageBackfillJob::idle_response( StorageOffloader::driver(), StorageBackfillRunner::pending() )
			);
		}

		return new WP_REST_Response( StorageBackfillJob::to_response( $job ) );
	}

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
