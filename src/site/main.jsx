/**
 * Public app entry.
 *
 * Every public embed (shortcode, block, Elementor widget — M04) prints its own
 * mount node, `<div class="rtbp-root rtbp-site-mount" data-embed="search|booking" …>`,
 * so a search bar and the booking flow can share a page. Each node gets its own
 * React root; its `data-*` attributes carry the embed's options. The original
 * single node `#radius-hotel-booking-site` still mounts as a booking embed.
 */
import { createRoot } from 'react-dom/client';

import App from './App';
import '../site.css';

const nodes = document.querySelectorAll(
	'.rtbp-site-mount, #radius-hotel-booking-site'
);

nodes.forEach( ( node ) => {
	// Mount once, even if the page prints the same node twice or the script runs again.
	if ( node.dataset.rtbpMounted ) {
		return;
	}
	node.dataset.rtbpMounted = '1';
	const { embed = 'booking', ...options } = node.dataset;
	createRoot( node ).render( <App embed={ embed } options={ options } /> );
} );
