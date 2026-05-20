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
			container._bltLightbox       = lb;
			container._bltLightboxImages = images;

			// Delegated handler so paginated triggers (added later) just work.
			// AJAX-added triggers carry data-index="-1"; resolve those by id.
			container.addEventListener( 'click', function ( e ) {
				var trigger = e.target.closest( '.bltgallery-lightbox__trigger' );
				if ( ! trigger ) return;
				var idx = parseInt( trigger.dataset.index, 10 );
				if ( isNaN( idx ) || idx < 0 ) {
					var imageId = parseInt( trigger.dataset.imageId, 10 );
					idx = container._bltLightboxImages.findIndex( function ( i ) { return i.id === imageId; } );
				}
				lb.open( Math.max( 0, idx ) );
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
		initPagination();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}

	// ------------------------------------------------------------------
	// AJAX Pagination
	//
	// Driven by `data-pagination="load-more|numbered|infinite"` and
	// `data-per-page="N"` on the .bltgallery container. Pages are fetched
	// from /wp-json/bltgallery/v1/galleries/{id}/images?page=&per_page=.
	//
	// Speed:
	//   • Cancellable AbortController per gallery
	//   • In-memory cache keyed by `${galleryId}:${page}` so re-visiting
	//     a numbered page doesn't refetch
	//   • IntersectionObserver-driven prefetch ~1 viewport before the
	//     load-more button enters view
	//   • document.createDocumentFragment() for batch DOM appends
	//   • Newly-added <img> nodes inherit loading="lazy" / decoding="async"
	//     so the browser doesn't fetch them until they're near the viewport
	// ------------------------------------------------------------------

	function initPagination() {
		document.querySelectorAll( '.bltgallery[data-pagination]' ).forEach( setupPaginatedGallery );
	}

	function setupPaginatedGallery( container ) {
		var mode       = container.dataset.pagination;
		var perPage    = parseInt( container.dataset.perPage, 10 ) || 24;
		var galleryId  = ( container.id || '' ).replace( 'bltgallery-', '' );
		if ( ! galleryId ) return;

		var grid    = container.querySelector( '.bltgallery-masonry__grid, .bltgallery-tile__grid, .bltgallery-lightbox__grid' );
		var nav     = container.querySelector( '.bltgallery-pagination' );
		if ( ! grid || ! nav ) return;

		var btn      = nav.querySelector( '.bltgallery-pagination__load-more' );
		var status   = nav.querySelector( '.bltgallery-pagination__status' );
		var pageBtns = nav.querySelectorAll( '.bltgallery-pagination__page' );
		var total    = parseInt( grid.dataset.total, 10 ) || 0;
		var cache    = new Map(); // page -> array of image objects
		var inflight = null;

		// API base lives on a global injected by WordPress when the
		// frontend script is enqueued. Falls back to the standard mount.
		var apiBase = ( window.bltGalleryFrontend && window.bltGalleryFrontend.apiBase )
			? window.bltGalleryFrontend.apiBase
			: '/wp-json/bltgallery/v1';

		function fetchPage( page ) {
			var cached = cache.get( page );
			if ( cached ) return Promise.resolve( cached );

			if ( inflight ) inflight.abort();
			inflight = new AbortController();

			var url = apiBase + '/galleries/' + galleryId + '/images?page=' + page + '&per_page=' + perPage + '&shape=paged';
			return fetch( url, { signal: inflight.signal, headers: { Accept: 'application/json' } } )
				.then( function ( r ) {
					if ( ! r.ok ) throw new Error( 'HTTP ' + r.status );
					return r.json();
				} )
				.then( function ( data ) {
					cache.set( page, data );
					return data;
				} );
		}

		function buildItem( image ) {
			var displayType = container.dataset.type;
			var li = document.createElement( 'li' );

			if ( 'masonry' === displayType ) {
				li.className = 'bltgallery-masonry__item';
				li.innerHTML = buildAnchorMarkup( image, 'medium' );
			} else if ( 'tile' === displayType ) {
				li.className = 'bltgallery-tile__item';
				var showCaptions = grid.dataset.showCaptions === '1';
				li.innerHTML = buildAnchorMarkup( image, 'thumb', showCaptions ? 'tile' : null );
			} else if ( 'lightbox' === displayType ) {
				li.className = 'bltgallery-lightbox__thumb';
				li.innerHTML = '<button class="bltgallery-lightbox__trigger" data-image-id="' + image.id + '" data-index="-1" aria-label="' + escAttr( image.alt_text || image.filename ) + '">' + buildImgTag( image, 'thumb' ) + '</button>';
			}
			return li;
		}

		function buildAnchorMarkup( image, size, captionVariant ) {
			var caption = image.caption
				? '<span class="' + ( 'tile' === captionVariant ? 'bltgallery-tile__caption' : 'bltgallery__caption' ) + '">' + escHtml( image.caption ) + '</span>'
				: '';
			return '<a href="' + escAttr( image.url ) + '" class="bltgallery__link" data-image-id="' + image.id + '" aria-label="' + escAttr( image.alt_text || image.filename ) + '">' + buildImgTag( image, size ) + caption + '</a>';
		}

		function buildImgTag( image, size ) {
			var url = ( image.thumbs && image.thumbs[ size ] && image.thumbs[ size ].url ) || image.thumb_url || image.url;
			var w   = ( image.thumbs && image.thumbs[ size ] && image.thumbs[ size ].width )  || image.width || '';
			var h   = ( image.thumbs && image.thumbs[ size ] && image.thumbs[ size ].height ) || image.height || '';
			return '<img src="' + escAttr( url ) + '" alt="' + escAttr( image.alt_text || image.filename || '' ) + '"' + ( w ? ' width="' + w + '"' : '' ) + ( h ? ' height="' + h + '"' : '' ) + ' loading="lazy" decoding="async">';
		}

		function escHtml( s ) { return String( s ).replace( /&/g, '&amp;' ).replace( /</g, '&lt;' ).replace( />/g, '&gt;' ); }
		function escAttr( s ) { return escHtml( s ).replace( /"/g, '&quot;' ); }

		function appendImages( images ) {
			var frag = document.createDocumentFragment();
			images.forEach( function ( image ) { frag.appendChild( buildItem( image ) ); } );
			grid.appendChild( frag );

			// Merge into the lightbox's image array, if any.
			if ( container._bltLightbox && Array.isArray( container._bltLightboxImages ) ) {
				images.forEach( function ( img ) {
					container._bltLightboxImages.push( {
						id:      img.id,
						src:     img.url,
						thumb:   img.thumb_url,
						alt:     img.alt_text || img.filename,
						caption: img.caption || '',
						w:       img.width,
						h:       img.height,
					} );
				} );
			}
		}

		async function loadPage( page, opts ) {
			opts = opts || {};
			if ( status ) { status.hidden = false; status.textContent = 'Loading…'; }
			if ( btn && ! opts.silent ) { btn.disabled = true; }
			try {
				var data = await fetchPage( page );
				if ( ! opts.replace ) {
					appendImages( data.images );
				} else {
					// numbered mode: replace the grid contents
					grid.innerHTML = '';
					appendImages( data.images );
				}
				if ( btn ) {
					if ( ! data.has_more ) {
						nav.remove();
					} else {
						btn.dataset.page = String( page + 1 );
						btn.disabled = false;
						btn.textContent = 'Load more';
					}
				}
				if ( status ) {
					status.hidden = true;
					status.textContent = '';
				}
				if ( pageBtns.length ) {
					pageBtns.forEach( function ( b ) {
						var active = parseInt( b.dataset.page, 10 ) === page;
						b.classList.toggle( 'is-active', active );
						b.setAttribute( 'aria-current', active ? 'page' : 'false' );
					} );
				}
			} catch ( err ) {
				if ( err && err.name === 'AbortError' ) return;
				if ( status ) {
					status.hidden = false;
					status.textContent = 'Could not load more.';
				}
				if ( btn ) { btn.disabled = false; }
			}
		}

		// --- load-more / infinite ---
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var page = parseInt( btn.dataset.page, 10 ) || 2;
				loadPage( page );
			} );

			// Prefetch the next page ~1 viewport before the button is visible.
			if ( 'IntersectionObserver' in window ) {
				var prefetched = new Set();
				var prefetcher = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( ! entry.isIntersecting ) return;
						var page = parseInt( btn.dataset.page, 10 ) || 2;
						if ( prefetched.has( page ) ) return;
						prefetched.add( page );
						fetchPage( page ).catch( function () { prefetched.delete( page ); } );
					} );
				}, { rootMargin: '600px 0px' } );
				prefetcher.observe( btn );
			}

			// Infinite mode: auto-click whenever the button enters view.
			if ( 'infinite' === mode && 'IntersectionObserver' in window ) {
				var io = new IntersectionObserver( function ( entries ) {
					entries.forEach( function ( entry ) {
						if ( entry.isIntersecting && ! btn.disabled ) {
							btn.click();
						}
					} );
				}, { rootMargin: '200px 0px' } );
				io.observe( btn );
			}
		}

		// --- numbered ---
		if ( pageBtns.length ) {
			pageBtns.forEach( function ( b ) {
				b.addEventListener( 'click', function () {
					var page = parseInt( b.dataset.page, 10 ) || 1;
					loadPage( page, { replace: true } );
					container.scrollIntoView( { behavior: 'smooth', block: 'start' } );
				} );

				// Hover prefetch for numbered links.
				b.addEventListener( 'pointerenter', function () {
					var page = parseInt( b.dataset.page, 10 ) || 1;
					fetchPage( page ).catch( function () {} );
				}, { once: true } );
			} );
		}
	}

} )();
