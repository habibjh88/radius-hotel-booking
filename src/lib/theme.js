/**
 * Live brand colour.
 *
 * The saved primary colour is printed inline by PHP (Helpers\ThemeHelper), so
 * pages load already branded. This module only handles changes made in the
 * browser: the Settings screen previews a colour as it is picked, and
 * re-applies the saved one if the page is left without saving.
 */

export const DEFAULT_PRIMARY = '#0040ff';

const STYLE_ID = 'rtbp-theme-live';

/**
 * Whether a string is a 3- or 6-digit hex colour.
 *
 * @param {string} value Candidate.
 * @return {boolean} Valid.
 */
export const isHexColor = ( value ) =>
	/^#([0-9a-f]{3}|[0-9a-f]{6})$/i.test( String( value || '' ) );

/**
 * Apply a primary colour to every `.rtbp-root` on the page (portals included).
 *
 * @param {string} color Hex colour. Invalid values fall back to the default.
 * @return {void}
 */
export function applyPrimaryColor( color ) {
	if ( typeof document === 'undefined' ) {
		return;
	}

	const value = isHexColor( color ) ? color : DEFAULT_PRIMARY;
	let style = document.getElementById( STYLE_ID );

	if ( ! style ) {
		style = document.createElement( 'style' );
		style.id = STYLE_ID;
		document.head.appendChild( style );
	}

	style.textContent = `.rtbp-root{--primary:${ value };}`;
}

/**
 * Suggested brand colours for the settings picker.
 *
 * @type {Array<{value: string, name: string}>}
 */
export const PRIMARY_PRESETS = [
	{ value: '#0040ff', name: 'Radius blue' },
	{ value: '#4800ff', name: 'Violet' },
	{ value: '#0e7490', name: 'Lagoon' },
	{ value: '#047857', name: 'Emerald' },
	{ value: '#b45309', name: 'Amber' },
	{ value: '#be123c', name: 'Rose' },
	{ value: '#0f172a', name: 'Midnight' },
];
