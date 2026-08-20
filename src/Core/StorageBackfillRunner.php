<?php

declare( strict_types=1 );

namespace BltGallery\Core;

/**
 * Background worker that pushes existing local images out to whichever
 * remote backend (R2 or S3) is configured.
 *
 * StorageOffloader only ever ran on the moment an image was created — a
 * fresh upload, or one landing via the NextGEN/Modula importers. Turning R2
 * on afterwards, or turning it off and back on, left every image already on
 * disk stranded there with nothing to notice and re-offload them. This
 * worker is that catch-up pass.
 *
 * Architecture mirrors ImportRunner: time-boxed passes dispatched by both a
 * WP-Cron event and an immediate loopback ping, an add_option() lock so the
 * two can't double up, and a stall watchdog that revives a job whose worker
 * died. The queue itself needs no cursor — each pass simply asks the
 * database for the next local images, and a row leaves that result set the
 * instant it is offloaded, so resuming after an interruption is automatic.
 */
class StorageBackfillRunner {

	const HOOK         = 'bltgallery_backfill_worker';
	const AJAX_ACTION   = 'bltgallery_backfill_worker';
	const LOCK          = 'bltgallery_backfill_lock';
	const LOCK_TTL       = 180;
	const QUEUED_STALL_SECONDS = 15;
	const STALL_SECONDS = 120;

	/**
	 * Images fetched per query. Each may cost up to four HTTP PUTs (original
	 * + up to three thumbnail sizes), so this stays small.
	 */
	const BATCH_SIZE = 5;

	/**
	 * How many times the same image may be attempted before it is given up
	 * on. Mirrors ImportRunner's guard against a single file that fatals PHP
	 * outright (too large for the memory limit) crash-looping the run
	 * forever.
	 */
	const MAX_ATTEMPTS = 3;

	// ------------------------------------------------------------------
	// Wiring
	// ------------------------------------------------------------------

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run_scheduled' ], 10, 1 );
		add_action( 'wp_ajax_' . self::AJAX_ACTION, [ self::class, 'handle_loopback' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION, [ self::class, 'handle_loopback' ] );

		if ( is_admin() ) {
			add_action( 'admin_init', [ self::class, 'revive_if_stalled' ] );
		}
	}

	// ------------------------------------------------------------------
	// Public entry points
	// ------------------------------------------------------------------

	/**
	 * How many local images are waiting to be offloaded right now.
	 */
	public static function pending(): int {
		return ImageRepository::count_local();
	}

	/**
	 * Queue a backfill run against whichever backend is currently active.
	 *
	 * @throws \RuntimeException When no remote backend is configured, a run
	 *                           is already active, or nothing needs doing.
	 */
	public static function start(): array {
		$driver = StorageOffloader::driver();

		if ( 'local' === $driver ) {
			throw new \RuntimeException(
				__( 'No remote storage is enabled. Turn on Amazon S3 or Cloudflare R2 under Settings first.', 'bltgallery' )
			);
		}

		$existing = StorageBackfillJob::get( true );
		if ( $existing && StorageBackfillJob::is_active( $existing ) ) {
			throw new \RuntimeException(
				__( 'A backfill is already running. Wait for it to finish or cancel it first.', 'bltgallery' )
			);
		}

		$total = ImageRepository::count_local();

		if ( $total < 1 ) {
			throw new \RuntimeException( __( 'Every image is already on remote storage.', 'bltgallery' ) );
		}

		$job = StorageBackfillJob::create( $driver, $total );

		self::dispatch( (string) $job['id'] );

		return $job;
	}

	public static function cancel(): ?array {
		$job = StorageBackfillJob::get( true );

		if ( ! $job || ! StorageBackfillJob::is_active( $job ) ) {
			return $job;
		}

		$job = StorageBackfillJob::finish( $job, 'cancelled', __( 'Backfill cancelled.', 'bltgallery' ) );

		wp_clear_scheduled_hook( self::HOOK, [ (string) $job['id'] ] );

		return $job;
	}

	public static function is_stalled( ?array $job ): bool {
		if ( ! $job || ! StorageBackfillJob::is_active( $job ) ) {
			return false;
		}

		$window = ( 0 === (int) ( $job['started_at'] ?? 0 ) )
			? self::QUEUED_STALL_SECONDS
			: self::STALL_SECONDS;

		return ( time() - (int) ( $job['updated_at'] ?? 0 ) ) > $window;
	}

	public static function revive(): ?array {
		$job = StorageBackfillJob::get( true );

		if ( ! self::is_stalled( $job ) ) {
			return $job;
		}

		self::dispatch( (string) $job['id'] );

		return $job;
	}

	public static function revive_if_stalled(): void {
		$job = StorageBackfillJob::get();
		if ( self::is_stalled( $job ) ) {
			self::revive();
		}
	}

	// ------------------------------------------------------------------
	// Pass triggers
	// ------------------------------------------------------------------

	public static function run_scheduled( string $job_id = '' ): void {
		$job = StorageBackfillJob::get( true );

		if ( ! $job || (string) $job['id'] !== $job_id || ! StorageBackfillJob::is_active( $job ) ) {
			return;
		}

		self::process();
	}

