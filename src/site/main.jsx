/**
 * Public app entry.
 *
 * Mounted into `#radius-hotel-booking-site`, which the public shortcodes,
 * blocks and Elementor widgets print (M04: search bar and booking flow).
 * The node's `data-*` attributes carry the embed's options.
 */
import { createRoot } from 'react-dom/client';

import App from './App';
import '../site.css';

const container = document.getElementById( 'radius-hotel-booking-site' );

if ( container ) {
	createRoot( container ).render( <App options={ { ...container.dataset } } /> );
}
