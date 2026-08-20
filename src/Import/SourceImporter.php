<?php

declare( strict_types=1 );

namespace BltGallery\Import;

use BltGallery\Core\ImageProcessor;
use BltGallery\Models\Gallery;

/**
 * Contract shared by every migration source (NextGEN, Modula, …).
 *
 * The methods below are deliberately split so a migration can be driven
 * incrementally by ImportRunner: the queue is planned up-front with
 * plan_galleries(), each destination gallery is created once with
 * create_target_gallery(), and its images are then copied a slice at a time
 * with import_slice(). Because every step is addressable by
 * (source id, offset), a background worker can stop after any slice and pick
 * up exactly where it left off on the next run.
 */
interface SourceImporter {

	/**
	 * Machine name of the source, e.g. 'nextgen'. Used in REST routes,
	 * option keys, and cron arguments.
	 */
	public function source_key(): string;

	/**
	 * Human-readable source name, e.g. 'NextGEN Gallery'.
	 */
	public function source_label(): string;

	/**
	 * True when this source's data is present on the site.
	 */
	public function is_available(): bool;

	/**
	 * Message shown when is_available() is false.
	 */
	public function unavailable_message(): string;

	/**
	 * Summary of every gallery in the source, for the preview table.
	 *
	 * @return array[] Source-specific rows that always include `image_count`.
	 */
	public function get_galleries(): array;

	/**
	 * Build the work queue for a migration.
	 *
	 * @param int[]|null $gallery_ids Source gallery IDs to migrate, or null for all.
	 * @return array<int, array{source_id:int,title:string,total:int}>
	 */
	public function plan_galleries( ?array $gallery_ids = null ): array;

	/**
	 * Create the BltGallery gallery that a source gallery's images land in.
	 *
	 * @throws \RuntimeException When the source gallery no longer exists.
	 */
	public function create_target_gallery( int $source_id ): Gallery;

	/**
	 * Copy a contiguous slice of one source gallery's images into $target.
	 *
	 * Implementations must honour $offset/$limit against the same stable
	 * ordering used by plan_galleries() so slices neither overlap nor skip.
	 *
	 * @return array{processed:int,imported:int,skipped:int,errors:string[]}
	 */
	public function import_slice( int $source_id, Gallery $target, int $offset, int $limit, ImageProcessor $processor ): array;
}
