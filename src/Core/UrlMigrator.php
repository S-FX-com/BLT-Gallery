<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Rewrites stored R2 image URLs from Cloudflare's blocked
 * `pub-*.r2.dev` hostnames to a custom domain attached to the bucket.
 *
 * Touches two places on each {prefix}blt_images row:
 *   - `cloudfront_url` (full-size URL)
 *   - `meta.thumbs.<size>.url` for each thumbnail size
 *
 * Idempotent: rows that already point at safe hosts are skipped, so the
 * migrator can be re-run safely and used in incremental batches without
 * a timeout risk on large libraries.
 */
final class UrlMigrator {

	/**
	 * Count of image rows that still reference an `r2.dev` host in either
	 * the `cloudfront_url` column or in `meta`.
	 */
	public static function count_unsafe(): int {
		global $wpdb;
		$table = Database::images_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$table}
			 WHERE storage_driver = 'r2'
			   AND (cloudfront_url LIKE %s OR meta LIKE %s)",
			'%r2.dev%', '%r2.dev%'
		) );
	}

	/**
	 * Returns one example `*.r2.dev` host pulled from the data, for display
	 * to the admin so they can confirm what's about to be rewritten.
	 */
	public static function sample_from_host(): ?string {
		global $wpdb;
		$table = Database::images_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$url = $wpdb->get_var( $wpdb->prepare(
			"SELECT cloudfront_url FROM {$table}
			 WHERE storage_driver = 'r2'
			   AND cloudfront_url LIKE %s
			 ORDER BY id ASC LIMIT 1",
			'%r2.dev%'
		) );
		if ( ! is_string( $url ) || '' === $url ) {
			return null;
		}
		$host = parse_url( $url, PHP_URL_HOST );
		return is_string( $host ) && '' !== $host ? strtolower( $host ) : null;
	}

	/**
	 * Returns up to 3 sample before/after rewrites for the given target.
	 */
	public static function preview( string $target_base ): array {
		global $wpdb;
		$table = Database::images_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, cloudfront_url FROM {$table}
			 WHERE storage_driver = 'r2'
			   AND cloudfront_url LIKE %s
			 ORDER BY id ASC LIMIT 3",
			'%r2.dev%'
		), ARRAY_A );

		$samples = [];
		foreach ( (array) $rows as $row ) {
			$rewritten = self::rewrite_url( (string) $row['cloudfront_url'], $target_base );
			if ( null !== $rewritten ) {
				$samples[] = [
					'id'     => (int) $row['id'],
					'before' => (string) $row['cloudfront_url'],
					'after'  => $rewritten,
				];
			}
		}

		return [
			'count'   => self::count_unsafe(),
			'samples' => $samples,
		];
	}

	/**
	 * Migrate one batch using id-cursor pagination. Re-call with the
	 * returned `next_cursor` while `more` is true to walk the whole
	 * library without holding a long HTTP request. The cursor guarantees
	 * forward progress even on rows that match the coarse SQL filter but
	 * turn out not to need any rewriting.
	 *
	 * @return array{scanned:int,updated:int,more:bool,remaining:int,next_cursor:int}
	 */
	public static function migrate_batch( string $target_base, int $batch_size = 100, int $cursor_id = 0 ): array {
		global $wpdb;
		$table      = Database::images_table();
		$batch_size = max( 1, min( 500, $batch_size ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, cloudfront_url, meta FROM {$table}
			 WHERE id > %d
			   AND storage_driver = 'r2'
			   AND (cloudfront_url LIKE %s OR meta LIKE %s)
			 ORDER BY id ASC LIMIT %d",
			$cursor_id, '%r2.dev%', '%r2.dev%', $batch_size
		), ARRAY_A );

		$scanned = 0;
		$updated = 0;
		$max_id  = $cursor_id;

		foreach ( (array) $rows as $row ) {
			$scanned++;
			$max_id         = max( $max_id, (int) $row['id'] );
			$changed        = false;
			$new_cloudfront = $row['cloudfront_url'];

			if ( is_string( $row['cloudfront_url'] ) ) {
				$rewritten = self::rewrite_url( $row['cloudfront_url'], $target_base );
				if ( null !== $rewritten && $rewritten !== $row['cloudfront_url'] ) {
					$new_cloudfront = $rewritten;
					$changed        = true;
				}
			}

			$new_meta = $row['meta'];
			$meta     = is_string( $row['meta'] ) ? json_decode( $row['meta'], true ) : null;
			if ( is_array( $meta ) && isset( $meta['thumbs'] ) && is_array( $meta['thumbs'] ) ) {
				$meta_changed = false;
				foreach ( $meta['thumbs'] as $size => $thumb ) {
					if ( is_array( $thumb ) && isset( $thumb['url'] ) && is_string( $thumb['url'] ) ) {
						$rewritten = self::rewrite_url( $thumb['url'], $target_base );
						if ( null !== $rewritten && $rewritten !== $thumb['url'] ) {
							$meta['thumbs'][ $size ]['url'] = $rewritten;
							$meta_changed                   = true;
						}
					}
				}
				if ( $meta_changed ) {
					$new_meta = wp_json_encode( $meta );
					$changed  = true;
				}
			}

			if ( $changed ) {
				$wpdb->update(
					$table,
					[
						'cloudfront_url' => $new_cloudfront,
						'meta'           => $new_meta,
					],
					[ 'id' => (int) $row['id'] ],
					[ '%s', '%s' ],
					[ '%d' ]
				);
				$updated++;
			}
		}

		return [
			'scanned'     => $scanned,
			'updated'     => $updated,
			// A full batch means there may be more rows past the cursor.
			// A short batch means we hit the end of the candidate set.
			'more'        => $scanned >= $batch_size,
			'remaining'   => self::count_unsafe(),
			'next_cursor' => $max_id,
		];
	}

	// ------------------------------------------------------------------
	// Internals
	// ------------------------------------------------------------------

	/**
	 * Returns the URL rewritten onto $target_base if $url's host is a
	 * `*.r2.dev` host, or null if no rewrite applies (so the caller can
	 * leave the value untouched). Preserves the path, query, and fragment
	 * of the source URL and respects any path prefix on $target_base.
	 */
	private static function rewrite_url( string $url, string $target_base ): ?string {
		if ( '' === $url ) {
			return null;
		}
		$normalized = preg_match( '#^https?://#i', $url )
			? $url
			: 'https://' . ltrim( $url, '/' );
		$src = parse_url( $normalized );
		if ( ! is_array( $src ) || ! isset( $src['host'] ) ) {
			return null;
		}
		$src_host = strtolower( (string) $src['host'] );
		if ( 'r2.dev' !== $src_host && ! str_ends_with( $src_host, '.r2.dev' ) ) {
			return null;
		}

		$tgt = parse_url( rtrim( $target_base, '/' ) );
		if ( ! is_array( $tgt ) || ! isset( $tgt['scheme'], $tgt['host'] ) ) {
			return null;
		}

		$out = $tgt['scheme'] . '://' . strtolower( (string) $tgt['host'] );
		if ( isset( $tgt['port'] ) ) {
			$out .= ':' . $tgt['port'];
		}
		if ( isset( $tgt['path'] ) ) {
			$out .= rtrim( (string) $tgt['path'], '/' );
		}
		if ( isset( $src['path'] ) ) {
			$out .= $src['path'];
		}
		if ( isset( $src['query'] ) ) {
			$out .= '?' . $src['query'];
		}
		if ( isset( $src['fragment'] ) ) {
			$out .= '#' . $src['fragment'];
		}
		return $out;
	}
}
