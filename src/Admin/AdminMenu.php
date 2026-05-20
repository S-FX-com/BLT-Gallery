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

	public function render_shortcodes_page(): void {
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
				<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
			</div>
		</div>
		<script>
		document.addEventListener( 'DOMContentLoaded', function () {
			if ( window.BltGalleryAdmin && BltGalleryAdmin.initShortcodesDoc ) {
				BltGalleryAdmin.initShortcodesDoc();
			}
		} );
		</script>
		<?php
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
			<h1><?php esc_html_e( 'BltGallery Settings', 'bltgallery' ); ?></h1>
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

			<!-- AWS S3 & CloudFront Settings -->
			<div class="bltgallery-panel">
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'AWS S3 &amp; CloudFront', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-aws-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare R2 Settings -->
			<div class="bltgallery-panel">
				<div class="bltgallery-panel__header">
					<h2><?php esc_html_e( 'Cloudflare R2', 'bltgallery' ); ?></h2>
				</div>
				<div class="bltgallery-panel__body" id="bltgallery-r2-settings">
					<p class="bltgallery-loading"><?php esc_html_e( 'Loading…', 'bltgallery' ); ?></p>
				</div>
			</div>

			<!-- Cloudflare Image Resizing -->
			<div class="bltgallery-panel">
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
