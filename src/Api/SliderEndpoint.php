<?php

declare( strict_types=1 );

namespace BltGallery\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use BltGallery\Core\SliderRepository;
use BltGallery\Core\SliderResolver;
use BltGallery\Core\SliderShortcode;
use BltGallery\Models\Slider;

/**
 * REST API endpoints for Sliders (admin builder).
 *
 * Namespace : bltgallery/v1
 * Base route: /sliders
 *
 * GET    /sliders           – list
 * POST   /sliders           – create
 * GET    /sliders/{id}      – single slider + resolved slides
 * GET    /sliders/{id}/render – front-end HTML (live preview)
 * PUT    /sliders/{id}      – update
 * DELETE /sliders/{id}      – delete
 */
class SliderEndpoint {

	const NAMESPACE = 'bltgallery/v1';
	const BASE      = '/sliders';

	public function register(): void {
		register_rest_route(
			self::NAMESPACE,
			self::BASE,
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'index' ],
					'permission_callback' => [ $this, 'manage_permission' ],
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
			self::BASE . '/(?P<id>\d+)/render',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ $this, 'render_html' ],
					'permission_callback' => [ $this, 'manage_permission' ],
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
					'permission_callback' => [ $this, 'manage_permission' ],
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

	public function index(): WP_REST_Response {
		$sliders = SliderRepository::all( 200, 1 );

		return new WP_REST_Response(
			array_map( fn( Slider $s ) => $s->to_array(), $sliders )
		);
	}

	public function create( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$title = sanitize_text_field( (string) ( $request->get_param( 'title' ) ?? '' ) );
		if ( '' === $title ) {
			return new WP_Error( 'missing_title', __( 'Title is required.', 'bltgallery' ), [ 'status' => 422 ] );
		}

		$slider           = new Slider();
		$slider->title    = $title;
		$slider->slug     = sanitize_title( (string) ( $request->get_param( 'slug' ) ?? $title ) );
		$slider->settings = $this->sanitize_settings( (array) ( $request->get_param( 'settings' ) ?? [] ) );
		$slider->items    = SliderResolver::sanitize_items( (array) ( $request->get_param( 'items' ) ?? [] ) );

		$slider = SliderRepository::save( $slider );

		return new WP_REST_Response( $this->decorate( $slider ), 201 );
	}

	public function show( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slider = SliderRepository::find( (int) $request->get_param( 'id' ) );
		if ( ! $slider ) {
			return new WP_Error( 'not_found', __( 'Slider not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $this->decorate( $slider ) );
	}

	/**
	 * Render the slider's front-end HTML for a live preview in the editor.
	 * Reuses the [blt_slider] shortcode so the output matches a real embed.
	 */
	public function render_html( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slider = SliderRepository::find( (int) $request->get_param( 'id' ) );
		if ( ! $slider ) {
			return new WP_Error( 'not_found', __( 'Slider not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		$html = ( new SliderShortcode() )->render( [ 'id' => (string) $slider->id ] );

		return new WP_REST_Response(
			[
				'id'    => $slider->id,
				'title' => $slider->title,
				'html'  => $html,
			]
		);
	}

	public function update( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$slider = SliderRepository::find( (int) $request->get_param( 'id' ) );
		if ( ! $slider ) {
			return new WP_Error( 'not_found', __( 'Slider not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}

		if ( null !== $request->get_param( 'title' ) ) {
			$slider->title = sanitize_text_field( (string) $request->get_param( 'title' ) );
		}
		if ( null !== $request->get_param( 'slug' ) ) {
			$slider->slug = sanitize_title( (string) $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'settings' ) ) {
			$slider->settings = $this->sanitize_settings( (array) $request->get_param( 'settings' ) );
		}
		if ( null !== $request->get_param( 'items' ) ) {
			$slider->items = SliderResolver::sanitize_items( (array) $request->get_param( 'items' ) );
		}

		$slider = SliderRepository::save( $slider );

		return new WP_REST_Response( $this->decorate( $slider ) );
	}

	public function delete( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$deleted = SliderRepository::delete( (int) $request->get_param( 'id' ) );
		if ( ! $deleted ) {
			return new WP_Error( 'not_found', __( 'Slider not found.', 'bltgallery' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( [ 'deleted' => true ] );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Slider payload plus the resolved (flattened) slide metadata the builder
	 * needs to render thumbnails and effective captions.
	 */
	private function decorate( Slider $slider ): array {
		$data           = $slider->to_array();
		$data['slides'] = SliderResolver::describe_items( $slider->items );
		return $data;
	}

	/**
	 * Whitelist + normalise slider display settings.
	 */
	private function sanitize_settings( array $incoming ): array {
		$out = [];

		// Booleans / on-off.
		if ( array_key_exists( 'captions', $incoming ) ) {
			$out['captions'] = ( '0' === (string) $incoming['captions'] || 'off' === sanitize_key( (string) $incoming['captions'] ) ) ? 'off' : 'on';
		}
		foreach ( [ 'arrows', 'dots', 'loop' ] as $key ) {
			if ( array_key_exists( $key, $incoming ) ) {
				$out[ $key ] = ( $incoming[ $key ] && '0' !== (string) $incoming[ $key ] ) ? '1' : '0';
			}
		}
		if ( array_key_exists( 'autoplay', $incoming ) ) {
			$out['autoplay'] = (bool) $incoming['autoplay'] && '0' !== (string) $incoming['autoplay'];
		}
		if ( array_key_exists( 'speed', $incoming ) ) {
			$out['speed'] = max( 1000, min( 30000, (int) $incoming['speed'] ) );
		}
		if ( array_key_exists( 'radius', $incoming ) ) {
			$out['radius'] = max( 0, min( 200, (int) $incoming['radius'] ) );
		}
		if ( array_key_exists( 'height', $incoming ) ) {
			$h = trim( (string) $incoming['height'] );
			$out['height'] = preg_match( '/^[0-9.]+(px|vh|vw|rem|em|%)$/', $h ) ? $h : '';
		}

		return $out;
	}

	private function schema_args(): array {
		return [
			'title'    => [ 'type' => 'string', 'required' => false ],
			'slug'     => [ 'type' => 'string', 'required' => false ],
			'settings' => [ 'type' => 'object', 'required' => false ],
			'items'    => [ 'type' => 'array', 'required' => false ],
		];
	}

	public function manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}
}
