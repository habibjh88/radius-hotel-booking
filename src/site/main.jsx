/**
 * Public app entry.
 *
 * Mounted into the node printed by templates/items/item-list.php, which the
 * `[rtbp_items]` shortcode, the `radius-hotel-booking/items` block and the
 * Elementor widget all render.
 */
import { createRoot } from 'react-dom/client';

import App from './App';
import '../site.css';

const container = document.getElementById( 'radius-hotel-booking-site' );

if ( container ) {
	createRoot( container ).render(
		<App
			layout={ container.dataset.layout || 'grid' }
			columns={ Number( container.dataset.columns ) || 3 }
			perPage={ Number( container.dataset.perPage ) || 9 }
		/>
	);
}
