/**
 * ZymGallery Admin App – entry point.
 *
 * Uses @wordpress/element (React) and @wordpress/components for UI,
 * @wordpress/api-fetch for authenticated REST calls, and client-side
 * hash routing (no react-router dependency needed).
 */
import { render } from '@wordpress/element';
import App from './components/App';
import './style.css';

const root = document.getElementById( 'zymgallery-admin' );
if ( root ) {
	render( <App />, root );
}
