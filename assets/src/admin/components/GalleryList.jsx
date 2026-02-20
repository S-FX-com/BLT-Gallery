/**
 * GalleryList – shows all galleries with create / delete actions.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice, Spinner } from '@wordpress/components';

export default function GalleryList( { onEdit, onCreate } ) {
	const [ galleries, setGalleries ] = useState( [] );
	const [ loading,   setLoading   ] = useState( true );
	const [ error,     setError     ] = useState( null );
	const [ creating,  setCreating  ] = useState( false );

	const load = useCallback( async () => {
		setLoading( true );
		setError( null );
		try {
			const data = await apiFetch( { path: '/zymgallery/v1/galleries?per_page=100' } );
			setGalleries( data );
		} catch ( e ) {
			setError( e.message || 'Failed to load galleries.' );
		} finally {
			setLoading( false );
		}
	}, [] );

	useEffect( () => { load(); }, [ load ] );

	const createGallery = async () => {
		const title = window.prompt( 'Gallery title:' );
		if ( ! title ) return;

		setCreating( true );
		try {
			const gallery = await apiFetch( {
				path:   '/zymgallery/v1/galleries',
				method: 'POST',
				data:   { title },
			} );
			onCreate( gallery.id );
		} catch ( e ) {
			setError( e.message || 'Failed to create gallery.' );
		} finally {
			setCreating( false );
		}
	};

	const deleteGallery = async ( id, title ) => {
		if ( ! window.confirm( `Delete gallery "${ title }"? This cannot be undone.` ) ) return;

		try {
			await apiFetch( { path: `/zymgallery/v1/galleries/${ id }`, method: 'DELETE' } );
			setGalleries( ( prev ) => prev.filter( ( g ) => g.id !== id ) );
		} catch ( e ) {
			setError( e.message || 'Failed to delete gallery.' );
		}
	};

	return (
		<div className="zymgallery-list">
			<div className="zymgallery-list__header">
				<h1>Galleries</h1>
				<Button variant="primary" onClick={ createGallery } isBusy={ creating }>
					+ New Gallery
				</Button>
			</div>

			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }

			{ loading ? (
				<Spinner />
			) : galleries.length === 0 ? (
				<div className="zymgallery-list__empty">
					<p>No galleries yet. Create your first one!</p>
				</div>
			) : (
				<table className="zymgallery-list__table wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Title</th>
							<th>Display Type</th>
							<th>Shortcode</th>
							<th>Created</th>
							<th>Actions</th>
						</tr>
					</thead>
					<tbody>
						{ galleries.map( ( g ) => (
							<tr key={ g.id }>
								<td>
									<strong>
										<button className="button-link" onClick={ () => onEdit( g.id ) }>
											{ g.title }
										</button>
									</strong>
								</td>
								<td>{ g.display_type }</td>
								<td>
									<code>[zymgallery id="{ g.id }"]</code>
								</td>
								<td>{ new Date( g.created_at ).toLocaleDateString() }</td>
								<td>
									<Button variant="secondary" onClick={ () => onEdit( g.id ) } style={ { marginRight: 8 } }>
										Edit
									</Button>
									<Button isDestructive onClick={ () => deleteGallery( g.id, g.title ) }>
										Delete
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
}
