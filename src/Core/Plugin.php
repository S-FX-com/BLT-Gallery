<?php

declare( strict_types=1 );

namespace ZymGallery\Core;

use ZymGallery\Admin\AdminMenu;
use ZymGallery\Api\GalleryEndpoint;
use ZymGallery\Api\ImageEndpoint;
use ZymGallery\Api\SettingsEndpoint;
use ZymGallery\Api\ImportEndpoint;
use ZymGallery\Api\UploadEndpoint;
use ZymGallery\Display\LightboxDisplay;
use ZymGallery\Display\MasonryDisplay;
use ZymGallery\Display\SlideshowDisplay;
use ZymGallery\Display\TileGridDisplay;

/**
 * Main plugin bootstrap. Loaded via plugins_loaded.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Database $db;

	private function __construct() {
		$this->db = new Database();
		$this->init_hooks();
	}

	public static function get_instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------
	// Lifecycle hooks
	// -----------------------------------------------------------------

	public static function activate(): void {
		$db = new Database();
		$db->install();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		if ( get_option( 'zymgallery_delete_data_on_uninstall' ) ) {
			$db = new Database();
			$db->drop_tables();
			delete_option( 'zymgallery_settings' );
			delete_option( 'zymgallery_aws_settings' );
			delete_option( 'zymgallery_r2_settings' );
			delete_option( 'zymgallery_delete_data_on_uninstall' );
			delete_option( 'zymgallery_db_version' );
		}
	}

	// -----------------------------------------------------------------
	// WordPress hook registration
	// -----------------------------------------------------------------

	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_shortcodes' ] );
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		if ( is_admin() ) {
			$admin = new AdminMenu();
			$admin->init();
		}

		// Database upgrade check on every admin page load.
		if ( is_admin() ) {
			add_action( 'admin_init', [ $this->db, 'maybe_upgrade' ] );
		}
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'zymgallery',
			false,
			dirname( ZYMGALLERY_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public function register_shortcodes(): void {
		$shortcode = new Shortcode();
		add_shortcode( 'zymgallery', [ $shortcode, 'render' ] );
	}

	public function register_api_endpoints(): void {
		( new GalleryEndpoint() )->register();
		( new ImageEndpoint() )->register();
		( new SettingsEndpoint() )->register();
		( new UploadEndpoint() )->register();
		( new ImportEndpoint() )->register();
	}

	public function enqueue_frontend_assets(): void {
		wp_register_style(
			'zymgallery-frontend',
			ZYMGALLERY_PLUGIN_URL . 'assets/frontend/frontend.css',
			[],
			ZYMGALLERY_VERSION
		);

		wp_register_script(
			'zymgallery-frontend',
			ZYMGALLERY_PLUGIN_URL . 'assets/frontend/frontend.js',
			[],
			ZYMGALLERY_VERSION,
			true
		);
	}

	// -----------------------------------------------------------------
	// Display-type factory
	// -----------------------------------------------------------------

	public static function make_display( string $type ): ?object {
		return match ( $type ) {
			'masonry'   => new MasonryDisplay(),
			'tile'      => new TileGridDisplay(),
			'slideshow' => new SlideshowDisplay(),
			'lightbox'  => new LightboxDisplay(),
			default     => null,
		};
	}
}
