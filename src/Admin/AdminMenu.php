<?php

declare( strict_types=1 );

namespace BltGallery\Admin;

use BltGallery\Core\Updater;

/**
 * Registers the BLT Gallery admin menu and renders pure-PHP views.
 * No build step required.
 */
class AdminMenu {

	const MENU_SLUG = 'bltgallery';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_head', [ $this, 'print_menu_icon_style' ] );
	}

	public function register_pages(): void {
		add_menu_page(
			__( 'BLT Gallery', 'bltgallery' ),
			__( 'BLT Gallery', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_galleries_page' ],
			// Second argument keeps this screen's historical dashicon as the
			// degraded fallback if the bundled mark ever goes missing.
			\BLT_Family_Brand::menu_icon( BLT_GALLERY_PLUGIN_DIR, 'dashicons-format-gallery' ),
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
			__( 'Sliders', 'bltgallery' ),
			__( 'Sliders', 'bltgallery' ),
			'manage_options',
			self::MENU_SLUG . '-sliders',
			[ $this, 'render_sliders_page' ]
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
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'Albums', 'bltgallery' ); ?>
				</h1>
				<button type="button" class="button button-primary" id="bltgallery-add-album">
					<?php esc_html_e( 'Add album', 'bltgallery' ); ?>
				</button>
			</div>
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

	// ------------------------------------------------------------------
	// Sliders (builder)
	// ------------------------------------------------------------------

	public function render_sliders_page(): void {
		$action    = sanitize_key( $_GET['action'] ?? 'list' );
		$slider_id = isset( $_GET['slider_id'] ) ? (int) $_GET['slider_id'] : 0;

		if ( 'edit' === $action && $slider_id > 0 ) {
			$this->render_slider_editor( $slider_id );
		} else {
			$this->render_slider_list();
		}
	}

	private function render_slider_list(): void {
		$list_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '-sliders' );
		?>
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'Sliders', 'bltgallery' ); ?>
				</h1>
				<button class="button button-primary" id="bltgallery-new-slider-btn">
					<?php esc_html_e( '+ New Slider', 'bltgallery' ); ?>
				</button>
			</div>
			<p>
				<?php esc_html_e(
					'Build an image slider from your galleries and the media library, then drop it anywhere with its shortcode. Every image is served through your Cloudflare optimisation pipeline.',
					'bltgallery'
				); ?>
			</p>
			<div id="bltgallery-notice"></div>

			<div id="bltgallery-slider-list">
				<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
			</div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			BltGalleryAdmin.initSlidersPage( <?php echo wp_json_encode( $list_url ); ?> );
		} );
		</script>
		<?php
	}

	private function render_slider_editor( int $slider_id ): void {
		$back_url = admin_url( 'admin.php?page=' . self::MENU_SLUG . '-sliders' );
		?>
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Sliders', 'bltgallery' ); ?>
				</a>
				<h1 id="bltgallery-slider-editor-title"><?php esc_html_e( 'Edit Slider', 'bltgallery' ); ?></h1>
				<code id="bltgallery-slider-shortcode"></code>
			</div>
			<div id="bltgallery-notice"></div>

			<div class="bltgallery-editor-layout">
				<div class="bltgallery-editor-layout__main">
					<!-- Slides panel -->
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header bltgallery-panel__header--actions blt-card-header">
							<h2><?php esc_html_e( 'Slides', 'bltgallery' ); ?></h2>
							<div class="bltgallery-slider-add">
								<button type="button" class="button button-primary" id="bltgallery-add-media">
									<?php esc_html_e( '+ Add from media library', 'bltgallery' ); ?>
								</button>
								<button type="button" class="button button-secondary" id="bltgallery-add-gallery">
									<?php esc_html_e( '+ Add from gallery', 'bltgallery' ); ?>
								</button>
							</div>
						</div>
						<div class="bltgallery-panel__body blt-card-body">
							<div id="bltgallery-slider-slides">
								<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Live preview panel -->
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header bltgallery-panel__header--actions blt-card-header">
							<h2><?php esc_html_e( 'Preview', 'bltgallery' ); ?></h2>
							<button type="button" class="button button-secondary" id="bltgallery-slider-refresh-preview">
								<?php esc_html_e( 'Save & refresh preview', 'bltgallery' ); ?>
							</button>
						</div>
						<div class="bltgallery-panel__body blt-card-body">
							<div id="bltgallery-slider-preview" class="bltgallery-slider-preview">
								<p class="bltgallery-muted"><?php esc_html_e( 'Save the slider to see a live preview here.', 'bltgallery' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<aside class="bltgallery-editor-layout__sidebar">
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header blt-card-header">
							<h2><?php esc_html_e( 'Slider Settings', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body blt-card-body" id="bltgallery-slider-settings">
							<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
						</div>
					</div>
				</aside>
			</div>
		</div>

		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			BltGalleryAdmin.initSliderEditor( <?php echo (int) $slider_id; ?> );
		} );
		</script>
		<?php
	}

	public function render_shortcodes_page(): void {
		$docs = $this->shortcode_docs();
		?>
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'Shortcodes', 'bltgallery' ); ?>
				</h1>
			</div>
			<p>
				<?php esc_html_e(
					'Drop these shortcodes into any post, page, or widget to display galleries. Every shortcode attribute below overrides the corresponding gallery setting for that single placement.',
					'bltgallery'
				); ?>
			</p>

			<div id="bltgallery-shortcodes-doc">
				<?php foreach ( $docs as $sc ) : ?>
					<div class="bltgallery-panel blt-card bltgallery-shortcode-doc">
						<div class="bltgallery-panel__header blt-card-header">
							<h2><code>[<?php echo esc_html( $sc['tag'] ); ?>]</code> — <?php echo esc_html( $sc['title'] ); ?></h2>
						</div>
						<div class="bltgallery-panel__body blt-card-body">
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
			[
				'tag'      => 'blt_slider',
				'title'    => __( 'Image slider', 'bltgallery' ),
				'intro'    => __( 'Renders an image slider built in BLT Gallery → Sliders. Build it visually — adding images from the media library and/or your galleries — then paste its shortcode. Captions, hover arrows, and a dot counter are built in. An ad-hoc source path is also supported for code-only sliders.', 'bltgallery' ),
				'examples' => [
					'[blt_slider id="3"]',
					'[blt_slider slug="homepage-hero"]',
					'[blt_slider id="3" autoplay="1" speed="6000" height="60vh"]',
					'[blt_slider galleries="5,7"]',
					'[blt_slider attachments="123,456" arrows="0" captions="off"]',
				],
				'attrs'    => [
					[ 'id',          'int',                                             __( 'Saved slider ID (primary — built in BLT Gallery → Sliders).', 'bltgallery' ) ],
					[ 'slug',        'string',                                          __( 'Saved slider slug (alternative to id).', 'bltgallery' ) ],
					[ 'galleries',   'comma-separated ints',                            __( 'Ad-hoc: gallery IDs whose images feed the slider.', 'bltgallery' ) ],
					[ 'slugs',       'comma-separated slugs',                           __( 'Ad-hoc: galleries by slug.', 'bltgallery' ) ],
					[ 'images',      'comma-separated ints',                            __( 'Ad-hoc: specific BLT gallery image IDs.', 'bltgallery' ) ],
					[ 'attachments', 'comma-separated ints',                            __( 'Ad-hoc: WordPress media library attachment IDs.', 'bltgallery' ) ],
					[ 'title',       'string',                                          __( 'Accessible label for the carousel.', 'bltgallery' ) ],
					[ 'captions',    'on · off',                                        __( 'Show the subtle image caption / photo credit.', 'bltgallery' ) ],
					[ 'arrows',      '1 · 0',                                           __( 'Show the hover-reveal nav arrows.', 'bltgallery' ) ],
					[ 'dots',        '1 · 0',                                           __( 'Show the dot counter.', 'bltgallery' ) ],
					[ 'dot_color',   'primary · secondary · tertiary · accent · custom', __( 'Color the dot indicators — aligns with the ACSS primary/secondary/tertiary/accent palette when present.', 'bltgallery' ) ],
					[ 'dot_color_custom', 'hex',                                         __( 'Hex color used when dot_color is "custom", e.g. #ff5a1f.', 'bltgallery' ) ],
					[ 'autoplay',    '1 · 0',                                           __( 'Auto-advance slides.', 'bltgallery' ) ],
					[ 'speed',       'ms',                                              __( 'Autoplay interval (default 5000).', 'bltgallery' ) ],
					[ 'loop',        '1 · 0',                                           __( 'Wrap from the last slide back to the first.', 'bltgallery' ) ],
					[ 'height',      'px · vh · %',                                     __( 'Max height of each slide, e.g. 70vh.', 'bltgallery' ) ],
					[ 'radius',      'px',                                              __( 'Slider border radius.', 'bltgallery' ) ],
					[ 'arrow_position', 'sides · below',                                __( 'Hover-reveal at the edges, or a static row below the slider.', 'bltgallery' ) ],
					[ 'image_size',  'medium · large',                                  __( 'Which pre-generated thumbnail size to use.', 'bltgallery' ) ],
					[ 'image_fit',   'contain · cover',                                 __( 'Letterbox to fit (no cropping), or crop to fill the height.', 'bltgallery' ) ],
					[ 'order',       'menu · random · reverse',                         __( 'Slide order.', 'bltgallery' ) ],
					[ 'limit',       'int',                                             __( 'Cap the number of slides rendered.', 'bltgallery' ) ],
					[ 'class',       'string',                                          __( 'Extra CSS class on the wrapper.', 'bltgallery' ) ],
					[ 'style',       'string',                                          __( 'Extra inline style on the wrapper.', 'bltgallery' ) ],
				],
			],
		];
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		$current_page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		/*
		 * Shared BLT admin design system (DESIGN.md §2), on this plugin's own
		 * screens only. Deliberately enqueued FIRST — before frontend.css below
		 * and before admin.css — for two different reasons:
		 *
		 *   - admin.css is page-specific and should still win where the two
		 *     overlap;
		 *   - frontend.css declares `--blt-radius` on :root (6px) and so does
		 *     the design system (8px). On the Sliders screen both sheets load,
		 *     and at equal specificity the later one wins. The live preview's
		 *     whole job is to look like the published page, so the front-end
		 *     sheet has to be the one that wins there. The cost is that the
		 *     admin cards on that single screen round at 6px rather than the
		 *     family's 8px.
		 *
		 * The real fix is to stop the two namespaces colliding, but `--blt-radius`
		 * is not internal to frontend.css: AbstractDisplay and AlbumDisplay emit
		 * it as an inline custom property on the front-end wrapper, driven by
		 * each gallery's own `radius` setting, so renaming it is a front-end API
		 * change and belongs in its own pass.
		 */
		wp_enqueue_style(
			'blt-gallery-design-system',
			BLT_GALLERY_PLUGIN_URL . 'assets/css/blt-design-system.css',
			[],
			BLT_GALLERY_VERSION
		);

		// The slider builder lets editors add images straight from the media
		// library, so it needs the WordPress media modal scripts. It also loads
		// the front-end bundle so the live preview renders + behaves exactly as
		// it will on the site.
		if ( self::MENU_SLUG . '-sliders' === $current_page ) {
			wp_enqueue_media();
			wp_enqueue_style(
				'bltgallery-frontend',
				BLT_GALLERY_PLUGIN_URL . 'assets/frontend/frontend.css',
				[],
				BLT_GALLERY_VERSION
			);
			wp_enqueue_script(
				'bltgallery-frontend',
				BLT_GALLERY_PLUGIN_URL . 'assets/frontend/frontend.js',
				[],
				BLT_GALLERY_VERSION,
				true
			);
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
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'BLT Gallery Settings', 'bltgallery' ); ?>
				</h1>
			</div>
			<div id="bltgallery-notice"></div>

			<!-- General Settings -->
			<div class="bltgallery-panel blt-card">
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'General', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-general-settings">
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
			<div class="bltgallery-panel blt-card"<?php echo $enable_s3 ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Amazon S3 & CloudFront', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-aws-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare R2 Settings (hidden until enabled in General) -->
			<div class="bltgallery-panel blt-card"<?php echo $enable_r2 ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Cloudflare R2', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-r2-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare Image Resizing (hidden until enabled in General) -->
			<div class="bltgallery-panel blt-card"<?php echo $enable_cfi ? '' : ' hidden'; ?>>
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Cloudflare Image Resizing', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-cf-images-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<?php
			// The automatic check runs once a day, at midnight site time
			// (BLT_Family_Updates). This link is the explicit path: it hits
			// plugin-update-checker's own nonced handler, which ignores the
			// daily floor and reports the result on the Plugins screen.
			$updates_checker    = Updater::checker();
			$updates_last_check = $updates_checker ? \BLT_Family_Updates::last_check_time( $updates_checker ) : 0;
			?>
			<!-- Plugin Updates -->
			<div class="bltgallery-panel blt-card">
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Plugin Updates', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-updates-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
				<div class="bltgallery-panel__body blt-card-body">
					<p>
						<a class="button button-secondary" href="<?php echo esc_url( \BLT_Family_Updates::check_now_url( Updater::checker_slug() ) ); ?>">
							<?php esc_html_e( 'Check for Updates', 'bltgallery' ); ?>
						</a>
					</p>
					<p class="description">
						<?php esc_html_e( 'BLT Gallery checks GitHub for a new release once a day, at midnight site time. Checking here asks GitHub straight away.', 'bltgallery' ); ?>
						<?php
						if ( $updates_last_check ) {
							printf(
								/* translators: %s: human-readable time difference, e.g. "3 hours". */
								esc_html__( 'Last checked %s ago.', 'bltgallery' ),
								esc_html( human_time_diff( $updates_last_check ) )
							);
						}
						?>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_import_page(): void {
		?>
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'Migrate Galleries', 'bltgallery' ); ?>
				</h1>
			</div>
			<div id="bltgallery-notice"></div>

			<!-- NextGEN Gallery Migration -->
			<div class="bltgallery-panel blt-card">
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Migrate from Imagely NextGEN Gallery', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-nextgen-importer">
					<p class="bltgallery-loading"><?php esc_html_e( 'Checking for NextGEN Gallery…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Modula Gallery Migration -->
			<div class="bltgallery-panel blt-card">
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Migrate from Modula', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-modula-importer">
					<p class="bltgallery-loading"><?php esc_html_e( 'Checking for Modula galleries…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Post-migration cleanup: backup + delete legacy NextGEN files -->
			<div class="bltgallery-panel blt-card" id="bltgallery-nextgen-cleanup-panel" hidden>
				<div class="bltgallery-panel__header blt-card-header">
					<h2><?php esc_html_e( 'Clean up NextGEN Gallery files', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body blt-card-body" id="bltgallery-nextgen-cleanup">
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
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
				<h1>
					<?php echo $this->brand_mark(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- KSES-filtered SVG from the shared brand class. ?>
					<?php esc_html_e( 'Galleries', 'bltgallery' ); ?>
				</h1>
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
		<div class="wrap blt-ui bltgallery-wrap">
			<div class="bltgallery-page-header blt-admin-page-header">
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
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header blt-card-header">
							<h2><?php esc_html_e( 'Gallery Settings', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body blt-card-body" id="bltgallery-editor-settings">
							<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
						</div>
					</div>

					<!-- Images panel -->
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header blt-card-header">
							<h2><?php esc_html_e( 'Images', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body blt-card-body">
							<!-- Uploader -->
							<div class="bltgallery-uploader" id="bltgallery-uploader">
								<input type="file" id="bltgallery-file-input" accept="image/*" multiple style="display:none">
								<div class="bltgallery-uploader__zone" id="bltgallery-drop-zone" tabindex="0" role="button"
									aria-label="<?php esc_attr_e( 'Drop images here or click to upload', 'bltgallery' ); ?>">
									<span class="bltgallery-uploader__icon" aria-hidden="true">&#128247;</span>
									<p><?php esc_html_e( 'Drag & drop images here, or', 'bltgallery' ); ?> <strong><?php esc_html_e( 'click to browse', 'bltgallery' ); ?></strong></p>
									<p class="bltgallery-uploader__hint"><?php esc_html_e( 'JPEG, PNG, GIF, WebP, AVIF · Max 50 MB each', 'bltgallery' ); ?></p>
								</div>
								<div class="bltgallery-uploader__actions">
									<button type="button" class="button" id="bltgallery-add-from-media">
										<?php esc_html_e( '+ Add from media library', 'bltgallery' ); ?>
									</button>
									<p class="description"><?php esc_html_e( "Pull in a copy of images you've already uploaded elsewhere on the site, instead of uploading them again.", 'bltgallery' ); ?></p>
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
					<div class="bltgallery-panel blt-card">
						<div class="bltgallery-panel__header blt-card-header">
							<h2><?php esc_html_e( 'Albums', 'bltgallery' ); ?></h2>
						</div>
						<div class="bltgallery-panel__body blt-card-body" id="bltgallery-albums-metabox">
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

	/**
	 * The BLT mark for a page header, from the shared brand class.
	 *
	 * Returns KSES-filtered SVG markup (or '' when the asset is missing), so
	 * callers echo it unescaped.
	 */
	private function brand_mark(): string {
		return \BLT_Family_Brand::inline_mark( BLT_GALLERY_PLUGIN_DIR );
	}

	/**
	 * Light the menu icon up on hover and while the section is open.
	 *
	 * The mark, the data URI, and this rule all come from the shared
	 * BLT_Family_Brand class — see DESIGN.md §1.
	 */
	public function print_menu_icon_style(): void {
		\BLT_Family_Brand::print_menu_icon_style( self::MENU_SLUG );
	}
}
