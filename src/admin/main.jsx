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
import '../index.css';

const container = document.getElementById( 'radius-hotel-booking' );

if ( container ) {
	createRoot( container ).render( <App /> );
}
