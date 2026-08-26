<?php

declare( strict_types=1 );

namespace BltGallery\Integrations\Bricks;

use BltGallery\Core\SliderRepository;
use BltGallery\Core\SliderShortcode;

/**
 * "BLT Slider" Bricks element — picks a saved slider from a dropdown and
 * renders it exactly as [blt_slider] would, by delegating straight into
 * SliderShortcode::render(). Optional controls override the slider's own
 * stored autoplay/arrows/dots/loop/speed/height for this one placement,
 * same as the shortcode's attributes do.
 */
class SliderElement extends \Bricks\Element {

	public $category = 'general';
	public $name     = 'blt-slider';
	public $icon     = 'ti-layout-slider';

	public function get_label() {
		return esc_html__( 'BLT Slider', 'bltgallery' );
	}

	public function get_keywords() {
		return [ 'slider', 'slideshow', 'carousel', 'images', 'blt' ];
	}

	public function set_control_groups() {
		$this->control_groups['blt_slider'] = [
			'title' => esc_html__( 'Slider', 'bltgallery' ),
			'tab'   => 'content',
		];
	}

	public function set_controls() {
		$this->controls['slider_id'] = [
			'tab'     => 'content',
			'group'   => 'blt_slider',
			'label'   => esc_html__( 'Slider', 'bltgallery' ),
			'type'    => 'select',
			'options' => $this->slider_options(),
			'default' => '',
		];

		$inherit_options = [
			''  => esc_html__( "Use the slider's own setting", 'bltgallery' ),
			'1' => esc_html__( 'On', 'bltgallery' ),
			'0' => esc_html__( 'Off', 'bltgallery' ),
		];

		foreach ( [
			'autoplay' => esc_html__( 'Autoplay', 'bltgallery' ),
			'arrows'   => esc_html__( 'Nav arrows', 'bltgallery' ),
			'dots'     => esc_html__( 'Dot counter', 'bltgallery' ),
			'loop'     => esc_html__( 'Loop', 'bltgallery' ),
		] as $key => $label ) {
			$this->controls[ $key ] = [
				'tab'     => 'content',
				'group'   => 'blt_slider',
				'label'   => $label,
				'type'    => 'select',
				'options' => $inherit_options,
				'default' => '',
			];
		}

		$this->controls['speed'] = [
			'tab'         => 'content',
			'group'       => 'blt_slider',
			'label'       => esc_html__( 'Autoplay speed (ms)', 'bltgallery' ),
			'type'        => 'number',
			'min'         => 500,
			'description' => esc_html__( "Leave blank to use the slider's own setting.", 'bltgallery' ),
		];

		$this->controls['height'] = [
			'tab'         => 'content',
			'group'       => 'blt_slider',
			'label'       => esc_html__( 'Slide height', 'bltgallery' ),
			'type'        => 'text',
			'description' => esc_html__( "CSS length, e.g. 70vh or 480px. Leave blank to use the slider's own setting.", 'bltgallery' ),
		];
	}

	/**
	 * @return array<string, string>
	 */
	private function slider_options(): array {
		$options = [ '' => esc_html__( '— Select a slider —', 'bltgallery' ) ];

		foreach ( SliderRepository::all( 200 ) as $slider ) {
			$options[ (string) $slider->id ] = '' !== $slider->title ? $slider->title : sprintf( '#%d', $slider->id );
		}

		return $options;
	}

	public function render() {
		$this->set_attribute( '_root', 'class', [ 'blt-slider-element' ] );
		echo '<div ' . $this->render_attributes( '_root' ) . '>';

		$slider_id = (int) ( $this->settings['slider_id'] ?? 0 );

		if ( $slider_id <= 0 ) {
			echo '<p class="blt-slider-element__placeholder">' . esc_html__( 'Select a slider in the element settings.', 'bltgallery' ) . '</p>';
			echo '</div>';
			return;
		}

		$atts = [ 'id' => $slider_id ];

		foreach ( [ 'autoplay', 'arrows', 'dots', 'loop' ] as $key ) {
			if ( '' !== (string) ( $this->settings[ $key ] ?? '' ) ) {
				$atts[ $key ] = (string) $this->settings[ $key ];
			}
		}
		if ( isset( $this->settings['speed'] ) && '' !== $this->settings['speed'] ) {
			$atts['speed'] = (int) $this->settings['speed'];
		}
		if ( ! empty( $this->settings['height'] ) ) {
			$atts['height'] = (string) $this->settings['height'];
		}

		echo ( new SliderShortcode() )->render( $atts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SliderShortcode::render() returns pre-escaped markup, same as the [blt_slider] shortcode callback.

		echo '</div>';
	}
}
