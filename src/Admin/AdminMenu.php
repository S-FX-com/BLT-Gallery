<?php

declare( strict_types=1 );

namespace ZymGallery\Admin;

/**
 * Registers the ZymGallery admin menu and renders pure-PHP views.
 * No build step required.
 */
class AdminMenu {

	const MENU_SLUG = 'zymgallery';

	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_pages(): void {
		add_menu_page(
			__( 'ZymGallery', 'zymgallery' ),
			__( 'ZymGallery', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_galleries_page' ],
			$this->get_menu_icon(),
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Galleries', 'zymgallery' ),
			__( 'Galleries', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_galleries_page' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'zymgallery' ),
			__( 'Settings', 'zymgallery' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! str_contains( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'zymgallery-admin',
			ZYMGALLERY_PLUGIN_URL . 'assets/admin/admin.css',
			[],
			ZYMGALLERY_VERSION
		);

		wp_enqueue_script(
			'zymgallery-admin',
			ZYMGALLERY_PLUGIN_URL . 'assets/admin/admin.js',
			[],
			ZYMGALLERY_VERSION,
			true
		);

		wp_localize_script(
			'zymgallery-admin',
			'zymGalleryConfig',
			[
				'apiBase'  => rest_url( 'zymgallery/v1' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'pluginUrl' => ZYMGALLERY_PLUGIN_URL,
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
		<div class="wrap zymgallery-wrap">
			<h1><?php esc_html_e( 'ZymGallery Settings', 'zymgallery' ); ?></h1>
			<div id="zymgallery-notice"></div>

			<!-- General Settings -->
			<div class="zymgallery-panel">
				<div class="zymgallery-panel__header">
					<h2><?php esc_html_e( 'General', 'zymgallery' ); ?></h2>
				</div>
				<div class="zymgallery-panel__body" id="zymgallery-general-settings">
					<p class="zymgallery-loading"><?php esc_html_e( 'Loading…', 'zymgallery' ); ?></p>
				</div>
			</div>

			<!-- AWS Settings -->
			<div class="zymgallery-panel">
				<div class="zymgallery-panel__header">
					<h2><?php esc_html_e( 'AWS S3 &amp; CloudFront', 'zymgallery' ); ?></h2>
				</div>
				<div class="zymgallery-panel__body" id="zymgallery-aws-settings">
					<p class="zymgallery-loading"><?php esc_html_e( 'Loading…', 'zymgallery' ); ?></p>
				</div>
			</div>
		</div>
		<?php
	}

	// ------------------------------------------------------------------
	// Private view helpers
	// ------------------------------------------------------------------

	private function render_gallery_list(): void {
		$list_url   = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="wrap zymgallery-wrap">
			<div class="zymgallery-page-header">
				<h1><?php esc_html_e( 'Galleries', 'zymgallery' ); ?></h1>
				<button class="button button-primary" id="zymgallery-new-gallery-btn">
					<?php esc_html_e( '+ New Gallery', 'zymgallery' ); ?>
				</button>
			</div>
			<div id="zymgallery-notice"></div>

			<div id="zymgallery-gallery-list">
				<p class="zymgallery-loading"><?php esc_html_e( 'Loading…', 'zymgallery' ); ?></p>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			ZymGalleryAdmin.initGalleryList(
				<?php echo wp_json_encode( $list_url ); ?>
			);
		});
		</script>
		<?php
	}

	private function render_gallery_editor( int $gallery_id ): void {
		$back_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		?>
		<div class="wrap zymgallery-wrap">
			<div class="zymgallery-page-header">
				<a href="<?php echo esc_url( $back_url ); ?>" class="button button-secondary">
					&larr; <?php esc_html_e( 'Galleries', 'zymgallery' ); ?>
				</a>
				<h1 id="zymgallery-editor-title"><?php esc_html_e( 'Edit Gallery', 'zymgallery' ); ?></h1>
				<code id="zymgallery-shortcode"></code>
			</div>
			<div id="zymgallery-notice"></div>

			<!-- Settings panel -->
			<div class="zymgallery-panel">
				<div class="zymgallery-panel__header">
					<h2><?php esc_html_e( 'Gallery Settings', 'zymgallery' ); ?></h2>
				</div>
				<div class="zymgallery-panel__body" id="zymgallery-editor-settings">
					<p class="zymgallery-loading"><?php esc_html_e( 'Loading…', 'zymgallery' ); ?></p>
				</div>
			</div>

			<!-- Images panel -->
			<div class="zymgallery-panel">
				<div class="zymgallery-panel__header">
					<h2><?php esc_html_e( 'Images', 'zymgallery' ); ?></h2>
				</div>
				<div class="zymgallery-panel__body">
					<!-- Uploader -->
					<div class="zymgallery-uploader" id="zymgallery-uploader">
						<input type="file" id="zymgallery-file-input" accept="image/*" multiple style="display:none">
						<div class="zymgallery-uploader__zone" id="zymgallery-drop-zone" tabindex="0" role="button"
							aria-label="<?php esc_attr_e( 'Drop images here or click to upload', 'zymgallery' ); ?>">
							<span class="zymgallery-uploader__icon" aria-hidden="true">&#128247;</span>
							<p><?php esc_html_e( 'Drag & drop images here, or', 'zymgallery' ); ?> <strong><?php esc_html_e( 'click to browse', 'zymgallery' ); ?></strong></p>
							<p class="zymgallery-uploader__hint"><?php esc_html_e( 'JPEG, PNG, GIF, WebP, AVIF · Max 50 MB each', 'zymgallery' ); ?></p>
						</div>
						<ul class="zymgallery-uploader__progress-list" id="zymgallery-progress-list"></ul>
					</div>
					<!-- Image grid -->
					<div id="zymgallery-image-grid">
						<p class="zymgallery-loading"><?php esc_html_e( 'Loading…', 'zymgallery' ); ?></p>
					</div>
				</div>
			</div>
		</div>

		<script>
		document.addEventListener('DOMContentLoaded', function () {
			ZymGalleryAdmin.initGalleryEditor(<?php echo (int) $gallery_id; ?>);
		});
		</script>
		<?php
	}

	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}
