/**
 * Tailwind for the public site (src/site.css, `@config`).
 *
 * The admin config, with two changes:
 * - **No global preflight.** Tailwind's reset styles every element on the
 *   page; on a guest page that would restyle the theme (found in M00 T5).
 *   site.css carries a reset scoped under `.rtbp-root` instead.
 * - **Only the sources the site uses** are scanned, so the public CSS does
 *   not carry the admin screens' classes (the site bundle budget, M04 T4).
 *
 * @type {import('tailwindcss').Config}
 */
const base = require( './tailwind.config.js' );

module.exports = {
	...base,
	content: [
		'./src/site/**/*.{js,jsx}',
		'./src/components/**/*.{js,jsx}',
		'./src/lib/**/*.{js,jsx}',
		// The embeds' plain-HTML fallbacks (shown before the app mounts, or without JS).
		'./templates/embeds/**/*.php',
		// The room type page (rendered in PHP, inside the theme).
		'./templates/booking/room-type.php',
	],
	corePlugins: {
		...( base.corePlugins || {} ),
		preflight: false,
		// `.container` is not scoped by `important` and themes use the class: off.
		container: false,
	},
};
