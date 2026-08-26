<?php

declare( strict_types=1 );

namespace BltGallery\Integrations\Bricks;

/**
 * Registers BLT Gallery's Bricks Builder elements — "BLT Gallery" and
 * "BLT Slider" — so they show up in the Bricks element panel alongside
 * Bricks' own elements, each with a dropdown to pick a saved gallery or
 * slider.
 *
 * Entirely opt-in and inert everywhere else: nothing here runs unless
 * Bricks itself is active AND the "Register Bricks Builder elements"
 * toggle in Settings → General is on.
 */
class BricksIntegration {

	public static function init(): void {
		// Priority 11: Bricks sets up its own element registry on init at
		// the default priority (10), so custom elements must register after
		// it or \Bricks\Elements won't be ready yet.
		add_action( 'init', [ self::class, 'maybe_register_elements' ], 11 );
	}

	public static function maybe_register_elements(): void {
		if ( ! class_exists( '\Bricks\Elements' ) ) {
			return;
		}

		$settings = get_option( 'bltgallery_settings', [] );
		if ( empty( $settings['enable_bricks_elements'] ) ) {
			return;
		}

		\Bricks\Elements::register_element(
			BLT_GALLERY_PLUGIN_DIR . 'src/Integrations/Bricks/GalleryElement.php',
			'blt-gallery',
			GalleryElement::class
		);

		\Bricks\Elements::register_element(
			BLT_GALLERY_PLUGIN_DIR . 'src/Integrations/Bricks/SliderElement.php',
			'blt-slider',
			SliderElement::class
		);
	}
}
