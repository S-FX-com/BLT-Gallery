/**
 * ZymGallery Lightbox – fully accessible, no-dependency modal viewer.
 *
 * Features:
 *   • Keyboard trap inside modal (Tab, Shift+Tab, Escape)
 *   • Swipe / touch navigation
 *   • Preloads adjacent images for smooth browsing
 *   • Respects prefers-reduced-motion
 *   • Works with both tile/masonry grids and the dedicated lightbox display type
 */

const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

export function initLightbox() {
	document.querySelectorAll( '.zymgallery--lightbox' ).forEach( initLightboxDisplay );

	// Masonry / tile galleries that set data-lightbox="1" on their grid.
	document.querySelectorAll( '[data-lightbox="1"]' ).forEach( ( grid ) => {
		const container = grid.closest( '.zymgallery' );
		if ( container && ! container.classList.contains( 'zymgallery--lightbox' ) ) {
			initGridLightbox( container, grid );
		}
	} );

	// Allow other modules to open the lightbox via a custom event.
	document.addEventListener( 'zym:open-lightbox', ( e ) => {
		const container = e.target.closest( '.zymgallery' );
		if ( container && container._zymLightbox ) {
			container._zymLightbox.open( e.detail.idx ?? 0 );
		}
	} );
}

// ─── Dedicated lightbox display type ─────────────────────────────────────────

function initLightboxDisplay( container ) {
	const modal    = container.querySelector( '.zymgallery-lightbox__modal' );
	const template = container.querySelector( '.zymgallery-lightbox__data' );
	const triggers = [ ...container.querySelectorAll( '.zymgallery-lightbox__trigger' ) ];

	if ( ! modal || ! template ) return;

	let images = [];
	try { images = JSON.parse( template.textContent ); } catch {}

	const lb = createLightbox( modal, images );
	container._zymLightbox = lb;

	triggers.forEach( ( btn ) => {
		btn.addEventListener( 'click', () => lb.open( parseInt( btn.dataset.index, 10 ) || 0 ) );
	} );
}

// ─── Masonry / tile grid ──────────────────────────────────────────────────────

function initGridLightbox( container, grid ) {
	const links = [ ...grid.querySelectorAll( '.zymgallery__link' ) ];

	const images = links.map( ( link ) => {
		const img = link.querySelector( 'img' );
		return {
			src:     link.href,
			thumb:   img?.src ?? '',
			alt:     img?.alt ?? '',
			caption: link.querySelector( '.zymgallery__caption' )?.textContent ?? '',
			w: 0, h: 0,
		};
	} );

	// Create a detached modal.
	const modal = document.createElement( 'div' );
	modal.className  = 'zymgallery-lightbox__modal';
	modal.setAttribute( 'role', 'dialog' );
	modal.setAttribute( 'aria-modal', 'true' );
	modal.setAttribute( 'aria-label', 'Image lightbox' );
	modal.hidden = true;
	modal.innerHTML = `
		<button class="zymgallery-lightbox__close" aria-label="Close lightbox">&times;</button>
		<button class="zymgallery-lightbox__prev"  aria-label="Previous image">&#8249;</button>
		<button class="zymgallery-lightbox__next"  aria-label="Next image">&#8250;</button>
		<figure class="zymgallery-lightbox__figure">
			<img class="zymgallery-lightbox__img" src="" alt="">
			<figcaption class="zymgallery-lightbox__caption"></figcaption>
		</figure>`;
	document.body.appendChild( modal );

	const lb = createLightbox( modal, images );
	container._zymLightbox = lb;

	links.forEach( ( link, idx ) => {
		link.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			lb.open( idx );
		} );
	} );
}

// ─── Lightbox logic ───────────────────────────────────────────────────────────

function createLightbox( modal, images ) {
	let current     = 0;
	let openerEl    = null;

	const closeBtn  = modal.querySelector( '.zymgallery-lightbox__close' );
	const prevBtn   = modal.querySelector( '.zymgallery-lightbox__prev' );
	const nextBtn   = modal.querySelector( '.zymgallery-lightbox__next' );
	const imgEl     = modal.querySelector( '.zymgallery-lightbox__img' );
	const captionEl = modal.querySelector( '.zymgallery-lightbox__caption' );

	function show( idx ) {
		current = ( idx + images.length ) % images.length;
		const item = images[ current ];
		imgEl.src        = item.src;
		imgEl.alt        = item.alt || '';
		captionEl.hidden = ! item.caption;
		captionEl.textContent = item.caption || '';

		prevBtn?.toggleAttribute( 'hidden', images.length <= 1 );
		nextBtn?.toggleAttribute( 'hidden', images.length <= 1 );

		// Preload adjacent images.
		preload( images[ ( current + 1 ) % images.length ]?.src );
		preload( images[ ( current - 1 + images.length ) % images.length ]?.src );
	}

	function open( idx ) {
		openerEl = document.activeElement;
		show( idx );
		modal.hidden = false;
		document.body.classList.add( 'zym-lightbox-open' );
		closeBtn?.focus();
		trapFocus( modal );
	}

	function close() {
		modal.hidden = true;
		document.body.classList.remove( 'zym-lightbox-open' );
		releaseFocus( modal );
		openerEl?.focus();
	}

	closeBtn?.addEventListener( 'click', close );
	prevBtn?.addEventListener( 'click', () => show( current - 1 ) );
	nextBtn?.addEventListener( 'click', () => show( current + 1 ) );

	// Click on backdrop closes.
	modal.addEventListener( 'click', ( e ) => {
		if ( e.target === modal ) close();
	} );

	// Keyboard.
	modal.addEventListener( 'keydown', ( e ) => {
		switch ( e.key ) {
			case 'Escape':    close(); break;
			case 'ArrowLeft': show( current - 1 ); break;
			case 'ArrowRight': show( current + 1 ); break;
		}
	} );

	// Touch / swipe.
	let touchStartX = null;
	modal.addEventListener( 'touchstart', ( e ) => { touchStartX = e.touches[ 0 ].clientX; }, { passive: true } );
	modal.addEventListener( 'touchend', ( e ) => {
		if ( touchStartX === null ) return;
		const delta = e.changedTouches[ 0 ].clientX - touchStartX;
		if ( Math.abs( delta ) > 50 ) show( delta < 0 ? current + 1 : current - 1 );
		touchStartX = null;
	} );

	return { open, close, show };
}

// ─── Focus trap ───────────────────────────────────────────────────────────────

const FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

function trapFocus( container ) {
	container._zymFocusTrap = ( e ) => {
		if ( e.key !== 'Tab' ) return;
		const focusable = [ ...container.querySelectorAll( FOCUSABLE ) ];
		const first = focusable[ 0 ];
		const last  = focusable[ focusable.length - 1 ];

		if ( e.shiftKey && document.activeElement === first ) {
			e.preventDefault();
			last?.focus();
		} else if ( ! e.shiftKey && document.activeElement === last ) {
			e.preventDefault();
			first?.focus();
		}
	};
	document.addEventListener( 'keydown', container._zymFocusTrap );
}

function releaseFocus( container ) {
	if ( container._zymFocusTrap ) {
		document.removeEventListener( 'keydown', container._zymFocusTrap );
		delete container._zymFocusTrap;
	}
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

const preloadCache = new Set();
function preload( src ) {
	if ( ! src || preloadCache.has( src ) ) return;
	preloadCache.add( src );
	const img = new Image();
	img.src = src;
}
