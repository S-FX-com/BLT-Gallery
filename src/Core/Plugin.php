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
use BltGallery\Api\StorageBackfillEndpoint;
use BltGallery\Api\UploadEndpoint;
use BltGallery\Display\LightboxDisplay;
use BltGallery\Display\MasonryDisplay;
use BltGallery\Display\SlideshowDisplay;
use BltGallery\Display\SliderDisplay;
use BltGallery\Display\TileGridDisplay;
use BltGallery\Integrations\Bricks\BricksIntegration;
use BltGallery\Import\ImportJob;
use BltGallery\Import\ImportRunner;
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
		wp_unschedule_hook( StoragePurgeQueue::HOOK );
		wp_unschedule_hook( StorageBackfillRunner::HOOK );
		// Stop any queued background migration passes; the job state itself
		// is left alone so a reactivated plugin can resume from where it got
		// to rather than re-copying everything.
		wp_unschedule_hook( ImportRunner::HOOK );
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

			foreach ( ImportRunner::SOURCES as $source ) {
				delete_option( ImportJob::option_name( $source ) );
				delete_option( ImportRunner::LOCK_PREFIX . $source );
			}

			delete_option( StoragePurgeQueue::OPTION );
			delete_option( StoragePurgeQueue::LOCK );
			delete_option( StorageBackfillJob::OPTION );
			delete_option( StorageBackfillRunner::LOCK );
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

		// Background gallery migrations. Registered outside the is_admin()
		// branch below because the worker runs on WP-Cron and admin-ajax
		// requests that carry no admin screen.
		ImportRunner::init();

		// Deletes remote objects for galleries that have already gone.
		StoragePurgeQueue::init();

		// Pushes existing local images out to R2/S3 when asked to from the
		// Settings page.
		StorageBackfillRunner::init();

		// Registers the BLT Gallery / BLT Slider Bricks elements. Runs on
		// the front end too — that's where Bricks actually renders built
		// pages — and is a no-op unless Bricks is active and the setting
		// is on.
		BricksIntegration::init();

		if ( is_admin() ) {
			$admin = new AdminMenu();
			$admin->init();
			add_action( 'admin_init', [ $this->db, 'maybe_upgrade' ] );
			add_action( 'admin_init', [ Migration::class, 'run' ] );
		}

		/*
		 * Update checks: admin requests, WP-Cron and WP-CLI — not the front end.
		 *
		 * WP-Cron matters here and is easy to miss. plugin-update-checker
		 * attaches its cron callback inside its Scheduler constructor, so if
		 * Updater::init() only ran under is_admin() the daily midnight event
		 * would fire in wp-cron.php with nothing listening: the scheduled check
		 * would silently never happen, and the only check that ever ran would be
		 * the opportunistic one on the next admin page load. Front-end requests
		 * are still excluded — no check can start there, and previously fetched
		 * update data lives in the update_plugins transient regardless.
		 */
		if ( is_admin() || wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			Updater::init();
		}
	}

	/**
	 * Register BLT Gallery's thumbnail dimensions through WordPress's
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
		( new StorageBackfillEndpoint() )->register();
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
