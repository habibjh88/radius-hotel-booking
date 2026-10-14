/**
 * Webpack configuration.
 *
 * Extends the official @wordpress/scripts config, which already handles JSX,
 * SASS/PostCSS, asset copying and — crucially — the
 * DependencyExtractionWebpackPlugin: every WordPress package you import
 * (`@wordpress/i18n`, `@wordpress/api-fetch`, `react`, `react-dom`, …) is left
 * out of the bundle and declared in `build/<entry>.asset.php` instead, which
 * includes/Assets/LoadAssets.php reads to enqueue the right dependencies and a
 * content-hash version.
 *
 * `npm run start` and `npm run build` write to the same `build/` directory, so
 * there is no dev-server/production switch on the PHP side.
 *
 * To add an entry: add a line to `entry` below, add a matching
 * `src/<name>/main.jsx`, and enqueue it from LoadAssets.
 */
const path = require( 'path' );
const CopyWebpackPlugin = require( 'copy-webpack-plugin' );
const defaults = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaults,
	entry: {
		admin: path.resolve( __dirname, 'src/admin/main.jsx' ),
		site: path.resolve( __dirname, 'src/site/main.jsx' ),
		blocks: path.resolve( __dirname, 'src/blocks/index.js' ),
		// The room type page's photo lightbox (only on /rooms/{slug}).
		gallery: path.resolve( __dirname, 'src/gallery/index.js' ),
	},
	output: {
		...defaults.output,
		path: path.resolve( __dirname, 'build' ),
		// Lazy chunks carry a content hash: after an update, browsers fetch the
		// new code instead of running a cached `6096.js` from before.
		chunkFilename: '[name].[contenthash:8].js',
	},
	// Size budget: the admin entry may grow to 300 KB (JS + CSS, minified;
	// ~80 KB gzipped). The RTL stylesheet is excluded — it replaces admin.css
	// on RTL sites, never loads alongside it. Screens stay lazy-loaded (see
	// src/admin/routes.js), so this budget covers the shell and shared libs only.
	performance: {
		...( defaults.performance || {} ),
		hints: 'warning',
		maxEntrypointSize: 300 * 1024,
		maxAssetSize: 300 * 1024,
		assetFilter: ( file ) =>
			! /-rtl\.css$/.test( file ) && ! /\.asset\.php$/.test( file ),
	},
	plugins: [
		...( defaults.plugins || [] ),
		// Country flags for the phone fields, served as files (one small SVG
		// per shown country) instead of bundling 260 flags into the JS.
		new CopyWebpackPlugin( {
			patterns: [
				{
					from: path.resolve(
						__dirname,
						'node_modules/country-flag-icons/3x2/*.svg'
					),
					to: 'flags/[name][ext]',
				},
				// Lucide icons as files: the server prints them inline
				// (Support\Icons) and screens show them without loading
				// the whole set. Comments and line breaks dropped.
				{
					from: path.resolve(
						__dirname,
						'node_modules/lucide-static/icons/*.svg'
					),
					to: 'icons/[name][ext]',
					transform: ( content ) =>
						content
							.toString()
							.replace( /<!--[\s\S]*?-->/g, '' )
							.replace( /\s*\n\s*/g, ' ' )
							.replace( /\s+\/>/g, '/>' )
							.replace( /<svg\s+class="[^"]*"/, '<svg' )
							.trim(),
				},
			],
		} ),
	],
	resolve: {
		...defaults.resolve,
		alias: {
			...( defaults.resolve?.alias || {} ),
			'@': path.resolve( __dirname, 'src' ),
		},
		extensions: [
			'.js',
			'.jsx',
			'.json',
			...( defaults.resolve?.extensions || [] ),
		],
	},
};
