<?php

declare( strict_types=1 );

namespace BltGallery\Models;

/**
 * Value object representing a gallery row.
 */
class Gallery {

	public int    $id          = 0;
	public string $title       = '';
	public string $slug        = '';
	public string $description = '';
	public string $display_type = 'masonry';
	public array  $settings    = [];
	public string $created_at  = '';
	public string $updated_at  = '';
	public int    $author_id   = 0;

	// ------------------------------------------------------------------
	// Factory
	// ------------------------------------------------------------------

	public static function from_row( array $row ): self {
		$g               = new self();
		$g->id           = (int) ( $row['id'] ?? 0 );
		$g->title        = $row['title'] ?? '';
		$g->slug         = $row['slug'] ?? '';
		$g->description  = $row['description'] ?? '';
		$g->display_type = $row['display_type'] ?? 'masonry';
		$g->settings     = ! empty( $row['settings'] )
			? (array) json_decode( $row['settings'], true )
			: [];
		$g->created_at   = $row['created_at'] ?? '';
		$g->updated_at   = $row['updated_at'] ?? '';
		$g->author_id    = (int) ( $row['author_id'] ?? 0 );

		return $g;
	}

	// ------------------------------------------------------------------
	// Serialisation
	// ------------------------------------------------------------------

	public function to_array(): array {
		return [
			'id'           => $this->id,
			'title'        => $this->title,
			'slug'         => $this->slug,
			'description'  => $this->description,
			'display_type' => $this->display_type,
			'settings'     => $this->settings,
			'created_at'   => $this->created_at,
			'updated_at'   => $this->updated_at,
			'author_id'    => $this->author_id,
		];
	}
}
