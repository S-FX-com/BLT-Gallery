<?php

declare( strict_types=1 );

namespace BltGallery\Core;

use BltGallery\Aws\S3Storage;
use BltGallery\Storage\R2Storage;

/**
 * Deletes remote objects in the background, after the thing that owned them
 * has already gone from the database.
 *
 * Removing a gallery means removing an object per image plus one per
 * thumbnail. Locally that is a few thousand unlinks and takes no time; in R2
 * or S3 it is a few thousand HTTP round trips, which no admin request should
 * be made to sit through. So a delete clears the database rows and the local
 * files straight away — the gallery disappears from the screen at once — and
 * the remote keys are handed to this queue to work through on WP-Cron.
 *
 * Runs are deliberately delayed a little so a bulk delete of fifteen
 * galleries coalesces into one drain rather than fifteen.
 *
 * Deletion is idempotent, so a pass that dies partway through costs nothing
 * more than repeating a few DELETEs on the next run.
 */
class StoragePurgeQueue {

	const OPTION = 'bltgallery_storage_purge_queue';
	const HOOK   = 'bltgallery_storage_purge';
	const LOCK   = 'bltgallery_storage_purge_lock';

	/**
	 * Seconds to wait before draining, so a burst of deletes batches up.
	 */
	const DELAY = 60;

	/**
	 * A lock older than this belonged to a worker that died mid-pass.
	 */
	const LOCK_TTL = 300;

	/**
	 * Keys deleted between progress saves. Small enough that a killed pass
	 * repeats little, large enough that the option isn't rewritten per key.
	 */
	const SAVE_EVERY = 100;

	// ------------------------------------------------------------------
	// Wiring
	// ------------------------------------------------------------------

	public static function init(): void {
		add_action( self::HOOK, [ self::class, 'run' ] );

		// Safety net for a queue whose scheduled run was lost — a cleared
		// cron table, a cancelled event, a worker killed before it could
		// reschedule. Cheap: one option read on admin page loads.
		if ( is_admin() && ! wp_doing_ajax() ) {
			add_action( 'admin_init', [ self::class, 'ensure_scheduled' ] );
		}
	}

	// ------------------------------------------------------------------
	// Queueing
	// ------------------------------------------------------------------

	/**
	 * Hand over a batch of remote keys to delete later.
	 *
	 * @param string   $driver 's3' or 'r2'.
	 * @param string[] $keys   Object keys, already including any path prefix.
	 */
	public static function enqueue( string $driver, array $keys ): void {
		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );

		if ( ! $keys || ! in_array( $driver, [ 's3', 'r2' ], true ) ) {
			return;
		}

		$queue = self::read();

		if ( isset( $queue[ $driver ] ) ) {
			$queue[ $driver ] = array_merge( $queue[ $driver ], $keys );
		} else {
			$queue[ $driver ] = $keys;
		}

		self::write( $queue );
		self::schedule();
	}

	/**
	 * How many objects are still waiting to be deleted.
	 */
	public static function pending(): int {
		$total = 0;

		foreach ( self::read() as $keys ) {
			$total += count( $keys );
		}

		return $total;
	}

	public static function schedule( int $delay = null ): void {
		if ( wp_next_scheduled( self::HOOK ) ) {
			return;
		}

		$delay = null === $delay
			? (int) apply_filters( 'bltgallery_storage_purge_delay', self::DELAY )
			: $delay;

		wp_schedule_single_event( time() + max( 0, $delay ), self::HOOK );
	}

	/**
	 * Re-arm a queue that has work but no run booked.
	 */
	public static function ensure_scheduled(): void {
		if ( self::pending() > 0 ) {
			self::schedule();
		}
	}

	// ------------------------------------------------------------------
	// Draining
	// ------------------------------------------------------------------

	/**
	 * Cron callback.
	 */
	public static function run(): void {
		self::process();
	}

	/**
	 * Delete as many queued objects as the time budget allows, then book
	 * another run if anything is left.
	 *
	 * @return array{deleted:int,remaining:int}
	 */
	public static function process( ?float $budget = null ): array {
		$out = [ 'deleted' => 0, 'remaining' => 0 ];

		$queue = self::read();

		if ( ! $queue ) {
			return $out;
		}

		if ( ! self::lock() ) {
			$out['remaining'] = self::pending();
			return $out;
		}

		if ( function_exists( 'set_time_limit' ) && ! str_contains( (string) ini_get( 'disable_functions' ), 'set_time_limit' ) ) {
			@set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		$max      = (int) ini_get( 'max_execution_time' );
		$budget   = $budget ?? (float) apply_filters(
			'bltgallery_storage_purge_budget',
			$max > 0 ? max( 5, min( 45, (int) floor( $max * 0.6 ) ) ) : 45
		);
		$deadline = microtime( true ) + $budget;

		try {
			$since_save = 0;

			foreach ( array_keys( $queue ) as $driver ) {
				$client = self::client( (string) $driver );

				if ( ! $client ) {
					// That backend is no longer configured, so its keys can
					// never be deleted from here. Drop them rather than
					// keeping a queue that can only ever grow.
					unset( $queue[ $driver ] );
					continue;
				}

				while ( ! empty( $queue[ $driver ] ) ) {
					if ( microtime( true ) >= $deadline ) {
						break 2;
					}

					$key = array_shift( $queue[ $driver ] );
					$client->delete( (string) $key );

					$out['deleted']++;
					$since_save++;

					if ( $since_save >= self::SAVE_EVERY ) {
						self::write( $queue );
						self::touch_lock();
						$since_save = 0;
					}
				}

				unset( $queue[ $driver ] );
			}
		} finally {
			self::write( $queue );
			self::unlock();
		}

		$out['remaining'] = self::pending();

		if ( $out['remaining'] > 0 ) {
			// Straight back in: the delay exists to batch up a burst of
			// deletes, not to slow down a drain already under way.
			self::schedule( 0 );
		}

		return $out;
	}

	// ------------------------------------------------------------------
	// Storage
	// ------------------------------------------------------------------

	/**
	 * @return array<string, string[]> driver => keys
	 */
	private static function read(): array {
		wp_cache_delete( self::OPTION, 'options' );

		$queue = get_option( self::OPTION, [] );

		if ( ! is_array( $queue ) ) {
			return [];
		}

		return array_filter( array_map( 'array_values', array_filter( $queue, 'is_array' ) ) );
	}

	/**
	 * @param array<string, string[]> $queue
	 */
	private static function write( array $queue ): void {
		$queue = array_filter( $queue );

		if ( ! $queue ) {
			delete_option( self::OPTION );
			return;
		}

		update_option( self::OPTION, $queue, false );
	}

	/**
	 * The client for a driver, or null when that backend is no longer set up.
	 *
	 * Filterable so another storage implementation can be drained by the same
	 * worker — anything exposing delete( string $key ) will do.
	 */
	private static function client( string $driver ): ?object {
		$client = match ( $driver ) {
			's3'    => S3Storage::is_configured() ? new S3Storage() : null,
			'r2'    => R2Storage::is_configured() ? new R2Storage() : null,
			default => null,
		};

		$client = apply_filters( 'bltgallery_storage_client', $client, $driver );

		return is_object( $client ) && method_exists( $client, 'delete' ) ? $client : null;
	}

	// ------------------------------------------------------------------
	// Locking — same add_option() trick the import worker uses.
	// ------------------------------------------------------------------

	private static function lock(): bool {
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

	private static function unlock(): void {
		delete_option( self::LOCK );
	}
}
