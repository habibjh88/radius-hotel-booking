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
const defaults = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaults,
	entry: {
		admin: path.resolve( __dirname, 'src/admin/main.jsx' ),
		site: path.resolve( __dirname, 'src/site/main.jsx' ),
		blocks: path.resolve( __dirname, 'src/blocks/index.js' ),
	},
	output: {
		...defaults.output,
		path: path.resolve( __dirname, 'build' ),
	},
	resolve: {
		...defaults.resolve,
		alias: {
			...( defaults.resolve?.alias || {} ),
			'@': path.resolve( __dirname, 'src' ),
		},
		extensions: [ '.js', '.jsx', '.json', ...( defaults.resolve?.extensions || [] ) ],
	},
};
