<?php

declare( strict_types=1 );

namespace BltGallery\Import;

/**
 * Persistent state for one background migration.
 *
 * A job is a queue of galleries plus a cursor into the gallery currently
 * being copied, so ImportRunner can stop after any slice and resume from the
 * exact image it left off on — even in a different PHP process minutes later.
 *
 * State lives in a non-autoloaded option per source
 * (`bltgallery_import_job_nextgen`, `bltgallery_import_job_modula`), which
 * keeps the two migration panels independent and means the admin can close
 * the browser without losing the run.
 *
 * Shape:
 *   id            string   unique run id
 *   source        string   'nextgen' | 'modula'
 *   status        string   queued | running | complete | failed | cancelled
 *   queue         array    [ {source_id, title, total, target_id, offset, done} ]
 *   queue_index   int      index of the gallery being processed
 *   totals        array    {galleries, images}
 *   progress      array    {galleries_imported, images_processed, images_imported, images_skipped}
 *   errors        string[] most recent warnings, capped at ERROR_LIMIT
 *   error_count   int      total warnings seen, including any dropped
 *   message       string   fatal error message when status is 'failed'
 *   user_id       int      admin who started the run; the worker adopts it
 *   attempt       array    {index, offset, count} — the position a pass was
 *                          last seen working on, used to detect an image that
 *                          kills the process outright
 *   created_at    int      unix timestamps …
 *   started_at    int
 *   updated_at    int      bumped on every save; drives stall detection
 *   finished_at   int
 */
class ImportJob {

	/**
	 * Option name prefix. The source key is appended.
	 */
	const OPTION_PREFIX = 'bltgallery_import_job_';

	/**
	 * How many warning messages to keep. Beyond this only the counter grows,
	 * so a migration of a broken 10k-image library can't bloat the option.
	 */
	const ERROR_LIMIT = 200;

	/**
	 * How long a finished job stays readable so the admin can reload the page
	 * and still see the outcome. Older jobs are cleared on the next read.
	 */
	const RETAIN_FINISHED = DAY_IN_SECONDS;

	// ------------------------------------------------------------------
	// Storage
	// ------------------------------------------------------------------

	public static function option_name( string $source ): string {
		return self::OPTION_PREFIX . $source;
	}

	/**
	 * Read the job for a source, or null when there is none.
	 *
	 * Finished jobs older than RETAIN_FINISHED are dropped here rather than
	 * on a schedule — the read path is the only place they matter.
	 */
	public static function get( string $source, bool $fresh = false ): ?array {
		$option = self::option_name( $source );

		// Workers live in their own PHP process, so a cancellation written by
		// the REST request would never be seen through the per-request option
		// cache. Drop the cached copy when the caller needs the truth.
		if ( $fresh ) {
			wp_cache_delete( $option, 'options' );
		}

		$job = get_option( $option );

		if ( ! is_array( $job ) || empty( $job['id'] ) ) {
			return null;
		}

		if (
			self::is_finished( $job )
			&& ( time() - (int) ( $job['finished_at'] ?? 0 ) ) > self::RETAIN_FINISHED
		) {
			self::clear( $source );
			return null;
		}

		return $job;
	}

	/**
	 * Persist a job, stamping `updated_at` so the stall watchdog can tell a
	 * worker that is chewing through images from one that died mid-run.
	 */
	public static function save( array $job ): array {
		$job['updated_at'] = time();

		// Never let the option grow without bound.
		if ( count( $job['errors'] ?? [] ) > self::ERROR_LIMIT ) {
			$job['errors'] = array_slice( $job['errors'], -self::ERROR_LIMIT );
		}

		update_option( self::option_name( (string) $job['source'] ), $job, false );

		return $job;
	}

	public static function clear( string $source ): void {
		delete_option( self::option_name( $source ) );
	}

	// ------------------------------------------------------------------
	// Lifecycle
	// ------------------------------------------------------------------

