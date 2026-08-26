/**
 * BLT Gallery – Front-end gallery uploader ([blt_my_gallery]).
 *
 * No build step, vanilla JS — mirrors the upload-with-progress and
 * delete-with-confirm patterns already used by the admin uploader
 * (assets/admin/admin.js), wired to the nonce-authenticated
 * /my-gallery/* REST routes instead of the admin-only /galleries/* ones.
 */
( function () {
	'use strict';

	const cfg = window.bltGalleryMyGallery || {};

	function escHtml( str ) {
		return String( str ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ).replace( /"/g, '&quot;' );
	}

	async function api( path, options = {} ) {
		const init = {
			headers: { 'X-WP-Nonce': cfg.nonce },
			...options,
		};

		const resp = await fetch( cfg.apiBase + path, init );
		const text = await resp.text();
		let data;
		try { data = JSON.parse( text ); } catch { data = text; }

		if ( ! resp.ok ) {
			throw new Error( data?.message || `HTTP ${ resp.status }` );
		}
		return data;
	}

	function boot() {
		// [blt_my_gallery] can only appear once per visitor in any meaningful
		// sense, but nothing stops a page from placing the shortcode twice —
		// so this stays scoped per-root rather than relying on element ids,
		// which would otherwise collide.
		document.querySelectorAll( '.bltgallery-my-gallery' ).forEach( initWidget );
	}

	function initWidget( root ) {
		const dropZone   = root.querySelector( '.bltgallery-my-gallery__dropzone' );
		const fileInput  = root.querySelector( 'input[type="file"]' );
		const progress   = root.querySelector( '.bltgallery-my-gallery__progress' );
		const grid       = root.querySelector( '.bltgallery-my-gallery__grid' );
		const quota      = root.querySelector( '.bltgallery-my-gallery__quota' );
		const uploaderEl = root.querySelector( '.bltgallery-my-gallery__uploader' );

		if ( ! dropZone || ! fileInput || ! grid ) return;

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

		grid.addEventListener( 'click', ( e ) => {
			const btn = e.target.closest( '.bltgallery-my-gallery__delete' );
			if ( btn ) deleteImage( btn.dataset.id, btn.closest( 'li' ) );
		} );

		function handleFiles( files ) {
			Array.from( files ).forEach( uploadFile );
		}

		function uploadFile( file ) {
			if ( parseInt( root.dataset.remaining, 10 ) <= 0 ) {
				showProgressError( file.name, 'Upload limit reached.' );
				return;
			}

			const item = document.createElement( 'li' );
			item.className = 'bltgallery-my-gallery__progress-item';
			item.innerHTML = `
				<span class="bltgallery-my-gallery__progress-name">${ escHtml( file.name ) }</span>
				<progress value="0" max="100"></progress>
			`;
			progress.appendChild( item );

			const bar  = item.querySelector( 'progress' );
			const body = new FormData();
			body.append( 'file', file );

			const xhr = new XMLHttpRequest();
			xhr.open( 'POST', cfg.apiBase + '/my-gallery/upload' );
			xhr.setRequestHeader( 'X-WP-Nonce', cfg.nonce );

			xhr.upload.onprogress = ( e ) => {
				if ( e.lengthComputable ) bar.value = Math.round( ( e.loaded / e.total ) * 100 );
			};

			xhr.onload = () => {
				if ( xhr.status === 201 ) {
					bar.value = 100;
					const res = JSON.parse( xhr.responseText );
					onUploaded( res );
					setTimeout( () => item.remove(), 1200 );
				} else {
					let msg = 'Upload failed.';
					try { msg = JSON.parse( xhr.responseText )?.message ?? msg; } catch {}
					showProgressError( file.name, msg, item );
				}
			};

			xhr.onerror = () => showProgressError( file.name, 'Network error.', item );

			xhr.send( body );
		}

		function showProgressError( filename, msg, item ) {
			if ( ! item ) {
				item = document.createElement( 'li' );
				item.className = 'bltgallery-my-gallery__progress-item';
				progress.appendChild( item );
			}
			item.innerHTML = `
				<span class="bltgallery-my-gallery__progress-name">${ escHtml( filename ) }</span>
				<span class="bltgallery-my-gallery__error">${ escHtml( msg ) }</span>
			`;
		}

		function onUploaded( res ) {
			const empty = grid.querySelector( '.bltgallery-my-gallery__empty' );
			if ( empty ) empty.remove();

			const li = document.createElement( 'li' );
			li.className = 'bltgallery-my-gallery__item';
			li.dataset.id = res.image.id;
			li.innerHTML = `
				<img src="${ escHtml( res.image.thumb_url || res.image.url ) }" alt="${ escHtml( res.image.alt_text || res.image.filename ) }" loading="lazy">
				<button type="button" class="bltgallery-my-gallery__delete" data-id="${ res.image.id }" aria-label="Delete this image">&times;</button>
			`;
			grid.appendChild( li );

			applyQuota( res.remaining, res.limit );
		}

		async function deleteImage( id, li ) {
			if ( ! window.confirm( 'Delete this image?' ) ) return;

			try {
				const res = await api( `/my-gallery/images/${ id }`, { method: 'DELETE' } );
				li?.remove();
				applyQuota( res.remaining, res.limit );
				if ( ! grid.children.length ) {
					grid.innerHTML = `<li class="bltgallery-my-gallery__empty">You haven't added any images yet.</li>`;
				}
			} catch ( e ) {
				window.alert( e.message );
			}
		}

		function applyQuota( remaining, limit ) {
			root.dataset.remaining = remaining;
			root.dataset.limit     = limit;

			if ( quota ) {
				const used = limit - remaining;
				quota.textContent = remaining <= 0
					? `You've used all ${ limit } of your image slots.`
					: `${ used } of ${ limit } images used.`;
			}

			if ( uploaderEl ) uploaderEl.hidden = remaining <= 0;
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
