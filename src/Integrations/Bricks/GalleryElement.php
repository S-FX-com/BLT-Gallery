<?php

declare( strict_types=1 );

namespace BltGallery\Integrations\Bricks;

use BltGallery\Core\GalleryRepository;
use BltGallery\Core\Shortcode;

/**
 * "BLT Gallery" Bricks element — picks a saved gallery from a dropdown and
 * renders it exactly as [blt_gallery] would, by delegating straight into
 * Shortcode::render(). Optional controls override the gallery's own
 * stored layout/columns/gap/lightbox for this one placement, same as the
 * shortcode's attributes do.
 */
class GalleryElement extends \Bricks\Element {

	public $category = 'general';
	public $name     = 'blt-gallery';
	public $icon     = 'ti-gallery';

	public function get_label() {
		return esc_html__( 'BLT Gallery', 'bltgallery' );
	}

	public function get_keywords() {
		return [ 'gallery', 'photos', 'images', 'masonry', 'blt' ];
	}

	public function set_control_groups() {
		$this->control_groups['blt_gallery'] = [
			'title' => esc_html__( 'Gallery', 'bltgallery' ),
			'tab'   => 'content',
		];
	}

	public function set_controls() {
		$this->controls['gallery_id'] = [
			'tab'     => 'content',
			'group'   => 'blt_gallery',
			'label'   => esc_html__( 'Gallery', 'bltgallery' ),
			'type'    => 'select',
			'options' => $this->gallery_options(),
			'default' => '',
		];

		$this->controls['display_type'] = [
			'tab'     => 'content',
			'group'   => 'blt_gallery',
			'label'   => esc_html__( 'Layout', 'bltgallery' ),
			'type'    => 'select',
			'options' => [
				''          => esc_html__( "Use the gallery's own setting", 'bltgallery' ),
				'masonry'   => esc_html__( 'Masonry', 'bltgallery' ),
				'tile'      => esc_html__( 'Tile grid', 'bltgallery' ),
				'slideshow' => esc_html__( 'Slideshow', 'bltgallery' ),
				'lightbox'  => esc_html__( 'Lightbox grid', 'bltgallery' ),
			],
			'default' => '',
		];

		$this->controls['cols'] = [
			'tab'         => 'content',
			'group'       => 'blt_gallery',
			'label'       => esc_html__( 'Columns', 'bltgallery' ),
			'type'        => 'number',
			'min'         => 1,
			'max'         => 8,
			'description' => esc_html__( "Leave blank to use the gallery's own setting.", 'bltgallery' ),
		];

		$this->controls['gap'] = [
			'tab'         => 'content',
			'group'       => 'blt_gallery',
			'label'       => esc_html__( 'Gap (px)', 'bltgallery' ),
			'type'        => 'number',
			'min'         => 0,
			'description' => esc_html__( "Leave blank to use the gallery's own setting.", 'bltgallery' ),
		];

		$this->controls['lightbox'] = [
			'tab'     => 'content',
			'group'   => 'blt_gallery',
			'label'   => esc_html__( 'Lightbox', 'bltgallery' ),
			'type'    => 'select',
			'options' => [
				''  => esc_html__( "Use the gallery's own setting", 'bltgallery' ),
				'1' => esc_html__( 'On', 'bltgallery' ),
				'0' => esc_html__( 'Off', 'bltgallery' ),
			],
			'default' => '',
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function gallery_options(): array {
		$options = [ '' => esc_html__( '— Select a gallery —', 'bltgallery' ) ];

		foreach ( GalleryRepository::all( 200 ) as $gallery ) {
			$options[ (string) $gallery->id ] = '' !== $gallery->title ? $gallery->title : sprintf( '#%d', $gallery->id );
		}

		return $options;
	}

	public function render() {
		$this->set_attribute( '_root', 'class', [ 'blt-gallery-element' ] );
		echo '<div ' . $this->render_attributes( '_root' ) . '>';

		$gallery_id = (int) ( $this->settings['gallery_id'] ?? 0 );

		if ( $gallery_id <= 0 ) {
			echo '<p class="blt-gallery-element__placeholder">' . esc_html__( 'Select a gallery in the element settings.', 'bltgallery' ) . '</p>';
			echo '</div>';
			return;
		}

		$atts = [ 'id' => $gallery_id ];

		if ( ! empty( $this->settings['display_type'] ) ) {
			$atts['type'] = sanitize_key( (string) $this->settings['display_type'] );
		}
		if ( isset( $this->settings['cols'] ) && '' !== $this->settings['cols'] ) {
			$atts['cols'] = (int) $this->settings['cols'];
		}
		if ( isset( $this->settings['gap'] ) && '' !== $this->settings['gap'] ) {
			$atts['gap'] = (int) $this->settings['gap'];
		}
		if ( '' !== (string) ( $this->settings['lightbox'] ?? '' ) ) {
			$atts['lightbox'] = (string) $this->settings['lightbox'];
		}

		echo ( new Shortcode() )->render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode::render() returns pre-escaped markup, same as the [blt_gallery] shortcode callback.

		echo '</div>';
	}
}
