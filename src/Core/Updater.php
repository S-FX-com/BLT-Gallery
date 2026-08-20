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
 *
 * Check policy is the shared BLT family one, applied by
 * BLT_Family_Updates::apply(): at most one automatic check a day, anchored to
 * 00:00 site time, with manual checks ("Check for updates" on the Plugins row,
 * "Check again" on Dashboard → Updates, and the link on BLT Gallery →
 * Settings) always running immediately.
 */
final class Updater {

	private const GITHUB_REPO = 'https://github.com/S-FX-com/BLT-Gallery';
	private const BRANCH      = 'main';

	/**
	 * The configured checker, so the Settings screen can report when the last
	 * check ran. Null until init() has run (admin requests only).
	 *
	 * @var object|null
	 */
	private static ?object $checker = null;

	public static function init(): void {
		$loader = BLT_GALLERY_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $loader ) ) {
			return;
		}
		require_once $loader;

		// plugin-update-checker renders GitHub release notes (markdown) through
		// Parsedown when a release has a body. The vendored PUC copy ships
		// without its Parsedown dependency, so load our bundled copy here —
		// otherwise checking for updates fatals with "Class Parsedown not found".
		if ( ! class_exists( 'Parsedown' ) ) {
			$parsedown = BLT_GALLERY_PLUGIN_DIR . 'lib/parsedown/Parsedown.php';
			if ( file_exists( $parsedown ) ) {
				require_once $parsedown;
			}
		}

		if ( ! class_exists( PucFactory::class ) ) {
			return;
		}

		// Slug intentionally omitted. PUC then derives it from the main plugin
		// file name — basename( plugin_basename( $file ), '.php' ), i.e.
		// 'blt-gallery' — while the folder it updates in place always comes
		// from the install path, so the plugin keeps working under either
		// BLT-Gallery/ or blt-gallery/ without forcing a rename.
		//
		// The check period MUST be 24, not 0: a checker built with 0 registers
		// none of PUC's scheduler hooks and cannot be revived afterwards.
		// BLT_Family_Updates then holds automatic checks to one a day.
		$checker = PucFactory::buildUpdateChecker(
			self::GITHUB_REPO,
			BLT_GALLERY_PLUGIN_FILE,
			'',
			24
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

		// One automatic check a day at midnight site time, manual checks always
		// immediate, and the BLT mark on the plugin's update-screen card.
		\BLT_Family_Updates::apply(
			$checker,
			[
				'basename'  => BLT_GALLERY_PLUGIN_BASENAME,
				'icons_url' => BLT_GALLERY_PLUGIN_URL . 'assets/img/',
			]
		);

		self::$checker = $checker;
	}

	/**
	 * The configured plugin-update-checker instance, or null on a request where
	 * init() never ran (front end, or PUC missing).
	 */
	public static function checker(): ?object {
		return self::$checker;
	}

	/**
	 * The slug plugin-update-checker registered this plugin under, which is
	 * what its manual-check link keys on.
	 *
	 * Because buildUpdateChecker() is called with an empty slug, PUC derives it
	 * as basename( plugin_basename( __FILE__ ), '.php' ) — the main file name,
	 * not the install directory. Mirror that exactly.
	 */
	public static function checker_slug(): string {
		return basename( plugin_basename( BLT_GALLERY_PLUGIN_FILE ), '.php' );
	}

	/**
	 * Optional GitHub PAT for private repos or higher rate limits. Set via
	 * the BLT_GALLERY_GITHUB_TOKEN PHP constant in wp-config.php — never
	 * commit a token to the repo.
	 *
	 * Precedence: wp-config constant → the BLT family shared store. There is
	 * no per-plugin option for this token, and nothing here ever writes one.
	 */
	private static function auth_token(): string {
		$token = '';

		if ( defined( 'BLT_GALLERY_GITHUB_TOKEN' ) && is_string( BLT_GALLERY_GITHUB_TOKEN ) ) {
			$token = (string) BLT_GALLERY_GITHUB_TOKEN;
		}

		if ( '' === $token && class_exists( 'BLT_Family' ) ) {
			$token = (string) \BLT_Family::get( 'blt-gallery', 'github', 'token' );
		}

		return $token;
	}
}
