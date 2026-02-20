/**
 * Slideshow – accessible carousel.
 *
 * Features:
 *   • Keyboard navigation (← →)
 *   • Touch / swipe support
 *   • Autoplay with play/pause on focus/blur
 *   • Respects prefers-reduced-motion
 */

const REDUCED_MOTION = window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

export function initSlideshow() {
	document.querySelectorAll( '.zymgallery--slideshow' ).forEach( initGallery );
}

function initGallery( container ) {
	const ss     = container.querySelector( '.zymgallery-slideshow' );
	if ( ! ss ) return;

	const track  = ss.querySelector( '.zymgallery-slideshow__track' );
	const slides = [ ...ss.querySelectorAll( '.zymgallery-slideshow__slide' ) ];
	const dots   = [ ...ss.querySelectorAll( '.zymgallery-slideshow__dot' ) ];
	const prev   = ss.querySelector( '.zymgallery-slideshow__prev' );
	const next   = ss.querySelector( '.zymgallery-slideshow__next' );

	if ( slides.length < 2 ) return; // Nothing to slide.

	let current    = 0;
	let autoplayId = null;
	const autoplay = ss.dataset.autoplay === 'true' && ! REDUCED_MOTION;
	const speed    = parseInt( ss.dataset.speed, 10 ) || 4000;

	function goTo( idx ) {
		slides[ current ].classList.remove( 'is-active' );
		slides[ current ].setAttribute( 'aria-hidden', 'true' );
		if ( dots[ current ] ) dots[ current ].classList.remove( 'is-active' );

		current = ( idx + slides.length ) % slides.length;

		slides[ current ].classList.add( 'is-active' );
		slides[ current ].setAttribute( 'aria-hidden', 'false' );
		if ( dots[ current ] ) dots[ current ].classList.add( 'is-active' );

		track.style.transform = REDUCED_MOTION
			? 'none'
			: `translateX(-${ current * 100 }%)`;
	}

	// Hide all but first slide initially.
	slides.forEach( ( slide, i ) => {
		slide.setAttribute( 'aria-hidden', i === 0 ? 'false' : 'true' );
	} );

	if ( prev ) prev.addEventListener( 'click', () => { goTo( current - 1 ); resetAutoplay(); } );
	if ( next ) next.addEventListener( 'click', () => { goTo( current + 1 ); resetAutoplay(); } );

	dots.forEach( ( dot, i ) => {
		dot.addEventListener( 'click', () => { goTo( i ); resetAutoplay(); } );
	} );

	// Keyboard.
	ss.addEventListener( 'keydown', ( e ) => {
		if ( e.key === 'ArrowLeft'  ) { goTo( current - 1 ); resetAutoplay(); }
		if ( e.key === 'ArrowRight' ) { goTo( current + 1 ); resetAutoplay(); }
	} );

	// Touch / swipe.
	let touchStartX = null;
	ss.addEventListener( 'touchstart', ( e ) => { touchStartX = e.touches[ 0 ].clientX; }, { passive: true } );
	ss.addEventListener( 'touchend', ( e ) => {
		if ( touchStartX === null ) return;
		const delta = e.changedTouches[ 0 ].clientX - touchStartX;
		if ( Math.abs( delta ) > 40 ) {
			goTo( delta < 0 ? current + 1 : current - 1 );
			resetAutoplay();
		}
		touchStartX = null;
	} );

	// Autoplay.
	function startAutoplay() {
		if ( ! autoplay ) return;
		autoplayId = setInterval( () => goTo( current + 1 ), speed );
	}

	function stopAutoplay() {
		clearInterval( autoplayId );
	}

	function resetAutoplay() {
		stopAutoplay();
		startAutoplay();
	}

	ss.addEventListener( 'mouseenter', stopAutoplay );
	ss.addEventListener( 'mouseleave', startAutoplay );
	ss.addEventListener( 'focusin',    stopAutoplay );
	ss.addEventListener( 'focusout',   startAutoplay );

	startAutoplay();
}
