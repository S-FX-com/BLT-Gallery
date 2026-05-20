/**
 * BltGallery – Frontend JS (no build required, vanilla ES2020).
 * Combines: masonry, slideshow, lightbox.
 */
( function () {
	'use strict';

	const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

	// ------------------------------------------------------------------
	// Masonry
	// ------------------------------------------------------------------

	function initMasonry() {
		document.querySelectorAll( '.bltgallery--masonry' ).forEach( function ( container ) {
			var grid = container.querySelector( '.bltgallery-masonry__grid' );
			if ( ! grid ) return;
			if ( grid.dataset.lightbox ) {
				grid.addEventListener( 'click', function ( e ) {
					var link = e.target.closest( '.bltgallery__link' );
					if ( ! link ) return;
					var c = link.closest( '.bltgallery' );
					if ( c && c._bltLightbox ) {
						e.preventDefault();
						var links = [ ...c.querySelectorAll( '.bltgallery__link' ) ];
						c._bltLightbox.open( Math.max( 0, links.indexOf( link ) ) );
					}
				} );
			}
		} );
	}

	// ------------------------------------------------------------------
	// Slideshow
	// ------------------------------------------------------------------

	function initSlideshow() {
		document.querySelectorAll( '.bltgallery--slideshow' ).forEach( function ( container ) {
			var ss     = container.querySelector( '.bltgallery-slideshow' );
			if ( ! ss ) return;

			var track  = ss.querySelector( '.bltgallery-slideshow__track' );
			var slides = [ ...ss.querySelectorAll( '.bltgallery-slideshow__slide' ) ];
			var dots   = [ ...ss.querySelectorAll( '.bltgallery-slideshow__dot' ) ];
			var prev   = ss.querySelector( '.bltgallery-slideshow__prev' );
			var next   = ss.querySelector( '.bltgallery-slideshow__next' );

			if ( slides.length < 2 ) return;

			var current    = 0;
			var autoplayId = null;
			var autoplay   = ss.dataset.autoplay === 'true' && ! REDUCED_MOTION;
			var speed      = parseInt( ss.dataset.speed, 10 ) || 4000;

			function goTo( idx ) {
				slides[ current ].classList.remove( 'is-active' );
				slides[ current ].setAttribute( 'aria-hidden', 'true' );
				if ( dots[ current ] ) dots[ current ].classList.remove( 'is-active' );

				current = ( idx + slides.length ) % slides.length;

				slides[ current ].classList.add( 'is-active' );
				slides[ current ].setAttribute( 'aria-hidden', 'false' );
				if ( dots[ current ] ) dots[ current ].classList.add( 'is-active' );

				track.style.transform = REDUCED_MOTION ? 'none' : 'translateX(-' + ( current * 100 ) + '%)';
			}

			slides.forEach( function ( slide, i ) {
				slide.setAttribute( 'aria-hidden', i === 0 ? 'false' : 'true' );
			} );

			if ( prev ) prev.addEventListener( 'click', function () { goTo( current - 1 ); resetAutoplay(); } );
			if ( next ) next.addEventListener( 'click', function () { goTo( current + 1 ); resetAutoplay(); } );

			dots.forEach( function ( dot, i ) {
				dot.addEventListener( 'click', function () { goTo( i ); resetAutoplay(); } );
			} );

			ss.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'ArrowLeft'  ) { goTo( current - 1 ); resetAutoplay(); }
				if ( e.key === 'ArrowRight' ) { goTo( current + 1 ); resetAutoplay(); }
			} );

			var touchStartX = null;
			ss.addEventListener( 'touchstart', function ( e ) { touchStartX = e.touches[ 0 ].clientX; }, { passive: true } );
			ss.addEventListener( 'touchend', function ( e ) {
				if ( touchStartX === null ) return;
				var delta = e.changedTouches[ 0 ].clientX - touchStartX;
				if ( Math.abs( delta ) > 40 ) { goTo( delta < 0 ? current + 1 : current - 1 ); resetAutoplay(); }
				touchStartX = null;
			} );

			function startAutoplay() { if ( autoplay ) autoplayId = setInterval( function () { goTo( current + 1 ); }, speed ); }
			function stopAutoplay()  { clearInterval( autoplayId ); }
			function resetAutoplay() { stopAutoplay(); startAutoplay(); }

			ss.addEventListener( 'mouseenter', stopAutoplay );
			ss.addEventListener( 'mouseleave', startAutoplay );
			ss.addEventListener( 'focusin',    stopAutoplay );
			ss.addEventListener( 'focusout',   startAutoplay );

			startAutoplay();
		} );
	}

	// ------------------------------------------------------------------
	// Lightbox helpers
	// ------------------------------------------------------------------

	var FOCUSABLE = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

	function trapFocus( container ) {
		container._bltFocusTrap = function ( e ) {
			if ( e.key !== 'Tab' ) return;
			var focusable = [ ...container.querySelectorAll( FOCUSABLE ) ];
			var first = focusable[ 0 ];
			var last  = focusable[ focusable.length - 1 ];
			if ( e.shiftKey && document.activeElement === first ) { e.preventDefault(); last && last.focus(); }
			else if ( ! e.shiftKey && document.activeElement === last ) { e.preventDefault(); first && first.focus(); }
		};
		document.addEventListener( 'keydown', container._bltFocusTrap );
	}

	function releaseFocus( container ) {
		if ( container._bltFocusTrap ) {
			document.removeEventListener( 'keydown', container._bltFocusTrap );
			delete container._bltFocusTrap;
		}
	}

	var preloadCache = new Set();
	function preload( src ) {
		if ( ! src || preloadCache.has( src ) ) return;
		preloadCache.add( src );
		var img = new Image();
		img.src = src;
	}

	function createLightbox( modal, images ) {
		var current  = 0;
		var openerEl = null;

		var closeBtn  = modal.querySelector( '.bltgallery-lightbox__close' );
		var prevBtn   = modal.querySelector( '.bltgallery-lightbox__prev' );
		var nextBtn   = modal.querySelector( '.bltgallery-lightbox__next' );
		var imgEl     = modal.querySelector( '.bltgallery-lightbox__img' );
		var captionEl = modal.querySelector( '.bltgallery-lightbox__caption' );

		function show( idx ) {
			current = ( idx + images.length ) % images.length;
			var item = images[ current ];
			imgEl.src             = item.src;
			imgEl.alt             = item.alt || '';
			captionEl.hidden      = ! item.caption;
			captionEl.textContent = item.caption || '';
			if ( prevBtn ) prevBtn.toggleAttribute( 'hidden', images.length <= 1 );
			if ( nextBtn ) nextBtn.toggleAttribute( 'hidden', images.length <= 1 );
			preload( images[ ( current + 1 ) % images.length ] && images[ ( current + 1 ) % images.length ].src );
			preload( images[ ( current - 1 + images.length ) % images.length ] && images[ ( current - 1 + images.length ) % images.length ].src );
		}

		function open( idx ) {
			openerEl     = document.activeElement;
			show( idx );
			modal.hidden = false;
			document.body.classList.add( 'blt-lightbox-open' );
			if ( closeBtn ) closeBtn.focus();
			trapFocus( modal );
		}

		function close() {
			modal.hidden = true;
			document.body.classList.remove( 'blt-lightbox-open' );
			releaseFocus( modal );
			if ( openerEl ) openerEl.focus();
		}

		if ( closeBtn ) closeBtn.addEventListener( 'click', close );
		if ( prevBtn  ) prevBtn.addEventListener( 'click', function () { show( current - 1 ); } );
		if ( nextBtn  ) nextBtn.addEventListener( 'click', function () { show( current + 1 ); } );

		modal.addEventListener( 'click', function ( e ) { if ( e.target === modal ) close(); } );
		modal.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape'     ) close();
			if ( e.key === 'ArrowLeft'  ) show( current - 1 );
			if ( e.key === 'ArrowRight' ) show( current + 1 );
		} );

		var touchStartX = null;
		modal.addEventListener( 'touchstart', function ( e ) { touchStartX = e.touches[ 0 ].clientX; }, { passive: true } );
		modal.addEventListener( 'touchend', function ( e ) {
			if ( touchStartX === null ) return;
			var delta = e.changedTouches[ 0 ].clientX - touchStartX;
			if ( Math.abs( delta ) > 50 ) show( delta < 0 ? current + 1 : current - 1 );
			touchStartX = null;
		} );

		return { open: open, close: close, show: show };
	}

	// ------------------------------------------------------------------
	// Lightbox
	// ------------------------------------------------------------------

	function initLightbox() {
		document.querySelectorAll( '.bltgallery--lightbox' ).forEach( function ( container ) {
			var modal    = container.querySelector( '.bltgallery-lightbox__modal' );
			var template = container.querySelector( '.bltgallery-lightbox__data' );
			var triggers = [ ...container.querySelectorAll( '.bltgallery-lightbox__trigger' ) ];
			if ( ! modal || ! template ) return;

			var images = [];
			try { images = JSON.parse( template.textContent ); } catch {}

			var lb = createLightbox( modal, images );
			container._bltLightbox = lb;

			triggers.forEach( function ( btn ) {
				btn.addEventListener( 'click', function () { lb.open( parseInt( btn.dataset.index, 10 ) || 0 ); } );
			} );
		} );

		// Masonry/tile grids with data-lightbox="1".
		document.querySelectorAll( '[data-lightbox="1"]' ).forEach( function ( grid ) {
			var container = grid.closest( '.bltgallery' );
			if ( ! container || container.classList.contains( 'bltgallery--lightbox' ) ) return;

			var links  = [ ...grid.querySelectorAll( '.bltgallery__link' ) ];
			var images = links.map( function ( link ) {
				var img = link.querySelector( 'img' );
				return {
					src:     link.href,
					thumb:   img ? img.src : '',
					alt:     img ? img.alt : '',
					caption: ( link.querySelector( '.bltgallery__caption' ) || {} ).textContent || '',
				};
			} );

			var modal = document.createElement( 'div' );
			modal.className = 'bltgallery-lightbox__modal';
			modal.setAttribute( 'role', 'dialog' );
			modal.setAttribute( 'aria-modal', 'true' );
			modal.setAttribute( 'aria-label', 'Image lightbox' );
			modal.hidden = true;
			modal.innerHTML = '<button class="bltgallery-lightbox__close" aria-label="Close lightbox">&times;</button>' +
				'<button class="bltgallery-lightbox__prev" aria-label="Previous image">&#8249;</button>' +
				'<button class="bltgallery-lightbox__next" aria-label="Next image">&#8250;</button>' +
				'<figure class="bltgallery-lightbox__figure">' +
				'<img class="bltgallery-lightbox__img" src="" alt="">' +
				'<figcaption class="bltgallery-lightbox__caption"></figcaption>' +
				'</figure>';
			document.body.appendChild( modal );

			var lb = createLightbox( modal, images );
			container._bltLightbox = lb;

			links.forEach( function ( link, idx ) {
				link.addEventListener( 'click', function ( e ) { e.preventDefault(); lb.open( idx ); } );
			} );
		} );

		document.addEventListener( 'blt:open-lightbox', function ( e ) {
			var container = e.target.closest( '.bltgallery' );
			if ( container && container._bltLightbox ) {
				container._bltLightbox.open( ( e.detail && e.detail.idx ) || 0 );
			}
		} );
	}

	// ------------------------------------------------------------------
	// Boot
	// ------------------------------------------------------------------

	function boot() {
		initMasonry();
		initSlideshow();
		initLightbox();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

} )();
