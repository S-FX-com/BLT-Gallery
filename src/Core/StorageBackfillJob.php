<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Persistent state for a "push existing local images to remote storage" run.
 *
 * There is only ever one backend active at a time, so unlike ImportJob (one
 * option per source) this is a single option covering whichever driver the
 * run targets. The driver is captured once at start and never re-read from
 * settings, so flipping the R2/S3 toggle mid-run can't send half a run's
 * images to one bucket and the rest to another.
 *
 * The queue itself is not stored here — unlike a migration, there is no
 * fixed list to plan up front. StorageBackfillRunner simply asks the database
 * for "the next local images" each pass; a row leaves that result set the
 * moment it is offloaded, so progress needs no cursor to track.
 */
class StorageBackfillJob {

	const OPTION = 'bltgallery_backfill_job';

	/**
	 * How many warning messages to keep. Beyond this only the counter grows.
	 */
	const ERROR_LIMIT = 200;

	/**
	 * How long a finished job stays readable so the admin can reload the
	 * Settings page and still see the outcome.
	 */
	const RETAIN_FINISHED = DAY_IN_SECONDS;

	// ------------------------------------------------------------------
	// Storage
	// ------------------------------------------------------------------

	/**
	 * Read the job, or null when there is none.
	 *
	 * @param bool $fresh Bypass the per-request option cache. Needed by the
	 *                     worker: cron and loopback passes run in their own
	 *                     PHP process, so a cancellation written by a REST
	 *                     request would otherwise never be seen.
	 */
	public static function get( bool $fresh = false ): ?array {
		if ( $fresh ) {
			wp_cache_delete( self::OPTION, 'options' );
		}

		$job = get_option( self::OPTION );

		if ( ! is_array( $job ) || empty( $job['id'] ) ) {
			return null;
		}

		if (
			self::is_finished( $job )
			&& ( time() - (int) ( $job['finished_at'] ?? 0 ) ) > self::RETAIN_FINISHED
		) {
			self::clear();
			return null;
		}

		return $job;
	}

	public static function save( array $job ): array {
		$job['updated_at'] = time();

		if ( count( $job['errors'] ?? [] ) > self::ERROR_LIMIT ) {
			$job['errors'] = array_slice( $job['errors'], -self::ERROR_LIMIT );
		}

		update_option( self::OPTION, $job, false );

		return $job;
	}

	public static function clear(): void {
		delete_option( self::OPTION );
	}

	// ------------------------------------------------------------------
	// Lifecycle
	// ------------------------------------------------------------------

	public static function create( string $driver, int $total_images ): array {
		$now = time();

		$job = [
			'id'          => 'backfill-' . gmdate( 'YmdHis' ) . '-' . substr( md5( uniqid( $driver, true ) ), 0, 8 ),
			'status'      => 'queued',
			'driver'      => $driver,
			'totals'      => [ 'images' => max( 0, $total_images ) ],
			'progress'    => [ 'processed' => 0, 'offloaded' => 0, 'skipped' => 0 ],
			// Image ids given up on after repeatedly bringing a pass down,
			// excluded from future batches so the run can still finish.
			'skip_ids'    => [],
			// {id, count} for whichever image the current pass is mid-way
			// through, so a crash that kills the whole PHP process (not just
			// a caught exception) can be noticed on the next pass.
			'attempt'     => null,
			'errors'      => [],
			'error_count' => 0,
			'message'     => '',
			// Shared secret for the loopback worker ping. Never exposed by
			// to_response().
			'token'       => wp_generate_password( 32, false, false ),
			// Cron and loopback passes carry no session; the worker adopts
			// this so offloaded images and any capability-sensitive filter
			// behave as they would have in-page.
			'user_id'     => get_current_user_id(),
			'created_at'  => $now,
			'started_at'  => 0,
			'updated_at'  => $now,
			'finished_at' => 0,
		];

		return self::save( $job );
	}

	/**
	 * @param string[] $errors
	 */
	public static function add_errors( array $job, array $errors ): array {
		if ( ! $errors ) {
			return $job;
		}

		$job['error_count'] = (int) ( $job['error_count'] ?? 0 ) + count( $errors );
		$job['errors']      = array_slice(
			array_merge( $job['errors'] ?? [], array_map( 'strval', $errors ) ),
			-self::ERROR_LIMIT
		);

		return $job;
	}

	public static function finish( array $job, string $status, string $message = '' ): array {
		$job['status']      = $status;
		$job['finished_at'] = time();

		if ( '' !== $message ) {
			$job['message'] = $message;
		}

		return self::save( $job );
	}

	// ------------------------------------------------------------------
	// Queries
	// ------------------------------------------------------------------

	public static function is_finished( array $job ): bool {
		return in_array( (string) ( $job['status'] ?? '' ), [ 'complete', 'failed', 'cancelled' ], true );
	}

	public static function is_active( array $job ): bool {
		return in_array( (string) ( $job['status'] ?? '' ), [ 'queued', 'running' ], true );
	}

	public static function percent( array $job ): int {
		$total = (int) ( $job['totals']['images'] ?? 0 );

		if ( $total <= 0 ) {
			return self::is_finished( $job ) ? 100 : 0;
		}

		$done = (int) ( $job['progress']['processed'] ?? 0 );

		return (int) min( 100, max( 0, round( ( $done / $total ) * 100 ) ) );
	}

	public static function elapsed( array $job ): int {
		$started = (int) ( $job['started_at'] ?? 0 );
		if ( $started <= 0 ) {
			return 0;
		}

		$end = self::is_finished( $job ) && ! empty( $job['finished_at'] )
			? (int) $job['finished_at']
			: time();

		return max( 0, $end - $started );
	}

	// ------------------------------------------------------------------
	// Presentation
	// ------------------------------------------------------------------

	public static function to_response( array $job, bool $stalled = false ): array {
		return [
			'id'          => (string) $job['id'],
			'status'      => (string) $job['status'],
			'driver'      => (string) $job['driver'],
			'percent'     => self::percent( $job ),
			'totals'      => $job['totals'],
			'progress'    => $job['progress'],
			'errors'      => array_values( $job['errors'] ?? [] ),
			'error_count' => (int) ( $job['error_count'] ?? 0 ),
			'message'     => (string) ( $job['message'] ?? '' ),
			'started_at'  => (int) ( $job['started_at'] ?? 0 ),
			'updated_at'  => (int) ( $job['updated_at'] ?? 0 ),
			'finished_at' => (int) ( $job['finished_at'] ?? 0 ),
			'elapsed'     => self::elapsed( $job ),
			'stalled'     => $stalled,
			'now'         => time(),
		];
	}

	/**
	 * Response body when no run exists yet. Carries the current backlog size
	 * so the Settings page can show "1,847 images not yet in R2" before
	 * anyone presses start.
	 */
	public static function idle_response( string $driver, int $pending ): array {
		return [
			'id'          => '',
			'status'      => 'idle',
			'driver'      => $driver,
			'percent'     => 0,
			'totals'      => [ 'images' => $pending ],
			'progress'    => [ 'processed' => 0, 'offloaded' => 0, 'skipped' => 0 ],
			'errors'      => [],
			'error_count' => 0,
			'message'     => '',
			'started_at'  => 0,
			'updated_at'  => 0,
			'finished_at' => 0,
			'elapsed'     => 0,
			'stalled'     => false,
			'now'         => time(),
		];
	}
}
