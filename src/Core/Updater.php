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
 * Checks are manual only. GitHub is contacted when — and only when — an
 * administrator asks: the "Check for updates" link on the Plugins page, or
 * the "Check again" button on Dashboard → Updates. No cron event, no check on
 * admin page loads. Whatever the last check found stays cached and keeps
 * being offered until someone checks again.
 */
final class Updater {

	private const GITHUB_REPO = 'https://github.com/S-FX-com/BLT-Gallery';
	private const BRANCH      = 'main';

	/**
	 * Hours between automatic checks. 0 = never check on our own.
	 */
	private const CHECK_PERIOD_HOURS = 0;

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

		/**
		 * Hours between automatic update checks. Zero — the default here —
		 * turns every automatic check off: plugin-update-checker clears its
		 * cron event and registers none of its admin_init / load-plugins.php
		 * / load-update.php hooks, so nothing reaches out to GitHub on its
		 * own. Filter it to a positive number to restore periodic checks.
		 *
		 * @param int $hours
		 */
		$check_period = (int) apply_filters( 'bltgallery_update_check_period', self::CHECK_PERIOD_HOURS );

		// Slug intentionally omitted — PUC derives it from the install
		// directory name (plugin_basename), so updates land back in whatever
		// folder the plugin is currently installed under (e.g. BLT-Gallery/
		// or blt-gallery/) instead of forcing a rename.
		$checker = PucFactory::buildUpdateChecker(
			self::GITHUB_REPO,
			BLT_GALLERY_PLUGIN_FILE,
			'',
			max( 0, $check_period )
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

		self::wire_manual_recheck( $checker );

		add_filter( 'site_transient_update_plugins', [ self::class, 'attach_plugin_icons' ], 20 );
	}

	/**
	 * Show the BLT Gallery mark on the plugin's card in Dashboard → Updates.
	 *
	 * Icons normally come from a plugin's wordpress.org asset directory, which
	 * a GitHub-hosted plugin doesn't have, so WordPress falls back to a generic
	 * placeholder. Point it at the bundled logo instead.
	 *
	 * @param mixed $transient The update_plugins site transient.
	 * @return mixed
	 */
	public static function attach_plugin_icons( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$basename = BLT_GALLERY_PLUGIN_BASENAME;
		$base_url = BLT_GALLERY_PLUGIN_URL . 'assets/img/';

		$icons = [
			'1x'      => $base_url . 'icon-128x128.png',
			'2x'      => $base_url . 'icon-256x256.png',
			'svg'     => $base_url . 'blt-gallery-mark.svg',
			'default' => $base_url . 'icon-256x256.png',
		];

		// `response` holds plugins with an update pending, `no_update` the
		// rest; both are rendered with an icon in one screen or another.
		foreach ( [ 'response', 'no_update' ] as $bucket ) {
			if ( empty( $transient->{$bucket} ) || ! is_array( $transient->{$bucket} ) ) {
				continue;
			}

			if ( ! isset( $transient->{$bucket}[ $basename ] ) || ! is_object( $transient->{$bucket}[ $basename ] ) ) {
				continue;
			}

			$transient->{$bucket}[ $basename ]->icons = $icons;
		}

		return $transient;
	}

	/**
	 * Honour the "Check again" button on Dashboard → Updates.
	 *
	 * With automatic checks off, plugin-update-checker no longer hooks that
	 * screen at all, so WordPress would refresh every other plugin and
	 * silently skip this one. Pressing the button is an explicit request, so
	 * we run a check for it — but only for the button, not for merely opening
	 * the page.
	 *
	 * @param object $checker The plugin-update-checker instance.
	 */
	private static function wire_manual_recheck( object $checker ): void {
		if ( ! method_exists( $checker, 'checkForUpdates' ) ) {
			return;
		}

		add_action(
			'load-update-core.php',
			static function () use ( $checker ): void {
				// WordPress appends ?force-check=1 when the button is pressed.
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				if ( empty( $_GET['force-check'] ) ) {
					return;
				}

				if ( ! current_user_can( 'update_plugins' ) ) {
					return;
				}

				$checker->checkForUpdates();
			}
		);
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
