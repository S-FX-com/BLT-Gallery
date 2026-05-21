<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Wires the GitHub releases of S-FX-com/BLT-Gallery into WordPress's
 * native plugin-update flow via plugin-update-checker (vendored at
 * lib/plugin-update-checker/).
 *
 * Cuts a release on GitHub with a tag matching the plugin header
 * `Version:` (e.g. `v3.2.1`) and WP will offer the update on the
 * Plugins page like any wordpress.org plugin.
 */
final class Updater {

	private const GITHUB_REPO = 'https://github.com/S-FX-com/BLT-Gallery';
	private const BRANCH      = 'main';

	public static function init(): void {
		$loader = BLT_GALLERY_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}
		require_once $loader;

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// Slug intentionally omitted — PUC derives it from the install
		// directory name (plugin_basename), so updates land back in whatever
		// folder the plugin is currently installed under (e.g. BLT-Gallery/
		// or blt-gallery/) instead of forcing a rename.
		$checker = PucFactory::buildUpdateChecker(
			self::GITHUB_REPO,
			BLT_GALLERY_PLUGIN_FILE
		);

		$checker->setBranch( self::BRANCH );

		// Prefer GitHub Releases (with attached zip if present) over branch
		// tarballs, so version metadata comes from the release rather than
		// whatever happens to be on `main`.
		if ( method_exists( $checker, 'getVcsApi' ) ) {
			$api = $checker->getVcsApi();
			if ( $api && method_exists( $api, 'enableReleaseAssets' ) ) {
				$api->enableReleaseAssets();
			}
		}

		$token = self::auth_token();
		if ( $token !== '' && method_exists( $checker, 'setAuthentication' ) ) {
			$checker->setAuthentication( $token );
		}
	}

	/**
	 * Optional GitHub PAT for private repos or higher rate limits. Set via
	 * the BLT_GALLERY_GITHUB_TOKEN PHP constant in wp-config.php — never
	 * commit a token to the repo.
	 */
	private static function auth_token(): string {
		if ( defined( 'BLT_GALLERY_GITHUB_TOKEN' ) && is_string( BLT_GALLERY_GITHUB_TOKEN ) ) {
			return (string) BLT_GALLERY_GITHUB_TOKEN;
		}
		return '';
	}
}
