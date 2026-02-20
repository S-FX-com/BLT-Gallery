<?php

declare( strict_types=1 );

namespace ZymGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use ZymGallery\Core\GalleryRepository;
use ZymGallery\Core\ImageRepository;
use ZymGallery\Models\Gallery;

/**
 * REST API endpoints for Galleries.
 *
 * Namespace : zymgallery/v1
 * Base route: /galleries
 *
 * GET    /galleries           – paginated list
 * POST   /galleries           – create
 * GET    /galleries/{id}      – single gallery + image count
 * PUT    /galleries/{id}      – update
 * DELETE /galleries/{id}      – delete
 */
class GalleryEndpoint {

	const NAMESPACE = 'zymgallery/v1';
	const BASE      = '/galleries';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::BASE,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'per_page' => [ 'type' => 'integer', 'default' => 20, 'minimum' => 1, 'maximum' => 100 ],
						'page'     => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
					],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'manage_permission' ],
					'args'                => $this->schema_args(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/(?P<id>\d+)',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'show' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'manage_permission' ],
					'args'                => $this->schema_args(),
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete' ],
					'permission_callback' => [ $this, 'manage_permission' ],
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handlers
	// ------------------------------------------------------------------

	public function index( WP_REST_Request $request ): WP_REST_Response {
		$per_page = (int) $request->get_param( 'per_page' );
		$page     = (int) $request->get_param( 'page' );
		$total    = GalleryRepository::count();
		$galleries = GalleryRepository::all( $per_page, $page );

		$response = new WP_REST_Response(
			array_map( fn( Gallery $g ) => $g->to_array(), $galleries )
		);

		$response->header( 'X-WP-Total', (string) $total );
		$response->header( 'X-WP-TotalPages', (string) max( 1, ceil( $total / $per_page ) ) );

		return $response;
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery               = new Gallery();
		$gallery->title        = sanitize_text_field( $request->get_param( 'title' ) ?? '' );
		$gallery->slug         = sanitize_title( $request->get_param( 'slug' ) ?? $gallery->title );
		$gallery->description  = wp_kses_post( $request->get_param( 'description' ) ?? '' );
		$gallery->display_type = sanitize_key( $request->get_param( 'display_type' ) ?? 'masonry' );
		$gallery->settings     = (array) ( $request->get_param( 'settings' ) ?? [] );

		if ( empty( $gallery->title ) ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'zymgallery' ), [ 'status' => 422 ] );
		}

		$gallery = GalleryRepository::save( $gallery );

		return new WP_REST_Response( $gallery->to_array(), 201 );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = GalleryRepository::find( (int) $request->get_param( 'id' ) );

		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'zymgallery' ), [ 'status' => 404 ] );
		}

		$data               = $gallery->to_array();
		$data['image_count'] = ImageRepository::count_by_gallery( $gallery->id );

		return new WP_REST_Response( $data );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = GalleryRepository::find( (int) $request->get_param( 'id' ) );

		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'zymgallery' ), [ 'status' => 404 ] );
		}

		if ( null !== $request->get_param( 'title' ) ) {
			$gallery->title = sanitize_text_field( $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$gallery->slug = sanitize_title( $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$gallery->description = wp_kses_post( $request->get_param( 'description' ) );
		}
		if ( null !== $request->get_param( 'display_type' ) ) {
			$gallery->display_type = sanitize_key( $request->get_param( 'display_type' ) );
		}
		if ( null !== $request->get_param( 'settings' ) ) {
			$gallery->settings = (array) $request->get_param( 'settings' );
		}

		$gallery = GalleryRepository::save( $gallery );

		return new WP_REST_Response( $gallery->to_array() );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = GalleryRepository::delete( (int) $request->get_param( 'id' ) );

		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'zymgallery' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( [ 'deleted' => true ] );
	}

	// ------------------------------------------------------------------
	// Permissions
	// ------------------------------------------------------------------

	public function manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ------------------------------------------------------------------
	// Schema
	// ------------------------------------------------------------------

	private function schema_args(): array {
		return [
			'title'        => [ 'type' => 'string', 'required' => false ],
			'slug'         => [ 'type' => 'string', 'required' => false ],
			'description'  => [ 'type' => 'string', 'required' => false ],
			'display_type' => [
				'type'    => 'string',
				'enum'    => [ 'masonry', 'tile', 'slideshow', 'lightbox' ],
				'default' => 'masonry',
			],
			'settings'     => [ 'type' => 'object', 'required' => false ],
		];
	}
}