	public static function handle_loopback(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing
		$job_id = isset( $_POST['job'] ) ? sanitize_text_field( wp_unslash( $_POST['job'] ) ) : '';
		$token  = isset( $_POST['token'] ) ? sanitize_text_field( wp_unslash( $_POST['token'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		$job = StorageBackfillJob::get( true );

		if (
			! $job
			|| (string) $job['id'] !== $job_id
			|| ! hash_equals( (string) ( $job['token'] ?? '' ), $token )
			|| ! StorageBackfillJob::is_active( $job )
		) {
			wp_die( '', '', [ 'response' => 403 ] );
		}

		ignore_user_abort( true );

		self::process();

		wp_die( '', '', [ 'response' => 200 ] );
	}

	public static function process( ?float $budget = null ): ?array {
		$job = StorageBackfillJob::get( true );

		if ( ! $job || ! StorageBackfillJob::is_active( $job ) ) {
			return $job;
		}

		if ( ! self::acquire_lock() ) {
			return $job;
		}

		self::raise_limits();

		try {
			$job = self::process_locked( $budget ?? self::time_budget() );
		} catch ( \Throwable $e ) {
			$current = StorageBackfillJob::get( true );
			$job     = StorageBackfillJob::finish( $current ?: $job, 'failed', $e->getMessage() );
		} finally {
			self::release_lock();
		}

		if ( $job && StorageBackfillJob::is_active( $job ) ) {
			self::dispatch( (string) $job['id'] );
		}

		return $job;
	}

	// ------------------------------------------------------------------
	// The pass itself
	// ------------------------------------------------------------------

	private static function process_locked( float $budget ): ?array {
		$job = StorageBackfillJob::get( true );

		if ( ! $job ) {
			return $job;
		}

		$user_id = (int) ( $job['user_id'] ?? 0 );
		if ( $user_id > 0 && get_current_user_id() !== $user_id ) {
			wp_set_current_user( $user_id );
		}

		if ( 'queued' === $job['status'] ) {
			$job['status']     = 'running';
			$job['started_at'] = time();
			$job               = StorageBackfillJob::save( $job );
		}

		$driver   = (string) $job['driver'];
		$deadline = microtime( true ) + $budget;

		while ( microtime( true ) < $deadline ) {
			$job = StorageBackfillJob::get( true );

			if ( ! $job || ! StorageBackfillJob::is_active( $job ) ) {
				return $job;
			}

			$skip_ids = array_map( 'intval', (array) ( $job['skip_ids'] ?? [] ) );
			$batch    = ImageRepository::find_local_batch( self::BATCH_SIZE, $skip_ids );

			if ( ! $batch ) {
				return StorageBackfillJob::finish( $job, 'complete' );
			}

			foreach ( $batch as $image ) {
				if ( microtime( true ) >= $deadline ) {
					break;
				}

				// Record which image this pass is about to attempt *before*
				// touching it, so a crash that takes the whole PHP process
				// down outright (a file too large for the memory limit)
				// leaves a trace for the next pass to find. This is cleared
				// again the moment we return alive from offload_to() —
				// success or a caught failure both count as "returned" — so
				// attempt_count can only climb above 1 via a real crash.
				$previous      = $job['attempt'] ?? null;
				$repeat        = is_array( $previous ) && (int) ( $previous['id'] ?? -1 ) === $image->id;
				$attempt_count = $repeat ? (int) $previous['count'] + 1 : 1;

				if ( $attempt_count > self::MAX_ATTEMPTS ) {
					$job['attempt'] = null;
					$job            = self::give_up_on( $job, $image, true );
					$job            = StorageBackfillJob::save( $job );
					self::touch_lock();
					continue;
				}

				$job['attempt'] = [ 'id' => $image->id, 'count' => $attempt_count ];
				$job            = StorageBackfillJob::save( $job );

				// Refresh the lease *before* the risky call, not just after.
				// An image can cost up to four sequential PUTs (original +
				// up to three thumbnails), each with its own timeout in
				// S3HttpClient — comfortably longer, in the worst case, than
				// LOCK_TTL. Without this, a slow-but-alive upload could look
				// abandoned to the stall watchdog, which would then dispatch
				// a second worker to run concurrently with this one.
				self::touch_lock();

				$offloaded = StorageOffloader::offload_to( $image, $driver );

				// The job may have been cancelled by a separate request
				// while that upload was in flight. This process has been
				// sitting on a stale in-memory copy since before the call, so
				// check the real, current status before writing anything —
				// otherwise saving our now-outdated "running" copy back would
				// silently resurrect a run the admin just told to stop.
				$job = StorageBackfillJob::get( true );
				if ( ! $job || ! StorageBackfillJob::is_active( $job ) ) {
					return $job;
				}

				// Alive past the risky call: the crash marker no longer
				// applies, whichever way this attempt went.
				$job['attempt'] = null;
				$job['progress']['processed']++;

				// storage_driver flips to the target driver as soon as the
				// original file lands, before any thumbnail is attempted, so
				// on its own it can't distinguish a full success from the
				// original succeeding and a thumbnail failing partway
				// through. Every thumbnail carrying its own key is the real
				// signal that nothing was left behind on this attempt.
				if ( $offloaded->storage_driver === $driver && self::all_thumbs_offloaded( $offloaded ) ) {
					ImageRepository::save( $offloaded );
					$job['progress']['offloaded']++;
				} else {
					// Deliberately not saved: $offloaded may already carry a
					// mutated storage_driver from a partial attempt (the
					// original landed, a thumbnail didn't), and persisting
					// that would flip the row out of future find_local_batch()
					// results for good — the opposite of what give_up_on()
					// promises below. Leaving the row untouched keeps it
					// exactly as it was: still 'local', so a fresh run later
					// sees it again.
					//
					// A caught failure — bad credentials, a file the bucket
					// rejects, a quota, a thumbnail that didn't make it — is
					// not transient in any way another attempt one second
					// later would fix, so this image is given up on for the
					// rest of *this* run rather than being fetched again on
					// the very next batch and looping forever. Pressing the
					// backfill button again later starts a fresh run with an
					// empty skip list, so it does get retried eventually.
					$job = self::give_up_on( $job, $image, false );
				}

				$job = StorageBackfillJob::save( $job );

				self::touch_lock();

				if ( self::memory_exhausted() ) {
					return $job;
				}
			}
		}

		return $job;
	}

	/**
	 * Write off an image and exclude it from future batches so the run can
	 * finish without it.
	 *
	 * @param bool $crashed True when this follows repeated process deaths;
	 *                      false for a single caught upload failure.
	 */
	private static function give_up_on( array $job, \BltGallery\Models\Image $image, bool $crashed ): array {
		$job['skip_ids'][]           = $image->id;
		$job['progress']['processed']++;
		$job['progress']['skipped']++;

		$message = $crashed
			? sprintf(
				/* translators: %d: image id */
				__( 'Skipped image #%d — it repeatedly stopped the backfill, so it was passed over. The file is probably too large for this server to process.', 'bltgallery' ),
				$image->id
			)
			: sprintf(
				/* translators: %d: image id */
				__( 'Image #%d could not be uploaded and was skipped — check the storage credentials and quota. Run the backfill again to retry it.', 'bltgallery' ),
				$image->id
			);

		return StorageBackfillJob::add_errors( $job, [ $message ] );
	}

	/**
	 * True when every thumbnail this image has actually carries a key on the
	 * driver it was just sent to.
	 *
	 * R2Storage/S3Storage set storage_driver the moment the *original* file
	 * lands, before looping over thumbnails — so on a partial failure
	 * (original succeeds, a thumbnail throws) the image comes back already
	 * flagged as offloaded even though something was left on local disk.
	 * This is the check that actually reflects "nothing left behind".
	 */
	private static function all_thumbs_offloaded( \BltGallery\Models\Image $image ): bool {
		foreach ( (array) ( $image->meta['thumbs'] ?? [] ) as $thumb ) {
			if ( empty( $thumb['s3_key'] ) ) {
				return false;
			}
		}

		return true;
	}

	// ------------------------------------------------------------------
	// Dispatch
	// ------------------------------------------------------------------

	private static function dispatch( string $job_id ): void {
		$args = [ $job_id ];

		wp_clear_scheduled_hook( self::HOOK, $args );
		wp_schedule_single_event( time(), self::HOOK, $args );

		self::ping_loopback( $job_id );
	}

	private static function ping_loopback( string $job_id ): void {
		if ( ! apply_filters( 'bltgallery_backfill_use_loopback', true ) ) {
			return;
		}

		$job   = StorageBackfillJob::get();
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
					'job'    => $job_id,
					'token'  => $token,
				],
			]
		);
	}

