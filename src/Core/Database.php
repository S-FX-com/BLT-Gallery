<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Manages custom database tables for BltGallery.
 *
 * Tables:
 *   {prefix}blt_galleries  – gallery metadata
 *   {prefix}blt_images     – per-image records
 *   {prefix}blt_sliders    – standalone image sliders (built in the admin)
 */
class Database {

	const DB_VERSION = '3.1.0';

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	public function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$galleries_table = $wpdb->prefix . 'blt_galleries';
		$images_table    = $wpdb->prefix . 'blt_images';
		$sliders_table   = $wpdb->prefix . 'blt_sliders';

		$sql = "
		CREATE TABLE {$galleries_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title         VARCHAR(255)        NOT NULL DEFAULT '',
			slug          VARCHAR(200)        NOT NULL DEFAULT '',
			description   TEXT,
			display_type  VARCHAR(50)         NOT NULL DEFAULT 'masonry',
			settings      LONGTEXT,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			author_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY author_id (author_id)
		) {$charset_collate};

		CREATE TABLE {$images_table} (
			id              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			gallery_id      BIGINT(20) UNSIGNED NOT NULL,
			filename        VARCHAR(255)        NOT NULL DEFAULT '',
			alt_text        VARCHAR(500)        NOT NULL DEFAULT '',
			caption         TEXT,
			description     TEXT,
			sort_order      INT                 NOT NULL DEFAULT 0,
			width           INT                 NOT NULL DEFAULT 0,
			height          INT                 NOT NULL DEFAULT 0,
			filesize        BIGINT(20)          NOT NULL DEFAULT 0,
			mime_type       VARCHAR(100)        NOT NULL DEFAULT '',
			storage_driver  ENUM('local','s3','r2') NOT NULL DEFAULT 'local',
			local_path      VARCHAR(1000),
			s3_key          VARCHAR(1000),
			s3_bucket       VARCHAR(255),
			cloudfront_url  VARCHAR(1000),
			meta            LONGTEXT,
			created_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at      DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY gallery_id (gallery_id),
			KEY sort_order (gallery_id, sort_order)
		) {$charset_collate};

		CREATE TABLE {$sliders_table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			title         VARCHAR(255)        NOT NULL DEFAULT '',
			slug          VARCHAR(200)        NOT NULL DEFAULT '',
			settings      LONGTEXT,
			items         LONGTEXT,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			author_id     BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug),
			KEY author_id (author_id)
		) {$charset_collate};
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'bltgallery_db_version', self::DB_VERSION );
	}

	public function maybe_upgrade(): void {
		$installed = get_option( 'bltgallery_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			$this->install();
			$this->run_migrations( $installed );
		}
	}

	/**
	 * Run version-specific ALTER TABLE migrations that dbDelta cannot handle
	 * (e.g. ENUM expansions, column type changes).
	 *
	 * @param string $from_version The previously installed DB version.
	 */
	private function run_migrations( string $from_version ): void {
		global $wpdb;
		$images_table = $wpdb->prefix . 'blt_images';

		// 2.0.x → 2.1.0: expand storage_driver ENUM to include 'r2'.
		if ( version_compare( $from_version, '2.1.0', '<' ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				"ALTER TABLE {$images_table}
				 MODIFY COLUMN storage_driver ENUM('local','s3','r2') NOT NULL DEFAULT 'local'"
			);
		}

		update_option( 'bltgallery_db_version', self::DB_VERSION );
	}

	public function drop_tables(): void {
		global $wpdb;
		// Order matters due to FK conventions (images references galleries).
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blt_images" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blt_galleries" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}blt_sliders" );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	public static function galleries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'blt_galleries';
	}

	public static function images_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'blt_images';
	}

	public static function sliders_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'blt_sliders';
	}
}
