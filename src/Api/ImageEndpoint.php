<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageRepository;
use BltGallery\Aws\S3Storage;
use BltGallery\Models\Image;

/**
 * REST API endpoints for Images within a Gallery.
 *
 * GET    /galleries/{gid}/images            – list images
 * GET    /galleries/{gid}/images/{id}       – single image
 * PATCH  /galleries/{gid}/images/{id}       – update metadata
 * DELETE /galleries/{gid}/images/{id}       – delete
 * POST   /galleries/{gid}/images/reorder    – reorder images
 */
class ImageEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	public function register(): void {
		// Collection.
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/images',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ $this, 'index' ],
				'permission_callback' => '__return_true',
			]
		);

		// Single item.
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/images/(?P<id>\d+)',
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
				],
				[
					'methods'             => \WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'delete' ],
					'permission_callback' => [ $this, 'manage_permission' ],
				],
			]
		);

		// Reorder.
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/images/reorder',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'reorder' ],
				'permission_callback' => [ $this, 'manage_permission' ],
				'args'                => [
					'order' => [
						'type'     => 'array',
						'required' => true,
						'items'    => [ 'type' => 'integer' ],
					],
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handlers
	// ------------------------------------------------------------------

	public function index( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery_id = (int) $request->get_param( 'gallery_id' );

		if ( ! GalleryRepository::find( $gallery_id ) ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$images = ImageRepository::find_by_gallery( $gallery_id );
		return new WP_REST_Response( array_map( fn( Image $img ) => $img->to_array(), $images ) );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ 'image' => $image, 'error' => $error ] = $this->resolve( $request );
		return $error ?? new WP_REST_Response( $image->to_array() );
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ 'image' => $image, 'error' => $error ] = $this->resolve( $request );
		if ( $error ) {
			return $error;
		}

		if ( null !== $request->get_param( 'alt_text' ) ) {
			$image->alt_text = sanitize_text_field( $request->get_param( 'alt_text' ) );
		}
		if ( null !== $request->get_param( 'caption' ) ) {
			$image->caption = wp_kses_post( $request->get_param( 'caption' ) );
		}
		if ( null !== $request->get_param( 'description' ) ) {
			$image->description = wp_kses_post( $request->get_param( 'description' ) );
		}

		$image = ImageRepository::save( $image );

		return new WP_REST_Response( $image->to_array() );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		[ 'image' => $image, 'error' => $error ] = $this->resolve( $request );
		if ( $error ) {
			return $error;
		}

		// Remove from S3 if applicable.
		if ( 's3' === $image->storage_driver && $image->s3_key && S3Storage::is_configured() ) {
			$s3 = new S3Storage();
			$s3->delete( $image->s3_key );

			foreach ( ( $image->meta['thumbs'] ?? [] ) as $thumb ) {
				if ( ! empty( $thumb['s3_key'] ) ) {
					$s3->delete( $thumb['s3_key'] );
				}
			}
		}

		// Remove local files.
		if ( $image->local_path && file_exists( $image->local_path ) ) {
			@unlink( $image->local_path );
			foreach ( ( $image->meta['thumbs'] ?? [] ) as $thumb ) {
				if ( ! empty( $thumb['path'] ) && file_exists( $thumb['path'] ) ) {
					@unlink( $thumb['path'] );
				}
			}
		}

		ImageRepository::delete( $image->id );

		return new WP_REST_Response( [ 'deleted' => true ] );
	}

	public function reorder( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery_id = (int) $request->get_param( 'gallery_id' );

		if ( ! GalleryRepository::find( $gallery_id ) ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$ordered_ids = array_map( 'intval', (array) $request->get_param( 'order' ) );
		ImageRepository::reorder( $gallery_id, $ordered_ids );

		return new WP_REST_Response( [ 'reordered' => true ] );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function resolve( WP_REST_Request $request ): array {
		$gallery_id = (int) $request->get_param( 'gallery_id' );
		$image_id   = (int) $request->get_param( 'id' );

		if ( ! GalleryRepository::find( $gallery_id ) ) {
			return [ 'image' => null, 'error' => new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] ) ];
		}

		$image = ImageRepository::find( $image_id );
		if ( ! $image || $image->gallery_id !== $gallery_id ) {
			return [ 'image' => null, 'error' => new WP_Error( 'not_found', __( 'Image not found.', 'bltgallery' ), [ 'status' => 404 ] ) ];
		}

		return [ 'image' => $image, 'error' => null ];
	}

	public function manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