	// ------------------------------------------------------------------
	// Locking
	// ------------------------------------------------------------------

	private static function acquire_lock(): bool {
		$now = time();

		if ( add_option( self::LOCK, $now, '', false ) ) {
			return true;
		}

		wp_cache_delete( self::LOCK, 'options' );
		$held = (int) get_option( self::LOCK, 0 );

		if ( $held > 0 && ( $now - $held ) < self::LOCK_TTL ) {
			return false;
		}

		update_option( self::LOCK, $now, false );

		return true;
	}

	private static function touch_lock(): void {
		update_option( self::LOCK, time(), false );
	}

	private static function release_lock(): void {
		delete_option( self::LOCK );
	}

	// ------------------------------------------------------------------
	// Environment
	// ------------------------------------------------------------------

	private static function time_budget(): float {
		$max    = (int) ini_get( 'max_execution_time' );
		$budget = $max > 0 ? (int) floor( $max * 0.6 ) : 45;
		$budget = max( 5, min( 45, $budget ) );

		return (float) apply_filters( 'bltgallery_backfill_time_budget', $budget );
	}

	private static function raise_limits(): void {
		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	private static function memory_exhausted(): bool {
		$limit = wp_convert_hr_to_bytes( (string) ini_get( 'memory_limit' ) );

		if ( $limit <= 0 ) {
			return false;
		}

		return memory_get_usage( true ) > ( $limit * 0.85 );
	}
}