	/**
	 * Build a fresh queued job from a planned gallery list.
	 *
	 * @param array<int, array{source_id:int,title:string,total:int}> $plan
	 */
	public static function create( string $source, array $plan ): array {
		$queue        = [];
		$total_images = 0;

		foreach ( $plan as $entry ) {
			$total  = max( 0, (int) $entry['total'] );
			$queue[] = [
				'source_id' => (int) $entry['source_id'],
				'title'     => (string) $entry['title'],
				'total'     => $total,
				'target_id' => 0,
				'offset'    => 0,
				'imported'  => 0,
				'skipped'   => 0,
				'done'      => false,
			];
			$total_images += $total;
		}

		$now = time();

		$job = [
			'id'          => self::generate_id( $source ),
			'source'      => $source,
			'status'      => 'queued',
			'queue'       => $queue,
			'queue_index' => 0,
			'totals'      => [
				'galleries' => count( $queue ),
				'images'    => $total_images,
			],
			'progress'    => [
				'galleries_imported' => 0,
				'images_processed'   => 0,
				'images_imported'    => 0,
				'images_skipped'     => 0,
			],
			'errors'      => [],
			'error_count' => 0,
			'message'     => '',
			'attempt'     => null,
			// Shared secret for the loopback worker ping. Never exposed by
			// to_response(), so it stays server side.
			'token'       => wp_generate_password( 32, false, false ),
			// Cron and loopback passes run with no session, so remember who
			// asked for the migration and let the worker act as them —
			// otherwise every imported gallery would be authored by user 0.
			'user_id'     => get_current_user_id(),
			'created_at'  => $now,
			'started_at'  => 0,
			'updated_at'  => $now,
			'finished_at' => 0,
		];

		return self::save( $job );
	}

	/**
	 * Append warnings, keeping only the most recent ERROR_LIMIT of them.
	 *
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

	/**
	 * The queue entry currently being processed, or null when the queue is
	 * exhausted.
	 *
	 * @return array{index:int,entry:array}|null
	 */
	public static function current( array $job ): ?array {
		$queue = $job['queue'] ?? [];

		for ( $i = max( 0, (int) ( $job['queue_index'] ?? 0 ) ); $i < count( $queue ); $i++ ) {
			if ( empty( $queue[ $i ]['done'] ) ) {
				return [ 'index' => $i, 'entry' => $queue[ $i ] ];
			}
		}

		return null;
	}

	/**
	 * Fraction of the run that is complete, 0–100.
	 *
	 * Images drive the number because they dominate the wall-clock time;
	 * galleries only stand in when a run has no images at all.
	 */
	public static function percent( array $job ): int {
		$total_images = (int) ( $job['totals']['images'] ?? 0 );

		if ( $total_images > 0 ) {
			$done = (int) ( $job['progress']['images_processed'] ?? 0 );
			return (int) min( 100, max( 0, round( ( $done / $total_images ) * 100 ) ) );
		}

		$total_galleries = (int) ( $job['totals']['galleries'] ?? 0 );
		if ( $total_galleries > 0 ) {
			$done = (int) ( $job['progress']['galleries_imported'] ?? 0 );
			return (int) min( 100, max( 0, round( ( $done / $total_galleries ) * 100 ) ) );
		}

		return self::is_finished( $job ) ? 100 : 0;
	}

	// ------------------------------------------------------------------
	// Presentation
	// ------------------------------------------------------------------

	/**
	 * Shape the job for the REST response: everything the progress UI needs
	 * and nothing it doesn't (the per-gallery cursor bookkeeping stays server
	 * side, only the human-readable per-gallery status is exposed).
	 */
	public static function to_response( array $job, bool $stalled = false ): array {
		$current = self::current( $job );

		return [
			'id'          => (string) $job['id'],
			'source'      => (string) $job['source'],
			'status'      => (string) $job['status'],
			'percent'     => self::percent( $job ),
			'totals'      => $job['totals'],
			'progress'    => $job['progress'],
			'current'     => $current
				? [
					'title'    => (string) $current['entry']['title'],
					'position' => $current['index'] + 1,
					'offset'   => (int) $current['entry']['offset'],
					'total'    => (int) $current['entry']['total'],
				]
				: null,
			'galleries'   => array_map(
				static fn( array $entry ): array => [
					'source_id' => (int) $entry['source_id'],
					'title'     => (string) $entry['title'],
					'total'     => (int) $entry['total'],
					'imported'  => (int) ( $entry['imported'] ?? 0 ),
					'skipped'   => (int) ( $entry['skipped'] ?? 0 ),
					'done'      => (bool) $entry['done'],
				],
				array_values( $job['queue'] ?? [] )
			),
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
	 * Seconds the job has been running (frozen once it finishes).
	 */
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

	/**
	 * Response body used when a source has never been migrated (or the job
	 * has aged out), so the UI can treat "no job" like any other state.
	 */
	public static function idle_response( string $source ): array {
		return [
			'id'          => '',
			'source'      => $source,
			'status'      => 'idle',
			'percent'     => 0,
			'totals'      => [ 'galleries' => 0, 'images' => 0 ],
			'progress'    => [
				'galleries_imported' => 0,
				'images_processed'   => 0,
				'images_imported'    => 0,
				'images_skipped'     => 0,
			],
			'current'     => null,
			'galleries'   => [],
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

	// ------------------------------------------------------------------

	private static function generate_id( string $source ): string {
		return $source . '-' . gmdate( 'YmdHis' ) . '-' . substr( md5( uniqid( $source, true ) ), 0, 8 );
	}
}
