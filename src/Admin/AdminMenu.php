<?php

declare( strict_types=1 );

namespace BltGallery\Admin;

/**
 * Registers the BltGallery admin menu and renders pure-PHP views.
 * No build step required.
 */
class AdminMenu {

	const MENU_SLUG = 'bltgallery';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_pages(): void {
		add_menu_page(
			__( 'Blt Gallery', 'bltgallery' ),
			__( 'Blt Gallery', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_galleries_page' ],
			$this->get_menu_icon(),
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Galleries', 'bltgallery' ),
			__( 'Galleries', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_galleries_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Albums', 'bltgallery' ),
			__( 'Albums', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-albums',
			[ $this, 'render_albums_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'bltgallery' ),
			__( 'Settings', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			[ $this, 'render_settings_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Shortcodes', 'bltgallery' ),
			__( 'Shortcodes', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-shortcodes',
			[ $this, 'render_shortcodes_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Migrate', 'bltgallery' ),
			__( 'Migrate', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-migrate',
			[ $this, 'render_import_page' ]
		);

		// Backwards-compat: keep the old ?page=…-import URL working for
		// anyone who has it bookmarked.
		add_submenu_page(
			null,
			__( 'Migrate', 'bltgallery' ),
			__( 'Migrate', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-import',
			[ $this, 'render_import_page' ]
		);
	}

	public function render_albums_page(): void {
		?>
		<div class="wrap bltgallery-wrap">
			<h1><?php esc_html_e( 'Albums', 'bltgallery' ); ?></h1>
			<p>
				<?php esc_html_e(
					'Albums group galleries the same way categories group posts. Galleries can belong to multiple albums; albums can then be rendered with',
					'bltgallery'
				); ?>
				<code>[blt_album category="album-slug"]</code>.
			</p>
			<div id="bltgallery-notice"></div>
			<div id="bltgallery-albums-admin">
				<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
			</div>
		</div>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			if ( window.BltGalleryAdmin && BltGalleryAdmin.initAlbumsPage ) {
				BltGalleryAdmin.initAlbumsPage();
			}
		} );
		</script>
		<?php
	}

	public function render_shortcodes_page(): void {
		$docs = $this->shortcode_docs();
		?>
		<div class="wrap bltgallery-wrap">
			<h1><?php esc_html_e( 'Shortcodes', 'bltgallery' ); ?></h1>
			<p>
				<?php esc_html_e(
					'Drop these shortcodes into any post, page, or widget to display galleries. Every shortcode attribute below overrides the corresponding gallery setting for that single placement.',
					'bltgallery'
				); ?>
			</p>

			<div id="bltgallery-shortcodes-doc">
				<?php foreach ( $docs as $sc ) : ?>
					<div class="bltgallery-panel bltgallery-shortcode-doc">
						<div class="bltgallery-panel__header">
							<h2><code>[<?php echo esc_html( $sc['tag'] ); ?>]</code> — <?php echo esc_html( $sc['title'] ); ?></h2>
						</div>
						<div class="bltgallery-panel__body">
							<p><?php echo esc_html( $sc['intro'] ); ?></p>

							<h3><?php esc_html_e( 'Examples', 'bltgallery' ); ?></h3>
							<div class="bltgallery-shortcode-doc__examples">
								<?php foreach ( $sc['examples'] as $ex ) : ?>
									<div class="bltgallery-shortcode-doc__example">
										<code><?php echo esc_html( $ex ); ?></code>
										<button type="button" class="button button-secondary bltgallery-copy" data-copy="<?php echo esc_attr( $ex ); ?>">
											<?php esc_html_e( 'Copy', 'bltgallery' ); ?>
										</button>
									</div>
								<?php endforeach; ?>
							</div>

							<h3><?php esc_html_e( 'Attributes', 'bltgallery' ); ?></h3>
							<table class="wp-list-table widefat fixed striped bltgallery-table bltgallery-shortcode-doc__table">
								<thead>
									<tr>
										<th style="width:18%"><?php esc_html_e( 'Attribute', 'bltgallery' ); ?></th>
										<th style="width:30%"><?php esc_html_e( 'Values', 'bltgallery' ); ?></th>
										<th><?php esc_html_e( 'Description', 'bltgallery' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $sc['attrs'] as [ $a, $v, $d ] ) : ?>
										<tr>
											<td><code><?php echo esc_html( $a ); ?></code></td>
											<td>
												<?php
												$tokens = array_map( 'trim', explode( '·', (string) $v ) );
												echo implode(
													' · ',
													array_map( static fn( $t ) => '<code>' . esc_html( $t ) . '</code>', $tokens )
												);
												?>
											</td>
											<td><?php echo esc_html( $d ); ?></td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<script>
		document.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.bltgallery-copy' );
			if ( ! btn || ! navigator.clipboard ) return;
			navigator.clipboard.writeText( btn.dataset.copy ).then( function () {
				var label = btn.textContent;
				btn.textContent = 'Copied!';
				setTimeout( function () { btn.textContent = label; }, 1500 );
			} );
		} );
		</script>
		<?php
	}

	/**
	 * Source of truth for the Shortcodes reference page. Rendered both
	 * server-side (PHP, so it works without admin.js loading) and
	 * mirrored in admin.js for live-rendering scenarios.
	 */
	private function shortcode_docs(): array {
		return [
			[
				'tag'      => 'blt_gallery',
				'title'    => __( 'Single gallery', 'bltgallery' ),
				'intro'    => __( 'Renders one gallery. Every attribute below temporarily overrides the matching gallery setting for this placement.', 'bltgallery' ),
				'examples' => [
					'[blt_gallery id="5"]',
					'[blt_gallery slug="weddings-2026" type="masonry" cols="4" gap="16"]',
					'[blt_gallery id="5" type="slideshow" autoplay="1" speed="4000"]',
					'[blt_gallery id="5" type="tile" pagination="load-more" per_page="24"]',
					'[blt_gallery id="5" captions="hover" radius="12" lightbox="1"]',
					'[blt_gallery id="5" date="2026-05-20"]',
				],
				'attrs'    => [
					[ 'id',         'int',                                              __( 'Gallery ID.', 'bltgallery' ) ],
					[ 'slug',       'string',                                           __( 'Gallery slug — used when `id` is omitted.', 'bltgallery' ) ],
					[ 'type',       'masonry · tile · slideshow · lightbox',            __( 'Override the stored display type.', 'bltgallery' ) ],
					[ 'cols',       '1–8',                                              __( 'Target column count at desktop width.', 'bltgallery' ) ],
					[ 'gap',        'px',                                               __( 'Gutter between items.', 'bltgallery' ) ],
					[ 'radius',     'px',                                               __( 'Per-item border radius.', 'bltgallery' ) ],
					[ 'size',       'small · medium · large · xlarge',                  __( 'Preset minimum tile width.', 'bltgallery' ) ],
					[ 'thumb_min',  'px',                                               __( 'Raw minimum tile width (advanced override).', 'bltgallery' ) ],
					[ 'captions',   'below · hover · off',                              __( 'Caption position.', 'bltgallery' ) ],
					[ 'lightbox',   '1 · 0',                                            __( 'Enable click-to-lightbox on grids.', 'bltgallery' ) ],
					[ 'pagination', 'off · load-more · numbered · infinite',            __( 'AJAX pagination mode.', 'bltgallery' ) ],
					[ 'per_page',   'int',                                              __( 'Images per page when pagination is on.', 'bltgallery' ) ],
					[ 'date',       'YYYY-MM-DD',                                       __( 'Override the gallery’s display date.', 'bltgallery' ) ],
					[ 'autoplay',   '1 · 0',                                            __( 'Slideshow autoplay.', 'bltgallery' ) ],
					[ 'speed',      'ms',                                               __( 'Slideshow autoplay interval.', 'bltgallery' ) ],
					[ 'arrows',     '1 · 0',                                            __( 'Show slideshow nav arrows.', 'bltgallery' ) ],
					[ 'dots',       '1 · 0',                                            __( 'Show slideshow dot indicators.', 'bltgallery' ) ],
					[ 'limit',      'int',                                              __( 'Cap the number of images rendered.', 'bltgallery' ) ],
					[ 'order',      'menu · date · random',                             __( 'Image sort order.', 'bltgallery' ) ],
					[ 'class',      'string',                                           __( 'Extra CSS class on the wrapper.', 'bltgallery' ) ],
					[ 'style',      'string',                                           __( 'Extra inline style on the wrapper.', 'bltgallery' ) ],
				],
			],
			[
				'tag'      => 'blt_album',
				'title'    => __( 'Album (collection of galleries)', 'bltgallery' ),
				'intro'    => __( 'Renders a group of galleries as clickable cards. Albums behave like a category — galleries that share an Album/Category value show up together, and you can sort by date or name.', 'bltgallery' ),
				'examples' => [
					'[blt_album category="weddings" sort_by="date"]',
					'[blt_album ids="3,7,9" style="grid" cols="3" gap="20"]',
					'[blt_album slugs="nature,travel" style="masonry" cols="4"]',
					'[blt_album category="portfolio" style="carousel" cols="4"]',
					'[blt_album category="portfolio" style="accordion" gallery_type="masonry"]',
					'[blt_album category="portfolio" sort_by="name" order="asc"]',
				],
				'attrs'    => [
					[ 'ids',          'comma-separated ints',                            __( 'Explicit gallery IDs to include.', 'bltgallery' ) ],
					[ 'slugs',        'comma-separated slugs',                           __( 'Alternative to `ids`.', 'bltgallery' ) ],
					[ 'category',     'string',                                          __( 'Pull every gallery whose Album/Category matches.', 'bltgallery' ) ],
					[ 'style',        'grid · masonry · carousel · accordion',           __( 'Album layout.', 'bltgallery' ) ],
					[ 'cols',         '1–8',                                             __( 'Card grid column count.', 'bltgallery' ) ],
					[ 'gap',          'px',                                              __( 'Space between cards.', 'bltgallery' ) ],
					[ 'radius',       'px',                                              __( 'Card border radius.', 'bltgallery' ) ],
					[ 'captions',     'below · hover · off',                             __( 'Title placement on each card.', 'bltgallery' ) ],
					[ 'show_count',   '1 · 0',                                           __( 'Render "N photos" under each card.', 'bltgallery' ) ],
					[ 'cover',        'first · random',                                  __( 'Which image becomes the card cover.', 'bltgallery' ) ],
					[ 'sort_by',      'menu · date · name · random',                     __( 'How to sort galleries within the album.', 'bltgallery' ) ],
					[ 'order',        'asc · desc',                                      __( 'Sort direction.', 'bltgallery' ) ],
					[ 'gallery_type', 'see [blt_gallery] type',                          __( 'Inline display type used in accordion mode.', 'bltgallery' ) ],
					[ 'limit',        'int',                                             __( 'Cap number of galleries rendered.', 'bltgallery' ) ],
				],
			],
		];
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'bltgallery-admin',
			BLT_GALLERY_PLUGIN_URL . 'assets/admin/admin.css',
			[],
			BLT_GALLERY_VERSION
		);

		wp_enqueue_script(
			'bltgallery-admin',
			BLT_GALLERY_PLUGIN_URL . 'assets/admin/admin.js',
			[],
			BLT_GALLERY_VERSION,
			true
		);

		wp_localize_script(
			'bltgallery-admin',
			'bltGalleryConfig',
			[
				'apiBase'  => rest_url( 'bltgallery/v1' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => BLT_GALLERY_PLUGIN_URL,
				'adminUrl' => admin_url( 'admin.php?page=' . self::MENU_SLUG ),
			]
		);
	}

	// ------------------------------------------------------------------
	// Page renderers
	// ------------------------------------------------------------------

	public function render_galleries_page(): void {
		$action     = sanitize_key( $_GET['action'] ?? 'list' );
		$gallery_id = isset( $_GET['gallery_id'] ) ? (int) $_GET['gallery_id'] : 0;

		if ( 'edit' === $action && $gallery_id > 0 ) {
			$this->render_gallery_editor( $gallery_id );
		} else {
			$this->render_gallery_list();
		}
	}

	public function render_settings_page(): void {
		?>
		<div class="wrap bltgallery-wrap">
			<h1><?php esc_html_e( 'Blt Gallery Settings', 'bltgallery' ); ?></h1>
			<div id="bltgallery-notice"></div>

			<!-- General Settings -->
			<div class="bltgallery-panel">
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'General', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-general-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<?php
			$general    = get_option( 'bltgallery_settings', [] );
			$enable_s3  = ! empty( $general['enable_s3'] );
			$enable_r2  = ! empty( $general['enable_r2'] );
			$enable_cfi = ! empty( $general['enable_cf_images'] );
			?>

			<!-- AWS S3 & CloudFront Settings (hidden until enabled in General) -->
			<div class="bltgallery-panel"<?php echo $enable_s3 ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Amazon S3 & CloudFront', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-aws-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare R2 Settings (hidden until enabled in General) -->
			<div class="bltgallery-panel"<?php echo $enable_r2 ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Cloudflare R2', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-r2-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare Image Resizing (hidden until enabled in General) -->
			<div class="bltgallery-panel"<?php echo $enable_cfi ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Cloudflare Image Resizing', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-cf-images-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_import_page(): void {
		?>
		<div class="wrap bltgallery-wrap">
			<h1><?php esc_html_e( 'Migrate Galleries', 'bltgallery' ); ?></h1>
			<div id="bltgallery-notice"></div>

			<!-- NextGEN Gallery Migration -->
			<div class="bltgallery-panel">
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Migrate from Imagely NextGEN Gallery', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-nextgen-importer">
					<p class="bltgallery-loading"><?php esc_html_e( 'Checking for NextGEN Gallery…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Post-migration cleanup: backup + delete legacy NextGEN files -->
			<div class="bltgallery-panel" id="bltgallery-nextgen-cleanup-panel" hidden>
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Clean up NextGEN Gallery files', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-nextgen-cleanup">
					<p class="bltgallery-loading"><?php esc_html_e( 'Scanning…', 'bltgallery' ); ?></p>
				</div>
			</div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			BltGalleryAdmin.initImporter();
		} );
		</script>
		<?php
	}

	// ------------------------------------------------------------------
	// Private view helpers
	// ------------------------------------------------------------------

	private function render_gallery_list(): void {
		$list_url   = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="wrap bltgallery-wrap">
			<div class="bltgallery-page-header">
				<h1><?php esc_html_e( 'Galleries', 'bltgallery' ); ?></h1>
				<button class="button button-primary" id="bltgallery-new-gallery-btn">
					<?php esc_html_e( '+ New Gallery', 'bltgallery' ); ?>
				</button>
			</div>
			<div id="bltgallery-notice"></div>

			<div id="bltgallery-gallery-list">
				<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			BltGalleryAdmin.initGalleryList(
				<?php echo wp_json_encode( $list_url ); ?>
			);
		});
		</script>
		<?php
	}

	private function render_gallery_editor( int $gallery_id ): void {
		$back_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="wrap bltgallery-wrap">
			<div class="bltgallery-page-header">
				<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Galleries', 'bltgallery' ); ?>
				</a>
				<h1 id="bltgallery-editor-title"><?php esc_html_e( 'Edit Gallery', 'bltgallery' ); ?></h1>
				<code id="bltgallery-shortcode"></code>
			</div>
			<div id="bltgallery-notice"></div>

			<div class="bltgallery-editor-layout">
				<div class="bltgallery-editor-layout__main">
					<!-- Settings panel -->
					<div class="bltgallery-panel">
						<div class="bltgallery-panel__header">
							<h2><?php esc_html_e( 'Gallery Settings', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body" id="bltgallery-editor-settings">
							<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
						</div>
					</div>

					<!-- Images panel -->
					<div class="bltgallery-panel">
						<div class="bltgallery-panel__header">
							<h2><?php esc_html_e( 'Images', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body">
							<!-- Uploader -->
							<div class="bltgallery-uploader" id="bltgallery-uploader">
								<input type="file" id="bltgallery-file-input" accept="image/*" multiple style="display:none">
								<div class="bltgallery-uploader__zone" id="bltgallery-drop-zone" tabindex="0" role="button"
									aria-label="<?php esc_attr_e( 'Drop images here or click to upload', 'bltgallery' ); ?>">
									<span class="bltgallery-uploader__icon" aria-hidden="true">&#128247;</span>
									<p><?php esc_html_e( 'Drag & drop images here, or', 'bltgallery' ); ?> <strong><?php esc_html_e( 'click to browse', 'bltgallery' ); ?></strong></p>
									<p class="bltgallery-uploader__hint"><?php esc_html_e( 'JPEG, PNG, GIF, WebP, AVIF · Max 50 MB each', 'bltgallery' ); ?></p>
								</div>
								<ul class="bltgallery-uploader__progress-list" id="bltgallery-progress-list"></ul>
							</div>
							<!-- Image grid -->
							<div id="bltgallery-image-grid">
								<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<aside class="bltgallery-editor-layout__sidebar">
					<!-- Albums (taxonomy) metabox -->
					<div class="bltgallery-panel">
						<div class="bltgallery-panel__header">
							<h2><?php esc_html_e( 'Albums', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body" id="bltgallery-albums-metabox">
							<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
						</div>
					</div>
				</aside>
			</div>
		</div>

		<!-- Image editor modal (opens when the user clicks "Edit" on a tile) -->
		<dialog id="bltgallery-image-modal" class="bltgallery-modal">
			<form method="dialog" class="bltgallery-modal__form" id="bltgallery-image-form">
				<header class="bltgallery-modal__header">
					<h2><?php esc_html_e( 'Edit image', 'bltgallery' ); ?></h2>
					<button type="button" class="bltgallery-modal__close" aria-label="<?php esc_attr_e( 'Close', 'bltgallery' ); ?>" data-close>&times;</button>
				</header>
				<div class="bltgallery-modal__body">
					<figure class="bltgallery-modal__preview">
						<img id="bltgallery-image-modal-thumb" src="" alt="">
					</figure>
					<div class="bltgallery-modal__fields">
						<label>
							<span><?php esc_html_e( 'Title', 'bltgallery' ); ?></span>
							<input type="text" name="title" id="bltgallery-image-modal-title" autocomplete="off">
						</label>
						<label>
							<span><?php esc_html_e( 'Alt text', 'bltgallery' ); ?></span>
							<input type="text" name="alt_text" id="bltgallery-image-modal-alt" autocomplete="off">
							<small><?php esc_html_e( 'Used by screen readers and as fallback when the image fails to load.', 'bltgallery' ); ?></small>
						</label>
						<label>
							<span><?php esc_html_e( 'Caption', 'bltgallery' ); ?></span>
							<textarea name="caption" id="bltgallery-image-modal-caption" rows="3"></textarea>
							<small><?php esc_html_e( 'Shown beneath the image in the lightbox and on hover in grids.', 'bltgallery' ); ?></small>
						</label>
					</div>
				</div>
				<footer class="bltgallery-modal__footer">
					<button type="button" class="button button-secondary" data-close><?php esc_html_e( 'Cancel', 'bltgallery' ); ?></button>
					<button type="submit" class="button button-primary" id="bltgallery-image-modal-save"><?php esc_html_e( 'Save changes', 'bltgallery' ); ?></button>
				</footer>
			</form>
		</dialog>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			BltGalleryAdmin.initGalleryEditor(<?php echo (int) $gallery_id; ?>);
		});
		</script>
		<?php
	}

	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
