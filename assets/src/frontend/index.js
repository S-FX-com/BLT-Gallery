/**
 * ZymGallery – frontend entry point.
 *
 * Initialises all display-type interactions once the DOM is ready.
 * Each module (masonry, slideshow, lightbox) is self-contained and
 * queries for its own container.
 */
import './style.css';
import { initMasonry }   from './masonry';
import { initSlideshow } from './slideshow';
import { initLightbox }  from './lightbox';

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
