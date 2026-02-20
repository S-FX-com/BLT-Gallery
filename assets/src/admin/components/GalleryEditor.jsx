/**
 * GalleryEditor – manages metadata, display settings, and image uploads
 * for a single gallery.
 */
import { useState, useEffect, useCallback } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button, TextControl, TextareaControl, SelectControl,
	Notice, Spinner, Panel, PanelBody, PanelRow,
} from '@wordpress/components';
import ImageUploader from './ImageUploader';
import ImageGrid     from './ImageGrid';

const DISPLAY_TYPES = window.zymGalleryConfig?.displayTypes ?? [
	{ value: 'masonry',   label: 'Masonry'   },
	{ value: 'tile',      label: 'Tile Grid' },
	{ value: 'slideshow', label: 'Slideshow' },
	{ value: 'lightbox',  label: 'Lightbox'  },
];

export default function GalleryEditor( { galleryId, onBack } ) {
	const [ gallery,  setGallery  ] = useState( null );
	const [ images,   setImages   ] = useState( [] );
	const [ loading,  setLoading  ] = useState( true );
	const [ saving,   setSaving   ] = useState( false );
	const [ error,    setError    ] = useState( null );
	const [ success,  setSuccess  ] = useState( false );

	// Form state.
	const [ title,       setTitle       ] = useState( '' );
	const [ description, setDescription ] = useState( '' );
	const [ displayType, setDisplayType ] = useState( 'masonry' );
	const [ settings,    setSettings    ] = useState( {} );

	const loadGallery = useCallback( async () => {
		setLoading( true );
		try {
			const [ g, imgs ] = await Promise.all( [
				apiFetch( { path: `/zymgallery/v1/galleries/${ galleryId }` } ),
				apiFetch( { path: `/zymgallery/v1/galleries/${ galleryId }/images` } ),
			] );
			setGallery( g );
			setImages( imgs );
			setTitle( g.title );
			setDescription( g.description );
			setDisplayType( g.display_type );
			setSettings( g.settings || {} );
		} catch ( e ) {
			setError( e.message || 'Failed to load gallery.' );
		} finally {
			setLoading( false );
		}
	}, [ galleryId ] );

	useEffect( () => { loadGallery(); }, [ loadGallery ] );

	const save = async () => {
		setSaving( true );
		setError( null );
		setSuccess( false );
		try {
			await apiFetch( {
				path:   `/zymgallery/v1/galleries/${ galleryId }`,
				method: 'PUT',
				data:   { title, description, display_type: displayType, settings },
			} );
			setSuccess( true );
			setTimeout( () => setSuccess( false ), 3000 );
		} catch ( e ) {
			setError( e.message || 'Save failed.' );
		} finally {
			setSaving( false );
		}
	};

	const onUploaded = ( newImage ) => {
		setImages( ( prev ) => [ ...prev, newImage ] );
	};

	const onImageDeleted = ( id ) => {
		setImages( ( prev ) => prev.filter( ( img ) => img.id !== id ) );
	};

	const onReordered = async ( orderedIds ) => {
		setImages( ( prev ) => {
			const map = Object.fromEntries( prev.map( ( img ) => [ img.id, img ] ) );
			return orderedIds.map( ( id ) => map[ id ] );
		} );
		try {
			await apiFetch( {
				path:   `/zymgallery/v1/galleries/${ galleryId }/images/reorder`,
				method: 'POST',
				data:   { order: orderedIds },
			} );
		} catch ( e ) {
			setError( 'Reorder failed: ' + ( e.message || '' ) );
		}
	};

	if ( loading ) return <Spinner />;

	return (
		<div className="zymgallery-editor">
			<div className="zymgallery-editor__header">
				<Button variant="tertiary" onClick={ onBack }>← Galleries</Button>
				<h1>{ gallery?.title ?? 'Edit Gallery' }</h1>
				<div>
					<code>[zymgallery id="{ galleryId }"]</code>
				</div>
			</div>

			{ error   && <Notice status="error"   onRemove={ () => setError(null)   }>{ error   }</Notice> }
			{ success && <Notice status="success" onRemove={ () => setSuccess(false) }>Settings saved.</Notice> }

			<Panel>
				<PanelBody title="Gallery Settings" initialOpen={ true }>
					<PanelRow>
						<TextControl
							label="Title"
							value={ title }
							onChange={ setTitle }
						/>
					</PanelRow>
					<PanelRow>
						<TextareaControl
							label="Description"
							value={ description }
							onChange={ setDescription }
							rows={ 3 }
						/>
					</PanelRow>
					<PanelRow>
						<SelectControl
							label="Display Type"
							value={ displayType }
							options={ DISPLAY_TYPES }
							onChange={ setDisplayType }
						/>
					</PanelRow>

					{ displayType !== 'slideshow' && (
						<PanelRow>
							<TextControl
								label="Columns"
								type="number"
								min={ 1 }
								max={ 6 }
								value={ settings.columns ?? 3 }
								onChange={ ( v ) => setSettings( { ...settings, columns: parseInt( v, 10 ) } ) }
							/>
						</PanelRow>
					) }

					{ displayType === 'slideshow' && (
						<>
							<PanelRow>
								<SelectControl
									label="Autoplay"
									value={ settings.autoplay ? '1' : '0' }
									options={ [ { value: '0', label: 'Off' }, { value: '1', label: 'On' } ] }
									onChange={ ( v ) => setSettings( { ...settings, autoplay: v === '1' } ) }
								/>
							</PanelRow>
							<PanelRow>
								<TextControl
									label="Speed (ms)"
									type="number"
									min={ 1000 }
									value={ settings.speed ?? 4000 }
									onChange={ ( v ) => setSettings( { ...settings, speed: parseInt( v, 10 ) } ) }
								/>
							</PanelRow>
						</>
					) }

					<PanelRow>
						<Button variant="primary" onClick={ save } isBusy={ saving }>
							Save Settings
						</Button>
					</PanelRow>
				</PanelBody>
			</Panel>

			<Panel>
				<PanelBody title={ `Images (${ images.length })` } initialOpen={ true }>
					<ImageUploader galleryId={ galleryId } onUploaded={ onUploaded } />
					<ImageGrid
						images={ images }
						galleryId={ galleryId }
						onDeleted={ onImageDeleted }
						onReordered={ onReordered }
					/>
				</PanelBody>
			</Panel>
		</div>
	);
}
