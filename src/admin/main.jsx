/**
 * Admin app entry.
 *
 * Mounted into the node printed by views/app.php.
 *
 * Translations are inlined by WordPress via `wp_set_script_translations()`
 * before this bundle runs (see includes/Assets/LoadAssets.php), and
 * `@wordpress/i18n` is externalised to `wp.i18n` by the dependency-extraction
 * plugin — so `__()` resolves against the same locale data with no runtime
 * loader and no bundled copy of the package.
 */
import { createRoot } from 'react-dom/client';

import App from './App';
import { publishRuntime } from '@/lib/runtime';
import '../index.css';

// Before anything renders: add-on scripts load after this bundle and extend
// the app through window.rtbp and the rtbp.* filters.
publishRuntime();

/**
 * Mount once every script on the page has run. Add-on scripts are printed
 * after this one, and the route table (rtbp.admin.routes) is read once, on
 * first render; rendering as soon as this bundle runs would race them, since
 * the browser can run the first render while it is still fetching the next
 * script. DOMContentLoaded fires only after all footer and deferred scripts.
 */
function mount() {
	const container = document.getElementById( 'radius-hotel-booking' );
	if ( container ) {
		createRoot( container ).render( <App /> );
	}
}

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', mount, { once: true } );
} else {
	mount();
}
