<?php

declare( strict_types=1 );

namespace BltGallery\Models;

/**
 * Value object representing a slider row.
 *
 * A slider is an ordered, hand-built list of slides assembled in the admin.
 * Each entry in {@see $items} is a lightweight descriptor pointing at a
 * source image rather than a copy of it:
 *
 *   [ 'source' => 'image',      'ref' => 44,  'caption' => 'optional override' ]
 *   [ 'source' => 'attachment', 'ref' => 123, 'caption' => '' ]
 *
 *   - source "image"      → a Blt gallery image ({prefix}blt_images.id)
 *   - source "attachment" → a WordPress media library attachment ID
 *
 * Resolution into renderable {@see Image} objects happens at display time via
 * SliderResolver, so a slider always reflects the current source images.
 */
class Slider {

	public int    $id         = 0;
	public string $title      = '';
	public string $slug       = '';
	public array  $settings   = [];
	public array  $items      = [];
	public string $created_at = '';
	public string $updated_at = '';
	public int    $author_id  = 0;

	// ------------------------------------------------------------------
	// Factory
	// ------------------------------------------------------------------

	public static function from_row( array $row ): self {
		$slider             = new self();
		$slider->id         = (int) ( $row['id'] ?? 0 );
		$slider->title      = $row['title'] ?? '';
		$slider->slug       = $row['slug'] ?? '';
		$slider->settings   = ! empty( $row['settings'] )
			? (array) json_decode( $row['settings'], true )
			: [];
		$slider->items      = ! empty( $row['items'] )
			? array_values( (array) json_decode( $row['items'], true ) )
			: [];
		$slider->created_at = $row['created_at'] ?? '';
		$slider->updated_at = $row['updated_at'] ?? '';
		$slider->author_id  = (int) ( $row['author_id'] ?? 0 );

		return $slider;
	}

	// ------------------------------------------------------------------
	// Serialisation
	// ------------------------------------------------------------------

	public function to_array(): array {
		return [
			'id'         => $this->id,
			'title'      => $this->title,
			'slug'       => $this->slug,
			'settings'   => (object) $this->settings,
			'items'      => $this->items,
			'item_count' => count( $this->items ),
			'created_at' => $this->created_at,
			'updated_at' => $this->updated_at,
			'author_id'  => $this->author_id,
		];
	}
}
