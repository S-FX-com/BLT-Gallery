/**
 * ZymGallery Admin – vanilla JS, no build step required.
 */
( function () {
	'use strict';

	const cfg = window.zymGalleryConfig || {};

	// ------------------------------------------------------------------
	// API helper
	// ------------------------------------------------------------------

	async function api( path, options = {} ) {
		const url  = cfg.apiBase + path;
		const init = {
			headers: {
				'X-WP-Nonce': cfg.nonce,
				...( options.body && ! ( options.body instanceof FormData )
					? { 'Content-Type': 'application/json' }
					: {} ),
			},
			...options,
		};

		if ( init.body && ! ( init.body instanceof FormData ) ) {
			init.body = JSON.stringify( init.body );
		}

		const resp = await fetch( url, init );
		const text = await resp.text();
		let data;
		try { data = JSON.parse( text ); } catch { data = text; }

		if ( ! resp.ok ) {
			throw new Error( data?.message || `HTTP ${ resp.status }` );
		}
		return data;
	}

	// ------------------------------------------------------------------
	// Notice helper
	// ------------------------------------------------------------------

	function showNotice( msg, type = 'success' ) {
		const el = document.getElementById( 'zymgallery-notice' );
		if ( ! el ) return;
		el.innerHTML = `<div class="notice notice-${ type } is-dismissible zymgallery-notice"><p>${ escHtml( msg ) }</p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Dismiss</span></button></div>`;
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	// ------------------------------------------------------------------
	// Gallery List
	// ------------------------------------------------------------------

	async function initGalleryList( listUrl ) {
		const container = document.getElementById( 'zymgallery-gallery-list' );
		const newBtn    = document.getElementById( 'zymgallery-new-gallery-btn' );

		newBtn?.addEventListener( 'click', async () => {
			const title = window.prompt( 'Gallery title:' );
			if ( ! title ) return;
			newBtn.disabled = true;
			try {
				const gallery = await api( '/galleries', { method: 'POST', body: { title } } );
				window.location.href = listUrl + '&action=edit&gallery_id=' + gallery.id;
			} catch ( e ) {
				showNotice( e.message, 'error' );
			} finally {
				newBtn.disabled = false;
			}
		} );

		await loadGalleryList( container, listUrl );
	}

	async function loadGalleryList( container, listUrl ) {
		container.innerHTML = '<p class="zymgallery-loading">Loading…</p>';
		try {
			const galleries = await api( '/galleries?per_page=100' );
			renderGalleryTable( container, galleries, listUrl );
		} catch ( e ) {
			container.innerHTML = `<p class="zymgallery-error">${ escHtml( e.message ) }</p>`;
		}
	}

	function renderGalleryTable( container, galleries, listUrl ) {
		if ( galleries.length === 0 ) {
			container.innerHTML = '<div class="zymgallery-empty"><p>No galleries yet. Create your first one!</p></div>';
			return;
		}

		const rows = galleries.map( ( g ) => `
			<tr>
				<td><strong><a href="${ escHtml( listUrl + '&action=edit&gallery_id=' + g.id ) }">${ escHtml( g.title ) }</a></strong></td>
				<td>${ escHtml( g.display_type ) }</td>
				<td><code>[zymgallery id="${ escHtml( String( g.id ) ) }"]</code></td>
				<td>${ escHtml( new Date( g.created_at ).toLocaleDateString() ) }</td>
				<td>
					<a href="${ escHtml( listUrl + '&action=edit&gallery_id=' + g.id ) }" class="button button-secondary">Edit</a>
					<button class="button zymgallery-delete-btn" data-id="${ g.id }" data-title="${ escHtml( g.title ) }">Delete</button>
				</td>
			</tr>
		` ).join( '' );

		container.innerHTML = `
			<table class="wp-list-table widefat fixed striped zymgallery-table">
				<thead>
					<tr>
						<th>Title</th>
						<th>Display Type</th>
						<th>Shortcode</th>
						<th>Created</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>${ rows }</tbody>
			</table>
		`;

		container.querySelectorAll( '.zymgallery-delete-btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				const id    = btn.dataset.id;
				const title = btn.dataset.title;
				if ( ! window.confirm( `Delete gallery "${ title }"? This cannot be undone.` ) ) return;
				btn.disabled = true;
				try {
					await api( `/galleries/${ id }`, { method: 'DELETE' } );
					btn.closest( 'tr' ).remove();
					if ( container.querySelectorAll( 'tbody tr' ).length === 0 ) {
						renderGalleryTable( container, [], listUrl );
					}
				} catch ( e ) {
					showNotice( e.message, 'error' );
					btn.disabled = false;
				}
			} );
		} );
	}

	// ------------------------------------------------------------------
	// Gallery Editor
	// ------------------------------------------------------------------

	const DISPLAY_TYPES = [
		{ value: 'masonry',   label: 'Masonry'   },
		{ value: 'tile',      label: 'Tile Grid' },
		{ value: 'slideshow', label: 'Slideshow' },
		{ value: 'lightbox',  label: 'Lightbox'  },
	];

	async function initGalleryEditor( galleryId ) {
		const titleEl     = document.getElementById( 'zymgallery-editor-title' );
		const shortcodeEl = document.getElementById( 'zymgallery-shortcode' );
		const settingsEl  = document.getElementById( 'zymgallery-editor-settings' );
		const gridEl      = document.getElementById( 'zymgallery-image-grid' );

		let gallery, images;

		try {
			[ gallery, images ] = await Promise.all( [
				api( `/galleries/${ galleryId }` ),
				api( `/galleries/${ galleryId }/images` ),
			] );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			settingsEl.innerHTML = `<p class="zymgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		if ( titleEl )     titleEl.textContent = gallery.title;
		if ( shortcodeEl ) shortcodeEl.textContent = `[zymgallery id="${ galleryId }"]`;

		renderEditorSettings( settingsEl, gallery, galleryId );
		renderImageGrid( gridEl, images, galleryId );
		initUploader( galleryId, ( newImage ) => {
			images.push( newImage );
			renderImageGrid( gridEl, images, galleryId );
		} );
	}

	function renderEditorSettings( container, gallery, galleryId ) {
		const typeOptions = DISPLAY_TYPES.map( ( t ) =>
			`<option value="${ t.value }"${ t.value === gallery.display_type ? ' selected' : '' }>${ escHtml( t.label ) }</option>`
		).join( '' );

		const settings = gallery.settings || {};
		const cols     = settings.columns ?? 3;
		const autoplay = settings.autoplay ? '1' : '0';
		const speed    = settings.speed ?? 4000;

		container.innerHTML = `
			<div class="zymgallery-field">
				<label for="zyg-title">Title</label>
				<input type="text" id="zyg-title" class="regular-text" value="${ escHtml( gallery.title ) }">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-description">Description</label>
				<textarea id="zyg-description" rows="3" class="large-text">${ escHtml( gallery.description || '' ) }</textarea>
			</div>
			<div class="zymgallery-field">
				<label for="zyg-display-type">Display Type</label>
				<select id="zyg-display-type">${ typeOptions }</select>
			</div>
			<div class="zymgallery-field" id="zyg-columns-row">
				<label for="zyg-columns">Columns</label>
				<input type="number" id="zyg-columns" class="small-text" min="1" max="6" value="${ cols }">
			</div>
			<div class="zymgallery-field" id="zyg-slideshow-row" style="display:none">
				<label for="zyg-autoplay">Autoplay</label>
				<select id="zyg-autoplay">
					<option value="0"${ autoplay === '0' ? ' selected' : '' }>Off</option>
					<option value="1"${ autoplay === '1' ? ' selected' : '' }>On</option>
				</select>
				<label for="zyg-speed" style="margin-left:1rem">Speed (ms)</label>
				<input type="number" id="zyg-speed" class="small-text" min="1000" value="${ speed }">
			</div>
			<div class="zymgallery-field">
				<button class="button button-primary" id="zyg-save-btn">Save Settings</button>
			</div>
		`;

		// Toggle slideshow vs columns fields.
		const typeSelect = container.querySelector( '#zyg-display-type' );
		const colsRow    = container.querySelector( '#zyg-columns-row' );
		const ssRow      = container.querySelector( '#zyg-slideshow-row' );

		function updateTypeVisibility() {
			const isSlide = typeSelect.value === 'slideshow';
			colsRow.style.display = isSlide ? 'none' : '';
			ssRow.style.display   = isSlide ? '' : 'none';
		}
		updateTypeVisibility();
		typeSelect.addEventListener( 'change', updateTypeVisibility );

		container.querySelector( '#zyg-save-btn' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const isSlide = typeSelect.value === 'slideshow';
			const body = {
				title:        container.querySelector( '#zyg-title' ).value,
				description:  container.querySelector( '#zyg-description' ).value,
				display_type: typeSelect.value,
				settings: isSlide ? {
					autoplay: container.querySelector( '#zyg-autoplay' ).value === '1',
					speed:    parseInt( container.querySelector( '#zyg-speed' ).value, 10 ),
				} : {
					columns: parseInt( container.querySelector( '#zyg-columns' ).value, 10 ),
				},
			};
			try {
				await api( `/galleries/${ galleryId }`, { method: 'PUT', body } );
				showNotice( 'Settings saved.' );
				document.getElementById( 'zymgallery-editor-title' ).textContent = body.title;
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save Settings';
			}
		} );
	}

	// ------------------------------------------------------------------
	// Image Grid
	// ------------------------------------------------------------------

	function renderImageGrid( container, images, galleryId ) {
		if ( images.length === 0 ) {
			container.innerHTML = '<p class="zymgallery-image-grid__empty">No images yet. Upload some above.</p>';
			return;
		}

		container.innerHTML = `
			<p class="zymgallery-image-grid__hint">Drag images to reorder.</p>
			<ul class="zymgallery-image-grid__list" id="zyg-img-list"></ul>
		`;

		const list = container.querySelector( '#zyg-img-list' );

		images.forEach( ( img ) => {
			const li = document.createElement( 'li' );
			li.className    = 'zymgallery-image-grid__item';
			li.draggable    = true;
			li.dataset.id   = img.id;
			li.innerHTML = `
				<img src="${ escHtml( img.thumb_url || img.url ) }" alt="${ escHtml( img.alt_text || img.filename ) }" loading="lazy" width="100" height="100">
				<div class="zymgallery-image-grid__meta">
					<span title="${ escHtml( img.filename ) }">${ escHtml( img.filename ) }</span>
					<button class="button-link-delete zyg-img-delete" aria-label="Delete ${ escHtml( img.filename ) }">&times;</button>
				</div>
			`;

			li.querySelector( '.zyg-img-delete' ).addEventListener( 'click', async () => {
				if ( ! window.confirm( `Delete image "${ img.filename }"?` ) ) return;
				try {
					await api( `/galleries/${ galleryId }/images/${ img.id }`, { method: 'DELETE' } );
					li.remove();
					const idx = images.findIndex( ( i ) => i.id === img.id );
					if ( idx > -1 ) images.splice( idx, 1 );
					if ( list.children.length === 0 ) {
						container.innerHTML = '<p class="zymgallery-image-grid__empty">No images yet. Upload some above.</p>';
					}
				} catch ( e ) {
					showNotice( e.message, 'error' );
				}
			} );

			list.appendChild( li );
		} );

		initDragReorder( list, galleryId );
	}

	function initDragReorder( list, galleryId ) {
		let dragEl = null;

		list.addEventListener( 'dragstart', ( e ) => {
			dragEl = e.target.closest( 'li' );
			dragEl?.classList.add( 'is-dragging' );
		} );

		list.addEventListener( 'dragover', ( e ) => {
			e.preventDefault();
			if ( ! dragEl ) return;
			const target = e.target.closest( 'li' );
			if ( ! target || target === dragEl ) return;
			const rect   = target.getBoundingClientRect();
			const after  = e.clientY > rect.top + rect.height / 2;
			list.insertBefore( dragEl, after ? target.nextSibling : target );
		} );

		list.addEventListener( 'dragend', async () => {
			dragEl?.classList.remove( 'is-dragging' );
			dragEl = null;
			const order = [ ...list.querySelectorAll( 'li' ) ].map( ( li ) => parseInt( li.dataset.id, 10 ) );
			try {
				await api( `/galleries/${ galleryId }/images/reorder`, { method: 'POST', body: { order } } );
			} catch ( e ) {
				showNotice( 'Reorder failed: ' + e.message, 'error' );
			}
		} );
	}

	// ------------------------------------------------------------------
	// Image Uploader
	// ------------------------------------------------------------------

	function initUploader( galleryId, onUploaded ) {
		const dropZone    = document.getElementById( 'zymgallery-drop-zone' );
		const fileInput   = document.getElementById( 'zymgallery-file-input' );
		const progressList = document.getElementById( 'zymgallery-progress-list' );

		if ( ! dropZone || ! fileInput ) return;

		dropZone.addEventListener( 'click', () => fileInput.click() );
		dropZone.addEventListener( 'keydown', ( e ) => { if ( e.key === 'Enter' ) fileInput.click(); } );
		fileInput.addEventListener( 'change', ( e ) => handleFiles( e.target.files ) );

		dropZone.addEventListener( 'dragover', ( e ) => { e.preventDefault(); dropZone.classList.add( 'is-dragging' ); } );
		dropZone.addEventListener( 'dragleave', () => dropZone.classList.remove( 'is-dragging' ) );
		dropZone.addEventListener( 'drop', ( e ) => {
			e.preventDefault();
			dropZone.classList.remove( 'is-dragging' );
			handleFiles( e.dataTransfer.files );
		} );

		function handleFiles( files ) {
			Array.from( files ).forEach( uploadFile );
		}

		function uploadFile( file ) {
			const id   = Math.random().toString( 36 ).slice( 2 );
			const li   = document.createElement( 'li' );
			li.id        = 'zyg-upload-' + id;
			li.className = 'zymgallery-uploader__progress-item';
			li.innerHTML = `
				<span class="zymgallery-uploader__filename">${ escHtml( file.name ) }</span>
				<progress value="0" max="100" class="zymgallery-uploader__bar"></progress>
			`;
			progressList.appendChild( li );

			const bar  = li.querySelector( 'progress' );
			const body = new FormData();
			body.append( 'file', file );

			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.apiBase + `/galleries/${ galleryId }/upload` );
			xhr.setRequestHeader( 'X-WP-Nonce', cfg.nonce );

			xhr.upload.onprogress = ( e ) => {
				if ( e.lengthComputable ) bar.value = Math.round( ( e.loaded / e.total ) * 100 );
			};

			xhr.onload = () => {
				if ( xhr.status === 201 ) {
					bar.value = 100;
					const image = JSON.parse( xhr.responseText );
					onUploaded( image );
					setTimeout( () => li.remove(), 1500 );
				} else {
					let msg = 'Upload failed.';
					try { msg = JSON.parse( xhr.responseText )?.message ?? msg; } catch {}
					li.innerHTML = `<span class="zymgallery-uploader__filename">${ escHtml( file.name ) }</span><span class="zymgallery-error"> — ${ escHtml( msg ) }</span>`;
				}
			};

			xhr.onerror = () => {
				li.innerHTML = `<span class="zymgallery-uploader__filename">${ escHtml( file.name ) }</span><span class="zymgallery-error"> — Network error.</span>`;
			};

			xhr.send( body );
		}
	}

	// ------------------------------------------------------------------
	// Settings page
	// ------------------------------------------------------------------

	async function initSettings() {
		const genEl = document.getElementById( 'zymgallery-general-settings' );
		const awsEl = document.getElementById( 'zymgallery-aws-settings' );

		if ( ! genEl && ! awsEl ) return;

		let general, aws;
		try {
			[ general, aws ] = await Promise.all( [
				api( '/settings' ),
				api( '/settings/aws' ),
			] );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			return;
		}

		renderGeneralSettings( genEl, general );
		renderAwsSettings( awsEl, aws );
	}

	function renderGeneralSettings( container, general ) {
		const g = general || {};
		container.innerHTML = `
			<div class="zymgallery-field">
				<label for="zyg-default-display">Default Display Type</label>
				<select id="zyg-default-display">
					${ DISPLAY_TYPES.map( ( t ) => `<option value="${ t.value }"${ t.value === g.default_display_type ? ' selected' : '' }>${ escHtml( t.label ) }</option>` ).join( '' ) }
				</select>
			</div>
			<div class="zymgallery-field zymgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-lazy-load"${ g.lazy_load ? ' checked' : '' }>
					Lazy-load images
				</label>
			</div>
			<div class="zymgallery-field">
				<label for="zyg-webp-quality">WebP quality (1–100)</label>
				<input type="number" id="zyg-webp-quality" class="small-text" min="1" max="100" value="${ g.webp_quality ?? 85 }">
			</div>
			<div class="zymgallery-field zymgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-delete-data"${ g.delete_data_on_uninstall ? ' checked' : '' }>
					Delete all data on uninstall
				</label>
			</div>
			<div class="zymgallery-field">
				<button class="button button-primary" id="zyg-save-general">Save General Settings</button>
			</div>
		`;

		container.querySelector( '#zyg-save-general' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			try {
				await api( '/settings', { method: 'POST', body: {
					default_display_type:    container.querySelector( '#zyg-default-display' ).value,
					lazy_load:               container.querySelector( '#zyg-lazy-load' ).checked,
					webp_quality:            parseInt( container.querySelector( '#zyg-webp-quality' ).value, 10 ),
					delete_data_on_uninstall: container.querySelector( '#zyg-delete-data' ).checked,
				} } );
				showNotice( 'General settings saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save General Settings';
			}
		} );
	}

	function renderAwsSettings( container, aws ) {
		const a = aws || {};
		container.innerHTML = `
			<div class="zymgallery-field">
				<label for="zyg-access-key">AWS Access Key ID</label>
				<input type="text" id="zyg-access-key" class="regular-text" value="${ escHtml( a.access_key_id ?? '' ) }" autocomplete="off">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-secret-key">AWS Secret Access Key</label>
				<input type="password" id="zyg-secret-key" class="regular-text" value="" autocomplete="new-password" placeholder="Leave blank to keep existing">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-region">AWS Region</label>
				<input type="text" id="zyg-region" class="regular-text" value="${ escHtml( a.region ?? 'us-east-1' ) }" placeholder="us-east-1">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-bucket">S3 Bucket Name</label>
				<input type="text" id="zyg-bucket" class="regular-text" value="${ escHtml( a.bucket ?? '' ) }">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-prefix">S3 Path Prefix (optional)</label>
				<input type="text" id="zyg-prefix" class="regular-text" value="${ escHtml( a.path_prefix ?? '' ) }" placeholder="gallery/">
			</div>
			<div class="zymgallery-field zymgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-auto-offload"${ a.auto_offload ? ' checked' : '' }>
					Auto-offload new uploads to S3
				</label>
			</div>
			<div class="zymgallery-field zymgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-delete-local"${ a.delete_local_after_upload ? ' checked' : '' }>
					Delete local files after upload to S3
				</label>
			</div>
			<hr>
			<h3>CloudFront CDN (optional)</h3>
			<div class="zymgallery-field">
				<label for="zyg-cf-domain">CloudFront Domain</label>
				<input type="text" id="zyg-cf-domain" class="regular-text" value="${ escHtml( a.cloudfront_domain ?? '' ) }" placeholder="d1234abcd.cloudfront.net">
			</div>
			<div class="zymgallery-field">
				<label for="zyg-cf-id">CloudFront Distribution ID</label>
				<input type="text" id="zyg-cf-id" class="regular-text" value="${ escHtml( a.cloudfront_distribution_id ?? '' ) }">
			</div>
			<div class="zymgallery-field" style="display:flex;gap:12px;align-items:center">
				<button class="button button-primary" id="zyg-save-aws">Save AWS Settings</button>
				<button class="button button-secondary" id="zyg-test-conn">Test S3 Connection</button>
			</div>
			<div id="zyg-test-result"></div>
		`;

		container.querySelector( '#zyg-save-aws' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const secret = container.querySelector( '#zyg-secret-key' ).value;
			const body = {
				access_key_id:               container.querySelector( '#zyg-access-key' ).value,
				region:                      container.querySelector( '#zyg-region' ).value,
				bucket:                      container.querySelector( '#zyg-bucket' ).value,
				path_prefix:                 container.querySelector( '#zyg-prefix' ).value,
				auto_offload:                container.querySelector( '#zyg-auto-offload' ).checked,
				delete_local_after_upload:   container.querySelector( '#zyg-delete-local' ).checked,
				cloudfront_domain:           container.querySelector( '#zyg-cf-domain' ).value,
				cloudfront_distribution_id:  container.querySelector( '#zyg-cf-id' ).value,
			};
			if ( secret ) body.secret_access_key = secret;
			try {
				await api( '/settings/aws', { method: 'POST', body } );
				showNotice( 'AWS settings saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save AWS Settings';
			}
		} );

		container.querySelector( '#zyg-test-conn' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Testing…';
			const resultEl = container.querySelector( '#zyg-test-result' );
			try {
				const result = await api( '/settings/aws/test', { method: 'POST' } );
				resultEl.innerHTML = `<div class="notice notice-${ result.success ? 'success' : 'error' } inline"><p>${ escHtml( result.message ) }</p></div>`;
			} catch ( err ) {
				resultEl.innerHTML = `<div class="notice notice-error inline"><p>${ escHtml( err.message ) }</p></div>`;
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Test S3 Connection';
			}
		} );
	}

	// ------------------------------------------------------------------
	// Auto-init settings page
	// ------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.getElementById( 'zymgallery-general-settings' ) ) {
			initSettings();
		}
	} );

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	window.ZymGalleryAdmin = {
		initGalleryList,
		initGalleryEditor,
		initSettings,
	};

} )();
