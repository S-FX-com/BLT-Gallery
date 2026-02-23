<?php
/**
 * Plugin Name:       ZymGallery
 * Plugin URI:        https://github.com/S-FX-com/ZymGallery
 * Description:       A modern, self-contained WordPress photo gallery plugin with AWS S3/CloudFront offloading, WebP image optimization, and beautiful responsive display types.
 * Version:           2.0.0
 * Requires at least: 6.3
 * Requires PHP:      8.1
 * Author:            ZymGallery Contributors
 * License:           GPLv3
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       zymgallery
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ZYMGALLERY_VERSION', '2.0.0' );
define( 'ZYMGALLERY_PLUGIN_FILE', __FILE__ );
define( 'ZYMGALLERY_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZYMGALLERY_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'ZYMGALLERY_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader.
if ( file_exists( ZYMGALLERY_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	require_once ZYMGALLERY_PLUGIN_DIR . 'vendor/autoload.php';
} else {
	// Fallback PSR-4 autoloader for ZymGallery\ namespace.
	spl_autoload_register( function ( string $class ): void {
		$prefix = 'ZymGallery\\';
		if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$relative = substr( $class, strlen( $prefix ) );
		$file     = ZYMGALLERY_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';
		if ( file_exists( $file ) ) {
			require_once $file;
		}
	} );
}

// Bootstrap.
require_once ZYMGALLERY_PLUGIN_DIR . 'src/Core/Plugin.php';

register_activation_hook( __FILE__, [ 'ZymGallery\\Core\\Plugin', 'activate' ] );
register_deactivation_hook( __FILE__, [ 'ZymGallery\\Core\\Plugin', 'deactivate' ] );
register_uninstall_hook( __FILE__, [ 'ZymGallery\\Core\\Plugin', 'uninstall' ] );

add_action( 'plugins_loaded', [ 'ZymGallery\\Core\\Plugin', 'get_instance' ] );
