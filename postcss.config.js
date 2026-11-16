module.exports = {
	plugins: [
		require( 'tailwindcss' ),
		// Public stylesheet only: colour utilities beat a Bootstrap theme's `!important` ones.
		require( './bin/postcss-theme-proof-colors.js' ),
		require( 'autoprefixer' ),
	],
};
