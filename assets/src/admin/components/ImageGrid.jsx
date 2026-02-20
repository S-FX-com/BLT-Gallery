/**
 * ImageGrid – shows all images in a gallery with drag-to-reorder and delete.
 */
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Button, Notice } from '@wordpress/components';

export default function ImageGrid( { images, galleryId, onDeleted, onReordered } ) {
	const [ error,   setError   ] = useState( null );
	const [ dragIdx, setDragIdx ] = useState( null );

	const deleteImage = async ( id, filename ) => {
		if ( ! window.confirm( `Delete image "${ filename }"?` ) ) return;
		try {
			await apiFetch( {
				path:   `/zymgallery/v1/galleries/${ galleryId }/images/${ id }`,
				method: 'DELETE',
			} );
			onDeleted( id );
		} catch ( e ) {
			setError( e.message || 'Delete failed.' );
		}
	};

	// Drag-and-drop reorder handlers.
	const onDragStart = ( idx ) => setDragIdx( idx );

	const onDragOver = ( e, idx ) => {
		e.preventDefault();
		if ( dragIdx === null || dragIdx === idx ) return;
		const reordered = [ ...images ];
		const [ moved ] = reordered.splice( dragIdx, 1 );
		reordered.splice( idx, 0, moved );
		setDragIdx( idx );
		onReordered( reordered.map( ( img ) => img.id ) );
	};

	const onDragEnd = () => setDragIdx( null );

	if ( images.length === 0 ) {
		return <p className="zymgallery-image-grid__empty">No images yet. Upload some above.</p>;
	}

	return (
		<div className="zymgallery-image-grid">
			{ error && <Notice status="error" onRemove={ () => setError( null ) }>{ error }</Notice> }

			<p className="zymgallery-image-grid__hint">Drag images to reorder.</p>

			<ul className="zymgallery-image-grid__list">
				{ images.map( ( img, idx ) => (
					<li
						key={ img.id }
						className={ `zymgallery-image-grid__item${ dragIdx === idx ? ' is-dragging' : '' }` }
						draggable
						onDragStart={ () => onDragStart( idx ) }
						onDragOver={ ( e ) => onDragOver( e, idx ) }
						onDragEnd={ onDragEnd }
					>
						<img
							src={ img.thumb_url || img.url }
							alt={ img.alt_text || img.filename }
							loading="lazy"
							width={ 100 }
							height={ 100 }
						/>
						<div className="zymgallery-image-grid__meta">
							<span title={ img.filename }>{ img.filename }</span>
							<Button
								isDestructive
								onClick={ () => deleteImage( img.id, img.filename ) }
								aria-label={ `Delete ${ img.filename }` }
							>
								×
							</Button>
						</div>
					</li>
				) ) }
			</ul>
		</div>
	);
}
