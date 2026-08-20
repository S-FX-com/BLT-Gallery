<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Import\NextGenImporter;
use BltGallery\Import\ModulaImporter;
use BltGallery\Import\ImportJob;
use BltGallery\Import\ImportLedger;
use BltGallery\Import\SourceImporter;
use BltGallery\Admin\AdminMenu;
use BltGallery\Import\ImportRunner;

/**
 * REST API endpoints for the gallery importer tool.
 *
 * GET  /bltgallery/v1/import/nextgen/status   – detect if NextGEN is present
 * GET  /bltgallery/v1/import/nextgen/preview  – list NextGEN galleries with image counts
 * POST /bltgallery/v1/import/nextgen/run      – run the import (optionally limit to gallery IDs)
 *
 * GET  /bltgallery/v1/import/modula/status    – detect if Modula galleries exist
 * GET  /bltgallery/v1/import/modula/preview   – list Modula galleries with image counts
 * POST /bltgallery/v1/import/modula/run       – run the import (optionally limit to gallery IDs)
 *
 * Background migrations (both sources, {source} = nextgen|modula):
 *
 * POST /bltgallery/v1/import/{source}/start   – queue a background migration
 * GET  /bltgallery/v1/import/{source}/job     – poll progress for the current job
 * POST /bltgallery/v1/import/{source}/cancel  – stop the current job
 * POST /bltgallery/v1/import/{source}/tick    – run one pass in the foreground
 *
 * The /run routes stay for backwards compatibility, but the admin UI drives
 * the /start + /job pair so large collections can finish with the browser
 * closed.
 */
class ImportEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'status' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/preview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'run' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'gallery_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Specific NextGEN gallery IDs to migrate. Omit to migrate all.', 'bltgallery' ),
						'required'    => false,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/scan',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'scan_legacy' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/backup',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'backup_legacy' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/nextgen/cleanup',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'cleanup_legacy' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'confirm' => [
						'type'        => 'string',
						'description' => __( 'Must equal "DELETE" to proceed.', 'bltgallery' ),
						'required'    => true,
					],
				],
			]
		);

		// ------------------------------------------------------------------
		// Modula
		// ------------------------------------------------------------------

		register_rest_route(
			self::NAMESPACE,
			'/import/modula/status',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'modula_status' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/modula/preview',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'modula_preview' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/modula/run',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'modula_run' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'gallery_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Specific Modula gallery IDs to migrate. Omit to migrate all.', 'bltgallery' ),
						'required'    => false,
					],
				],
			]
		);

		// ------------------------------------------------------------------
		// Background jobs (shared by every source)
		// ------------------------------------------------------------------

		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>nextgen|modula)/start',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'job_start' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'source'      => [
						'type'     => 'string',
						'enum'     => ImportRunner::SOURCES,
						'required' => true,
					],
					'gallery_ids' => [
						'type'        => 'array',
						'items'       => [ 'type' => 'integer' ],
						'description' => __( 'Specific source gallery IDs to migrate. Omit to migrate all.', 'bltgallery' ),
						'required'    => false,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>nextgen|modula)/job',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'job_status' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'source' => [
						'type'     => 'string',
						'enum'     => ImportRunner::SOURCES,
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>nextgen|modula)/cancel',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'job_cancel' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'source' => [
						'type'     => 'string',
						'enum'     => ImportRunner::SOURCES,
						'required' => true,
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/import/(?P<source>nextgen|modula)/tick',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'job_tick' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'source' => [
						'type'     => 'string',
						'enum'     => ImportRunner::SOURCES,
						'required' => true,
					],
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handlers
	// ------------------------------------------------------------------

	/**
	 * Report whether NextGEN Gallery is detected on this site.
	 */
	public function status(): WP_REST_Response {
		$importer  = new NextGenImporter();
		$available = $importer->is_available();

		return new WP_REST_Response( [
			'available' => $available,
			'message'   => $available
				? __( 'NextGEN Gallery detected. Ready to import.', 'bltgallery' )
				: __( 'NextGEN Gallery tables not found. Is the plugin installed and have galleries been created?', 'bltgallery' ),
		] );
	}

	/**
	 * Return a list of NextGEN galleries with their image counts.
	 */
	public function preview(): WP_REST_Response {
		return new WP_REST_Response( $this->preview_for( new NextGenImporter() ) );
	}

	/**
	 * Build a preview payload: the source's galleries, each carrying whether
	 * it has already been migrated so the picker can tick it off rather than
	 * queue it up for a duplicate copy.
	 */
	private function preview_for( SourceImporter $importer ): array {
		if ( ! $importer->is_available() ) {
			return [
				'available' => false,
				'galleries' => [],
			];
		}

		$galleries = $importer->get_galleries();
		$status    = ImportLedger::status_for( $importer, $galleries );
		$id_key    = $importer->id_key();
		$edit_base = admin_url( 'admin.php?page=' . AdminMenu::MENU_SLUG . '&action=edit&gallery_id=' );

		foreach ( $galleries as &$gallery ) {
			$source_id = (int) ( $gallery[ $id_key ] ?? 0 );
			$record    = $status[ $source_id ] ?? null;

			$gallery['imported'] = $record
				? [
					'state'        => $record['state'],
					'gallery_id'   => $record['gallery_id'],
					'gallery_url'  => $edit_base . $record['gallery_id'],
					'title'        => $record['title'],
					'completed_at' => $record['completed_at'],
					'images'       => $record['images'],
					'skipped'      => $record['skipped'],
					'matched_by'   => $record['matched_by'],
				]
				: null;
		}
		unset( $gallery );

		return [
			'available' => true,
			'galleries' => $galleries,
		];
	}

	/**
	 * Run the import, optionally restricted to specific NextGEN gallery IDs.
	 */
	public function run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$importer = new NextGenImporter();

		if ( ! $importer->is_available() ) {
			return new WP_Error(
				'nextgen_not_found',
				__( 'NextGEN Gallery tables not found.', 'bltgallery' ),
				[ 'status' => 422 ]
			);
		}

		$gallery_ids = $request->get_param( 'gallery_ids' );

		// Ensure IDs are positive integers; null means import everything.
		if ( ! empty( $gallery_ids ) ) {
			$gallery_ids = array_values(
				array_filter(
					array_map( 'intval', (array) $gallery_ids ),
					fn( $id ) => $id > 0
				)
			);
		} else {
			$gallery_ids = null;
		}

		// Import can be slow for large collections; bump limits defensively.
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 );
		}

		$results = $importer->import( $gallery_ids );

		return new WP_REST_Response( $results, 200 );
	}

	// ------------------------------------------------------------------
	// Cleanup endpoints (post-migration)
	// ------------------------------------------------------------------

	public function scan_legacy(): WP_REST_Response|WP_Error {
		$importer = new NextGenImporter();
		if ( ! $importer->is_available() ) {
			return new WP_Error( 'nextgen_not_found', __( 'NextGEN Gallery tables not found.', 'bltgallery' ), [ 'status' => 422 ] );
		}
		return new WP_REST_Response( $importer->scan_legacy_files() );
	}

	public function backup_legacy(): WP_REST_Response|WP_Error {
		$importer = new NextGenImporter();
		if ( ! $importer->is_available() ) {
			return new WP_Error( 'nextgen_not_found', __( 'NextGEN Gallery tables not found.', 'bltgallery' ), [ 'status' => 422 ] );
		}

		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 600 );
		}

		try {
			return new WP_REST_Response( $importer->backup_legacy_files() );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'backup_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	public function cleanup_legacy( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( 'DELETE' !== (string) $request->get_param( 'confirm' ) ) {
			return new WP_Error(
				'confirmation_required',
				__( 'Type DELETE to confirm permanent removal.', 'bltgallery' ),
				[ 'status' => 422 ]
			);
		}

		$importer = new NextGenImporter();
		if ( ! $importer->is_available() ) {
			return new WP_Error( 'nextgen_not_found', __( 'NextGEN Gallery tables not found.', 'bltgallery' ), [ 'status' => 422 ] );
		}

		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 600 );
		}

		return new WP_REST_Response( $importer->delete_legacy_files() );
	}

	// ------------------------------------------------------------------
	// Modula handlers
	// ------------------------------------------------------------------

	/**
	 * Report whether any Modula galleries are detected on this site.
	 */
	public function modula_status(): WP_REST_Response {
		$importer  = new ModulaImporter();
		$available = $importer->is_available();

		return new WP_REST_Response( [
			'available' => $available,
			'message'   => $available
				? __( 'Modula galleries detected. Ready to import.', 'bltgallery' )
				: __( 'No Modula galleries found. Is the plugin installed and have galleries been created?', 'bltgallery' ),
		] );
	}

	/**
	 * Return a list of Modula galleries with their image counts.
	 */
	public function modula_preview(): WP_REST_Response {
		return new WP_REST_Response( $this->preview_for( new ModulaImporter() ) );
	}

	/**
	 * Run the Modula import, optionally restricted to specific gallery IDs.
	 */
	public function modula_run( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$importer = new ModulaImporter();

		if ( ! $importer->is_available() ) {
			return new WP_Error(
				'modula_not_found',
				__( 'No Modula galleries found.', 'bltgallery' ),
				[ 'status' => 422 ]
			);
		}

		$gallery_ids = $request->get_param( 'gallery_ids' );

		// Ensure IDs are positive integers; null means import everything.
		if ( ! empty( $gallery_ids ) ) {
			$gallery_ids = array_values(
				array_filter(
					array_map( 'intval', (array) $gallery_ids ),
					fn( $id ) => $id > 0
				)
			);
		} else {
			$gallery_ids = null;
		}

		// Import can be slow for large collections; bump limits defensively.
		if ( ! ini_get( 'safe_mode' ) ) {
			set_time_limit( 300 );
		}

		$results = $importer->import( $gallery_ids );

		return new WP_REST_Response( $results, 200 );
	}

	// ------------------------------------------------------------------
	// Background job handlers
	// ------------------------------------------------------------------

	/**
	 * Queue a background migration and return the freshly created job.
	 *
	 * Returns immediately: the copying happens in ImportRunner passes, so the
	 * admin can close the page while it runs.
	 */
	public function job_start( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$source      = (string) $request->get_param( 'source' );
		$gallery_ids = $this->sanitize_gallery_ids( $request->get_param( 'gallery_ids' ) );

		try {
			$job = ImportRunner::start( $source, $gallery_ids );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'import_start_failed', $e->getMessage(), [ 'status' => 409 ] );
		}

		return new WP_REST_Response( ImportJob::to_response( $job ), 201 );
	}

	/**
	 * Current progress for a source.
	 *
	 * Doubles as the stall watchdog: if the worker died, polling revives it,
	 * and the `stalled` flag tells the admin page it may need to drive the
	 * job in the foreground (hosts with WP-Cron and loopback both blocked).
	 */
	public function job_status( WP_REST_Request $request ): WP_REST_Response {
		$source = (string) $request->get_param( 'source' );
		$job    = ImportJob::get( $source, true );

		if ( ! $job ) {
			return new WP_REST_Response( ImportJob::idle_response( $source ) );
		}

		$stalled = ImportRunner::is_stalled( $job );

		if ( $stalled ) {
			$job = ImportRunner::revive( $source ) ?: $job;
		}

		return new WP_REST_Response( ImportJob::to_response( $job, $stalled ) );
	}

	/**
	 * Stop a running migration. Galleries already copied stay put.
	 */
	public function job_cancel( WP_REST_Request $request ): WP_REST_Response {
		$source = (string) $request->get_param( 'source' );
		$job    = ImportRunner::cancel( $source );

		if ( ! $job ) {
			return new WP_REST_Response( ImportJob::idle_response( $source ) );
		}

		return new WP_REST_Response( ImportJob::to_response( $job ) );
	}

	/**
	 * Run one migration pass inside this request.
	 *
	 * The admin page falls back to this when a job stalls, which happens on
	 * hosts where WP-Cron is disabled and loopback HTTP requests are blocked.
	 * The budget is deliberately short so the browser request still returns
	 * promptly; progress simply continues on the next tick.
	 */
	public function job_tick( WP_REST_Request $request ): WP_REST_Response {
		$source = (string) $request->get_param( 'source' );

		$job = ImportRunner::process( $source, 20.0 );

		if ( ! $job ) {
			return new WP_REST_Response( ImportJob::idle_response( $source ) );
		}

		return new WP_REST_Response( ImportJob::to_response( $job ) );
	}

	/**
	 * Normalise a gallery_ids parameter to positive ints, or null for "all".
	 *
	 * @return int[]|null
	 */
	private function sanitize_gallery_ids( $gallery_ids ): ?array {
		if ( empty( $gallery_ids ) ) {
			return null;
		}

		$ids = array_values(
			array_filter(
				array_map( 'intval', (array) $gallery_ids ),
				static fn( $id ) => $id > 0
			)
		);

		return $ids ?: null;
	}

	// ------------------------------------------------------------------

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
