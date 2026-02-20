<?php

declare( strict_types=1 );

namespace ZymGallery\Core;

/**
 * Manages custom database tables for ZymGallery.
 *
 * Tables:
 *   {prefix}zym_galleries  – gallery metadata
 *   {prefix}zym_images     – per-image records
 */
class Database {

	const DB_VERSION = '2.0.0';

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	public function install(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		$galleries_table = $wpdb->prefix . 'zym_galleries';
		$images_table    = $wpdb->prefix . 'zym_images';

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
			storage_driver  ENUM('local','s3')  NOT NULL DEFAULT 'local',
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
		";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( 'zymgallery_db_version', self::DB_VERSION );
	}

	public function maybe_upgrade(): void {
		$installed = get_option( 'zymgallery_db_version', '0' );
		if ( version_compare( $installed, self::DB_VERSION, '<' ) ) {
			$this->install();
		}
	}

	public function drop_tables(): void {
		global $wpdb;
		// Order matters due to FK conventions (images references galleries).
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}zym_images" );
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}zym_galleries" );
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	public static function galleries_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'zym_galleries';
	}

	public static function images_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'zym_images';
	}
}
