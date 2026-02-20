/**
 * Root App component.  Handles hash-based client-side routing.
 */
import { useState, useEffect } from '@wordpress/element';
import GalleryList   from './GalleryList';
import GalleryEditor from './GalleryEditor';
import Settings      from './Settings';

function getRoute() {
	const hash = window.location.hash.replace( '#/', '' );
	const parts = hash.split( '/' );
	return { view: parts[ 0 ] || 'galleries', id: parts[ 1 ] ? parseInt( parts[ 1 ], 10 ) : null };
}

export default function App() {
	const [ route, setRoute ] = useState( getRoute );

	useEffect( () => {
		const onHash = () => setRoute( getRoute() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	const navigate = ( path ) => {
		window.location.hash = '/' + path;
	};

	return (
		<div className="zymgallery-admin">
			<nav className="zymgallery-admin__nav">
				<span className="zymgallery-admin__brand">ZymGallery</span>
				<button onClick={ () => navigate( 'galleries' ) }
					className={ route.view === 'galleries' && !route.id ? 'is-active' : '' }>
					Galleries
				</button>
				<button onClick={ () => navigate( 'settings' ) }
					className={ route.view === 'settings' ? 'is-active' : '' }>
					Settings
				</button>
			</nav>

			<main className="zymgallery-admin__main">
				{ route.view === 'galleries' && ! route.id && (
					<GalleryList onEdit={ ( id ) => navigate( `galleries/${ id }` ) }
								 onCreate={ ( id ) => navigate( `galleries/${ id }` ) } />
				) }
				{ route.view === 'galleries' && route.id && (
					<GalleryEditor galleryId={ route.id }
								   onBack={ () => navigate( 'galleries' ) } />
				) }
				{ route.view === 'settings' && <Settings /> }
			</main>
		</div>
	);
}
