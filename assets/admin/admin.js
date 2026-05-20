/**
 * BltGallery Admin – vanilla JS, no build step required.
 */
( function () {
	'use strict';

	const cfg = window.bltGalleryConfig || {};

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
		const el = document.getElementById( 'bltgallery-notice' );
		if ( ! el ) return;
		el.innerHTML = `<div class="notice notice-${ type } is-dismissible bltgallery-notice"><p>${ escHtml( msg ) }</p><button type="button" class="notice-dismiss" onclick="this.parentElement.remove()"><span class="screen-reader-text">Dismiss</span></button></div>`;
		el.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	// ------------------------------------------------------------------
	// Gallery List
	// ------------------------------------------------------------------

	async function initGalleryList( listUrl ) {
		const container = document.getElementById( 'bltgallery-gallery-list' );
		const newBtn    = document.getElementById( 'bltgallery-new-gallery-btn' );

		newBtn?.addEventListener( 'click', () => openInlineCreateForm( newBtn, listUrl ) );

		await loadGalleryList( container, listUrl );
	}

	/**
	 * Inject (or focus) an inline gallery-creation form right above the
	 * gallery table, replacing the legacy window.prompt() dialog.
	 */
	function openInlineCreateForm( newBtn, listUrl ) {
		const existing = document.getElementById( 'bltgallery-inline-create' );
		if ( existing ) {
			existing.querySelector( 'input[name="title"]' )?.focus();
			return;
		}

		const wrap     = document.createElement( 'div' );
		wrap.id        = 'bltgallery-inline-create';
		wrap.className = 'bltgallery-inline-create';
		wrap.innerHTML = `
			<form class="bltgallery-inline-create__form" novalidate>
				<label class="bltgallery-inline-create__label" for="bltgallery-inline-create-title">
					New gallery title
				</label>
				<div class="bltgallery-inline-create__row">
					<input
						id="bltgallery-inline-create-title"
						name="title"
						type="text"
						class="regular-text"
						placeholder="e.g. Summer 2026 wedding"
						required
						autocomplete="off">
					<button type="submit" class="button button-primary">Create &amp; Edit</button>
					<button type="button" class="button button-secondary" data-action="cancel">Cancel</button>
				</div>
				<p class="bltgallery-inline-create__hint description">
					You can upload images and tweak display settings on the next screen.
				</p>
			</form>
		`;

		const notice = document.getElementById( 'bltgallery-notice' );
		notice.parentNode.insertBefore( wrap, notice.nextSibling );

		const input  = wrap.querySelector( 'input[name="title"]' );
		const submit = wrap.querySelector( 'button[type="submit"]' );
		input.focus();

		wrap.querySelector( '[data-action="cancel"]' ).addEventListener( 'click', () => {
			wrap.remove();
		} );

		wrap.querySelector( 'form' ).addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const title = input.value.trim();
			if ( ! title ) {
				input.focus();
				return;
			}
			submit.disabled = true;
			submit.textContent = 'Creating…';
			try {
				const gallery = await api( '/galleries', { method: 'POST', body: { title } } );
				window.location.href = listUrl + '&action=edit&gallery_id=' + gallery.id;
			} catch ( err ) {
				submit.disabled = false;
				submit.textContent = 'Create & Edit';
				showNotice( err.message, 'error' );
			}
		} );
	}

	async function loadGalleryList( container, listUrl ) {
		container.innerHTML = '<p class="bltgallery-loading">Loading…</p>';
		try {
			const galleries = await api( '/galleries?per_page=100' );
			renderGalleryTable( container, galleries, listUrl );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
		}
	}

	function renderGalleryTable( container, galleries, listUrl ) {
		if ( galleries.length === 0 ) {
			container.innerHTML = '<div class="bltgallery-empty"><p>No galleries yet. Create your first one!</p></div>';
			return;
		}

		const rows = galleries.map( ( g ) => `
			<tr>
				<td><strong><a href="${ escHtml( listUrl + '&action=edit&gallery_id=' + g.id ) }">${ escHtml( g.title ) }</a></strong></td>
				<td>${ escHtml( g.display_type ) }</td>
				<td><code>[blt_gallery id="${ escHtml( String( g.id ) ) }"]</code></td>
				<td>${ escHtml( new Date( g.created_at ).toLocaleDateString() ) }</td>
				<td>
					<a href="${ escHtml( listUrl + '&action=edit&gallery_id=' + g.id ) }" class="button button-secondary">Edit</a>
					<button class="button bltgallery-delete-btn" data-id="${ g.id }" data-title="${ escHtml( g.title ) }">Delete</button>
				</td>
			</tr>
		` ).join( '' );

		container.innerHTML = `
			<table class="wp-list-table widefat fixed striped bltgallery-table">
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

		container.querySelectorAll( '.bltgallery-delete-btn' ).forEach( ( btn ) => {
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
		{
			value: 'masonry',
			label: 'Masonry',
			desc:  'Variable-height columns. Best for mixed portrait + landscape shots.',
			preview: `
				<rect x="2"  y="2"  width="18" height="26" rx="2"/>
				<rect x="22" y="2"  width="18" height="14" rx="2"/>
				<rect x="22" y="18" width="18" height="22" rx="2"/>
				<rect x="42" y="2"  width="18" height="22" rx="2"/>
				<rect x="42" y="26" width="18" height="14" rx="2"/>
			`,
		},
		{
			value: 'tile',
			label: 'Tile Grid',
			desc:  'Uniform square thumbnails. Great for clean, editorial layouts.',
			preview: `
				<rect x="2"  y="2"  width="18" height="18" rx="2"/>
				<rect x="22" y="2"  width="18" height="18" rx="2"/>
				<rect x="42" y="2"  width="18" height="18" rx="2"/>
				<rect x="2"  y="22" width="18" height="18" rx="2"/>
				<rect x="22" y="22" width="18" height="18" rx="2"/>
				<rect x="42" y="22" width="18" height="18" rx="2"/>
			`,
		},
		{
			value: 'slideshow',
			label: 'Slideshow',
			desc:  'One image at a time with optional autoplay. Perfect for hero areas.',
			preview: `
				<rect x="2"  y="6" width="58" height="30" rx="2"/>
				<circle cx="22" cy="40" r="2"/>
				<circle cx="30" cy="40" r="2" fill="currentColor"/>
				<circle cx="38" cy="40" r="2"/>
			`,
		},
		{
			value: 'lightbox',
			label: 'Lightbox',
			desc:  'Tile grid that opens a full-screen modal viewer on click.',
			preview: `
				<rect x="2"  y="2"  width="18" height="18" rx="2"/>
				<rect x="22" y="2"  width="18" height="18" rx="2"/>
				<rect x="42" y="2"  width="18" height="18" rx="2"/>
				<rect x="2"  y="22" width="18" height="18" rx="2"/>
				<rect x="22" y="22" width="18" height="18" rx="2" fill="currentColor" fill-opacity="0.18"/>
				<rect x="42" y="22" width="18" height="18" rx="2"/>
				<path d="M26 26 L36 36 M36 26 L26 36" stroke-linecap="round"/>
			`,
		},
	];

	const THUMB_SIZES = [
		{ value: 'small',  label: 'Small',  desc: '~140px columns' },
		{ value: 'medium', label: 'Medium', desc: '~200px columns' },
		{ value: 'large',  label: 'Large',  desc: '~280px columns' },
		{ value: 'xlarge', label: 'XL',     desc: '~360px columns' },
	];

	async function initGalleryEditor( galleryId ) {
		const titleEl     = document.getElementById( 'bltgallery-editor-title' );
		const shortcodeEl = document.getElementById( 'bltgallery-shortcode' );
		const settingsEl  = document.getElementById( 'bltgallery-editor-settings' );
		const gridEl      = document.getElementById( 'bltgallery-image-grid' );

		let gallery, images;

		try {
			[ gallery, images ] = await Promise.all( [
				api( `/galleries/${ galleryId }` ),
				api( `/galleries/${ galleryId }/images` ),
			] );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			settingsEl.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		if ( titleEl )     titleEl.textContent = gallery.title;
		if ( shortcodeEl ) shortcodeEl.textContent = `[blt_gallery id="${ galleryId }"]`;

		renderEditorSettings( settingsEl, gallery, galleryId );
		renderImageGrid( gridEl, images, galleryId );
		initUploader( galleryId, ( newImage ) => {
			images.push( newImage );
			renderImageGrid( gridEl, images, galleryId );
		} );
	}

	function renderEditorSettings( container, gallery, galleryId ) {
		const settings = gallery.settings || {};
		const selected = gallery.display_type || 'masonry';
		const size     = settings.thumbnail_size || 'medium';
		const autoplay = settings.autoplay ? '1' : '0';
		const speed    = settings.speed ?? 4000;

		const typeCards = DISPLAY_TYPES.map( ( t ) => `
			<label class="bltgallery-type-card${ t.value === selected ? ' is-selected' : '' }" data-type="${ t.value }">
				<input type="radio" name="bltgallery-display-type" value="${ t.value }"${ t.value === selected ? ' checked' : '' }>
				<svg class="bltgallery-type-card__preview" viewBox="0 0 62 42" aria-hidden="true">
					<g fill="none" stroke="currentColor" stroke-width="1.5">${ t.preview }</g>
				</svg>
				<span class="bltgallery-type-card__label">${ escHtml( t.label ) }</span>
				<span class="bltgallery-type-card__desc">${ escHtml( t.desc ) }</span>
			</label>
		` ).join( '' );

		const sizeOptions = THUMB_SIZES.map( ( s ) =>
			`<option value="${ s.value }"${ s.value === size ? ' selected' : '' }>${ escHtml( s.label ) } — ${ escHtml( s.desc ) }</option>`
		).join( '' );

		container.innerHTML = `
			<div class="bltgallery-field">
				<label for="zyg-title">Title</label>
				<input type="text" id="zyg-title" class="regular-text" value="${ escHtml( gallery.title ) }">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-description">Description</label>
				<textarea id="zyg-description" rows="3" class="large-text">${ escHtml( gallery.description || '' ) }</textarea>
			</div>
			<div class="bltgallery-field">
				<span class="bltgallery-field__label">Display Type</span>
				<div class="bltgallery-type-cards" role="radiogroup" aria-label="Display type">
					${ typeCards }
				</div>
			</div>
			<div class="bltgallery-field" id="zyg-size-row">
				<label for="zyg-thumb-size">Thumbnail size</label>
				<select id="zyg-thumb-size">${ sizeOptions }</select>
				<p class="description">Columns auto-fit responsively to your page width based on this size — no manual column count needed.</p>
			</div>
			<div class="bltgallery-field" id="zyg-slideshow-row" style="display:none">
				<label for="zyg-autoplay">Autoplay</label>
				<select id="zyg-autoplay">
					<option value="0"${ autoplay === '0' ? ' selected' : '' }>Off</option>
					<option value="1"${ autoplay === '1' ? ' selected' : '' }>On</option>
				</select>
				<label for="zyg-speed" style="margin-left:1rem">Speed (ms)</label>
				<input type="number" id="zyg-speed" class="small-text" min="1000" value="${ speed }">
			</div>
			<div class="bltgallery-field">
				<button class="button button-primary" id="zyg-save-btn">Save Settings</button>
			</div>
		`;

		const cardsWrap = container.querySelector( '.bltgallery-type-cards' );
		const sizeRow   = container.querySelector( '#zyg-size-row' );
		const ssRow     = container.querySelector( '#zyg-slideshow-row' );

		function selectedType() {
			return cardsWrap.querySelector( 'input[name="bltgallery-display-type"]:checked' )?.value || 'masonry';
		}

		function updateTypeVisibility() {
			const type = selectedType();
			const isSlide = type === 'slideshow';
			sizeRow.style.display = isSlide ? 'none' : '';
			ssRow.style.display   = isSlide ? '' : 'none';
		}

		cardsWrap.addEventListener( 'change', ( e ) => {
			if ( e.target.name !== 'bltgallery-display-type' ) return;
			cardsWrap.querySelectorAll( '.bltgallery-type-card' ).forEach( ( el ) => {
				el.classList.toggle( 'is-selected', el.dataset.type === e.target.value );
			} );
			updateTypeVisibility();
		} );

		updateTypeVisibility();

		container.querySelector( '#zyg-save-btn' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const type    = selectedType();
			const isSlide = type === 'slideshow';
			const body = {
				title:        container.querySelector( '#zyg-title' ).value,
				description:  container.querySelector( '#zyg-description' ).value,
				display_type: type,
				settings: isSlide ? {
					autoplay: container.querySelector( '#zyg-autoplay' ).value === '1',
					speed:    parseInt( container.querySelector( '#zyg-speed' ).value, 10 ),
				} : {
					thumbnail_size: container.querySelector( '#zyg-thumb-size' ).value,
				},
			};
			try {
				await api( `/galleries/${ galleryId }`, { method: 'PUT', body } );
				showNotice( 'Settings saved.' );
				document.getElementById( 'bltgallery-editor-title' ).textContent = body.title;
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
			container.innerHTML = '<p class="bltgallery-image-grid__empty">No images yet. Upload some above.</p>';
			return;
		}

		container.innerHTML = `
			<p class="bltgallery-image-grid__hint">Drag images to reorder.</p>
			<ul class="bltgallery-image-grid__list" id="zyg-img-list"></ul>
		`;

		const list = container.querySelector( '#zyg-img-list' );

		images.forEach( ( img ) => {
			const li = document.createElement( 'li' );
			li.className    = 'bltgallery-image-grid__item';
			li.draggable    = true;
			li.dataset.id   = img.id;
			li.innerHTML = `
				<img src="${ escHtml( img.thumb_url || img.url ) }" alt="${ escHtml( img.alt_text || img.filename ) }" loading="lazy" width="100" height="100">
				<div class="bltgallery-image-grid__meta">
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
						container.innerHTML = '<p class="bltgallery-image-grid__empty">No images yet. Upload some above.</p>';
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
		const dropZone    = document.getElementById( 'bltgallery-drop-zone' );
		const fileInput   = document.getElementById( 'bltgallery-file-input' );
		const progressList = document.getElementById( 'bltgallery-progress-list' );

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
			li.className = 'bltgallery-uploader__progress-item';
			li.innerHTML = `
				<span class="bltgallery-uploader__filename">${ escHtml( file.name ) }</span>
				<progress value="0" max="100" class="bltgallery-uploader__bar"></progress>
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
					li.innerHTML = `<span class="bltgallery-uploader__filename">${ escHtml( file.name ) }</span><span class="bltgallery-error"> — ${ escHtml( msg ) }</span>`;
				}
			};

			xhr.onerror = () => {
				li.innerHTML = `<span class="bltgallery-uploader__filename">${ escHtml( file.name ) }</span><span class="bltgallery-error"> — Network error.</span>`;
			};

			xhr.send( body );
		}
	}

	// ------------------------------------------------------------------
	// Settings page
	// ------------------------------------------------------------------

	async function initSettings() {
		const genEl = document.getElementById( 'bltgallery-general-settings' );
		const awsEl = document.getElementById( 'bltgallery-aws-settings' );
		const r2El  = document.getElementById( 'bltgallery-r2-settings' );

		if ( ! genEl && ! awsEl && ! r2El ) return;

		let general, aws, r2;
		try {
			[ general, aws, r2 ] = await Promise.all( [
				api( '/settings' ),
				api( '/settings/aws' ),
				api( '/settings/r2' ),
			] );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			return;
		}

		renderGeneralSettings( genEl, general );
		renderAwsSettings( awsEl, aws );
		renderR2Settings( r2El, r2 );
	}

	function renderGeneralSettings( container, general ) {
		const g = general || {};
		container.innerHTML = `
			<div class="bltgallery-field">
				<label for="zyg-default-display">Default Display Type</label>
				<select id="zyg-default-display">
					${ DISPLAY_TYPES.map( ( t ) => `<option value="${ t.value }"${ t.value === g.default_display_type ? ' selected' : '' }>${ escHtml( t.label ) }</option>` ).join( '' ) }
				</select>
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-lazy-load"${ g.lazy_load ? ' checked' : '' }>
					Lazy-load images
				</label>
			</div>
			<div class="bltgallery-field">
				<label for="zyg-webp-quality">WebP quality (1–100)</label>
				<input type="number" id="zyg-webp-quality" class="small-text" min="1" max="100" value="${ g.webp_quality ?? 85 }">
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-delete-data"${ g.delete_data_on_uninstall ? ' checked' : '' }>
					Delete all data on uninstall
				</label>
			</div>
			<div class="bltgallery-field">
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
			<div class="bltgallery-field">
				<label for="zyg-access-key">AWS Access Key ID</label>
				<input type="text" id="zyg-access-key" class="regular-text" value="${ escHtml( a.access_key_id ?? '' ) }" autocomplete="off">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-secret-key">AWS Secret Access Key</label>
				<input type="password" id="zyg-secret-key" class="regular-text" value="" autocomplete="new-password" placeholder="Leave blank to keep existing">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-region">AWS Region</label>
				<input type="text" id="zyg-region" class="regular-text" value="${ escHtml( a.region ?? 'us-east-1' ) }" placeholder="us-east-1">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-bucket">S3 Bucket Name</label>
				<input type="text" id="zyg-bucket" class="regular-text" value="${ escHtml( a.bucket ?? '' ) }">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-prefix">S3 Path Prefix (optional)</label>
				<input type="text" id="zyg-prefix" class="regular-text" value="${ escHtml( a.path_prefix ?? '' ) }" placeholder="gallery/">
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-auto-offload"${ a.auto_offload ? ' checked' : '' }>
					Auto-offload new uploads to S3
				</label>
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-delete-local"${ a.delete_local_after_upload ? ' checked' : '' }>
					Delete local files after upload to S3
				</label>
			</div>
			<hr>
			<h3>CloudFront CDN (optional)</h3>
			<div class="bltgallery-field">
				<label for="zyg-cf-domain">CloudFront Domain</label>
				<input type="text" id="zyg-cf-domain" class="regular-text" value="${ escHtml( a.cloudfront_domain ?? '' ) }" placeholder="d1234abcd.cloudfront.net">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-cf-id">CloudFront Distribution ID</label>
				<input type="text" id="zyg-cf-id" class="regular-text" value="${ escHtml( a.cloudfront_distribution_id ?? '' ) }">
			</div>
			<div class="bltgallery-field" style="display:flex;gap:12px;align-items:center">
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
	// Cloudflare R2 Settings
	// ------------------------------------------------------------------

	function renderR2Settings( container, r2 ) {
		if ( ! container ) return;
		const a = r2 || {};
		container.innerHTML = `
			<p>Store original files on <strong>Cloudflare R2</strong> to minimise local disk usage.
			R2 is S3-compatible and has no egress fees.</p>
			<div class="bltgallery-field">
				<label for="zyg-r2-account-id">Cloudflare Account ID</label>
				<input type="text" id="zyg-r2-account-id" class="regular-text" value="${ escHtml( a.account_id ?? '' ) }" placeholder="Found in the Cloudflare dashboard">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-r2-access-key">R2 Access Key ID</label>
				<input type="text" id="zyg-r2-access-key" class="regular-text" value="${ escHtml( a.access_key_id ?? '' ) }" autocomplete="off">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-r2-secret-key">R2 Secret Access Key</label>
				<input type="password" id="zyg-r2-secret-key" class="regular-text" value="" autocomplete="new-password" placeholder="Leave blank to keep existing">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-r2-bucket">R2 Bucket Name</label>
				<input type="text" id="zyg-r2-bucket" class="regular-text" value="${ escHtml( a.bucket ?? '' ) }">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-r2-prefix">Path Prefix (optional)</label>
				<input type="text" id="zyg-r2-prefix" class="regular-text" value="${ escHtml( a.path_prefix ?? '' ) }" placeholder="gallery/">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-r2-public-url">Public Base URL</label>
				<input type="text" id="zyg-r2-public-url" class="regular-text" value="${ escHtml( a.public_url ?? '' ) }" placeholder="https://assets.example.com">
				<p class="description">Your bucket&rsquo;s custom domain or the <code>pub-&hellip;.r2.dev</code> URL.</p>
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-r2-auto-offload"${ a.auto_offload ? ' checked' : '' }>
					Auto-offload new uploads to R2
				</label>
			</div>
			<div class="bltgallery-field bltgallery-field--toggle">
				<label>
					<input type="checkbox" id="zyg-r2-delete-local"${ a.delete_local_after_upload ? ' checked' : '' }>
					Delete local files after upload to R2
				</label>
			</div>
			<div class="bltgallery-field" style="display:flex;gap:12px;align-items:center">
				<button class="button button-primary" id="zyg-save-r2">Save R2 Settings</button>
				<button class="button button-secondary" id="zyg-test-r2">Test R2 Connection</button>
			</div>
			<div id="zyg-r2-test-result"></div>
		`;

		container.querySelector( '#zyg-save-r2' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const secret = container.querySelector( '#zyg-r2-secret-key' ).value;
			const body = {
				account_id:                container.querySelector( '#zyg-r2-account-id' ).value,
				access_key_id:             container.querySelector( '#zyg-r2-access-key' ).value,
				bucket:                    container.querySelector( '#zyg-r2-bucket' ).value,
				path_prefix:               container.querySelector( '#zyg-r2-prefix' ).value,
				public_url:                container.querySelector( '#zyg-r2-public-url' ).value,
				auto_offload:              container.querySelector( '#zyg-r2-auto-offload' ).checked,
				delete_local_after_upload: container.querySelector( '#zyg-r2-delete-local' ).checked,
			};
			if ( secret ) body.secret_access_key = secret;
			try {
				await api( '/settings/r2', { method: 'POST', body } );
				showNotice( 'R2 settings saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save R2 Settings';
			}
		} );

		container.querySelector( '#zyg-test-r2' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Testing…';
			const resultEl = container.querySelector( '#zyg-r2-test-result' );
			try {
				const result = await api( '/settings/r2/test', { method: 'POST' } );
				resultEl.innerHTML = `<div class="notice notice-${ result.success ? 'success' : 'error' } inline"><p>${ escHtml( result.message ) }</p></div>`;
			} catch ( err ) {
				resultEl.innerHTML = `<div class="notice notice-error inline"><p>${ escHtml( err.message ) }</p></div>`;
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Test R2 Connection';
			}
		} );
	}

	// ------------------------------------------------------------------
	// NextGEN Gallery Importer
	// ------------------------------------------------------------------

	async function initImporter() {
		const container = document.getElementById( 'bltgallery-nextgen-importer' );
		if ( ! container ) return;

		let status;
		try {
			status = await api( '/import/nextgen/status' );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		if ( ! status.available ) {
			container.innerHTML = `
				<div class="notice notice-warning inline"><p>${ escHtml( status.message ) }</p></div>
				<p>Install and activate <strong>Imagely NextGEN Gallery</strong> and create at least one gallery, then return to this page.</p>
			`;
			return;
		}

		// Load gallery preview.
		let preview;
		try {
			preview = await api( '/import/nextgen/preview' );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		renderImporterGalleryList( container, preview.galleries );
	}

	function renderImporterGalleryList( container, galleries ) {
		if ( ! galleries || galleries.length === 0 ) {
			container.innerHTML = '<p>No galleries found in NextGEN Gallery.</p>';
			return;
		}

		const rows = galleries.map( ( g ) => `
			<tr>
				<td><input type="checkbox" class="zyg-import-check" value="${ escHtml( String( g.gid ) ) }" checked></td>
				<td><strong>${ escHtml( g.title || g.name ) }</strong></td>
				<td>${ escHtml( g.galdesc || '—' ) }</td>
				<td>${ escHtml( String( g.image_count ) ) }</td>
			</tr>
		` ).join( '' );

		container.innerHTML = `
			<div class="notice notice-success inline"><p>NextGEN Gallery detected. Select galleries to import below.</p></div>
			<p><strong>Note:</strong> Your original NextGEN Gallery data and files will not be modified. BltGallery copies files into its own upload directory.</p>
			<table class="wp-list-table widefat fixed striped bltgallery-table" style="margin-bottom:1rem">
				<thead>
					<tr>
						<th style="width:40px"><input type="checkbox" id="zyg-import-check-all" checked></th>
						<th>Gallery Title</th>
						<th>Description</th>
						<th>Images</th>
					</tr>
				</thead>
				<tbody>${ rows }</tbody>
			</table>
			<div style="display:flex;gap:12px;align-items:center">
				<button class="button button-primary" id="zyg-run-import">Import Selected Galleries</button>
				<span id="zyg-import-status"></span>
			</div>
			<div id="zyg-import-results"></div>
		`;

		// Select all toggle.
		container.querySelector( '#zyg-import-check-all' ).addEventListener( 'change', ( e ) => {
			container.querySelectorAll( '.zyg-import-check' ).forEach( ( cb ) => {
				cb.checked = e.target.checked;
			} );
		} );

		container.querySelector( '#zyg-run-import' ).addEventListener( 'click', async ( e ) => {
			const checked = [ ...container.querySelectorAll( '.zyg-import-check:checked' ) ];
			if ( checked.length === 0 ) {
				showNotice( 'Please select at least one gallery to import.', 'error' );
				return;
			}

			const gallery_ids = checked.map( ( cb ) => parseInt( cb.value, 10 ) );

			e.target.disabled = true;
			const statusEl  = container.querySelector( '#zyg-import-status' );
			const resultsEl = container.querySelector( '#zyg-import-results' );
			statusEl.textContent = 'Importing… this may take a while for large galleries.';
			resultsEl.innerHTML  = '';

			try {
				const result = await api( '/import/nextgen/run', {
					method: 'POST',
					body:   { gallery_ids },
				} );

				statusEl.textContent = '';
				const errorHtml = result.errors.length
					? `<details style="margin-top:.5rem"><summary>${ result.errors.length } warning(s)</summary><ul>${ result.errors.map( ( e ) => `<li>${ escHtml( e ) }</li>` ).join( '' ) }</ul></details>`
					: '';

				resultsEl.innerHTML = `
					<div class="notice notice-success inline" style="margin-top:1rem">
						<p>
							Import complete: <strong>${ result.galleries_imported }</strong> ${ result.galleries_imported === 1 ? 'gallery' : 'galleries' } imported,
							<strong>${ result.images_imported }</strong> ${ result.images_imported === 1 ? 'image' : 'images' } imported
							${ result.images_skipped > 0 ? `, ${ result.images_skipped } skipped` : '' }.
						</p>
					</div>
					${ errorHtml }
				`;

				showNotice( `Import complete. ${ result.images_imported } image(s) imported.` );
			} catch ( err ) {
				statusEl.textContent = '';
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
			}
		} );
	}

	// ------------------------------------------------------------------
	// Auto-init settings page
	// ------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.getElementById( 'bltgallery-general-settings' ) ) {
			initSettings();
		}
	} );

	// ------------------------------------------------------------------
	// Public API
	// ------------------------------------------------------------------

	window.BltGalleryAdmin = {
		initGalleryList,
		initGalleryEditor,
		initSettings,
		initImporter,
	};

} )();
