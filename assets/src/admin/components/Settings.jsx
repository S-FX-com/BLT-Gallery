/**
 * Settings – general plugin settings + AWS S3/CloudFront configuration.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import {
	Button, TextControl, SelectControl, ToggleControl,
	Notice, Spinner, Panel, PanelBody, PanelRow,
} from '@wordpress/components';

export default function Settings() {
	const [ general,     setGeneral     ] = useState( null );
	const [ aws,         setAws         ] = useState( null );
	const [ loading,     setLoading     ] = useState( true );
	const [ savingGen,   setSavingGen   ] = useState( false );
	const [ savingAws,   setSavingAws   ] = useState( false );
	const [ testingConn, setTestingConn ] = useState( false );
	const [ testResult,  setTestResult  ] = useState( null );
	const [ error,       setError       ] = useState( null );
	const [ successMsg,  setSuccessMsg  ] = useState( null );

	useEffect( () => {
		Promise.all( [
			apiFetch( { path: '/zymgallery/v1/settings' } ),
			apiFetch( { path: '/zymgallery/v1/settings/aws' } ),
		] ).then( ( [ g, a ] ) => {
			setGeneral( g );
			setAws( a );
		} ).catch( ( e ) => setError( e.message ) )
		   .finally( () => setLoading( false ) );
	}, [] );

	const saveGeneral = async () => {
		setSavingGen( true );
		setError( null );
		try {
			const saved = await apiFetch( {
				path:   '/zymgallery/v1/settings',
				method: 'POST',
				data:   general,
			} );
			setGeneral( saved );
			flash( 'General settings saved.' );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setSavingGen( false );
		}
	};

	const saveAws = async () => {
		setSavingAws( true );
		setError( null );
		try {
			const saved = await apiFetch( {
				path:   '/zymgallery/v1/settings/aws',
				method: 'POST',
				data:   aws,
			} );
			setAws( saved );
			flash( 'AWS settings saved.' );
		} catch ( e ) {
			setError( e.message );
		} finally {
			setSavingAws( false );
		}
	};

	const testConnection = async () => {
		setTestingConn( true );
		setTestResult( null );
		try {
			const result = await apiFetch( {
				path:   '/zymgallery/v1/settings/aws/test',
				method: 'POST',
			} );
			setTestResult( result );
		} catch ( e ) {
			setTestResult( { success: false, message: e.message } );
		} finally {
			setTestingConn( false );
		}
	};

	const flash = ( msg ) => {
		setSuccessMsg( msg );
		setTimeout( () => setSuccessMsg( null ), 3000 );
	};

	const setGen = ( key, val ) => setGeneral( { ...general, [ key ]: val } );
	const setA   = ( key, val ) => setAws( { ...aws, [ key ]: val } );

	if ( loading ) return <Spinner />;

	return (
		<div className="zymgallery-settings">
			<h1>ZymGallery Settings</h1>

			{ error      && <Notice status="error"   onRemove={ () => setError( null )      }>{ error      }</Notice> }
			{ successMsg && <Notice status="success" onRemove={ () => setSuccessMsg( null ) }>{ successMsg }</Notice> }

			{ /* ── General ──────────────────────────────────────────────── */ }
			<Panel>
				<PanelBody title="General" initialOpen={ true }>
					<PanelRow>
						<SelectControl
							label="Default Display Type"
							value={ general?.default_display_type ?? 'masonry' }
							options={ [
								{ value: 'masonry',   label: 'Masonry'   },
								{ value: 'tile',      label: 'Tile Grid' },
								{ value: 'slideshow', label: 'Slideshow' },
								{ value: 'lightbox',  label: 'Lightbox'  },
							] }
							onChange={ ( v ) => setGen( 'default_display_type', v ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label="Lazy-load images"
							checked={ !! general?.lazy_load }
							onChange={ ( v ) => setGen( 'lazy_load', v ) }
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="WebP quality (1–100)"
							type="number"
							min={ 1 }
							max={ 100 }
							value={ general?.webp_quality ?? 85 }
							onChange={ ( v ) => setGen( 'webp_quality', parseInt( v, 10 ) ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label="Delete all data on uninstall"
							checked={ !! general?.delete_data_on_uninstall }
							onChange={ ( v ) => setGen( 'delete_data_on_uninstall', v ) }
						/>
					</PanelRow>
					<PanelRow>
						<Button variant="primary" onClick={ saveGeneral } isBusy={ savingGen }>
							Save General Settings
						</Button>
					</PanelRow>
				</PanelBody>
			</Panel>

			{ /* ── AWS ─────────────────────────────────────────────────── */ }
			<Panel>
				<PanelBody title="AWS S3 &amp; CloudFront" initialOpen={ false }>
					<PanelRow>
						<TextControl
							label="AWS Access Key ID"
							value={ aws?.access_key_id ?? '' }
							onChange={ ( v ) => setA( 'access_key_id', v ) }
							autoComplete="off"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="AWS Secret Access Key"
							type="password"
							value={ aws?.secret_access_key ?? '' }
							onChange={ ( v ) => setA( 'secret_access_key', v ) }
							autoComplete="new-password"
							help="Leave blank to keep the existing value."
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="AWS Region"
							value={ aws?.region ?? 'us-east-1' }
							onChange={ ( v ) => setA( 'region', v ) }
							placeholder="us-east-1"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="S3 Bucket Name"
							value={ aws?.bucket ?? '' }
							onChange={ ( v ) => setA( 'bucket', v ) }
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="S3 Path Prefix (optional)"
							value={ aws?.path_prefix ?? '' }
							onChange={ ( v ) => setA( 'path_prefix', v ) }
							placeholder="gallery/"
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label="Auto-offload new uploads to S3"
							checked={ !! aws?.auto_offload }
							onChange={ ( v ) => setA( 'auto_offload', v ) }
						/>
					</PanelRow>
					<PanelRow>
						<ToggleControl
							label="Delete local files after upload to S3"
							checked={ !! aws?.delete_local_after_upload }
							onChange={ ( v ) => setA( 'delete_local_after_upload', v ) }
						/>
					</PanelRow>

					<hr style={ { margin: '1.5rem 0' } } />
					<h3 style={ { marginBottom: '1rem' } }>CloudFront CDN (optional)</h3>

					<PanelRow>
						<TextControl
							label="CloudFront Domain"
							value={ aws?.cloudfront_domain ?? '' }
							onChange={ ( v ) => setA( 'cloudfront_domain', v ) }
							placeholder="d1234abcd.cloudfront.net"
						/>
					</PanelRow>
					<PanelRow>
						<TextControl
							label="CloudFront Distribution ID"
							value={ aws?.cloudfront_distribution_id ?? '' }
							onChange={ ( v ) => setA( 'cloudfront_distribution_id', v ) }
							help="Used for cache invalidation when images are deleted."
						/>
					</PanelRow>

					<PanelRow>
						<div style={ { display: 'flex', gap: 12, alignItems: 'center' } }>
							<Button variant="primary" onClick={ saveAws } isBusy={ savingAws }>
								Save AWS Settings
							</Button>
							<Button variant="secondary" onClick={ testConnection } isBusy={ testingConn }>
								Test S3 Connection
							</Button>
						</div>
					</PanelRow>

					{ testResult && (
						<PanelRow>
							<Notice
								status={ testResult.success ? 'success' : 'error' }
								onRemove={ () => setTestResult( null ) }
							>
								{ testResult.message }
							</Notice>
						</PanelRow>
					) }
				</PanelBody>
			</Panel>
		</div>
	);
}
