<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\GalleryDeleter;
use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageRepository;
use BltGallery\Models\Gallery;

/**
 * REST API endpoints for Galleries.
 *
 * Namespace : bltgallery/v1
 * Base route: /galleries
 *
 * GET    /galleries           – paginated list, each row with its image count
 * POST   /galleries           – create
 * GET    /galleries/{id}      – single gallery + image count
 * PUT    /galleries/{id}      – update
 * DELETE /galleries/{id}      – delete one, with its images and their files
 * DELETE /galleries           – bulk delete, time-boxed and resumable
 */
class GalleryEndpoint {

	const NAMESPACE = 'bltgallery/v1';
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
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'bulk_delete' ],
					'permission_callback' => [ $this, 'manage_permission' ],
					'args'                => [
						'ids' => [
							'type'        => 'array',
							'items'       => [ 'type' => 'integer' ],
							'required'    => true,
							'description' => __( 'Gallery IDs to delete.', 'bltgallery' ),
						],
					],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			self::BASE . '/(?P<id>\d+)/render',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'render_html' ],
					'permission_callback' => '__return_true',
					'args'                => [
						'type' => [ 'type' => 'string', 'required' => false ],
					],
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

		// One grouped query for the whole page rather than a count per row.
		$counts = ImageRepository::count_by_galleries(
			array_map( fn( Gallery $g ) => $g->id, $galleries )
		);

		$response = new WP_REST_Response(
			array_map(
				fn( Gallery $g ) => $g->to_array() + [ 'image_count' => (int) ( $counts[ $g->id ] ?? 0 ) ],
				$galleries
			)
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
			return new WP_Error( 'missing_title', __( 'Title is required.', 'bltgallery' ), [ 'status' => 422 ] );
		}

		$gallery = GalleryRepository::save( $gallery );

		return new WP_REST_Response( $gallery->to_array(), 201 );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = GalleryRepository::find( (int) $request->get_param( 'id' ) );

		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$data               = $gallery->to_array();
		$data['image_count'] = ImageRepository::count_by_gallery( $gallery->id );

		return new WP_REST_Response( $data );
	}

	/**
	 * Render a gallery's front-end display HTML, for opening a gallery inside
	 * the album modal without a dedicated page. Reuses the [blt_gallery]
	 * shortcode so the output matches a normal embed exactly.
	 */
	public function render_html( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = GalleryRepository::find( (int) $request->get_param( 'id' ) );

		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$atts = [ 'id' => (string) $gallery->id ];
		$type = (string) ( $request->get_param( 'type' ) ?? '' );
		if ( '' !== $type ) {
			$atts['type'] = sanitize_key( $type );
		}

		$html = ( new \BltGallery\Core\Shortcode() )->render( $atts );

		return new WP_REST_Response(
			[
				'id'    => $gallery->id,
				'title' => $gallery->title,
				'html'  => $html,
			]
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = GalleryRepository::find( (int) $request->get_param( 'id' ) );

		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
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
			$gallery->settings = $this->sanitize_settings( (array) $request->get_param( 'settings' ), $gallery->settings );
		}

		$gallery = GalleryRepository::save( $gallery );

		return new WP_REST_Response( $gallery->to_array() );
	}

	/**
	 * Merge incoming gallery settings into the existing array with light
	 * per-key sanitization for known fields. Unknown keys pass through
	 * (free-form is expected for theme integrations).
	 */
	private function sanitize_settings( array $incoming, array $existing ): array {
		$merged = array_merge( $existing, $incoming );

		// Date: must be YYYY-MM-DD or empty.
		if ( array_key_exists( 'gallery_date', $merged ) ) {
			$d = trim( (string) $merged['gallery_date'] );
			$merged['gallery_date'] = ( '' === $d || preg_match( '/^\d{4}-\d{2}-\d{2}$/', $d ) )
				? $d
				: '';
		}

		// Pagination mode.
		if ( array_key_exists( 'pagination', $merged ) ) {
			$allowed = [ 'off', 'load-more', 'numbered', 'infinite' ];
			$mode    = sanitize_key( (string) $merged['pagination'] );
			$merged['pagination'] = in_array( $mode, $allowed, true ) ? $mode : 'off';
		}
		if ( array_key_exists( 'per_page', $merged ) ) {
			$merged['per_page'] = max( 1, min( 200, (int) $merged['per_page'] ) );
		}

		return $merged;
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$result = GalleryDeleter::delete( (int) $request->get_param( 'id' ), self::deadline() );

		if ( $result['missing'] ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response(
			[
				'deleted'   => $result['deleted'],
				'images'    => $result['images'],
				'files'     => $result['files'],
				'queued'    => $result['queued'],
				'remaining' => $result['remaining'],
			]
		);
	}

	/**
	 * Delete several galleries in one request.
	 *
	 * Time-boxed rather than unbounded: removing a gallery means deleting
	 * every image's files, and for an S3/R2-offloaded library that is several
	 * HTTP round trips per image — thousands of them would outrun any request
	 * limit. Whatever is left over comes back in `remaining` for the caller to
	 * send again, so a long clean-up finishes across several requests instead
	 * of timing out halfway through one.
	 */
	public function bulk_delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', (array) $request->get_param( 'ids' ) ),
					static fn( int $id ): bool => $id > 0
				)
			)
		);

		if ( ! $ids ) {
			return new WP_Error(
				'no_galleries',
				__( 'No galleries were selected.', 'bltgallery' ),
				[ 'status' => 422 ]
			);
		}

		$has_time = self::deadline();

		$result = [
			'deleted'   => [],
			'missing'   => [],
			'remaining' => [],
			'images'    => 0,
			'files'     => 0,
			'queued'    => 0,
		];

		foreach ( $ids as $index => $id ) {
			// Always let the first gallery start, so a single very large one
			// still makes progress instead of bouncing back untouched.
			if ( $index > 0 && ! $has_time() ) {
				$result['remaining'] = array_values( array_slice( $ids, $index ) );
				break;
			}

			$one = GalleryDeleter::delete( $id, $has_time );

			$result['images'] += $one['images'];
			$result['files']  += $one['files'];
			$result['queued'] += $one['queued'];

			if ( $one['missing'] ) {
				$result['missing'][] = $id;
			} elseif ( $one['deleted'] ) {
				$result['deleted'][] = $id;
			} else {
				// Ran out of time inside this gallery — it keeps its place at
				// the front of the queue and resumes on the next request.
				$result['remaining'] = array_values( array_slice( $ids, $index ) );
				break;
			}
		}

		return new WP_REST_Response( $result );
	}

	/**
	 * A "still have time?" test sized from whatever execution cap is in
	 * force, leaving room for the response itself.
	 */
	private static function deadline(): callable {
		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$max     = (int) ini_get( 'max_execution_time' );
		$budget  = $max > 0 ? (int) floor( $max * 0.6 ) : 20;
		$budget  = max( 5, min( 20, $budget ) );
		$budget  = (float) apply_filters( 'bltgallery_delete_time_budget', $budget );
		$expires = microtime( true ) + $budget;

		return static fn(): bool => microtime( true ) < $expires;
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
