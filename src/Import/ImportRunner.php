<?php

declare( strict_types=1 );

namespace BltGallery\Import;

use BltGallery\Core\GalleryRepository;
use BltGallery\Core\ImageProcessor;
use BltGallery\Models\Gallery;

/**
 * Background worker that drives an ImportJob to completion.
 *
 * A migration of a few thousand photos takes far longer than any single HTTP
 * request may run, so the work is split into time-boxed passes. Each pass
 * copies as many images as it can inside its budget, saves progress, and then
 * queues the next pass — which means the admin can close the browser and the
 * migration keeps going.
 *
 * Every pass is kicked off two ways for resilience:
 *   1. a single WP-Cron event, so real system cron picks the work up; and
 *   2. a non-blocking loopback POST to admin-ajax.php, so the next pass starts
 *      immediately instead of waiting for the next cron tick.
 * Whichever arrives first takes the lock; the other becomes a no-op. If both
 * fail — WP-Cron disabled and loopback requests blocked — the progress
 * endpoint notices the stall and the admin page nudges the job along in the
 * foreground while it is open.
 */
class ImportRunner {

	/**
	 * Cron hook that runs one pass. Args: [ source, job id ].
	 */
	const HOOK = 'bltgallery_import_worker';

	/**
	 * admin-ajax action used for the loopback kick-off.
	 */
	const AJAX_ACTION = 'bltgallery_import_worker';

	/**
	 * Option name prefix for the per-source worker lock.
	 */
	const LOCK_PREFIX = 'bltgallery_import_lock_';

	/**
	 * A lock older than this belonged to a worker that was killed mid-pass,
	 * and may be taken over. Comfortably longer than any single pass.
	 */
	const LOCK_TTL = 180;

	/**
	 * Images copied between progress saves. Small enough that the progress
	 * bar moves visibly; large enough that per-slice query overhead is noise.
	 */
	const SLICE_SIZE = 3;

	/**
	 * A job whose `updated_at` is older than this has lost its worker.
	 */
	const STALL_SECONDS = 120;

	/**
	 * A job that has not started at all gets a much shorter window. Both
	 * kick-off routes (WP-Cron's own loopback and ours) are requests the site
	 * makes to its own public hostname, which some setups — a proxy that
	 * won't hairpin, a firewall rule, a WAF challenge — refuse. Nothing
	 * should take this long to get going, so failing over to the foreground
	 * fallback quickly beats leaving the admin watching "Queued".
	 */
	const QUEUED_STALL_SECONDS = 15;

	/**
	 * How many times one position may be attempted before the image sitting
	 * there is written off. See note_attempt() for why this exists.
	 */
	const MAX_SLICE_ATTEMPTS = 3;

	/**
	 * Sources this runner knows how to drive.
	 */
	const SOURCES = [ 'nextgen', 'modula' ];

	// ------------------------------------------------------------------
	// Wiring
	// ------------------------------------------------------------------

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run_scheduled' ], 10, 2 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'handle_loopback' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'handle_loopback' ] );

