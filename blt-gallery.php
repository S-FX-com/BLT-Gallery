<?php
/**
 * Plugin Name:       Blt Gallery
 * Plugin URI:        https://github.com/S-FX-com/BLT-Gallery
 * Description:       A modern, self-contained WordPress photo gallery plugin with Cloudflare R2 / AWS S3 offloading, Cloudflare Images URL-based optimisation, WebP/AVIF thumbnails, and easy [blt_gallery] / [blt_album] shortcodes.
 * Version:           3.2.0
 * Requires at least: 6.3
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Author:            Blt Gallery Contributors
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       bltgallery
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BLT_GALLERY_VERSION', '3.2.0' );
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

require_once BLT_GALLERY_PLUGIN_DIR . 'src/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'BltGallery\\Core\\Plugin', 'uninstall' ] );

add_action( 'plugins_loaded', [ 'BltGallery\\Core\\Plugin', 'get_instance' ] );
