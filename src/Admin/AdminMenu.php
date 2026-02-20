<?php

declare( strict_types=1 );

namespace ZymGallery\Admin;

/**
 * Registers the ZymGallery admin menu and enqueues the React admin app.
 */
class AdminMenu {

	const MENU_SLUG = 'zymgallery';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_pages(): void {
		add_menu_page(
			__( 'ZymGallery', 'zymgallery' ),
			__( 'ZymGallery', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_app_shell' ],
			$this->get_menu_icon(),
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Galleries', 'zymgallery' ),
			__( 'Galleries', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_app_shell' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'zymgallery' ),
			__( 'Settings', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			[ $this, 'render_app_shell' ]
		);
	}

	/**
	 * Renders the root div that React mounts into.
	 * All routing is handled client-side by the React app.
	 */
	public function render_app_shell(): void {
		echo '<div id="zymgallery-admin"></div>';
	}

	public function enqueue_assets( string $hook ): void {
		// Only load on ZymGallery admin pages.
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		$asset_file = ZYMGALLERY_PLUGIN_DIR . 'assets/build/admin/index.asset.php';
		$deps       = [ 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data' ];
		$version    = ZYMGALLERY_VERSION;

		if ( file_exists( $asset_file ) ) {
			$asset   = require $asset_file;
			$deps    = array_merge( $deps, $asset['dependencies'] ?? [] );
			$version = $asset['version'] ?? $version;
		}

		// WordPress component styles.
		wp_enqueue_style( 'wp-components' );

		wp_enqueue_style(
			'zymgallery-admin',
			ZYMGALLERY_PLUGIN_URL . 'assets/build/admin/style.css',
			[ 'wp-components' ],
			$version
		);

		wp_enqueue_script(
			'zymgallery-admin',
			ZYMGALLERY_PLUGIN_URL . 'assets/build/admin/index.js',
			array_unique( $deps ),
			$version,
			true
		);

		// Pass config to the React app.
		wp_localize_script(
			'zymgallery-admin',
			'zymGalleryConfig',
			[
				'apiBase'    => rest_url( 'zymgallery/v1' ),
				'nonce'      => wp_create_nonce( 'wp_rest' ),
				'pluginUrl'  => ZYMGALLERY_PLUGIN_URL,
				'adminUrl'   => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
				'version'    => ZYMGALLERY_VERSION,
				'displayTypes' => [
					[ 'value' => 'masonry',   'label' => __( 'Masonry',    'zymgallery' ) ],
					[ 'value' => 'tile',      'label' => __( 'Tile Grid',  'zymgallery' ) ],
					[ 'value' => 'slideshow', 'label' => __( 'Slideshow',  'zymgallery' ) ],
					[ 'value' => 'lightbox',  'label' => __( 'Lightbox',   'zymgallery' ) ],
				],
			]
		);

		wp_set_script_translations( 'zymgallery-admin', 'zymgallery' );
	}

	private function get_menu_icon(): string {
		// Inline SVG as base64 data URI – an aperture / camera-lens icon.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
