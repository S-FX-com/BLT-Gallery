<?php
/**
 * Plugin Name:       BLT Gallery
 * Plugin URI:        https://github.com/S-FX-com/BLT-Gallery
 * Description:       A modern, self-contained WordPress photo gallery plugin with Cloudflare R2 / AWS S3 offloading, Cloudflare Images URL-based optimisation, WebP/AVIF thumbnails, and easy [blt_gallery] / [blt_album] shortcodes.
 * Version:           3.4.13
 * Requires at least: 6.3
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Author:            S-FX.com
 * Author URI:        https://www.s-fx.com
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       bltgallery
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLT_GALLERY_VERSION', '3.4.13' );
define( 'BLT_GALLERY_PLUGIN_FILE', __FILE__ );
define( 'BLT_GALLERY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BLT_GALLERY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'BLT_GALLERY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
if ( file_exists( BLT_GALLERY_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once BLT_GALLERY_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register( function ( string $class ): void {
		$prefix = 'BltGallery\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = BLT_GALLERY_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// BLT family shared layer. Required during load — before plugins_loaded — so
// the registry is complete when the library elects a copy and boots, and so
// BLT_Family_Updates is available to src/Core/Updater.php. These are global
// BLT_Family_* classes and are deliberately NOT part of the BltGallery\ PSR-4
// autoload map.
require_once BLT_GALLERY_PLUGIN_DIR . 'includes/blt-family/bootstrap.php';

blt_family_register(
	BLT_GALLERY_PLUGIN_FILE,
	array(
		'name'        => 'BLT Gallery',
		'slug'        => 'blt-gallery',
		'version'     => BLT_GALLERY_VERSION,

		// Top-level admin page slug (BltGallery\Admin\AdminMenu::MENU_SLUG).
		'menu'        => 'bltgallery',
		'groups'      => array( 'github', 'cloudflare', 'r2' ),

		/*
		 * The update checker is built with an empty slug on purpose, so
		 * plugin-update-checker derives one itself. It derives it from the main
		 * file name sans '.php' (Puc\v5p6\Plugin\UpdateChecker::__construct:
		 * basename( $this->pluginFile, '.php' )), NOT from the install
		 * directory — which differs here, since the repo folder is
		 * "BLT-Gallery". Compute the same value so the family screen's
		 * "Check for Updates" link keys on the slug PUC actually registered.
		 */
		'update_slug' => basename( plugin_basename( BLT_GALLERY_PLUGIN_FILE ), '.php' ),
	)
);

require_once BLT_GALLERY_PLUGIN_DIR . 'src/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'uninstall' ] );

add_action( 'plugins_loaded', [ 'BltGallery\\Core\\Plugin', 'get_instance' ] );
