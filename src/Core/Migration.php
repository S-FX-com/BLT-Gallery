<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * One-shot rebrand migration: ZymGallery (≤ 2.x) → Blt Gallery (3.x).
 *
 * Renames legacy `{prefix}zym_*` tables and copies legacy `zymgallery_*`
 * options into their new `bltgallery_*` keys.
 *
 * Idempotent: safe to run on every admin_init; sets a sentinel option once
 * complete so subsequent calls return immediately.
 */
final class Migration {

	private const SENTINEL = 'bltgallery_rebrand_migrated';

	public static function run(): void {
		if ( get_option( self::SENTINEL ) ) {
			return;
		}

		self::rename_tables();
		self::copy_options();

		update_option( self::SENTINEL, gmdate( 'c' ), false );
	}

	private static function rename_tables(): void {
		global $wpdb;

		$legacy_galleries = $wpdb->prefix . 'zym_galleries';
		$legacy_images    = $wpdb->prefix . 'zym_images';
		$new_galleries    = $wpdb->prefix . 'blt_galleries';
		$new_images       = $wpdb->prefix . 'blt_images';

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.DirectDatabaseQuery
		if ( self::table_exists( $legacy_galleries ) && ! self::table_exists( $new_galleries ) ) {
			$wpdb->query( "RENAME TABLE {$legacy_galleries} TO {$new_galleries}" );
		}
		if ( self::table_exists( $legacy_images ) && ! self::table_exists( $new_images ) ) {
			$wpdb->query( "RENAME TABLE {$legacy_images} TO {$new_images}" );
		}
		// phpcs:enable
	}

	private static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	}

	private static function copy_options(): void {
		$map = [
			'zymgallery_settings'                  => 'bltgallery_settings',
			'zymgallery_aws_settings'              => 'bltgallery_aws_settings',
			'zymgallery_r2_settings'               => 'bltgallery_r2_settings',
			'zymgallery_db_version'                => 'bltgallery_db_version',
			'zymgallery_delete_data_on_uninstall'  => 'bltgallery_delete_data_on_uninstall',
		];

		foreach ( $map as $old => $new ) {
			if ( false === get_option( $new, false ) ) {
				$legacy = get_option( $old, null );
				if ( null !== $legacy ) {
					update_option( $new, $legacy, false );
				}
			}
		}
	}
}
