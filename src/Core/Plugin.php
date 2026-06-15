<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Admin\AdminMenu;
use BltGallery\Api\AlbumEndpoint;
use BltGallery\Api\GalleryEndpoint;
use BltGallery\Api\ImageEndpoint;
use BltGallery\Api\SettingsEndpoint;
use BltGallery\Api\ImportEndpoint;
use BltGallery\Api\SliderEndpoint;
use BltGallery\Api\UploadEndpoint;
use BltGallery\Display\LightboxDisplay;
use BltGallery\Display\MasonryDisplay;
use BltGallery\Display\SlideshowDisplay;
use BltGallery\Display\SliderDisplay;
use BltGallery\Display\TileGridDisplay;
use BltGallery\Display\AlbumDisplay;

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
		Migration::run();
		flush_rewrite_rules();
	}

	public static function deactivate(): void {
		flush_rewrite_rules();
	}

	public static function uninstall(): void {
		if ( get_option( 'bltgallery_delete_data_on_uninstall' ) ) {
			$db = new Database();
			$db->drop_tables();
			delete_option( 'bltgallery_settings' );
			delete_option( 'bltgallery_aws_settings' );
			delete_option( 'bltgallery_r2_settings' );
			delete_option( 'bltgallery_cf_images_settings' );
			delete_option( 'bltgallery_delete_data_on_uninstall' );
			delete_option( 'bltgallery_db_version' );
		}
	}

	// -----------------------------------------------------------------
	// WordPress hook registration
	// -----------------------------------------------------------------

	private function init_hooks(): void {
		add_action( 'init', [ $this, 'load_textdomain' ] );
		add_action( 'init', [ $this, 'register_image_sizes' ] );
		add_action( 'init', [ $this, 'register_shortcodes' ] );
		add_action( 'rest_api_init', [ $this, 'register_api_endpoints' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );

		if ( is_admin() ) {
			$admin = new AdminMenu();
			$admin->init();
			add_action( 'admin_init', [ $this->db, 'maybe_upgrade' ] );
			add_action( 'admin_init', [ Migration::class, 'run' ] );
			Updater::init();
		}
	}

	/**
	 * Register Blt Gallery's thumbnail dimensions through WordPress's
	 * standard image-size API so themes, REST consumers, and other plugins
	 * can address them with `wp_get_attachment_image_src(..., 'bltgallery-medium')`.
	 *
	 * The actual resize work still flows through WP_Image_Editor inside
	 * ImageProcessor — we just register the names + sizes here.
	 */
	public function register_image_sizes(): void {
		add_image_size( 'bltgallery-thumb',  320,  320,  true );
		add_image_size( 'bltgallery-medium', 800,  600,  false );
		add_image_size( 'bltgallery-large',  1600, 1200, false );
	}

	public function load_textdomain(): void {
		load_plugin_textdomain(
			'bltgallery',
			false,
			dirname( BLT_GALLERY_PLUGIN_BASENAME ) . '/languages'
		);
	}

	public function register_shortcodes(): void {
		$gallery = new Shortcode();
		$album   = new AlbumShortcode();
		$slider  = new SliderShortcode();

		add_shortcode( 'blt_gallery', [ $gallery, 'render' ] );
		add_shortcode( 'blt_album',   [ $album,   'render' ] );
		add_shortcode( 'blt_slider',  [ $slider,  'render' ] );

		// Backward-compatibility aliases for pre-3.0 content.
		add_shortcode( 'bltgallery',  [ $gallery, 'render' ] );
		add_shortcode( 'zymgallery',  [ $gallery, 'render' ] );
	}

	public function register_api_endpoints(): void {
		( new GalleryEndpoint() )->register();
		( new ImageEndpoint() )->register();
		( new SettingsEndpoint() )->register();
		( new UploadEndpoint() )->register();
		( new ImportEndpoint() )->register();
		( new AlbumEndpoint() )->register();
		( new SliderEndpoint() )->register();
	}

	public function enqueue_frontend_assets(): void {
		wp_register_style(
			'bltgallery-frontend',
			BLT_GALLERY_PLUGIN_URL . 'assets/frontend/frontend.css',
			[],
			BLT_GALLERY_VERSION
		);

		wp_register_script(
			'bltgallery-frontend',
			BLT_GALLERY_PLUGIN_URL . 'assets/frontend/frontend.js',
			[],
			BLT_GALLERY_VERSION,
			true
		);

		wp_script_add_data( 'bltgallery-frontend', 'strategy', 'defer' );

		wp_localize_script(
			'bltgallery-frontend',
			'bltGalleryFrontend',
			[
				'apiBase' => esc_url_raw( rest_url( 'bltgallery/v1' ) ),
			]
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
			'slider'    => new SliderDisplay(),
			'lightbox'  => new LightboxDisplay(),
			'album'     => new AlbumDisplay(),
			default     => null,
		};
	}
}
