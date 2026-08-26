<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Core\ImageRepository;
use BltGallery\Core\StorageOffloader;
use BltGallery\Models\Gallery;
use BltGallery\Models\Image;

/**
 * Handles getting images into a gallery: either a fresh upload, or a copy
 * pulled in from an existing media library attachment.
 *
 * POST /bltgallery/v1/galleries/{gallery_id}/upload
 *   - file : the uploaded file (multipart/form-data)
 *
 * POST /bltgallery/v1/galleries/{gallery_id}/import-attachments
 *   - attachment_ids : WP media library attachment IDs to copy in
 *
 * Either way, where the file ends up is decided by StorageOffloader from
 * the plugin's settings, the same way an imported image is.
 */
class UploadEndpoint {

	const NAMESPACE = 'bltgallery/v1';

	/** Maximum upload size in bytes (50 MB). Servers may enforce a lower limit. */
	const MAX_UPLOAD_SIZE = 52_428_800;

	/** Allowed MIME types. */
	const ALLOWED_TYPES = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif' ];

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/upload',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle' ],
				'permission_callback' => [ $this, 'permission' ],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/galleries/(?P<gallery_id>\d+)/import-attachments',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'import_attachments' ],
				'permission_callback' => [ $this, 'permission' ],
				'args'                => [
					'attachment_ids' => [
						'type'     => 'array',
						'items'    => [ 'type' => 'integer' ],
						'required' => true,
					],
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Handler
	// ------------------------------------------------------------------

	public function handle( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery_id = (int) $request->get_param( 'gallery_id' );

		$gallery = GalleryRepository::find( $gallery_id );
		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error( 'no_file', __( 'No file uploaded.', 'bltgallery' ), [ 'status' => 400 ] );
		}

		$file = $files['file'];

		// Validate the upload.
		$validation = $this->validate_file( $file );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Process: resize, strip EXIF, generate thumbs.
		try {
			$processor = new ImageProcessor();
			$image     = $processor->process_upload( $file['tmp_name'], $gallery );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'processing_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		// Hand it to whichever remote store is configured. Local storage is a
		// no-op — ImageProcessor has already written the files.
		$image = StorageOffloader::offload( $image );

		// Persist.
		$image = ImageRepository::save( $image );

		return new WP_REST_Response( $image->to_array(), 201 );
	}

	/**
	 * Copy one or more existing media library attachments into a gallery —
	 * an alternative to handle() for images already on the site, so picking
	 * them here saves downloading and re-uploading the same file.
	 *
	 * Time-boxed the same way GalleryEndpoint::bulk_delete() is: each image
	 * still goes through the full resize/thumbnail pipeline, so a very large
	 * selection hands back whatever it did not reach in `remaining` for the
	 * caller to send again instead of risking a timeout.
	 */
	public function import_attachments( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$gallery_id = (int) $request->get_param( 'gallery_id' );

		$gallery = GalleryRepository::find( $gallery_id );
		if ( ! $gallery ) {
			return new WP_Error( 'not_found', __( 'Gallery not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$attachment_ids = array_values(
			array_unique(
				array_filter(
					array_map( 'intval', (array) $request->get_param( 'attachment_ids' ) ),
					static fn( int $id ): bool => $id > 0
				)
			)
		);

		if ( ! $attachment_ids ) {
			return new WP_Error( 'no_attachments', __( 'No media library images were selected.', 'bltgallery' ), [ 'status' => 422 ] );
		}

		$has_time  = self::deadline();
		$processor = new ImageProcessor();
		$images    = [];
		$errors    = [];

		foreach ( $attachment_ids as $index => $attachment_id ) {
			// Always let the first attachment start, so a single slow image
			// still makes progress instead of bouncing the whole batch back.
			if ( $index > 0 && ! $has_time() ) {
				return new WP_REST_Response(
					[
						'images'    => $images,
						'errors'    => $errors,
						'remaining' => array_values( array_slice( $attachment_ids, $index ) ),
					]
				);
			}

			$result = $this->import_one_attachment( $attachment_id, $gallery, $processor );

			if ( is_wp_error( $result ) ) {
				$errors[] = [
					'attachment_id' => $attachment_id,
					'message'       => $result->get_error_message(),
				];
				continue;
			}

			$images[] = $result->to_array();
		}

		return new WP_REST_Response(
			[
				'images'    => $images,
				'errors'    => $errors,
				'remaining' => [],
			]
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	private function validate_file( array $file ): true|WP_Error {
		if ( ! empty( $file['error'] ) ) {
			return new WP_Error( 'upload_error', __( 'File upload error.', 'bltgallery' ), [ 'status' => 400 ] );
		}

		if ( $file['size'] > self::MAX_UPLOAD_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'File exceeds maximum size of %s MB.', 'bltgallery' ),
					self::MAX_UPLOAD_SIZE / 1_048_576
				),
				[ 'status' => 413 ]
			);
		}

		// Verify MIME by reading file magic bytes, not trusting $_FILES['type'].
		$finfo = finfo_open( FILEINFO_MIME_TYPE );
		$mime  = finfo_file( $finfo, $file['tmp_name'] );
		finfo_close( $finfo );

		if ( ! in_array( $mime, self::ALLOWED_TYPES, true ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Only JPEG, PNG, GIF, WebP, and AVIF images are allowed.', 'bltgallery' ),
				[ 'status' => 415 ]
			);
		}

		return true;
	}

	/**
	 * Validate, process, and save one media library attachment as a new
	 * gallery image. Metadata already on the attachment (alt text, caption,
	 * description, title) carries over, so pulling a copy in also saves
	 * re-typing them.
	 */
	private function import_one_attachment( int $attachment_id, Gallery $gallery, ImageProcessor $processor ): Image|WP_Error {
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return new WP_Error( 'invalid_attachment', __( 'Not a valid image from the media library.', 'bltgallery' ) );
		}

		if ( ! in_array( (string) get_post_mime_type( $attachment_id ), self::ALLOWED_TYPES, true ) ) {
			return new WP_Error( 'invalid_type', __( 'Only JPEG, PNG, GIF, WebP, and AVIF images are allowed.', 'bltgallery' ) );
		}

		$src_path = get_attached_file( $attachment_id );
		if ( ! $src_path || ! file_exists( $src_path ) ) {
			return new WP_Error( 'file_missing', __( 'Original file not found on this server (it may be stored elsewhere).', 'bltgallery' ) );
		}

		if ( filesize( $src_path ) > self::MAX_UPLOAD_SIZE ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max size in MB */
					__( 'File exceeds maximum size of %s MB.', 'bltgallery' ),
					self::MAX_UPLOAD_SIZE / 1_048_576
				)
			);
		}

		try {
			$image = $processor->process_upload( $src_path, $gallery );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'processing_failed', $e->getMessage() );
		}

		$image->alt_text    = sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$image->caption     = wp_kses_post( (string) wp_get_attachment_caption( $attachment_id ) );
		$attachment_post    = get_post( $attachment_id );
		$image->description = wp_kses_post( (string) ( $attachment_post->post_content ?? '' ) );

		$title = sanitize_text_field( (string) ( $attachment_post->post_title ?? '' ) );
		if ( '' !== $title ) {
			$image->meta['title'] = $title;
		}

		$image = StorageOffloader::offload( $image );

		return ImageRepository::save( $image );
	}

	/**
	 * A "still have time?" test, sized from whatever execution cap is in
	 * force, leaving room for the response itself. Same construction as
	 * GalleryEndpoint::deadline().
	 */
	private static function deadline(): callable {
		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$max     = (int) ini_get( 'max_execution_time' );
		$budget  = $max > 0 ? (int) floor( $max * 0.6 ) : 20;
		$budget  = max( 5, min( 20, $budget ) );
		$budget  = (float) apply_filters( 'bltgallery_import_attachments_time_budget', $budget );
		$expires = microtime( true ) + $budget;

		return static fn(): bool => microtime( true ) < $expires;
	}

	public function permission(): bool {
		return current_user_can( 'upload_files' );
	}
}
