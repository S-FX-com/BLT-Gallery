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

	// Mirrors R2Storage::is_public_url_safe() on the server.
	function isR2DevPublicUrl( value ) {
		const v = String( value || '' ).trim();
		if ( ! v ) return false;
		const normalized = /^https?:\/\//i.test( v ) ? v : 'https://' + v.replace( /^\/+/, '' );
		try {
			const host = new URL( normalized ).hostname.toLowerCase();
			return host === 'r2.dev' || host.endsWith( '.r2.dev' );
		} catch {
			return false;
		}
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

		const rows = galleries.map( ( g ) => {
			const albums = Array.isArray( g.settings && g.settings.albums )
				? g.settings.albums
				: ( g.settings && g.settings.category ? [ g.settings.category ] : [] );
			const date   = ( g.settings && g.settings.gallery_date ) || g.created_at;
			const shortcode = `[blt_gallery id="${ g.id }"]`;
			return `
				<tr>
					<td><strong><a href="${ escAttr( listUrl + '&action=edit&gallery_id=' + g.id ) }">${ escHtml( g.title ) }</a></strong></td>
					<td>${ escHtml( g.display_type ) }</td>
					<td>${ albums.length
						? albums.map( ( a ) => `<span class="bltgallery-cat-pill">${ escHtml( a ) }</span>` ).join( ' ' )
						: '<span class="bltgallery-muted">—</span>' }</td>
					<td>
						<button type="button" class="bltgallery-shortcode-copy" data-copy="${ escAttr( shortcode ) }" title="Click to copy">
							<code>${ escHtml( shortcode ) }</code>
						</button>
					</td>
					<td>${ escHtml( date ? new Date( date ).toLocaleDateString() : '' ) }</td>
					<td>
						<a href="${ escAttr( listUrl + '&action=edit&gallery_id=' + g.id ) }" class="button button-secondary">Edit</a>
						<button class="button bltgallery-delete-btn" data-id="${ g.id }" data-title="${ escAttr( g.title ) }">Delete</button>
					</td>
				</tr>
			`;
		} ).join( '' );

		container.innerHTML = `
			<table class="wp-list-table widefat fixed striped bltgallery-table">
				<thead>
					<tr>
						<th>Title</th>
						<th>Display Type</th>
						<th>Album</th>
						<th>Shortcode</th>
						<th>Date</th>
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

		container.querySelectorAll( '.bltgallery-shortcode-copy' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => copyToClipboard( btn.dataset.copy, btn ) );
		} );
	}

	/**
	 * Promotes a static <code> element into a click-to-copy button so we
	 * can reuse it from the gallery editor header.
	 */
	function makeCopyable( el ) {
		if ( ! el ) return;
		el.setAttribute( 'role', 'button' );
		el.setAttribute( 'tabindex', '0' );
		el.classList.add( 'bltgallery-copyable' );
		el.title = 'Click to copy';
		const fire = () => copyToClipboard( el.textContent, el );
		el.addEventListener( 'click', fire );
		el.addEventListener( 'keydown', ( e ) => {
			if ( e.key === 'Enter' || e.key === ' ' ) { e.preventDefault(); fire(); }
		} );
	}

	async function copyToClipboard( text, sourceEl ) {
		if ( ! text ) return;
		try {
			await navigator.clipboard.writeText( text );
			if ( sourceEl ) {
				sourceEl.classList.add( 'is-copied' );
				const label = sourceEl.dataset.flashLabel || 'Copied!';
				const original = sourceEl.innerHTML;
				sourceEl.innerHTML = `<code>${ escHtml( label ) }</code>`;
				setTimeout( () => {
					sourceEl.classList.remove( 'is-copied' );
					sourceEl.innerHTML = original;
				}, 1200 );
			}
		} catch {
			// Clipboard API unavailable — silent fallback.
		}
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
		if ( shortcodeEl ) {
			shortcodeEl.textContent = `[blt_gallery id="${ galleryId }"]`;
			makeCopyable( shortcodeEl );
		}

		renderEditorSettings( settingsEl, gallery, galleryId );
		renderImageGrid( gridEl, images, galleryId );
		renderAlbumsMetabox( gallery, galleryId );
		initUploader( galleryId, ( newImage ) => {
			images.push( newImage );
			renderImageGrid( gridEl, images, galleryId );
		} );
	}

	// ------------------------------------------------------------------
	// Albums sidebar metabox on the gallery editor
	// ------------------------------------------------------------------

	async function renderAlbumsMetabox( gallery, galleryId ) {
		const box = document.getElementById( 'bltgallery-albums-metabox' );
		if ( ! box ) return;

		const selected = new Set( normaliseGalleryAlbums( gallery ) );

		let albums = [];
		try {
			albums = await api( '/albums' );
		} catch ( e ) {
			box.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		box.innerHTML = `
			<p class="description">Tick the albums this gallery belongs to. Render them with <code>[blt_album category="slug"]</code>.</p>
			<ul class="bltgallery-album-list" id="zyg-album-list">
				${ albums.length
					? albums.map( ( a ) => `
						<li>
							<label>
								<input type="checkbox" value="${ escAttr( a.slug ) }"${ selected.has( a.slug ) ? ' checked' : '' }>
								<span>${ escHtml( a.name ) }</span>
								<small>(${ a.gallery_count || 0 })</small>
							</label>
						</li>
					` ).join( '' )
					: `<li class="bltgallery-muted">No albums yet.</li>`
				}
			</ul>
			<details class="bltgallery-album-add">
				<summary>+ Add new album</summary>
				<div class="bltgallery-album-add__row">
					<input type="text" id="zyg-new-album" placeholder="Album name" class="regular-text">
					<button type="button" class="button" id="zyg-add-album">Add</button>
				</div>
			</details>
			<div class="bltgallery-album-save">
				<button type="button" class="button button-primary" id="zyg-save-albums">Save album assignments</button>
			</div>
		`;

		const list = box.querySelector( '#zyg-album-list' );

		box.querySelector( '#zyg-add-album' ).addEventListener( 'click', async () => {
			const input = box.querySelector( '#zyg-new-album' );
			const name  = input.value.trim();
			if ( ! name ) { input.focus(); return; }
			try {
				const album = await api( '/albums', { method: 'POST', body: { name } } );
				// Insert into the list, pre-selected.
				const li = document.createElement( 'li' );
				li.innerHTML = `
					<label>
						<input type="checkbox" value="${ escAttr( album.slug ) }" checked>
						<span>${ escHtml( album.name ) }</span>
						<small>(0)</small>
					</label>
				`;
				const muted = list.querySelector( '.bltgallery-muted' );
				if ( muted ) muted.remove();
				list.appendChild( li );
				input.value = '';
				input.focus();
			} catch ( err ) {
				showNotice( err.message, 'error' );
			}
		} );

		box.querySelector( '#zyg-save-albums' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const slugs = [ ...box.querySelectorAll( 'input[type="checkbox"]:checked' ) ]
				.map( ( cb ) => cb.value );
			try {
				await api( `/galleries/${ galleryId }`, {
					method: 'PUT',
					body:   { settings: { albums: slugs } },
				} );
				showNotice( 'Album assignments saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save album assignments';
			}
		} );
	}

	function normaliseGalleryAlbums( gallery ) {
		const settings = gallery.settings || {};
		if ( Array.isArray( settings.albums ) ) return settings.albums;
		if ( settings.category )                return [ settings.category ];
		return [];
	}

	function renderEditorSettings( container, gallery, galleryId ) {
		const settings   = gallery.settings || {};
		const selected   = gallery.display_type || 'masonry';
		const columns    = settings.columns ?? 4;
		const galDate    = settings.gallery_date || '';
		const pagination = settings.pagination || 'off';
		const perPage    = settings.per_page ?? 24;
		const autoplay   = settings.autoplay ? '1' : '0';
		const speed      = settings.speed ?? 4000;

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
				<label for="zyg-gallery-date">Date (optional)</label>
				<input type="date" id="zyg-gallery-date" value="${ escHtml( galDate ) }">
				<p class="description">Rendered above the gallery using your site's date format and used for sorting albums by date.</p>
			</div>
			<div class="bltgallery-field">
				<span class="bltgallery-field__label">Display Type</span>
				<div class="bltgallery-type-cards" role="radiogroup" aria-label="Display type">
					${ typeCards }
				</div>
			</div>
			<div class="bltgallery-field" id="zyg-cols-row">
				<label for="zyg-columns">Columns</label>
				<input type="number" id="zyg-columns" class="small-text" min="1" max="8" value="${ columns }">
				<p class="description">Target column count on desktop. The grid still reorganises responsively on narrower screens.</p>
			</div>
			<div class="bltgallery-field" id="zyg-pagination-row">
				<label for="zyg-pagination">Pagination</label>
				<select id="zyg-pagination">
					<option value="off"${ pagination === 'off' ? ' selected' : '' }>Off — load all at once</option>
					<option value="load-more"${ pagination === 'load-more' ? ' selected' : '' }>Load more button</option>
					<option value="numbered"${ pagination === 'numbered' ? ' selected' : '' }>Numbered pages</option>
					<option value="infinite"${ pagination === 'infinite' ? ' selected' : '' }>Infinite scroll</option>
				</select>
				<span id="zyg-per-page-wrap" style="margin-left:1rem">
					<label for="zyg-per-page">Per page</label>
					<input type="number" id="zyg-per-page" class="small-text" min="1" max="200" value="${ perPage }">
				</span>
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

		const cardsWrap   = container.querySelector( '.bltgallery-type-cards' );
		const colsRow     = container.querySelector( '#zyg-cols-row' );
		const pagRow      = container.querySelector( '#zyg-pagination-row' );
		const ssRow       = container.querySelector( '#zyg-slideshow-row' );
		const paginationS = container.querySelector( '#zyg-pagination' );
		const perPageWrap = container.querySelector( '#zyg-per-page-wrap' );

		function selectedType() {
			return cardsWrap.querySelector( 'input[name="bltgallery-display-type"]:checked' )?.value || 'masonry';
		}

		function updateTypeVisibility() {
			const type    = selectedType();
			const isSlide = type === 'slideshow';
			colsRow.style.display = isSlide ? 'none' : '';
			pagRow.style.display  = isSlide ? 'none' : '';
			ssRow.style.display   = isSlide ? '' : 'none';
		}

		// "Per page" only matters when pagination is on.
		function updatePerPageVisibility() {
			perPageWrap.style.display = ( paginationS.value === 'off' ) ? 'none' : '';
		}

		cardsWrap.addEventListener( 'change', ( e ) => {
			if ( e.target.name !== 'bltgallery-display-type' ) return;
			cardsWrap.querySelectorAll( '.bltgallery-type-card' ).forEach( ( el ) => {
				el.classList.toggle( 'is-selected', el.dataset.type === e.target.value );
			} );
			updateTypeVisibility();
		} );
		paginationS.addEventListener( 'change', updatePerPageVisibility );

		updateTypeVisibility();
		updatePerPageVisibility();

		container.querySelector( '#zyg-save-btn' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			const type      = selectedType();
			const isSlide   = type === 'slideshow';
			const dateValue = container.querySelector( '#zyg-gallery-date' ).value;

			const baseSettings = {
				gallery_date: dateValue,
			};

			const body = {
				title:        container.querySelector( '#zyg-title' ).value,
				description:  container.querySelector( '#zyg-description' ).value,
				display_type: type,
				settings: isSlide
					? { ...baseSettings,
						autoplay: container.querySelector( '#zyg-autoplay' ).value === '1',
						speed:    parseInt( container.querySelector( '#zyg-speed' ).value, 10 ),
					}
					: { ...baseSettings,
						columns:    parseInt( container.querySelector( '#zyg-columns' ).value, 10 ),
						pagination: paginationS.value,
						per_page:   parseInt( container.querySelector( '#zyg-per-page' ).value, 10 ),
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
				<img src="${ escAttr( img.thumb_url || img.url ) }" alt="${ escAttr( img.alt_text || img.filename ) }" loading="lazy" width="100" height="100">
				<div class="bltgallery-image-grid__meta">
					<span class="bltgallery-image-grid__name" title="${ escAttr( img.filename ) }">${ escHtml( img.title || img.alt_text || img.filename ) }</span>
					<div class="bltgallery-image-grid__actions">
						<button class="button button-secondary button-small zyg-img-edit" aria-label="Edit ${ escAttr( img.filename ) }">Edit</button>
						<button class="button-link-delete zyg-img-delete" aria-label="Delete ${ escAttr( img.filename ) }">&times;</button>
					</div>
				</div>
			`;

			const nameEl = li.querySelector( '.bltgallery-image-grid__name' );

			li.querySelector( '.zyg-img-edit' ).addEventListener( 'click', () => {
				openImageEditor( img, galleryId, ( updated ) => {
					// Keep the in-memory model in sync so re-opens show fresh values.
					img.title    = updated.title;
					img.alt_text = updated.alt_text;
					img.caption  = updated.caption;
					nameEl.textContent = updated.title || updated.alt_text || updated.filename;
				} );
			} );

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

	function escAttr( s ) { return escHtml( s ).replace( /"/g, '&quot;' ); }

	// ------------------------------------------------------------------
	// Image edit modal — opened by the Edit button on a tile.
	// ------------------------------------------------------------------

	function openImageEditor( img, galleryId, onSaved ) {
		const dialog = document.getElementById( 'bltgallery-image-modal' );
		if ( ! dialog ) return;

		const form    = dialog.querySelector( '#bltgallery-image-form' );
		const thumb   = dialog.querySelector( '#bltgallery-image-modal-thumb' );
		const title   = dialog.querySelector( '#bltgallery-image-modal-title' );
		const alt     = dialog.querySelector( '#bltgallery-image-modal-alt' );
		const caption = dialog.querySelector( '#bltgallery-image-modal-caption' );
		const submit  = dialog.querySelector( '#bltgallery-image-modal-save' );

		thumb.src      = img.thumb_url || img.url || '';
		thumb.alt      = img.alt_text || img.filename || '';
		title.value    = img.title    || '';
		alt.value      = img.alt_text || '';
		caption.value  = img.caption  || '';
		title.placeholder = img.filename || '';

		dialog.querySelectorAll( '[data-close]' ).forEach( ( btn ) => {
			btn.onclick = () => dialog.close();
		} );

		// Replace any prior submit handler so we don't double-save.
		form.onsubmit = async ( e ) => {
			e.preventDefault();
			submit.disabled = true;
			const originalText = submit.textContent;
			submit.textContent = 'Saving…';
			try {
				const updated = await api( `/galleries/${ galleryId }/images/${ img.id }`, {
					method: 'PATCH',
					body:   {
						title:    title.value.trim(),
						alt_text: alt.value.trim(),
						caption:  caption.value,
					},
				} );
				onSaved( updated );
				dialog.close();
				showNotice( 'Image updated.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				submit.disabled = false;
				submit.textContent = originalText;
			}
		};

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
			setTimeout( () => title.focus(), 50 );
		} else {
			dialog.setAttribute( 'open', '' );
			title.focus();
		}
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
		const cfEl  = document.getElementById( 'bltgallery-cf-images-settings' );
		const upEl  = document.getElementById( 'bltgallery-updates-settings' );

		if ( ! genEl && ! awsEl && ! r2El && ! cfEl && ! upEl ) return;

		let general, aws, r2, cfImages, updates;
		try {
			[ general, aws, r2, cfImages, updates ] = await Promise.all( [
				api( '/settings' ),
				api( '/settings/aws' ),
				api( '/settings/r2' ),
				api( '/settings/cf-images' ),
				api( '/updates/status' ),
			] );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			return;
		}

		// Renders happen first so the panels exist in the DOM before
		// visibility is applied.
		renderGeneralSettings( genEl, general );
		renderAwsSettings( awsEl, aws );
		renderR2Settings( r2El, r2 );
		renderCfImagesSettings( cfEl, cfImages );
		renderUpdatesSettings( upEl, updates );

		applyIntegrationVisibility( {
			s3:        !! general.enable_s3,
			r2:        !! general.enable_r2,
			cf_images: !! general.enable_cf_images,
		} );
	}

	function renderIntegrationCard( id, title, descHtml, enabled ) {
		const label = escHtml( title );
		return `
			<div class="bltgallery-integration" data-enabled="${ enabled ? 'true' : 'false' }">
				<input type="checkbox" id="${ id }" class="bltgallery-integration__input"${ enabled ? ' checked' : '' } tabindex="-1" aria-hidden="true">
				<div class="bltgallery-integration__text">
					<span class="bltgallery-integration__label">${ label }</span>
					<span class="bltgallery-integration__desc">${ descHtml }</span>
				</div>
				<div class="bltgallery-integration__toggle" role="group" aria-label="${ label }">
					<button type="button" class="bltgallery-pill bltgallery-pill--enable${ enabled ? ' is-active' : '' }" data-target="${ id }" data-value="1" aria-pressed="${ enabled ? 'true' : 'false' }">Enable</button>
					<button type="button" class="bltgallery-pill bltgallery-pill--disable${ enabled ? '' : ' is-active' }" data-target="${ id }" data-value="0" aria-pressed="${ enabled ? 'false' : 'true' }">Disable</button>
				</div>
			</div>
		`;
	}

	function bindIntegrationPills( container ) {
		container.querySelectorAll( '.bltgallery-integration' ).forEach( ( card ) => {
			const input = card.querySelector( '.bltgallery-integration__input' );
			const pills = card.querySelectorAll( '.bltgallery-pill' );
			pills.forEach( ( pill ) => {
				pill.addEventListener( 'click', () => {
					const next = pill.dataset.value === '1';
					if ( input.checked === next ) return;
					input.checked = next;
					card.dataset.enabled = next ? 'true' : 'false';
					pills.forEach( ( p ) => {
						const active = ( p.dataset.value === '1' ) === next;
						p.classList.toggle( 'is-active', active );
						p.setAttribute( 'aria-pressed', active ? 'true' : 'false' );
					} );
					input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
				} );
			} );
		} );
	}

	/**
	 * Per-integration panel visibility. The Settings page renders all
	 * panels; we just toggle `.hidden` on the wrapping .bltgallery-panel.
	 */
	function applyIntegrationVisibility( flags ) {
		const map = {
			s3:        'bltgallery-aws-settings',
			r2:        'bltgallery-r2-settings',
			cf_images: 'bltgallery-cf-images-settings',
		};
		Object.entries( map ).forEach( ( [ key, panelId ] ) => {
			const body  = document.getElementById( panelId );
			const panel = body?.closest( '.bltgallery-panel' );
			if ( ! panel ) return;
			panel.hidden = ! flags[ key ];
		} );
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

			<div class="bltgallery-field">
				<span class="bltgallery-field__label">Integrations</span>
				<p class="description">Enable a service to reveal its configuration panel below. Disabled services stay hidden.</p>
				<div class="bltgallery-integration-row">
					${ renderIntegrationCard( 'zyg-enable-s3', 'Amazon S3', 'Offload uploads to an S3 bucket (optionally with CloudFront).', !! g.enable_s3 ) }
					${ renderIntegrationCard( 'zyg-enable-r2', 'Cloudflare R2', 'S3-compatible storage with no egress fees.', !! g.enable_r2 ) }
					${ renderIntegrationCard( 'zyg-enable-cf-images', 'Cloudflare Image Resizing', 'Serve every image through <code>/cdn-cgi/image/</code> for on-the-fly resize + AVIF/WebP.', !! g.enable_cf_images ) }
				</div>
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

		bindIntegrationPills( container );

		// Live preview: flipping a pill immediately shows/hides its panel.
		const toggles = {
			s3:        container.querySelector( '#zyg-enable-s3' ),
			r2:        container.querySelector( '#zyg-enable-r2' ),
			cf_images: container.querySelector( '#zyg-enable-cf-images' ),
		};
		const reflectVisibility = () => applyIntegrationVisibility( {
			s3:        toggles.s3.checked,
			r2:        toggles.r2.checked,
			cf_images: toggles.cf_images.checked,
		} );
		Object.values( toggles ).forEach( ( cb ) => cb.addEventListener( 'change', reflectVisibility ) );

		container.querySelector( '#zyg-save-general' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			try {
				const next = await api( '/settings', { method: 'POST', body: {
					default_display_type:     container.querySelector( '#zyg-default-display' ).value,
					enable_s3:                toggles.s3.checked,
					enable_r2:                toggles.r2.checked,
					enable_cf_images:         toggles.cf_images.checked,
					lazy_load:                container.querySelector( '#zyg-lazy-load' ).checked,
					webp_quality:             parseInt( container.querySelector( '#zyg-webp-quality' ).value, 10 ),
					delete_data_on_uninstall: container.querySelector( '#zyg-delete-data' ).checked,
				} } );
				showNotice( 'General settings saved.' );
				applyIntegrationVisibility( {
					s3:        !! next.enable_s3,
					r2:        !! next.enable_r2,
					cf_images: !! next.enable_cf_images,
				} );
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
		const persistedUnsafe = isR2DevPublicUrl( a.public_url );
		container.innerHTML = `
			<p>Store original files on <strong>Cloudflare R2</strong> to minimise local disk usage.
			R2 is S3-compatible and has no egress fees.</p>

			${ persistedUnsafe ? `
			<div class="notice notice-warning inline" style="margin:0 0 1rem">
				<p><strong>Your saved Public Base URL uses <code>r2.dev</code>.</strong>
				Microsoft Defender, Teams Safe Links, and similar scanners block these hostnames
				because they are heavily abused by phishing campaigns, which can prevent your galleries
				from previewing or loading in those products. Existing uploads continue to work, but
				new uploads should use a custom domain. Follow the steps below, then update the
				Public Base URL field.</p>
			</div>
			` : '' }

			<div class="notice notice-info inline" style="margin:0 0 1rem">
				<p style="margin-top:0"><strong>Required: connect a custom domain to your bucket.</strong>
				The plugin will not accept Cloudflare&rsquo;s default <code>pub-&hellip;.r2.dev</code> URL
				because it is broadly blocked by Microsoft security scanners.</p>
				<ol style="margin:0 0 0 1.5em">
					<li>Open your bucket in the Cloudflare dashboard &rarr;
						<strong>Settings</strong> &rarr; <strong>Custom Domains</strong> &rarr;
						<strong>Connect Domain</strong>.</li>
					<li>Enter a subdomain on a Cloudflare-managed zone (e.g.
						<code>cdn.yourdomain.com</code> or <code>images.yourdomain.com</code>).</li>
					<li>Cloudflare adds the DNS record and provisions an SSL certificate automatically
						(usually 1&ndash;2 minutes).</li>
					<li>Paste the resulting URL into <strong>Public Base URL</strong> below
						(e.g. <code>https://cdn.yourdomain.com</code>) and save.</li>
				</ol>
				<p style="margin-bottom:0"><a href="https://developers.cloudflare.com/r2/buckets/public-buckets/#custom-domains" target="_blank" rel="noopener">Cloudflare R2 custom domain documentation &rarr;</a></p>
			</div>

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
				<input type="text" id="zyg-r2-public-url" class="regular-text" value="${ escHtml( a.public_url ?? '' ) }" placeholder="https://cdn.yourdomain.com">
				<p class="description">The custom domain you connected to your R2 bucket. <code>pub-&hellip;.r2.dev</code> URLs are not permitted.</p>
				<p id="zyg-r2-public-url-error" class="description" style="color:#b32d2e;display:none;margin-top:.35em">
					This looks like a <code>pub-&hellip;.r2.dev</code> URL. Connect a custom domain to your bucket (see steps above) and enter that hostname instead.
				</p>
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

			<div id="zyg-r2-migrate-card" style="margin-top:1.5rem;display:none">
				<h3 style="margin-bottom:.5rem">Migrate existing image URLs</h3>
				<div id="zyg-r2-migrate-body"></div>
			</div>
		`;

		const publicUrlInput = container.querySelector( '#zyg-r2-public-url' );
		const publicUrlError = container.querySelector( '#zyg-r2-public-url-error' );
		const saveBtn        = container.querySelector( '#zyg-save-r2' );
		const validatePublicUrl = () => {
			const bad = isR2DevPublicUrl( publicUrlInput.value );
			publicUrlError.style.display = bad ? '' : 'none';
			publicUrlInput.style.borderColor = bad ? '#b32d2e' : '';
			saveBtn.disabled = bad;
		};
		publicUrlInput.addEventListener( 'input', validatePublicUrl );
		validatePublicUrl();

		saveBtn.addEventListener( 'click', async ( e ) => {
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
				renderR2MigrationCard( container );
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

		renderR2MigrationCard( container );
	}

	// ------------------------------------------------------------------
	// R2 URL Migration (rewrites stored r2.dev URLs to custom domain)
	// ------------------------------------------------------------------

	async function renderR2MigrationCard( container ) {
		const card = container.querySelector( '#zyg-r2-migrate-card' );
		const body = container.querySelector( '#zyg-r2-migrate-body' );
		if ( ! card || ! body ) return;

		let status;
		try {
			status = await api( '/settings/r2/migrate-urls' );
		} catch ( err ) {
			// Endpoint not available or transient error — keep the card hidden.
			return;
		}

		if ( ! status || ! status.unsafe_count ) {
			card.style.display = 'none';
			return;
		}

		card.style.display = '';

		if ( ! status.ready ) {
			body.innerHTML = `
				<div class="notice notice-warning inline" style="margin:0">
					<p><strong>${ escHtml( status.unsafe_count ) } image${ status.unsafe_count === 1 ? '' : 's' }</strong>
					still reference <code>${ escHtml( status.sample_from_host || 'pub-….r2.dev' ) }</code>.
					${ escHtml( status.blocked_reason || '' ) }</p>
				</div>`;
			return;
		}

		const samplesHtml = ( status.samples || [] ).map( ( s ) => `
			<li style="margin-bottom:.5em;font-family:monospace;font-size:12px;word-break:break-all">
				<span style="color:#b32d2e">${ escHtml( s.before ) }</span><br>
				<span style="color:#1e8a3e">${ escHtml( s.after ) }</span>
			</li>` ).join( '' );

		body.innerHTML = `
			<p>Found <strong>${ escHtml( status.unsafe_count ) } image${ status.unsafe_count === 1 ? '' : 's' }</strong>
			whose URLs still point at <code>${ escHtml( status.sample_from_host || 'pub-….r2.dev' ) }</code>.
			They will be rewritten to <code>${ escHtml( status.target_url ) }</code>.</p>
			${ samplesHtml ? `<p style="margin-bottom:.25em"><strong>Preview:</strong></p><ul style="margin:0 0 .75em 1.25em">${ samplesHtml }</ul>` : '' }
			<p style="color:#646970;font-size:13px"><strong>Tip:</strong> back up your database before running. The change rewrites the
			<code>cloudfront_url</code> column and <code>meta.thumbs.*.url</code> values on every affected row.</p>
			<div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap">
				<button class="button button-primary" id="zyg-r2-migrate-run">Migrate ${ escHtml( status.unsafe_count ) } URL${ status.unsafe_count === 1 ? '' : 's' }</button>
				<span id="zyg-r2-migrate-progress" style="color:#646970"></span>
			</div>
		`;

		container.querySelector( '#zyg-r2-migrate-run' ).addEventListener( 'click', async ( e ) => {
			if ( ! window.confirm( 'Rewrite all r2.dev image URLs to your custom domain? This updates the database in place. Make sure you have a backup.' ) ) {
				return;
			}
			const btn      = e.target;
			const progress = container.querySelector( '#zyg-r2-migrate-progress' );
			btn.disabled = true;
			let totalUpdated = 0;
			let cursor       = 0;
			let safety       = 10000; // hard ceiling so a logic bug can't loop forever
			try {
				while ( safety-- > 0 ) {
					btn.textContent = `Migrating… (${ totalUpdated } done)`;
					const res = await api( '/settings/r2/migrate-urls', { method: 'POST', body: { batch_size: 100, cursor } } );
					totalUpdated += Number( res.updated || 0 );
					cursor = Number( res.next_cursor || cursor );
					progress.textContent = `${ totalUpdated } updated, ${ res.remaining } remaining`;
					if ( ! res.more ) break;
				}
				btn.textContent = 'Migrate URLs';
				progress.innerHTML = `<span style="color:#1e8a3e">&#10003; Migrated ${ totalUpdated } URL${ totalUpdated === 1 ? '' : 's' }.</span>`;
				showNotice( `Migration complete. ${ totalUpdated } image URL${ totalUpdated === 1 ? '' : 's' } updated.` );
				// Re-render the card to reflect the new (zero) count.
				setTimeout( () => renderR2MigrationCard( container ), 800 );
			} catch ( err ) {
				progress.innerHTML = `<span style="color:#b32d2e">${ escHtml( err.message ) }</span>`;
				btn.disabled = false;
				btn.textContent = 'Migrate URLs';
			}
		} );
	}

	// ------------------------------------------------------------------
	// Gallery Importers (NextGEN, Modula)
	// ------------------------------------------------------------------

	async function initImporter() {
		// Imagely NextGEN Gallery.
		await initSourceImporter( {
			containerId: 'bltgallery-nextgen-importer',
			endpoint:    'nextgen',
			sourceName:  'NextGEN Gallery',
			setupHint:   'Install and activate <strong>Imagely NextGEN Gallery</strong> and create at least one gallery, then return to this page.',
			detectedMsg: 'NextGEN Gallery detected. Select galleries to import below.',
			note:        'Your original NextGEN Gallery data and files will not be modified. BltGallery copies files into its own upload directory.',
			idKey:       'gid',
			titleFor:    ( g ) => g.title || g.name,
			descFor:     ( g ) => g.galdesc,
			onImported:  ( result ) => {
				// Reveal the cleanup panel if anything was actually migrated.
				if ( result.images_imported > 0 ) initCleanup();
			},
		} );

		// If any BltGallery galleries already exist, surface the cleanup
		// panel right away so the user can revisit the page later to back
		// up / remove the legacy NextGEN files without re-migrating.
		try {
			const existing = await api( '/galleries?per_page=1' );
			if ( Array.isArray( existing ) && existing.length > 0 ) {
				initCleanup();
			}
		} catch {}

		// Modula.
		await initSourceImporter( {
			containerId: 'bltgallery-modula-importer',
			endpoint:    'modula',
			sourceName:  'Modula',
			setupHint:   'Install and activate <strong>Modula</strong> and create at least one gallery, then return to this page.',
			detectedMsg: 'Modula galleries detected. Select galleries to import below.',
			note:        'Your original Modula galleries and media-library files are left untouched. BltGallery copies each image into its own upload directory.',
			idKey:       'id',
			titleFor:    ( g ) => g.title,
			descFor:     ( g ) => g.description,
		} );
	}

	/**
	 * Drive a single migration source (status → preview → run) inside its
	 * own panel. `opts` describes the source-specific endpoint, labels, and
	 * the shape of each preview row.
	 */
	async function initSourceImporter( opts ) {
		const container = document.getElementById( opts.containerId );
		if ( ! container ) return;

		let status;
		try {
			status = await api( `/import/${ opts.endpoint }/status` );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		if ( ! status.available ) {
			container.innerHTML = `
				<div class="notice notice-warning inline"><p>${ escHtml( status.message ) }</p></div>
				<p>${ opts.setupHint }</p>
			`;
			return;
		}

		let preview;
		try {
			preview = await api( `/import/${ opts.endpoint }/preview` );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		renderImporterGalleryList( container, preview.galleries, opts );
	}

	function renderImporterGalleryList( container, galleries, opts ) {
		if ( ! galleries || galleries.length === 0 ) {
			container.innerHTML = `<p>No galleries found in ${ escHtml( opts.sourceName ) }.</p>`;
			return;
		}

		const rows = galleries.map( ( g ) => `
			<tr>
				<td><input type="checkbox" class="zyg-import-check" value="${ escHtml( String( g[ opts.idKey ] ) ) }" checked></td>
				<td><strong>${ escHtml( opts.titleFor( g ) || '' ) }</strong></td>
				<td>${ escHtml( opts.descFor( g ) || '—' ) }</td>
				<td>${ escHtml( String( g.image_count ) ) }</td>
			</tr>
		` ).join( '' );

		container.innerHTML = `
			<div class="notice notice-success inline"><p>${ escHtml( opts.detectedMsg ) }</p></div>
			<p><strong>Note:</strong> ${ escHtml( opts.note ) }</p>
			<table class="wp-list-table widefat fixed striped bltgallery-table" style="margin-bottom:1rem">
				<thead>
					<tr>
						<th style="width:40px"><input type="checkbox" class="zyg-import-check-all" checked></th>
						<th>Gallery Title</th>
						<th>Description</th>
						<th>Images</th>
					</tr>
				</thead>
				<tbody>${ rows }</tbody>
			</table>
			<div style="display:flex;gap:12px;align-items:center">
				<button class="button button-primary zyg-run-import">Import Selected Galleries</button>
				<span class="zyg-import-status"></span>
			</div>
			<div class="zyg-import-results"></div>
		`;

		// Select all toggle.
		container.querySelector( '.zyg-import-check-all' ).addEventListener( 'change', ( e ) => {
			container.querySelectorAll( '.zyg-import-check' ).forEach( ( cb ) => {
				cb.checked = e.target.checked;
			} );
		} );

		container.querySelector( '.zyg-run-import' ).addEventListener( 'click', async ( e ) => {
			const checked = [ ...container.querySelectorAll( '.zyg-import-check:checked' ) ];
			if ( checked.length === 0 ) {
				showNotice( 'Please select at least one gallery to import.', 'error' );
				return;
			}

			const gallery_ids = checked.map( ( cb ) => parseInt( cb.value, 10 ) );

			e.target.disabled = true;
			const statusEl  = container.querySelector( '.zyg-import-status' );
			const resultsEl = container.querySelector( '.zyg-import-results' );
			statusEl.textContent = 'Importing… this may take a while for large galleries.';
			resultsEl.innerHTML  = '';

			try {
				const result = await api( `/import/${ opts.endpoint }/run`, {
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
							Migration complete: <strong>${ result.galleries_imported }</strong> ${ result.galleries_imported === 1 ? 'gallery' : 'galleries' } migrated,
							<strong>${ result.images_imported }</strong> ${ result.images_imported === 1 ? 'image' : 'images' } migrated
							${ result.images_skipped > 0 ? `, ${ result.images_skipped } skipped` : '' }.
						</p>
					</div>
					${ errorHtml }
				`;

				showNotice( `Migration complete. ${ result.images_imported } image(s) migrated.` );

				if ( typeof opts.onImported === 'function' ) {
					opts.onImported( result );
				}
			} catch ( err ) {
				statusEl.textContent = '';
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
			}
		} );
	}

	// ------------------------------------------------------------------
	// NextGEN cleanup (post-migration)
	// ------------------------------------------------------------------

	async function initCleanup() {
		const panel = document.getElementById( 'bltgallery-nextgen-cleanup-panel' );
		const body  = document.getElementById( 'bltgallery-nextgen-cleanup' );
		if ( ! panel || ! body ) return;

		panel.hidden = false;
		body.innerHTML = '<p class="bltgallery-loading">Scanning NextGEN files on disk…</p>';

		let scan;
		try {
			scan = await api( '/import/nextgen/scan' );
		} catch ( e ) {
			body.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		renderCleanup( body, scan );
	}

	function renderCleanup( body, scan ) {
		if ( ! scan.total_files ) {
			body.innerHTML = `
				<div class="notice notice-info inline">
					<p>No NextGEN files remain on disk. Nothing to clean up.</p>
				</div>
			`;
			return;
		}

		const rows = scan.galleries.map( ( g ) => `
			<tr>
				<td><strong>${ escHtml( g.title ) }</strong></td>
				<td><code>${ escHtml( g.path ) }</code></td>
				<td>${ g.exists ? g.files.toLocaleString() : '—' }</td>
				<td>${ g.exists ? formatBytes( g.bytes ) : '<em>missing</em>' }</td>
			</tr>
		` ).join( '' );

		body.innerHTML = `
			<div class="notice notice-warning inline" style="margin:0 0 1rem">
				<p>
					<strong>⚠️ This permanently deletes the original NextGEN Gallery files from this server.</strong>
					Your migrated copies live in BltGallery's own storage and are unaffected, but
					reinstalling NextGEN won't bring these files back. <strong>Take a backup first.</strong>
				</p>
			</div>

			<p>
				Files on disk: <strong>${ scan.total_files.toLocaleString() }</strong>
				totalling <strong>${ formatBytes( scan.total_bytes ) }</strong>
				across <strong>${ scan.galleries.filter( ( g ) => g.exists ).length }</strong> folders.
			</p>

			<table class="wp-list-table widefat fixed striped bltgallery-table" style="margin-bottom:1rem">
				<thead><tr><th>Gallery</th><th>Path</th><th>Files</th><th>Size</th></tr></thead>
				<tbody>${ rows }</tbody>
			</table>

			<div class="bltgallery-cleanup__actions">
				<button class="button button-secondary" id="zyg-backup-btn">
					📦 Download backup ZIP first
				</button>
				<button class="button" id="zyg-delete-btn-stage1">
					🗑️ I have a backup — delete files
				</button>
			</div>
			<div id="zyg-backup-result"></div>
			<div id="zyg-cleanup-result"></div>

			<form class="bltgallery-cleanup__confirm" id="zyg-cleanup-confirm" hidden>
				<p>
					<strong>Final confirmation.</strong> This action is irreversible.
					Type <code>DELETE</code> below to remove all NextGEN files from disk.
				</p>
				<input type="text" id="zyg-cleanup-confirm-input" autocomplete="off" placeholder="DELETE">
				<button type="submit" class="button button-primary" disabled>Delete files permanently</button>
				<button type="button" class="button button-secondary" id="zyg-cleanup-cancel">Cancel</button>
			</form>
		`;

		// --- Backup
		body.querySelector( '#zyg-backup-btn' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Creating ZIP… this may take a while.';
			const out = body.querySelector( '#zyg-backup-result' );
			out.innerHTML = '';
			try {
				const res = await api( '/import/nextgen/backup', { method: 'POST', body: {} } );
				out.innerHTML = `
					<div class="notice notice-success inline" style="margin-top:.5rem">
						<p>
							✅ Backup created: <strong>${ res.files.toLocaleString() }</strong> files
							(${ formatBytes( res.bytes ) }).
							<a href="${ escHtml( res.url ) }" download class="button button-primary" style="margin-left:.5rem">
								Download ZIP
							</a>
						</p>
					</div>
				`;
			} catch ( err ) {
				out.innerHTML = `<div class="notice notice-error inline" style="margin-top:.5rem"><p>${ escHtml( err.message ) }</p></div>`;
			} finally {
				e.target.disabled = false;
				e.target.textContent = '📦 Download backup ZIP first';
			}
		} );

		// --- Stage 1: reveal confirmation form
		const confirm  = body.querySelector( '#zyg-cleanup-confirm' );
		const cInput   = body.querySelector( '#zyg-cleanup-confirm-input' );
		const cSubmit  = confirm.querySelector( 'button[type="submit"]' );

		body.querySelector( '#zyg-delete-btn-stage1' ).addEventListener( 'click', () => {
			confirm.hidden = false;
			cInput.focus();
		} );

		body.querySelector( '#zyg-cleanup-cancel' ).addEventListener( 'click', () => {
			confirm.hidden = true;
			cInput.value   = '';
			cSubmit.disabled = true;
		} );

		cInput.addEventListener( 'input', () => {
			cSubmit.disabled = cInput.value !== 'DELETE';
		} );

		confirm.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			if ( cInput.value !== 'DELETE' ) return;
			cSubmit.disabled = true;
			cSubmit.textContent = 'Deleting…';
			const out = body.querySelector( '#zyg-cleanup-result' );
			try {
				const res = await api( '/import/nextgen/cleanup', { method: 'POST', body: { confirm: 'DELETE' } } );
				out.innerHTML = `
					<div class="notice notice-success inline" style="margin-top:1rem">
						<p>
							✅ Deleted <strong>${ res.files.toLocaleString() }</strong> files
							(${ formatBytes( res.bytes ) }) across <strong>${ res.galleries }</strong> folders.
						</p>
					</div>
				`;
				// Hide action UI; show fresh empty state.
				body.querySelector( '.bltgallery-cleanup__actions' ).style.display = 'none';
				confirm.hidden = true;
				showNotice( 'NextGEN files deleted from disk.' );
			} catch ( err ) {
				out.innerHTML = `<div class="notice notice-error inline" style="margin-top:1rem"><p>${ escHtml( err.message ) }</p></div>`;
				cSubmit.disabled = false;
				cSubmit.textContent = 'Delete files permanently';
			}
		} );
	}

	function formatBytes( n ) {
		if ( ! n ) return '0 B';
		const units = [ 'B', 'KB', 'MB', 'GB', 'TB' ];
		let i = 0;
		while ( n >= 1024 && i < units.length - 1 ) { n /= 1024; i++; }
		return `${ n.toFixed( i === 0 ? 0 : 1 ) } ${ units[ i ] }`;
	}

	// ------------------------------------------------------------------
	// Shortcodes reference page
	// ------------------------------------------------------------------

	const SHORTCODE_DOCS = [
		{
			tag: 'blt_gallery',
			title: 'Single gallery',
			intro: 'Renders one gallery. Every attribute below temporarily overrides the matching gallery setting for this placement.',
			examples: [
				`[blt_gallery id="5"]`,
				`[blt_gallery slug="weddings-2026" type="masonry" cols="4" gap="16"]`,
				`[blt_gallery id="5" type="slideshow" autoplay="1" speed="4000"]`,
				`[blt_gallery id="5" type="tile" pagination="load-more" per_page="24"]`,
				`[blt_gallery id="5" captions="hover" radius="12" lightbox="1"]`,
				`[blt_gallery id="5" date="2026-05-20"]`,
			],
			attrs: [
				[ 'id',         'int',                 'Gallery ID.' ],
				[ 'slug',       'string',              'Gallery slug — used when `id` is omitted.' ],
				[ 'type',       'masonry · tile · slideshow · lightbox', 'Override the stored display type.' ],
				[ 'cols',       '1–8',                 'Target column count at desktop width.' ],
				[ 'gap',        'px',                  'Gutter between items.' ],
				[ 'radius',     'px',                  'Per-item border radius.' ],
				[ 'size',       'small · medium · large · xlarge', 'Preset minimum tile width.' ],
				[ 'thumb_min',  'px',                  'Raw minimum tile width (advanced override).' ],
				[ 'captions',   'below · hover · off', 'Caption position.' ],
				[ 'lightbox',   '1 · 0',               'Enable click-to-lightbox on grids.' ],
				[ 'pagination', 'off · load-more · numbered · infinite', 'AJAX pagination mode.' ],
				[ 'per_page',   'int',                 'Images per page when pagination is on.' ],
				[ 'date',       'YYYY-MM-DD',          'Override the gallery’s display date.' ],
				[ 'autoplay',   '1 · 0',               'Slideshow autoplay.' ],
				[ 'speed',      'ms',                  'Slideshow autoplay interval.' ],
				[ 'arrows',     '1 · 0',               'Show slideshow nav arrows.' ],
				[ 'dots',       '1 · 0',               'Show slideshow dot indicators.' ],
				[ 'limit',      'int',                 'Cap the number of images rendered.' ],
				[ 'order',      'menu · date · random','Image sort order.' ],
				[ 'class',      'string',              'Extra CSS class on the wrapper.' ],
				[ 'style',      'string',              'Extra inline style on the wrapper.' ],
			],
		},
		{
			tag: 'blt_album',
			title: 'Album (collection of galleries)',
			intro: 'Renders a group of galleries as clickable cards. Treat an album like a category — galleries grouped by the same value in their Album/Category field show up together.',
			examples: [
				`[blt_album category="weddings" sort_by="date"]`,
				`[blt_album ids="3,7,9" style="grid" cols="3" gap="20"]`,
				`[blt_album slugs="nature,travel" style="masonry" cols="4"]`,
				`[blt_album category="portfolio" style="carousel" cols="4"]`,
				`[blt_album category="portfolio" style="accordion" gallery_type="masonry"]`,
				`[blt_album category="portfolio" sort_by="name" order="asc"]`,
			],
			attrs: [
				[ 'ids',          'comma-separated ints',     'Explicit gallery IDs to include.' ],
				[ 'slugs',        'comma-separated slugs',    'Alternative to `ids`.' ],
				[ 'category',     'string',                   'Pull every gallery whose Album/Category matches.' ],
				[ 'style',        'grid · masonry · carousel · accordion', 'Album layout.' ],
				[ 'cols',         '1–8',                      'Card grid column count.' ],
				[ 'gap',          'px',                       'Space between cards.' ],
				[ 'radius',       'px',                       'Card border radius.' ],
				[ 'captions',     'below · hover · off',      'Title placement on each card.' ],
				[ 'show_count',   '1 · 0',                    'Render "N photos" under each card.' ],
				[ 'cover',        'first · random',           'Which image becomes the card cover.' ],
				[ 'sort_by',      'menu · date · name',       'How to sort galleries within the album.' ],
				[ 'order',        'asc · desc',               'Sort direction.' ],
				[ 'gallery_type', 'see [blt_gallery] type',   'Inline display type used in accordion mode.' ],
				[ 'limit',        'int',                      'Cap number of galleries rendered.' ],
			],
		},
		{
			tag: 'blt_slider',
			title: 'Image slider',
			intro: 'Renders an image slider built in Blt Gallery → Sliders. Build it visually, then paste its shortcode. Captions, hover arrows, and a dot counter are built in. An ad-hoc source path (galleries / attachments) is also supported for code-only sliders.',
			examples: [
				`[blt_slider id="3"]`,
				`[blt_slider slug="homepage-hero"]`,
				`[blt_slider id="3" autoplay="1" speed="6000" height="60vh"]`,
				`[blt_slider galleries="5,7"]`,
				`[blt_slider attachments="123,456" arrows="0" captions="off"]`,
			],
			attrs: [
				[ 'id',          'int',                      'Saved slider ID (primary).' ],
				[ 'slug',        'string',                   'Saved slider slug (alternative to id).' ],
				[ 'galleries',   'comma-separated ints',     'Ad-hoc: gallery IDs whose images feed the slider.' ],
				[ 'images',      'comma-separated ints',     'Ad-hoc: specific Blt gallery image IDs.' ],
				[ 'attachments', 'comma-separated ints',     'Ad-hoc: WordPress media attachment IDs.' ],
				[ 'captions',    'on · off',                 'Show the subtle caption / photo credit.' ],
				[ 'arrows',      '1 · 0',                    'Show the hover-reveal nav arrows.' ],
				[ 'dots',        '1 · 0',                    'Show the dot counter.' ],
				[ 'autoplay',    '1 · 0',                    'Auto-advance slides.' ],
				[ 'speed',       'ms',                       'Autoplay interval (default 5000).' ],
				[ 'loop',        '1 · 0',                    'Wrap from the last slide back to the first.' ],
				[ 'height',      'px · vh · %',              'Max height of each slide, e.g. 70vh.' ],
				[ 'radius',      'px',                       'Slider border radius.' ],
				[ 'order',       'menu · random · reverse',  'Slide order.' ],
				[ 'limit',       'int',                      'Cap the number of slides rendered.' ],
			],
		},
	];

	function initShortcodesDoc() {
		const root = document.getElementById( 'bltgallery-shortcodes-doc' );
		if ( ! root ) return;

		root.innerHTML = SHORTCODE_DOCS.map( ( sc ) => `
			<div class="bltgallery-panel bltgallery-shortcode-doc">
				<div class="bltgallery-panel__header">
					<h2><code>[${ escHtml( sc.tag ) }]</code> — ${ escHtml( sc.title ) }</h2>
				</div>
				<div class="bltgallery-panel__body">
					<p>${ escHtml( sc.intro ) }</p>

					<h3>Examples</h3>
					<div class="bltgallery-shortcode-doc__examples">
						${ sc.examples.map( ( ex ) => `
							<div class="bltgallery-shortcode-doc__example">
								<code>${ escHtml( ex ) }</code>
								<button type="button" class="button button-secondary bltgallery-copy" data-copy="${ escHtml( ex ) }">Copy</button>
							</div>
						` ).join( '' ) }
					</div>

					<h3>Attributes</h3>
					<table class="wp-list-table widefat fixed striped bltgallery-table bltgallery-shortcode-doc__table">
						<thead>
							<tr><th style="width:18%">Attribute</th><th style="width:30%">Values</th><th>Description</th></tr>
						</thead>
						<tbody>
							${ sc.attrs.map( ( [ a, v, d ] ) => `
								<tr>
									<td><code>${ escHtml( a ) }</code></td>
									<td>${ v.split( ' · ' ).map( ( token ) => `<code>${ escHtml( token ) }</code>` ).join( ' · ' ) }</td>
									<td>${ escHtml( d ) }</td>
								</tr>
							` ).join( '' ) }
						</tbody>
					</table>
				</div>
			</div>
		` ).join( '' );

		root.addEventListener( 'click', async ( e ) => {
			const btn = e.target.closest( '.bltgallery-copy' );
			if ( ! btn ) return;
			try {
				await navigator.clipboard.writeText( btn.dataset.copy );
				const label = btn.textContent;
				btn.textContent = 'Copied!';
				setTimeout( () => { btn.textContent = label; }, 1500 );
			} catch {
				/* clipboard unavailable — silent fallback */
			}
		} );
	}

	// ------------------------------------------------------------------
	// Cloudflare Image Resizing settings
	// ------------------------------------------------------------------

	function renderCfImagesSettings( container, cf ) {
		if ( ! container ) return;
		const s = cf || {};
		container.innerHTML = `
			<p>Serve every gallery image through Cloudflare's <code>/cdn-cgi/image/</code> endpoint for on-the-fly resize and AVIF/WebP conversion. Requires a Cloudflare zone with Image Resizing enabled.</p>
			<div class="bltgallery-field">
				<label for="zyg-cfi-zone">Zone URL</label>
				<input type="url" id="zyg-cfi-zone" class="regular-text" value="${ escAttr( s.zone_url || '' ) }" placeholder="https://example.com">
				<p class="description">Your WordPress site's URL behind Cloudflare.</p>
			</div>
			<div class="bltgallery-field">
				<label for="zyg-cfi-format">Default format</label>
				<select id="zyg-cfi-format">
					${ [ 'auto', 'webp', 'avif', 'json' ].map( ( f ) => `<option value="${ f }"${ ( s.default_format || 'auto' ) === f ? ' selected' : '' }>${ f }</option>` ).join( '' ) }
				</select>
				<label for="zyg-cfi-quality" style="margin-left:1rem">Quality</label>
				<input type="number" id="zyg-cfi-quality" class="small-text" min="1" max="100" value="${ s.default_quality ?? 85 }">
				<label for="zyg-cfi-fit" style="margin-left:1rem">Fit</label>
				<select id="zyg-cfi-fit">
					${ [ 'cover', 'contain', 'scale-down', 'crop', 'pad' ].map( ( f ) => `<option value="${ f }"${ ( s.default_fit || 'cover' ) === f ? ' selected' : '' }>${ f }</option>` ).join( '' ) }
				</select>
			</div>
			<div class="bltgallery-field">
				<button class="button button-primary" id="zyg-save-cfi">Save Cloudflare Image Settings</button>
			</div>
		`;

		container.querySelector( '#zyg-save-cfi' ).addEventListener( 'click', async ( e ) => {
			e.target.disabled = true;
			e.target.textContent = 'Saving…';
			try {
				await api( '/settings/cf-images', { method: 'POST', body: {
					enabled:         true,
					zone_url:        container.querySelector( '#zyg-cfi-zone' ).value.trim(),
					default_format:  container.querySelector( '#zyg-cfi-format' ).value,
					default_quality: parseInt( container.querySelector( '#zyg-cfi-quality' ).value, 10 ),
					default_fit:     container.querySelector( '#zyg-cfi-fit' ).value,
				} } );
				showNotice( 'Cloudflare Image Resizing settings saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				e.target.disabled = false;
				e.target.textContent = 'Save Cloudflare Image Settings';
			}
		} );
	}

	// ------------------------------------------------------------------
	// Plugin update status (Settings page → Plugin Updates panel)
	// ------------------------------------------------------------------

	function renderUpdatesSettings( container, status ) {
		if ( ! container ) return;
		const paint = ( s ) => {
			container.innerHTML = renderUpdatesMarkup( s );
			container.querySelector( '#zyg-check-update' ).addEventListener( 'click', async ( e ) => {
				const btn = e.target;
				btn.disabled = true;
				btn.textContent = 'Checking…';
				try {
					const next = await api( '/updates/check', { method: 'POST' } );
					paint( next );
					showNotice( next.update_available ? `Update available: ${ next.latest_version }.` : 'Plugin is up to date.' );
				} catch ( err ) {
					showNotice( err.message, 'error' );
					btn.disabled = false;
					btn.textContent = 'Check for Updates';
				}
			} );
		};
		paint( status );
	}

	function renderUpdatesMarkup( s ) {
		s = s || {};
		const current = escHtml( s.current_version || '—' );
		const latest  = s.latest_version ? escHtml( s.latest_version ) : null;
		const checked = s.last_checked_human ? `Last checked ${ escHtml( s.last_checked_human ) }.` : 'Not yet checked.';
		let statusLine;
		if ( s.update_available && latest ) {
			const url = s.update_url || s.plugins_page || '#';
			statusLine = `<span class="bltgallery-update-available">Update available: <strong>${ latest }</strong></span> — <a href="${ escHtml( url ) }">go to Plugins page</a> to install.`;
		} else if ( latest ) {
			statusLine = `Up to date (latest: <strong>${ latest }</strong>).`;
		} else {
			statusLine = 'No update channel is configured for this plugin yet — clicking the button forces WordPress to re-poll every source it knows about.';
		}
		return `
			<div class="bltgallery-field">
				<p><strong>Installed version:</strong> ${ current }</p>
				<p>${ statusLine }</p>
				<p class="description">${ escHtml( checked ) }</p>
			</div>
			<div class="bltgallery-field">
				<button class="button button-secondary" id="zyg-check-update">Check for Updates</button>
			</div>
		`;
	}

	// ------------------------------------------------------------------
	// Albums admin page (top-level submenu under Blt Gallery)
	// ------------------------------------------------------------------

	async function initAlbumsPage() {
		const root = document.getElementById( 'bltgallery-albums-admin' );
		if ( ! root ) return;

		let albums = [];
		try {
			albums = await api( '/albums' );
		} catch ( e ) {
			root.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		root.innerHTML = `
			<div class="bltgallery-albums-admin">
				<section class="bltgallery-albums-admin__list">
					<table class="wp-list-table widefat striped bltgallery-table bltgallery-albums-table">
						<thead>
							<tr>
								<th>Name</th>
								<th>Slug</th>
								<th>Galleries</th>
								<th>Shortcode</th>
								<th class="bltgallery-albums-table__actions">Actions</th>
							</tr>
						</thead>
						<tbody id="zyg-album-rows">
							${ renderAlbumRows( albums ) }
						</tbody>
					</table>
				</section>
			</div>
		`;

		const rowsEl = root.querySelector( '#zyg-album-rows' );

		// "Add album" button lives in the page header (rendered by PHP).
		const addBtn = document.getElementById( 'bltgallery-add-album' );
		if ( addBtn ) {
			addBtn.onclick = () => openAlbumModal( 'create', { name: '', slug: '', description: '' }, ( album ) => {
				albums = albums.filter( ( a ) => a.slug !== album.slug );
				albums.push( { ...album, gallery_count: album.gallery_count || 0 } );
				albums.sort( ( a, b ) => a.name.localeCompare( b.name ) );
				rowsEl.innerHTML = renderAlbumRows( albums );
			} );
		}

		rowsEl.addEventListener( 'click', async ( e ) => {
			const del = e.target.closest( '.zyg-album-delete' );
			if ( del ) {
				const slug = del.dataset.slug;
				if ( ! window.confirm( `Delete album "${ slug }"? Galleries currently in it stay; they just lose this album.` ) ) return;
				try {
					await api( `/albums/${ slug }`, { method: 'DELETE' } );
					albums = albums.filter( ( a ) => a.slug !== slug );
					rowsEl.innerHTML = renderAlbumRows( albums );
					showNotice( 'Album deleted.' );
				} catch ( err ) {
					showNotice( err.message, 'error' );
				}
				return;
			}
			const edit = e.target.closest( '.zyg-album-edit' );
			if ( edit ) {
				const album = albums.find( ( a ) => a.slug === edit.dataset.slug );
				if ( album ) {
					openAlbumModal( 'edit', album, ( updated ) => {
						const i = albums.findIndex( ( a ) => a.slug === updated.slug );
						if ( i !== -1 ) albums[ i ] = updated;
						albums.sort( ( a, b ) => a.name.localeCompare( b.name ) );
						rowsEl.innerHTML = renderAlbumRows( albums );
					} );
				}
				return;
			}
			const copy = e.target.closest( '.bltgallery-shortcode-copy' );
			if ( copy ) {
				copyToClipboard( copy.dataset.copy, copy );
			}
		} );
	}

	/**
	 * Lightbox for creating or editing an album.
	 *
	 * - mode "create": name / slug / description only. Saving POSTs a new album.
	 * - mode "edit": rename / re-describe, plus a gallery picker. Only galleries
	 *   that are unassigned or already in this album are offered, matching the
	 *   rule that a gallery joins one album at a time from this screen.
	 */
	function openAlbumModal( mode, album, onSaved ) {
		const isEdit = 'edit' === mode;

		let dialog = document.getElementById( 'bltgallery-album-modal' );
		if ( ! dialog ) {
			dialog = document.createElement( 'dialog' );
			dialog.id = 'bltgallery-album-modal';
			dialog.className = 'bltgallery-modal bltgallery-album-modal';
			document.body.appendChild( dialog );
		}

		const slugField = isEdit
			? `<input type="text" class="regular-text" value="${ escAttr( album.slug ) }" readonly>`
			: `<input type="text" name="slug" class="regular-text" placeholder="auto-generated from name">`;

		const galleriesSection = isEdit
			? `<div class="bltgallery-album-modal__galleries">
					<p class="description">Tick the galleries that belong to this album. Galleries already assigned to another album aren't shown.</p>
					<div class="bltgallery-album-picker" id="zyg-album-picker">
						<p class="bltgallery-loading">Loading galleries…</p>
					</div>
				</div>`
			: '';

		dialog.innerHTML = `
			<form method="dialog" class="bltgallery-modal__form">
				<header class="bltgallery-modal__header">
					<h2>${ isEdit ? 'Edit album' : 'Add album' }</h2>
					<button type="button" class="bltgallery-modal__close" data-close aria-label="Close">&times;</button>
				</header>
				<div class="bltgallery-modal__body ${ isEdit ? 'bltgallery-album-modal__body' : 'bltgallery-album-modal__body--single' }">
					<div class="bltgallery-field-stack">
						<label>
							<span>Name</span>
							<input type="text" name="name" class="regular-text" value="${ escAttr( album.name || '' ) }" required>
						</label>
						<label>
							<span>Slug${ isEdit ? '' : ' (optional)' }</span>
							${ slugField }
						</label>
						<label>
							<span>Description${ isEdit ? '' : ' (optional)' }</span>
							<textarea name="description" rows="2" class="large-text">${ escHtml( album.description || '' ) }</textarea>
						</label>
					</div>
					${ galleriesSection }
				</div>
				<footer class="bltgallery-modal__footer">
					<button type="button" class="button" data-close>Cancel</button>
					<button type="submit" class="button button-primary">${ isEdit ? 'Save album' : 'Add album' }</button>
				</footer>
			</form>
		`;

		const form   = dialog.querySelector( 'form' );
		const picker = dialog.querySelector( '#zyg-album-picker' );

		dialog.querySelectorAll( '[data-close]' ).forEach( ( btn ) => {
			btn.onclick = () => dialog.close();
		} );

		if ( isEdit ) {
			// Load galleries and render the checkbox picker.
			( async () => {
				try {
					const galleries = await api( '/galleries?per_page=100' );
					picker.innerHTML = renderGalleryPicker( galleries, album.slug );
				} catch ( err ) {
					picker.innerHTML = `<p class="bltgallery-error">${ escHtml( err.message ) }</p>`;
				}
			} )();
		}

		form.onsubmit = async ( e ) => {
			e.preventDefault();
			const submit = form.querySelector( 'button[type="submit"]' );
			const name        = form.elements.name.value.trim();
			const description = form.elements.description.value.trim();
			if ( ! name ) { form.elements.name.focus(); return; }
			submit.disabled = true;
			submit.textContent = 'Saving…';
			try {
				if ( isEdit ) {
					const updated = await api( `/albums/${ album.slug }`, {
						method: 'PUT',
						body:   { name, description },
					} );
					const ids = [ ...picker.querySelectorAll( 'input[type="checkbox"]:checked' ) ]
						.map( ( cb ) => parseInt( cb.value, 10 ) );
					await api( `/albums/${ album.slug }/galleries`, {
						method: 'POST',
						body:   { gallery_ids: ids },
					} );
					onSaved( { ...album, name: updated.name, description: updated.description, gallery_count: ids.length } );
					dialog.close();
					showNotice( `Album "${ updated.name }" updated.` );
				} else {
					const created = await api( '/albums', {
						method: 'POST',
						body:   { name, slug: form.elements.slug.value.trim(), description },
					} );
					onSaved( { ...created, gallery_count: 0 } );
					dialog.close();
					showNotice( `Album "${ created.name }" added.` );
				}
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				submit.disabled = false;
				submit.textContent = isEdit ? 'Save album' : 'Add album';
			}
		};

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
			form.elements.name.focus();
		} else {
			dialog.setAttribute( 'open', '' );
			form.elements.name.focus();
		}
	}

	function renderGalleryPicker( galleries, albumSlug ) {
		const available = ( galleries || [] ).filter( ( g ) => {
			const a = normaliseGalleryAlbums( g );
			return a.length === 0 || a.includes( albumSlug );
		} );

		if ( ! available.length ) {
			return `<p class="bltgallery-muted">No galleries available — every gallery is already assigned to another album.</p>`;
		}

		return `
			<ul class="bltgallery-album-picker__list">
				${ available.map( ( g ) => {
					const inAlbum = normaliseGalleryAlbums( g ).includes( albumSlug );
					return `
						<li>
							<label>
								<input type="checkbox" value="${ g.id }"${ inAlbum ? ' checked' : '' }>
								<span>${ escHtml( g.title || ( '#' + g.id ) ) }</span>
								${ g.slug ? `<small>${ escHtml( g.slug ) }</small>` : '' }
							</label>
						</li>
					`;
				} ).join( '' ) }
			</ul>
		`;
	}

	function renderAlbumRows( albums ) {
		if ( ! albums.length ) {
			return `<tr><td colspan="5" class="bltgallery-muted">No albums yet. Add one on the left.</td></tr>`;
		}
		return albums.map( ( a ) => {
			const sc = `[blt_album category="${ a.slug }" sort_by="date"]`;
			return `
				<tr>
					<td><strong>${ escHtml( a.name ) }</strong>${ a.description ? `<br><small>${ escHtml( a.description ) }</small>` : '' }</td>
					<td><code>${ escHtml( a.slug ) }</code></td>
					<td>${ a.gallery_count || 0 }</td>
					<td>
						<button type="button" class="bltgallery-shortcode-copy" data-copy="${ escAttr( sc ) }" title="Click to copy">
							<code>${ escHtml( sc ) }</code>
						</button>
					</td>
					<td class="bltgallery-albums-table__actions">
						<button type="button" class="button zyg-album-edit" data-slug="${ escAttr( a.slug ) }">Edit</button>
						<button type="button" class="button button-link-delete zyg-album-delete" data-slug="${ escAttr( a.slug ) }">Delete</button>
					</td>
				</tr>
			`;
		} ).join( '' );
	}

	// ------------------------------------------------------------------
	// Sliders — list page
	// ------------------------------------------------------------------

	async function initSlidersPage( listUrl ) {
		const container = document.getElementById( 'bltgallery-slider-list' );
		const newBtn    = document.getElementById( 'bltgallery-new-slider-btn' );
		if ( ! container ) return;

		newBtn?.addEventListener( 'click', () => openInlineCreateSlider( newBtn, listUrl ) );
		await loadSliderList( container, listUrl );
	}

	function openInlineCreateSlider( newBtn, listUrl ) {
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
				<label class="bltgallery-inline-create__label" for="bltgallery-inline-create-title">New slider title</label>
				<div class="bltgallery-inline-create__row">
					<input id="bltgallery-inline-create-title" name="title" type="text" class="regular-text" placeholder="e.g. Homepage hero" required autocomplete="off">
					<button type="submit" class="button button-primary">Create &amp; Edit</button>
					<button type="button" class="button button-secondary" data-action="cancel">Cancel</button>
				</div>
				<p class="bltgallery-inline-create__hint description">You can add images and tweak options on the next screen.</p>
			</form>
		`;

		const notice = document.getElementById( 'bltgallery-notice' );
		notice.parentNode.insertBefore( wrap, notice.nextSibling );

		const input  = wrap.querySelector( 'input[name="title"]' );
		const submit = wrap.querySelector( 'button[type="submit"]' );
		input.focus();

		wrap.querySelector( '[data-action="cancel"]' ).addEventListener( 'click', () => wrap.remove() );

		wrap.querySelector( 'form' ).addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const title = input.value.trim();
			if ( ! title ) { input.focus(); return; }
			submit.disabled = true;
			submit.textContent = 'Creating…';
			try {
				const slider = await api( '/sliders', { method: 'POST', body: { title } } );
				window.location.href = listUrl + '&action=edit&slider_id=' + slider.id;
			} catch ( err ) {
				submit.disabled = false;
				submit.textContent = 'Create & Edit';
				showNotice( err.message, 'error' );
			}
		} );
	}

	async function loadSliderList( container, listUrl ) {
		container.innerHTML = '<p class="bltgallery-loading">Loading…</p>';
		try {
			const sliders = await api( '/sliders' );
			renderSliderTable( container, sliders, listUrl );
		} catch ( e ) {
			container.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
		}
	}

	function renderSliderTable( container, sliders, listUrl ) {
		if ( ! sliders.length ) {
			container.innerHTML = '<div class="bltgallery-empty"><p>No sliders yet. Create your first one!</p></div>';
			return;
		}

		const rows = sliders.map( ( s ) => {
			const shortcode = `[blt_slider id="${ s.id }"]`;
			const editUrl   = listUrl + '&action=edit&slider_id=' + s.id;
			return `
				<tr>
					<td><strong><a href="${ escAttr( editUrl ) }">${ escHtml( s.title ) }</a></strong></td>
					<td>${ s.item_count || 0 }</td>
					<td>
						<button type="button" class="bltgallery-shortcode-copy" data-copy="${ escAttr( shortcode ) }" title="Click to copy">
							<code>${ escHtml( shortcode ) }</code>
						</button>
					</td>
					<td>${ escHtml( s.created_at ? new Date( s.created_at ).toLocaleDateString() : '' ) }</td>
					<td>
						<a href="${ escAttr( editUrl ) }" class="button button-secondary">Edit</a>
						<button class="button bltgallery-slider-delete" data-id="${ s.id }" data-title="${ escAttr( s.title ) }">Delete</button>
					</td>
				</tr>
			`;
		} ).join( '' );

		container.innerHTML = `
			<table class="wp-list-table widefat fixed striped bltgallery-table">
				<thead>
					<tr><th>Title</th><th>Slides</th><th>Shortcode</th><th>Created</th><th>Actions</th></tr>
				</thead>
				<tbody>${ rows }</tbody>
			</table>
		`;

		container.querySelectorAll( '.bltgallery-slider-delete' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', async () => {
				if ( ! window.confirm( `Delete slider "${ btn.dataset.title }"? This cannot be undone.` ) ) return;
				btn.disabled = true;
				try {
					await api( `/sliders/${ btn.dataset.id }`, { method: 'DELETE' } );
					btn.closest( 'tr' ).remove();
					if ( container.querySelectorAll( 'tbody tr' ).length === 0 ) {
						renderSliderTable( container, [], listUrl );
					}
				} catch ( e ) {
					showNotice( e.message, 'error' );
					btn.disabled = false;
				}
			} );
		} );

		container.querySelectorAll( '.bltgallery-shortcode-copy' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => copyToClipboard( btn.dataset.copy, btn ) );
		} );
	}

	// ------------------------------------------------------------------
	// Sliders — editor / builder
	// ------------------------------------------------------------------

	function sliderUid() { return 's' + Math.random().toString( 36 ).slice( 2, 9 ); }

	async function initSliderEditor( sliderId ) {
		const titleH      = document.getElementById( 'bltgallery-slider-editor-title' );
		const shortcodeEl = document.getElementById( 'bltgallery-slider-shortcode' );
		const settingsEl  = document.getElementById( 'bltgallery-slider-settings' );
		const slidesEl    = document.getElementById( 'bltgallery-slider-slides' );
		const previewEl   = document.getElementById( 'bltgallery-slider-preview' );

		let slider;
		try {
			slider = await api( `/sliders/${ sliderId }` );
		} catch ( e ) {
			showNotice( e.message, 'error' );
			if ( settingsEl ) settingsEl.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			return;
		}

		// Working state — `slides` carries resolved metadata plus the caption
		// override; `readForm` is wired up by renderSliderSettings.
		const state = {
			slides:   ( slider.slides || [] ).map( ( s ) => ( { ...s, _uid: sliderUid() } ) ),
			readForm: () => ( { title: slider.title, settings: slider.settings || {} } ),
		};

		if ( titleH ) titleH.textContent = slider.title;
		if ( shortcodeEl ) {
			shortcodeEl.textContent = `[blt_slider id="${ sliderId }"]`;
			makeCopyable( shortcodeEl );
		}

		function itemsPayload() {
			return state.slides.map( ( s ) => ( { source: s.source, ref: s.ref, caption: s.caption || '' } ) );
		}

		async function persist() {
			const { title, settings } = state.readForm();
			const saved = await api( `/sliders/${ sliderId }`, {
				method: 'PUT',
				body:   { title, settings, items: itemsPayload() },
			} );
			if ( titleH ) titleH.textContent = saved.title;
			return saved;
		}

		function rerenderSlides() { renderSliderSlides( slidesEl, state, rerenderSlides ); }

		rerenderSlides();
		renderSliderSettings( settingsEl, slider, state, async ( btn ) => {
			const label = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Saving…';
			try {
				await persist();
				showNotice( 'Slider saved.' );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				btn.disabled = false;
				btn.textContent = label;
			}
		} );

		document.getElementById( 'bltgallery-add-media' )?.addEventListener( 'click', () => addSlidesFromMedia( state, rerenderSlides ) );
		document.getElementById( 'bltgallery-add-gallery' )?.addEventListener( 'click', () => openSliderGalleryPicker( state, rerenderSlides ) );

		document.getElementById( 'bltgallery-slider-refresh-preview' )?.addEventListener( 'click', async ( e ) => {
			const btn   = e.currentTarget;
			const label = btn.textContent;
			btn.disabled = true;
			btn.textContent = 'Saving…';
			try {
				await persist();
				await refreshSliderPreview( sliderId, previewEl );
			} catch ( err ) {
				showNotice( err.message, 'error' );
			} finally {
				btn.disabled = false;
				btn.textContent = label;
			}
		} );
	}

	function renderSliderSettings( container, slider, state, onSave ) {
		if ( ! container ) return;
		const s        = slider.settings || {};
		const offish   = ( v ) => v === '0' || v === 0 || v === false;
		const captions = s.captions === 'off' ? 'off' : 'on';
		const arrows   = offish( s.arrows ) ? '0' : '1';
		const dots     = offish( s.dots ) ? '0' : '1';
		const loop     = offish( s.loop ) ? '0' : '1';
		const autoplay = s.autoplay ? '1' : '0';
		const speed    = s.speed ?? 5000;
		const height   = s.height || '';
		const radius   = s.radius ?? 6;
		const yesNo    = ( id, val ) => `
			<select id="${ id }">
				<option value="1"${ val === '1' ? ' selected' : '' }>On</option>
				<option value="0"${ val === '0' ? ' selected' : '' }>Off</option>
			</select>`;

		container.innerHTML = `
			<div class="bltgallery-field">
				<label for="zyg-slider-title">Title</label>
				<input type="text" id="zyg-slider-title" class="regular-text" value="${ escAttr( slider.title || '' ) }">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-captions">Captions</label>
				<select id="zyg-slider-captions">
					<option value="on"${ captions === 'on' ? ' selected' : '' }>On — description / photo credit</option>
					<option value="off"${ captions === 'off' ? ' selected' : '' }>Off</option>
				</select>
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-arrows">Hover arrows</label>
				${ yesNo( 'zyg-slider-arrows', arrows ) }
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-dots">Dot counter</label>
				${ yesNo( 'zyg-slider-dots', dots ) }
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-autoplay">Autoplay</label>
				${ yesNo( 'zyg-slider-autoplay', autoplay ) }
				<label for="zyg-slider-speed" style="margin-left:1rem">Speed (ms)</label>
				<input type="number" id="zyg-slider-speed" class="small-text" min="1000" max="30000" step="500" value="${ speed }">
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-loop">Loop</label>
				${ yesNo( 'zyg-slider-loop', loop ) }
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-height">Max height (optional)</label>
				<input type="text" id="zyg-slider-height" class="regular-text" value="${ escAttr( height ) }" placeholder="e.g. 70vh or 480px">
				<p class="description">Limits how tall each slide can be. Leave blank for the default.</p>
			</div>
			<div class="bltgallery-field">
				<label for="zyg-slider-radius">Corner radius (px)</label>
				<input type="number" id="zyg-slider-radius" class="small-text" min="0" max="200" value="${ radius }">
			</div>
			<div class="bltgallery-field">
				<button class="button button-primary" id="zyg-slider-save">Save slider</button>
			</div>
		`;

		state.readForm = () => ( {
			title: container.querySelector( '#zyg-slider-title' ).value,
			settings: {
				captions: container.querySelector( '#zyg-slider-captions' ).value,
				arrows:   container.querySelector( '#zyg-slider-arrows' ).value,
				dots:     container.querySelector( '#zyg-slider-dots' ).value,
				autoplay: container.querySelector( '#zyg-slider-autoplay' ).value === '1',
				speed:    parseInt( container.querySelector( '#zyg-slider-speed' ).value, 10 ) || 5000,
				loop:     container.querySelector( '#zyg-slider-loop' ).value,
				height:   container.querySelector( '#zyg-slider-height' ).value.trim(),
				radius:   parseInt( container.querySelector( '#zyg-slider-radius' ).value, 10 ) || 0,
			},
		} );

		container.querySelector( '#zyg-slider-save' ).addEventListener( 'click', ( e ) => onSave( e.target ) );
	}

	function renderSliderSlides( container, state, rerender ) {
		if ( ! container ) return;
		if ( ! state.slides.length ) {
			container.innerHTML = '<p class="bltgallery-image-grid__empty">No slides yet. Add images from the media library or a gallery above.</p>';
			return;
		}

		container.innerHTML = `
			<p class="bltgallery-image-grid__hint">Drag to reorder. Captions are optional — leave blank to use the image's own caption.</p>
			<ul class="bltgallery-slider-slides" id="zyg-slide-list"></ul>
		`;
		const list = container.querySelector( '#zyg-slide-list' );

		state.slides.forEach( ( slide ) => {
			const li = document.createElement( 'li' );
			li.className  = 'bltgallery-slider-slide' + ( slide.missing ? ' is-missing' : '' );
			li.draggable  = true;
			li.dataset.uid = slide._uid;

			const thumb = slide.thumb_url || slide.url || '';
			const sourceLabel = slide.source === 'attachment' ? 'Media library' : 'Gallery image';

			li.innerHTML = `
				<span class="bltgallery-slider-slide__handle" aria-hidden="true">⠿</span>
				${ thumb
					? `<img class="bltgallery-slider-slide__thumb" src="${ escAttr( thumb ) }" alt="${ escAttr( slide.alt || '' ) }" loading="lazy" width="72" height="72">`
					: `<span class="bltgallery-slider-slide__thumb bltgallery-slider-slide__thumb--missing">?</span>` }
				<div class="bltgallery-slider-slide__meta">
					<span class="bltgallery-slider-slide__source">${ escHtml( sourceLabel ) }${ slide.missing ? ' — source not found' : '' }</span>
					<input type="text" class="bltgallery-slider-slide__caption" placeholder="${ escAttr( slide.default_caption || 'Caption / photo credit (optional)' ) }" value="${ escAttr( slide.caption || '' ) }">
				</div>
				<button type="button" class="button-link-delete bltgallery-slider-slide__remove" aria-label="Remove slide">&times;</button>
			`;

			li.querySelector( '.bltgallery-slider-slide__caption' ).addEventListener( 'input', ( e ) => {
				slide.caption = e.target.value;
			} );

			li.querySelector( '.bltgallery-slider-slide__remove' ).addEventListener( 'click', () => {
				const idx = state.slides.findIndex( ( s ) => s._uid === slide._uid );
				if ( idx > -1 ) state.slides.splice( idx, 1 );
				rerender();
			} );

			list.appendChild( li );
		} );

		initSlideDragReorder( list, state );
	}

	function initSlideDragReorder( list, state ) {
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
			const rect  = target.getBoundingClientRect();
			const after = e.clientY > rect.top + rect.height / 2;
			list.insertBefore( dragEl, after ? target.nextSibling : target );
		} );

		list.addEventListener( 'dragend', () => {
			dragEl?.classList.remove( 'is-dragging' );
			dragEl = null;
			// Re-sync the in-memory order with the DOM (captions stay intact).
			const order = [ ...list.querySelectorAll( 'li' ) ].map( ( li ) => li.dataset.uid );
			state.slides.sort( ( a, b ) => order.indexOf( a._uid ) - order.indexOf( b._uid ) );
		} );
	}

	function addSlidesFromMedia( state, rerender ) {
		if ( ! window.wp || ! window.wp.media ) {
			showNotice( 'The WordPress media library is unavailable on this page.', 'error' );
			return;
		}
		const frame = window.wp.media( {
			title:    'Add images to slider',
			multiple: 'add',
			library:  { type: 'image' },
			button:   { text: 'Add to slider' },
		} );

		frame.on( 'select', () => {
			frame.state().get( 'selection' ).forEach( ( att ) => {
				const a     = att.toJSON();
				const sizes = a.sizes || {};
				const thumb = ( sizes.thumbnail && sizes.thumbnail.url ) || ( sizes.medium && sizes.medium.url ) || a.url;
				state.slides.push( {
					_uid:            sliderUid(),
					source:          'attachment',
					ref:             a.id,
					caption:         '',
					default_caption: a.caption || '',
					thumb_url:       thumb,
					url:             a.url,
					alt:             a.alt || '',
					title:           a.title || '',
					missing:         false,
				} );
			} );
			rerender();
		} );

		frame.open();
	}

	async function openSliderGalleryPicker( state, rerender ) {
		let dialog = document.getElementById( 'bltgallery-slider-gallery-modal' );
		if ( ! dialog ) {
			dialog = document.createElement( 'dialog' );
			dialog.id = 'bltgallery-slider-gallery-modal';
			dialog.className = 'bltgallery-modal bltgallery-slider-gallery-modal';
			document.body.appendChild( dialog );
		}

		dialog.innerHTML = `
			<form method="dialog" class="bltgallery-modal__form">
				<header class="bltgallery-modal__header">
					<h2>Add images from a gallery</h2>
					<button type="button" class="bltgallery-modal__close" data-close aria-label="Close">&times;</button>
				</header>
				<div class="bltgallery-modal__body">
					<div class="bltgallery-field">
						<label for="zyg-slider-gallery-select">Gallery</label>
						<select id="zyg-slider-gallery-select"><option value="">Loading…</option></select>
					</div>
					<div class="bltgallery-field bltgallery-field--toggle">
						<label><input type="checkbox" id="zyg-slider-gallery-all"> Select all</label>
					</div>
					<div id="zyg-slider-gallery-images" class="bltgallery-slider-picker">
						<p class="bltgallery-muted">Choose a gallery to list its images.</p>
					</div>
				</div>
				<footer class="bltgallery-modal__footer">
					<button type="button" class="button" data-close>Cancel</button>
					<button type="submit" class="button button-primary">Add selected</button>
				</footer>
			</form>
		`;

		const select   = dialog.querySelector( '#zyg-slider-gallery-select' );
		const imagesEl = dialog.querySelector( '#zyg-slider-gallery-images' );
		const allCb    = dialog.querySelector( '#zyg-slider-gallery-all' );
		const form     = dialog.querySelector( 'form' );
		let   loaded   = [];

		dialog.querySelectorAll( '[data-close]' ).forEach( ( b ) => { b.onclick = () => dialog.close(); } );

		try {
			const galleries = await api( '/galleries?per_page=100' );
			select.innerHTML = '<option value="">Select a gallery…</option>' +
				galleries.map( ( g ) => `<option value="${ g.id }">${ escHtml( g.title || ( '#' + g.id ) ) }</option>` ).join( '' );
		} catch ( e ) {
			select.innerHTML = '<option value="">Failed to load galleries</option>';
		}

		select.addEventListener( 'change', async () => {
			allCb.checked = false;
			const gid = select.value;
			if ( ! gid ) {
				imagesEl.innerHTML = '<p class="bltgallery-muted">Choose a gallery to list its images.</p>';
				loaded = [];
				return;
			}
			imagesEl.innerHTML = '<p class="bltgallery-loading">Loading images…</p>';
			try {
				loaded = await api( `/galleries/${ gid }/images` );
				if ( ! loaded.length ) {
					imagesEl.innerHTML = '<p class="bltgallery-muted">This gallery has no images.</p>';
					return;
				}
				imagesEl.innerHTML = `
					<ul class="bltgallery-slider-picker__list">
						${ loaded.map( ( img, i ) => `
							<li>
								<label>
									<input type="checkbox" value="${ i }">
									<img src="${ escAttr( img.thumb_url || img.url ) }" alt="${ escAttr( img.alt_text || img.filename ) }" loading="lazy">
									<span>${ escHtml( img.title || img.alt_text || img.filename ) }</span>
								</label>
							</li>
						` ).join( '' ) }
					</ul>
				`;
			} catch ( e ) {
				imagesEl.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
			}
		} );

		allCb.addEventListener( 'change', () => {
			imagesEl.querySelectorAll( 'input[type="checkbox"]' ).forEach( ( cb ) => { cb.checked = allCb.checked; } );
		} );

		form.onsubmit = ( e ) => {
			e.preventDefault();
			const picked = [ ...imagesEl.querySelectorAll( 'input[type="checkbox"]:checked' ) ]
				.map( ( cb ) => loaded[ parseInt( cb.value, 10 ) ] )
				.filter( Boolean );
			picked.forEach( ( img ) => {
				state.slides.push( {
					_uid:            sliderUid(),
					source:          'image',
					ref:             img.id,
					caption:         '',
					default_caption: img.caption || '',
					thumb_url:       img.thumb_url || img.url,
					url:             img.url,
					alt:             img.alt_text || img.filename || '',
					title:           img.title || '',
					missing:         false,
				} );
			} );
			dialog.close();
			if ( picked.length ) rerender();
		};

		if ( typeof dialog.showModal === 'function' ) {
			dialog.showModal();
		} else {
			dialog.setAttribute( 'open', '' );
		}
	}

	async function refreshSliderPreview( sliderId, previewEl ) {
		if ( ! previewEl ) return;
		previewEl.innerHTML = '<p class="bltgallery-loading">Loading preview…</p>';
		try {
			const data = await api( `/sliders/${ sliderId }/render` );
			previewEl.innerHTML = data.html || '<p class="bltgallery-muted">Nothing to preview yet — add some slides.</p>';
			if ( window.BltGallery && typeof window.BltGallery.init === 'function' ) {
				window.BltGallery.init( previewEl );
			}
		} catch ( e ) {
			previewEl.innerHTML = `<p class="bltgallery-error">${ escHtml( e.message ) }</p>`;
		}
	}

	// ------------------------------------------------------------------
	// Auto-init pages
	// ------------------------------------------------------------------

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.getElementById( 'bltgallery-general-settings' ) ) {
			initSettings();
		}
		if ( document.getElementById( 'bltgallery-shortcodes-doc' ) ) {
			initShortcodesDoc();
		}
		if ( document.getElementById( 'bltgallery-albums-admin' ) ) {
			initAlbumsPage();
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
		initShortcodesDoc,
		initAlbumsPage,
		initSlidersPage,
		initSliderEditor,
	};

} )();
