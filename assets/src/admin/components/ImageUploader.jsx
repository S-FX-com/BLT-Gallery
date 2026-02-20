/**
 * ImageUploader – drag-and-drop / click-to-upload component.
 */
import { useState, useRef, useCallback } from '@wordpress/element';
import { Notice, ProgressBar } from '@wordpress/components';

const { apiBase, nonce } = window.zymGalleryConfig ?? {};

export default function ImageUploader( { galleryId, onUploaded } ) {
	const [ dragging,  setDragging  ] = useState( false );
	const [ uploads,   setUploads   ] = useState( [] ); // { name, progress, error }
	const inputRef = useRef( null );

	const uploadFile = useCallback( async ( file ) => {
		const id = crypto.randomUUID();
		setUploads( ( prev ) => [ ...prev, { id, name: file.name, progress: 0, error: null } ] );

		const body = new FormData();
		body.append( 'file', file );

		const xhr = new XMLHttpRequest();
		xhr.open( 'POST', `${ apiBase }/galleries/${ galleryId }/upload` );
		xhr.setRequestHeader( 'X-WP-Nonce', nonce );

		xhr.upload.onprogress = ( e ) => {
			if ( e.lengthComputable ) {
				const pct = Math.round( ( e.loaded / e.total ) * 100 );
				setUploads( ( prev ) =>
					prev.map( ( u ) => ( u.id === id ? { ...u, progress: pct } : u ) )
				);
			}
		};

		xhr.onload = () => {
			if ( xhr.status === 201 ) {
				const image = JSON.parse( xhr.responseText );
				onUploaded( image );
				// Remove from uploads list after a brief delay.
				setTimeout( () => {
					setUploads( ( prev ) => prev.filter( ( u ) => u.id !== id ) );
				}, 1500 );
			} else {
				let msg = 'Upload failed.';
				try { msg = JSON.parse( xhr.responseText )?.message ?? msg; } catch {}
				setUploads( ( prev ) =>
					prev.map( ( u ) => ( u.id === id ? { ...u, error: msg } : u ) )
				);
			}
		};

		xhr.onerror = () => {
			setUploads( ( prev ) =>
				prev.map( ( u ) => ( u.id === id ? { ...u, error: 'Network error.' } : u ) )
			);
		};

		xhr.send( body );
	}, [ galleryId, onUploaded ] );

	const handleFiles = useCallback( ( files ) => {
		Array.from( files ).forEach( ( f ) => uploadFile( f ) );
	}, [ uploadFile ] );

	const onDrop = ( e ) => {
		e.preventDefault();
		setDragging( false );
		handleFiles( e.dataTransfer.files );
	};

	return (
		<div className="zymgallery-uploader">
			<div
				className={ `zymgallery-uploader__zone${ dragging ? ' is-dragging' : '' }` }
				onDragOver={ ( e ) => { e.preventDefault(); setDragging( true ); } }
				onDragLeave={ () => setDragging( false ) }
				onDrop={ onDrop }
				onClick={ () => inputRef.current?.click() }
				role="button"
				tabIndex={ 0 }
				onKeyDown={ ( e ) => e.key === 'Enter' && inputRef.current?.click() }
				aria-label="Drop images here or click to upload"
			>
				<span className="zymgallery-uploader__icon" aria-hidden="true">📷</span>
				<p>Drag &amp; drop images here, or <strong>click to browse</strong></p>
				<p className="zymgallery-uploader__hint">JPEG, PNG, GIF, WebP, AVIF · Max 50 MB each</p>

				<input
					ref={ inputRef }
					type="file"
					accept="image/*"
					multiple
					style={ { display: 'none' } }
					onChange={ ( e ) => handleFiles( e.target.files ) }
				/>
			</div>

			{ uploads.length > 0 && (
				<ul className="zymgallery-uploader__progress-list">
					{ uploads.map( ( u ) => (
						<li key={ u.id } className="zymgallery-uploader__progress-item">
							<span className="zymgallery-uploader__filename">{ u.name }</span>
							{ u.error ? (
								<Notice status="error" isDismissible={ false }>{ u.error }</Notice>
							) : (
								<ProgressBar value={ u.progress } />
							) }
						</li>
					) ) }
				</ul>
			) }
		</div>
	);
}
