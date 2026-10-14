/**
 * Room type page photo gallery (`/rooms/{slug}`): any photo, or the
 * "View all photos" button, opens a lightbox with previous / next, zoom,
 * full screen and the thumbnails strip at the bottom.
 *
 * Tiny on load: the lightbox (yet-another-react-lightbox) is fetched on the
 * first click. Without JavaScript each photo is a plain link to the full
 * image. The markup and the slides (`data-rtbp-gallery`, JSON) come from
 * templates/booking/room-type.php.
 */
import { createElement, createRoot, lazy, Suspense } from '@wordpress/element';

const Viewer = lazy( () => import( './Viewer' ) );

/**
 * Wire one gallery.
 *
 * @param {HTMLElement} el The `[data-rtbp-gallery]` element.
 */
function mount( el ) {
	let slides = [];
	try {
		slides = JSON.parse( el.dataset.rtbpGallery || '[]' );
	} catch ( e ) {
		return;
	}
	if ( ! slides.length ) {
		return;
	}
	const host = document.createElement( 'div' );
	document.body.appendChild( host );
	const root = createRoot( host );

	const render = ( index ) =>
		root.render(
			createElement(
				Suspense,
				{ fallback: null },
				createElement( Viewer, {
					slides,
					index,
					title: el.dataset.rtbpGalleryTitle || '',
					onClose: () => {
						render( -1 );
						// Back to where the visitor was.
						el.querySelector( '.rtbp-gallery-button' )?.focus();
					},
				} )
			)
		);

	el.addEventListener( 'click', ( event ) => {
		const target = event.target.closest( '[data-rtbp-gallery-index]' );
		if ( ! target || ! el.contains( target ) ) {
			return;
		}
		event.preventDefault();
		render( Number( target.dataset.rtbpGalleryIndex ) || 0 );
	} );

	// Fetch the lightbox once the pointer comes near, so the first open is instant.
	el.addEventListener( 'pointerenter', () => import( './Viewer' ), {
		once: true,
	} );
}

const start = () =>
	document.querySelectorAll( '[data-rtbp-gallery]' ).forEach( mount );

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', start );
} else {
	start();
}
