<?php

declare( strict_types=1 );

namespace ZymGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZymGallery\Import\NextGenImporter;

/**
 * REST API endpoints for the gallery importer tool.
 *
 * GET  /zymgallery/v1/import/nextgen/status   – detect if NextGEN is present
 * GET  /zymgallery/v1/import/nextgen/preview  – list NextGEN galleries with image counts
 * POST /zymgallery/v1/import/nextgen/run      – run the import (optionally limit to gallery IDs)
 */
class ImportEndpoint {

	const NAMESPACE = 'zymgallery/v1';

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
						'description' => __( 'Specific NextGEN gallery IDs to import. Omit to import all.', 'zymgallery' ),
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
				? __( 'NextGEN Gallery detected. Ready to import.', 'zymgallery' )
				: __( 'NextGEN Gallery tables not found. Is the plugin installed and have galleries been created?', 'zymgallery' ),
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
				__( 'NextGEN Gallery tables not found.', 'zymgallery' ),
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
