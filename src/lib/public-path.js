/**
 * Tell webpack where the lazy chunks live.
 *
 * Import this FIRST in every entry (imports run in order, so it must come
 * before any module that loads a chunk). LoadAssets::register_entry() prints
 * `window.rtbpBuildUrl` (the plugin's build/ URL) before each bundle.
 *
 * Without it webpack works the folder out from the bundle's own <script src>,
 * and a caching/optimisation plugin that combines or moves the file (e.g.
 * SiteGround Optimizer's "Combine JS") makes every chunk 404. When the value
 * is missing, webpack's automatic guess stays in place.
 */
/* global __webpack_public_path__: writable */
const buildUrl = typeof window !== 'undefined' ? window.rtbpBuildUrl : '';

if ( typeof buildUrl === 'string' && buildUrl ) {
	// eslint-disable-next-line no-global-assign, camelcase
	__webpack_public_path__ = buildUrl.replace( /\/?$/, '/' );
}
