<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\FrontEndGallery;
use BltGallery\Core\ImageFiles;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Core\StorageOffloader;
use BltGallery\Core\UploadValidator;

/**
 * REST API for the front-end gallery feature: a logged-in, admin-permitted
 * visitor uploading into — and deleting from — a gallery of their own.
 *
 * Namespace : bltgallery/v1
 *
 * POST   /my-gallery/upload          – upload an image into the current
 *                                       user's own gallery (created on
 *                                       first use)
 * DELETE /my-gallery/images/{id}     – delete one of the current user's own
 *                                       images
 *
 * Deliberately narrow: neither route accepts a gallery id from the client.
 * "Which gallery" is always resolved server-side from the logged-in
 * session via FrontEndGallery::resolve_gallery_for_current_user(), and a
 * delete additionally confirms the image actually belongs to that gallery
 * before touching it. A visitor can therefore never reach anyone else's
 * gallery or images through this endpoint, no matter what id they pass.
 */
class FrontEndGalleryEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/my-gallery/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'upload' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/my-gallery/images/(?P<id>\d+)',
			[
				'methods'             => \WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_image' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handlers
	// ------------------------------------------------------------------

	public function upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = FrontEndGallery::resolve_gallery_for_current_user( true );
		if ( ! $gallery ) {
			// Only reachable if the session's user id vanished between the
			// permission check and here (e.g. the account was deleted
			// mid-request) — current_user_allowed() already required a
			// logged-in user in an allowed role.
			return new WP_Error( 'not_found', __( 'Your gallery could not be found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$remaining = FrontEndGallery::remaining_uploads( $gallery );
		if ( $remaining <= 0 ) {
			return new WP_Error(
				'limit_reached',
				sprintf(
					/* translators: %d: the maximum number of images allowed */
					__( "You've reached your upload limit of %d images. Delete one of your own to make room for another.", 'bltgallery' ),
					FrontEndGallery::image_limit()
				),
				[ 'status' => 403 ]
			);
		}

		$files = $request->get_file_params();
		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'bltgallery' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		$validation = UploadValidator::validate( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		try {
			$processor = new ImageProcessor();
			$image     = $processor->process_upload( $file['tmp_name'], $gallery );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'processing_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		$image = StorageOffloader::offload( $image );
		$image = ImageRepository::save( $image );

		return new WP_REST_Response(
			[
				'image'     => $image->to_array(),
				'remaining' => FrontEndGallery::remaining_uploads( $gallery ),
				'limit'     => FrontEndGallery::image_limit(),
			],
			201
		);
	}

	public function delete_image( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery = FrontEndGallery::resolve_gallery_for_current_user( false );
		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Image not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$image = ImageRepository::find( (int) $request->get_param( 'id' ) );

		// Ownership check: this is the one line standing between "delete
		// your own image" and an IDOR that lets any permitted visitor
		// delete anyone else's. $gallery is always the caller's own row
		// (see resolve_gallery_for_current_user()), so this can only ever
		// pass for images inside it.
		if ( ! $image || $image->gallery_id !== $gallery->id ) {
			return new WP_Error( 'not_found', __( 'Image not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		ImageFiles::purge( $image );
		ImageRepository::delete( $image->id );

		return new WP_REST_Response(
			[
				'deleted'   => true,
				'remaining' => FrontEndGallery::remaining_uploads( $gallery ),
				'limit'     => FrontEndGallery::image_limit(),
			]
		);
	}

	// ------------------------------------------------------------------
	// Permission
	// ------------------------------------------------------------------

	public function permission(): bool {
		return FrontEndGallery::current_user_allowed();
	}
}
