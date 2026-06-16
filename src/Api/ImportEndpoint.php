<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Import\NextGenImporter;
use BltGallery\Import\ModulaImporter;

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
		$importer  = new NextGenImporter();

		if ( ! $importer->is_available() ) {
			return new WP_REST_Response( [
				'available' => false,
				'galleries' => [],
			] );
		}

		return new WP_REST_Response( [
			'available' => true,
			'galleries' => $importer->get_galleries(),
		] );
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
		$importer = new ModulaImporter();

		if ( ! $importer->is_available() ) {
			return new WP_REST_Response( [
				'available' => false,
				'galleries' => [],
			] );
		}

		return new WP_REST_Response( [
			'available' => true,
			'galleries' => $importer->get_galleries(),
		] );
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

	public function permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
