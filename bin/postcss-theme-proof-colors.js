/**
 * PostCSS: colour utilities of the public stylesheet win over a theme's.
 *
 * Themes built on Bootstrap (Beaver Builder's bb-theme, many others) force
 * class names Tailwind also uses — `.text-primary { color: #007bff !important }`,
 * `.bg-primary`, `.border-success`… — so on those sites our scoped
 * `.rtbp-root .text-primary` (the hotel's brand colour) lost to Bootstrap's blue.
 *
 * Every colour declaration of a `.rtbp-root …` utility in `site.css` gets
 * `!important`, variants included (`hover:`, `data-[state=…]:`, …), so they
 * still override each other exactly as before (same order, same specificity)
 * and a theme's `!important` can no longer reach inside the plugin.
 *
 * Left alone: the reset (`:where( … )`, lowest on purpose), the root rule
 * itself, and the admin stylesheet (wp-admin has no such theme).
 */
const COLOR_PROPS = new Set( [
	'color',
	'background-color',
	'border-color',
	'border-top-color',
	'border-right-color',
	'border-bottom-color',
	'border-left-color',
	'outline-color',
	'text-decoration-color',
	'fill',
	'stroke',
	'caret-color',
	'accent-color',
] );

module.exports = () => ( {
	postcssPlugin: 'rtbp-theme-proof-colors',
	Once( root ) {
		const file = root.source?.input?.file || '';
		if ( ! /[\\/]site\.css$/.test( file ) ) {
			return;
		}
		root.walkRules( ( rule ) => {
			const selectors = rule.selectors || [];
			const utility = selectors.every(
				( s ) =>
					s.startsWith( '.rtbp-root ' ) && ! s.includes( ':where(' )
			);
			if ( ! utility ) {
				return;
			}
			rule.walkDecls( ( decl ) => {
				if ( COLOR_PROPS.has( decl.prop ) ) {
					decl.important = true;
				}
			} );
		} );
	},
} );
module.exports.postcss = true;