		// Safety net: any admin page load revives a job whose worker died,
		// so a stalled migration recovers even after the admin navigates
		// away from the migration screen. Skipped on AJAX, where admin_init
		// also fires — including on this runner's own loopback request.
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'admin_init', [ self::class, 'revive_stalled_jobs' ] );
		}
	}

	/**
	 * Resolve a source key to the importer that handles it.
	 *
	 * Filterable so another plugin can add a migration source without
	 * touching this class — anything implementing SourceImporter can be
	 * driven by the same background worker.
	 */
	public static function importer( string $source ): ?SourceImporter {
		$importer = match ( $source ) {
			'nextgen' => new NextGenImporter(),
			'modula'  => new ModulaImporter(),
			default   => null,
		};

		$importer = apply_filters( 'bltgallery_import_source', $importer, $source );

		return $importer instanceof SourceImporter ? $importer : null;
	}

	// ------------------------------------------------------------------
	// Public entry points
	// ------------------------------------------------------------------

	/**
	 * Plan a migration and hand it to the background worker.
	 *
	 * @param int[]|null $gallery_ids Source gallery IDs, or null for all.
	 * @throws \RuntimeException When the source is unknown/unavailable, a job
	 *                           is already running, or nothing matched.
	 */
	public static function start( string $source, ?array $gallery_ids = null ): array {
		$importer = self::importer( $source );

		if ( ! $importer ) {
			throw new \RuntimeException( __( 'Unknown migration source.', 'bltgallery' ) );
		}

		if ( ! $importer->is_available() ) {
			throw new \RuntimeException( $importer->unavailable_message() );
		}

		$existing = ImportJob::get( $source, true );
		if ( $existing && ImportJob::is_active( $existing ) ) {
			throw new \RuntimeException(
				sprintf(
					/* translators: %s: migration source name, e.g. "NextGEN Gallery" */
					__( 'A %s migration is already running. Wait for it to finish or cancel it first.', 'bltgallery' ),
					$importer->source_label()
				)
			);
		}

		$plan = $importer->plan_galleries( $gallery_ids );

		if ( ! $plan ) {
			throw new \RuntimeException( __( 'None of the selected galleries could be found.', 'bltgallery' ) );
		}

		$job = ImportJob::create( $source, $plan );

		self::dispatch( $source, (string) $job['id'] );

		return $job;
	}

	/**
	 * Stop a running migration. Galleries already copied are left in place —
	 * cancelling means "stop here", not "undo".
	 */
	public static function cancel( string $source ): ?array {
		$job = ImportJob::get( $source, true );

		if ( ! $job || ! ImportJob::is_active( $job ) ) {
			return $job;
		}

		$job = ImportJob::finish( $job, 'cancelled', __( 'Migration cancelled.', 'bltgallery' ) );

		wp_clear_scheduled_hook( self::HOOK, [ $source, (string) $job['id'] ] );

		return $job;
	}

	/**
	 * True when the job looks abandoned: still active, but nothing has
	 * written progress for a while.
	 */
	public static function is_stalled( ?array $job ): bool {
		if ( ! $job || ! ImportJob::is_active( $job ) ) {
			return false;
		}

		$window = ( 0 === (int) ( $job['started_at'] ?? 0 ) )
			? self::QUEUED_STALL_SECONDS
			: self::STALL_SECONDS;

		return ( time() - (int) ( $job['updated_at'] ?? 0 ) ) > $window;
	}

	/**
	 * Re-dispatch a job whose worker went away. Cheap and idempotent: a
	 * healthy job is left alone, and the lock keeps a duplicate pass from
	 * doing any work.
	 */
	public static function revive( string $source ): ?array {
		$job = ImportJob::get( $source, true );

		if ( ! self::is_stalled( $job ) ) {
			return $job;
		}

		self::dispatch( $source, (string) $job['id'] );

		return $job;
	}

	/**
	 * admin_init safety net across every known source.
	 */
	public static function revive_stalled_jobs(): void {
		foreach ( self::SOURCES as $source ) {
			$job = ImportJob::get( $source );
			if ( self::is_stalled( $job ) ) {
				self::revive( $source );
			}
		}
	}

	// ------------------------------------------------------------------
	// Pass triggers
	// ------------------------------------------------------------------

	/**
	 * WP-Cron callback.
	 */
	public static function run_scheduled( string $source = '', string $job_id = '' ): void {
		$job = ImportJob::get( $source, true );

		if ( ! $job || (string) $job['id'] !== $job_id || ! ImportJob::is_active( $job ) ) {
			return;
		}

		self::process( $source );
	}

	/**
	 * Loopback callback. Authenticated by the job's own random token rather
	 * than a cookie, because the ping is fired server-to-server with no user
	 * session attached.
	 */
	public static function handle_loopback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$source = isset( $_POST['source'] ) ? sanitize_key( wp_unslash( $_POST['source'] ) ) : '';
		$job_id = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$job = ImportJob::get( $source, true );

		if (
			! $job
			|| (string) $job['id'] !== $job_id
			|| ! hash_equals( (string) ( $job['token'] ?? '' ), $token )
			|| ! ImportJob::is_active( $job )
		) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		// The caller fired this non-blocking and has already hung up.
		ignore_user_abort( true );

		self::process( $source );

		wp_die( '', '', [ 'response' => 200 ] );
	}

	/**
	 * Run one pass now, in the calling process.
	 *
	 * @param float|null $budget Seconds of work to do, or null for the
	 *                           environment's default.
	 */
	public static function process( string $source, ?float $budget = null ): ?array {
		$job = ImportJob::get( $source, true );

		if ( ! $job || ! ImportJob::is_active( $job ) ) {
			return $job;
		}

		if ( ! self::acquire_lock( $source ) ) {
			return $job; // Another worker already owns this pass.
		}

		self::raise_limits();

		try {
			$job = self::process_locked( $source, $budget ?? self::time_budget() );
		} catch ( \Throwable $e ) {
			$current = ImportJob::get( $source, true );
			$job     = ImportJob::finish( $current ?: $job, 'failed', $e->getMessage() );
		} finally {
			self::release_lock( $source );
		}

		// Still work to do — line up the next pass.
		if ( $job && ImportJob::is_active( $job ) ) {
			self::dispatch( $source, (string) $job['id'] );
		}

		return $job;
	}

	// ------------------------------------------------------------------
	// The pass itself
	// ------------------------------------------------------------------

	/**
	 * Copy images until the time budget runs out, the queue empties, memory
	 * gets tight, or the job is cancelled from another request.
	 */
	private static function process_locked( string $source, float $budget ): ?array {
		$importer = self::importer( $source );
		$job      = ImportJob::get( $source, true );

		if ( ! $importer || ! $job ) {
			return $job;
		}

		// Cron and loopback passes carry no session. Become the admin who
		// started the run so imported galleries get a sensible author and any
		// capability-sensitive filter behaves as it would have in-page.
		$user_id = (int) ( $job['user_id'] ?? 0 );
		if ( $user_id > 0 && get_current_user_id() !== $user_id ) {
			wp_set_current_user( $user_id );
		}

		if ( 'queued' === $job['status'] ) {
			$job['status']     = 'running';
			$job['started_at'] = time();
			$job               = ImportJob::save( $job );
		}

		$processor = new ImageProcessor();
		$deadline  = microtime( true ) + $budget;
		$slice     = (int) apply_filters( 'bltgallery_import_slice_size', self::SLICE_SIZE, $source );
		$slice     = max( 1, $slice );

		while ( microtime( true ) < $deadline ) {
			// Re-read every iteration so a cancel from the admin screen takes
			// effect within one slice rather than at the end of the pass.
			$job = ImportJob::get( $source, true );

			if ( ! $job || ! ImportJob::is_active( $job ) ) {
				return $job;
			}

			$current = ImportJob::current( $job );

			if ( ! $current ) {
				return ImportJob::finish( $job, 'complete' );
			}

			$index = $current['index'];
			$entry = $current['entry'];

			$job['queue_index'] = $index;

			$target = self::resolve_target( $importer, $job, $index, $entry );

			if ( ! $target ) {
				$job = ImportJob::save( $job ); // resolve_target() recorded the failure.
				continue;
			}

			$entry = $job['queue'][ $index ];

			if ( $entry['offset'] >= $entry['total'] ) {
				$job['queue'][ $index ]['done'] = true;
				$job                            = ImportJob::save( $job );
				continue;
			}

			// Record where this pass is about to work *before* doing any of
			// it, so a pass that never returns leaves a trace for the next
			// one. Without this, a single image that fatals PHP outright —
			// a file too big for the memory limit, say — would be retried
			// forever and the migration could never finish.
			$attempt        = self::note_attempt( $job, $index, (int) $entry['offset'] );
			$job['attempt'] = $attempt;

			if ( $attempt['count'] > self::MAX_SLICE_ATTEMPTS ) {
				$job = self::skip_stuck_image( $job, $index, $entry );
				$job = ImportJob::save( $job );
				continue;
			}

			$job = ImportJob::save( $job );

			$copied = $importer->import_slice(
				(int) $entry['source_id'],
				$target,
				(int) $entry['offset'],
				// A position that has already brought a pass down is retried
				// one image at a time, so the culprit ends up isolated at its
				// own offset rather than dragging its neighbours down with it.
				$attempt['count'] > 1 ? 1 : $slice,
				$processor
			);

			if ( $copied['processed'] < 1 ) {
				// The source ran out of rows earlier than planned (images
				// deleted since the preview). Close the gallery out and count
				// the shortfall so the bar still reaches 100%.
				$shortfall                            = max( 0, (int) $entry['total'] - (int) $entry['offset'] );
				$job['progress']['images_processed'] += $shortfall;
				$job['progress']['images_skipped']   += $shortfall;
				$job['queue'][ $index ]['done']       = true;
			} else {
				$entry['offset']   += $copied['processed'];
				$entry['imported'] += $copied['imported'];
				$entry['skipped']  += $copied['skipped'];
				$entry['done']      = $entry['offset'] >= $entry['total'];

				$job['queue'][ $index ]               = $entry;
				$job['progress']['images_processed'] += $copied['processed'];
				$job['progress']['images_imported']  += $copied['imported'];
				$job['progress']['images_skipped']   += $copied['skipped'];
			}

			$job = ImportJob::add_errors( $job, $copied['errors'] );
			$job = ImportJob::save( $job );

			self::touch_lock( $source );

			if ( self::memory_exhausted() ) {
				break; // Next pass starts with a clean heap.
			}
		}

		return $job;
	}

	/**
	 * Count consecutive attempts at the same queue position.
	 *
	 * A pass that finishes its slice always moves the offset on, so seeing
	 * the same (gallery, offset) twice in a row means the previous pass died
	 * partway through it.
	 *
	 * @return array{index:int,offset:int,count:int}
	 */
	private static function note_attempt( array $job, int $index, int $offset ): array {
		$previous = $job['attempt'] ?? null;

		$repeat = is_array( $previous )
			&& (int) ( $previous['index'] ?? -1 ) === $index
			&& (int) ( $previous['offset'] ?? -1 ) === $offset;

		return [
			'index'  => $index,
			'offset' => $offset,
			'count'  => $repeat ? (int) $previous['count'] + 1 : 1,
		];
	}

	/**
	 * Write off the image at the current position after it has taken down
	 * MAX_SLICE_ATTEMPTS passes, and step over it so the rest of the
	 * migration can finish. The admin sees it in the warnings list.
	 */
	private static function skip_stuck_image( array $job, int $index, array $entry ): array {
		$offset = (int) $entry['offset'];
		$total  = (int) $entry['total'];

		$job['queue'][ $index ]['offset']  = $offset + 1;
		$job['queue'][ $index ]['skipped'] = (int) $entry['skipped'] + 1;
		$job['queue'][ $index ]['done']    = ( $offset + 1 ) >= $total;

		$job['progress']['images_processed']++;
		$job['progress']['images_skipped']++;
		$job['attempt'] = null;

		return ImportJob::add_errors(
			$job,
			[
				sprintf(
					/* translators: 1: image position within the gallery, 2: gallery title */
					__( 'Skipped image %1$d of "%2$s" — it repeatedly stopped the migration, so it was passed over. The file is probably too large for this server to process.', 'bltgallery' ),
					$offset + 1,
					$entry['title']
				),
			]
		);
	}

	/**
	 * Return the BLT Gallery gallery a queue entry copies into, creating it on
	 * first touch. Returns null when the entry can't proceed, having already
	 * marked it done and recorded why on $job (passed by reference).
	 */
	private static function resolve_target( SourceImporter $importer, array &$job, int $index, array $entry ): ?Gallery {
		if ( ! empty( $entry['target_id'] ) ) {
			$target = GalleryRepository::find( (int) $entry['target_id'] );

			if ( $target ) {
				return $target;
			}

			// Deleted from under us mid-run.
			$job['queue'][ $index ]['done'] = true;
			$job['progress']['images_processed'] += max( 0, (int) $entry['total'] - (int) $entry['offset'] );
			$job                                  = ImportJob::add_errors(
				$job,
				[
					sprintf(
						/* translators: %s: gallery title */
						__( 'The destination gallery for "%s" was deleted while the migration was running.', 'bltgallery' ),
						$entry['title']
					),
				]
			);

			return null;
		}

		try {
			$target = $importer->create_target_gallery( (int) $entry['source_id'] );
		} catch ( \Throwable $e ) {
			$job['queue'][ $index ]['done']       = true;
			$job['progress']['images_processed'] += max( 0, (int) $entry['total'] );
			$job                                  = ImportJob::add_errors( $job, [ $e->getMessage() ] );

			return null;
		}

		$job['queue'][ $index ]['target_id'] = $target->id;
		$job['progress']['galleries_imported']++;
		$job = ImportJob::save( $job );

		return $target;
	}

	// ------------------------------------------------------------------
	// Dispatch
	// ------------------------------------------------------------------

	/**
	 * Queue the next pass: a cron event plus an immediate loopback ping.
	 */
	private static function dispatch( string $source, string $job_id ): void {
		$args = [ $source, $job_id ];

		// Clear first: wp_schedule_single_event() refuses to add an event
		// that duplicates one already queued within ten minutes.
		wp_clear_scheduled_hook( self::HOOK, $args );
		wp_schedule_single_event( time(), self::HOOK, $args );

		self::ping_loopback( $source, $job_id );
	}

	/**
	 * Fire-and-forget request that starts the next pass right away, without
	 * waiting for cron. Failures are ignored on purpose — the cron event and
	 * the stall watchdog are the fallbacks.
	 */
	private static function ping_loopback( string $source, string $job_id ): void {
		if ( ! apply_filters( 'bltgallery_import_use_loopback', true, $source ) ) {
			return;
		}

		$job   = ImportJob::get( $source );
		$token = (string) ( $job['token'] ?? '' );

		if ( '' === $token ) {
			return;
		}

		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			[
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'cookies'   => [],
				'body'      => [
					'action' => self::AJAX_ACTION,
					'source' => $source,
					'job'    => $job_id,
					'token'  => $token,
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Locking
	// ------------------------------------------------------------------

	/**
	 * Take the per-source worker lock.
	 *
	 * add_option() is atomic thanks to the unique index on option_name, which
	 * makes it a dependable cross-process lock without needing an object
	 * cache. A lock older than LOCK_TTL is assumed dead and taken over.
	 */
	private static function acquire_lock( string $source ): bool {
		$key = self::LOCK_PREFIX . $source;
		$now = time();

		if ( add_option( $key, $now, '', false ) ) {
			return true;
		}

		wp_cache_delete( $key, 'options' );
		$held = (int) get_option( $key, 0 );

		if ( $held > 0 && ( $now - $held ) < self::LOCK_TTL ) {
			return false;
		}

		update_option( $key, $now, false );

		return true;
	}

	/**
	 * Keep the lock fresh while a long pass is still making progress.
	 */
	private static function touch_lock( string $source ): void {
		update_option( self::LOCK_PREFIX . $source, time(), false );
	}

	private static function release_lock( string $source ): void {
		delete_option( self::LOCK_PREFIX . $source );
	}

	// ------------------------------------------------------------------
	// Environment
	// ------------------------------------------------------------------

	/**
	 * Seconds of work one pass should attempt.
	 *
	 * Read after raise_limits() has had its go at PHP's execution cap, so the
	 * budget reflects whatever cap is actually in force — a pass should
	 * always finish and save rather than be killed mid-image.
	 */
	private static function time_budget(): float {
		$max    = (int) ini_get( 'max_execution_time' );
		$budget = $max > 0 ? (int) floor( $max * 0.6 ) : 45;
		$budget = max( 5, min( 45, $budget ) );

		return (float) apply_filters( 'bltgallery_import_time_budget', $budget );
	}

	private static function raise_limits(): void {
		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * True when the heap is close enough to the PHP limit that another image
	 * could tip the process over. Stopping here loses nothing: progress is
	 * already saved and the next pass starts fresh.
	 */
	private static function memory_exhausted(): bool {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

		if ( $limit <= 0 ) {
			return false; // Unlimited.
		}

		return memory_get_usage( true ) > ( $limit * 0.85 );
	}
}
