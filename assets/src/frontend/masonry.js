/**
 * Masonry gallery – uses CSS columns (column-count) with a ResizeObserver
 * to update the column count from the data-settings attribute.
 *
 * No external Masonry.js library required.
 */

export function initMasonry() {
	document.querySelectorAll( '.zymgallery--masonry' ).forEach( initGallery );
}

function initGallery( container ) {
	const grid = container.querySelector( '.zymgallery-masonry__grid' );
	if ( ! grid ) return;

	// CSS variables are already set via inline style by PHP.
	// Wire up click → lightbox if data-lightbox is set.
	if ( grid.dataset.lightbox ) {
		grid.addEventListener( 'click', handleGridClick );
	}

	// Optionally re-balance columns on resize (browsers handle this natively
	// via CSS columns, but we can hook here for future enhancements).
}

function handleGridClick( e ) {
	const link = e.target.closest( '.zymgallery__link' );
	if ( ! link ) return;

	// If a ZymGallery lightbox is in scope, let it handle it.
	const container = link.closest( '.zymgallery' );
	if ( container ) {
		const modal = container.querySelector( '.zymgallery-lightbox__modal' );
		if ( modal ) {
			e.preventDefault();
			const idx = [ ...container.querySelectorAll( '.zymgallery__link' ) ].indexOf( link );
			openLightboxAt( container, idx >= 0 ? idx : 0 );
		}
	}
}

// Import lazily to avoid circular dependency; lightbox handles its own init.
function openLightboxAt( container, idx ) {
	container.dispatchEvent( new CustomEvent( 'zym:open-lightbox', { detail: { idx }, bubbles: true } ) );
}
