/**
 * ZymGallery – Webpack configuration.
 *
 * Builds two separate bundles:
 *   1. Admin   → assets/build/admin/{index.js,style.css,index.asset.php}
 *   2. Frontend → assets/build/frontend/{index.js,style.css,index.asset.php}
 */

const path                = require( 'path' );
const defaultConfig       = require( '@wordpress/scripts/config/webpack.config' );
const { BundleAnalyzerPlugin } = require( 'webpack-bundle-analyzer' );

const isDev  = process.env.NODE_ENV !== 'production';
const isAnalyze = !! process.env.ANALYZE;

/**
 * Helper: clone the base WordPress webpack config for a specific entry point.
 */
function makeConfig( entryName, entryFile, outputDir ) {
	return {
		...defaultConfig,
		name: entryName,
		entry: { index: path.resolve( __dirname, entryFile ) },
		output: {
			...defaultConfig.output,
			path: path.resolve( __dirname, outputDir ),
			filename: '[name].js',
		},
		plugins: [
			...( defaultConfig.plugins ?? [] ).filter(
				// Avoid duplicate DependencyExtractionWebpackPlugin instances.
				( p ) => p.constructor.name !== 'DependencyExtractionWebpackPlugin'
			),
			...( isAnalyze ? [ new BundleAnalyzerPlugin( { analyzerMode: 'static', reportFilename: `${ entryName }-report.html` } ) ] : [] ),
		],
	};
}

module.exports = [
	makeConfig( 'admin',    'assets/src/admin/index.jsx',    'assets/build/admin'    ),
	makeConfig( 'frontend', 'assets/src/frontend/index.js',  'assets/build/frontend' ),
];
