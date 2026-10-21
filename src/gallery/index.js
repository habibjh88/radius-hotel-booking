/**
 * Room type photo gallery: on the room type page (`/rooms/{slug}`) any
 * photo, or the "View all photos" button, opens a lightbox with previous /
 * next, zoom, full screen and the thumbnails strip at the bottom. The booking
 * form's results (src/components/booking/RoomTypeResults) use the same
 * markup, so their thumbnails open it too.
 *
 * One listener on the page: a click on `[data-rtbp-gallery-index]` inside a
 * `[data-rtbp-gallery]` (slides as JSON) opens that gallery at that photo —
 * also for markup the booking app renders after the page loaded.
 *
 * Tiny on load: the lightbox (yet-another-react-lightbox) is fetched when the
 * pointer first nears a gallery, or on the first click. Without JavaScript
 * each photo on the room type page is a plain link to the full image.
 */
import { createElement, createRoot, lazy, Suspense } from '@wordpress/element';

const Viewer = lazy( () => import( './Viewer' ) );

let root = null;

/**
 * Show a gallery at a photo (-1 closes it).
 *
 * @param {Object[]}         slides The slides.
 * @param {number}           index  Photo to open at.
 * @param {string}           title  Room type name.
 * @param {HTMLElement|null} back   Focused again on close.
 */
function render( slides, index, title, back ) {
	if ( ! root ) {
		const host = document.createElement( 'div' );
		document.body.appendChild( host );
		root = createRoot( host );
	}
	root.render(
		createElement(
			Suspense,
			{ fallback: null },
			createElement( Viewer, {
				slides,
				index,
				title,
				onClose: () => {
					render( slides, -1, title, null );
					// Back to where the visitor was.
					back?.focus?.();
				},
			} )
		)
	);
}

document.addEventListener( 'click', ( event ) => {
	const target = event.target.closest?.( '[data-rtbp-gallery-index]' );
	const gallery = target?.closest( '[data-rtbp-gallery]' );
	if ( ! gallery ) {
		return;
	}
	let slides = [];
	try {
		slides = JSON.parse( gallery.dataset.rtbpGallery || '[]' );
	} catch ( e ) {
		return;
	}
	if ( ! slides.length ) {
		return;
	}
	event.preventDefault();
	render(
		slides,
		Number( target.dataset.rtbpGalleryIndex ) || 0,
		gallery.dataset.rtbpGalleryTitle || '',
		target
	);
} );

// Fetch the lightbox once the pointer comes near a gallery, so the first open is instant.
const prefetch = ( event ) => {
	if ( event.target.closest?.( '[data-rtbp-gallery]' ) ) {
		document.removeEventListener( 'pointerover', prefetch );
		import( './Viewer' );
	}
};
document.addEventListener( 'pointerover', prefetch );
