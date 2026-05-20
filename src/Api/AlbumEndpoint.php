<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\AlbumRepository;
use BltGallery\Core\GalleryRepository;

/**
 * REST endpoints for Albums (option-backed taxonomy).
 *
 * GET    /bltgallery/v1/albums              – list
 * POST   /bltgallery/v1/albums              – create / update
 * DELETE /bltgallery/v1/albums/{slug}       – delete + detach from galleries
 */
class AlbumEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/albums',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create' ],
					'permission_callback' => [ $this, 'manage_permission' ],
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/albums/(?P<slug>[a-z0-9\-_]+)',
			[
				[
					'methods'             => \WP_REST_Server::EDITABLE,
					'callback'            => [ $this, 'update' ],
					'permission_callback' => [ $this, 'manage_permission' ],
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete' ],
					'permission_callback' => [ $this, 'manage_permission' ],
				],
			]
		);
	}

	public function index(): WP_REST_Response {
		$albums = AlbumRepository::all();

		// Decorate with per-album gallery counts so the admin Albums page
		// can render "N galleries" without N+1 round-trips.
		$counts = [];
		foreach ( GalleryRepository::all( 500, 1 ) as $gallery ) {
			foreach ( (array) ( $gallery->settings['albums'] ?? [] ) as $slug ) {
				$counts[ $slug ] = ( $counts[ $slug ] ?? 0 ) + 1;
			}
			$legacy = (string) ( $gallery->settings['category'] ?? '' );
			if ( $legacy && empty( $gallery->settings['albums'] ) ) {
				$counts[ $legacy ] = ( $counts[ $legacy ] ?? 0 ) + 1;
			}
		}

		foreach ( $albums as &$album ) {
			$album['gallery_count'] = (int) ( $counts[ $album['slug'] ] ?? 0 );
		}
		unset( $album );

		return new WP_REST_Response( $albums );
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$name        = (string) $request->get_param( 'name' );
		$slug        = (string) $request->get_param( 'slug' );
		$description = (string) $request->get_param( 'description' );

		try {
			$album = AlbumRepository::save( $name, $slug, $description );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'invalid_album', $e->getMessage(), [ 'status' => 422 ] );
		}

		return new WP_REST_Response( $album, 201 );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug   = (string) $request->get_param( 'slug' );
		$exists = AlbumRepository::find( $slug );
		if ( ! $exists ) {
			return new WP_Error( 'not_found', __( 'Album not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$name        = (string) ( $request->get_param( 'name' )        ?? $exists['name'] );
		$description = (string) ( $request->get_param( 'description' ) ?? $exists['description'] );

		try {
			$album = AlbumRepository::save( $name, $slug, $description );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'invalid_album', $e->getMessage(), [ 'status' => 422 ] );
		}

		return new WP_REST_Response( $album );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slug = (string) $request->get_param( 'slug' );
		if ( ! AlbumRepository::delete( $slug ) ) {
			return new WP_Error( 'not_found', __( 'Album not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true ] );
	}

	public function manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
