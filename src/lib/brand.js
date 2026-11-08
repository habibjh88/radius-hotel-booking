/**
 * The hotel's name and logo for the sidebar brand (Settings → General).
 *
 * PHP sends them as `radius_hotel_booking_param.brand`; saving Settings →
 * General updates them here, so the sidebar follows without a reload.
 */
import { useSyncExternalStore } from 'react';

const listeners = new Set();

let brand = window.radius_hotel_booking_param?.brand || {
	name: '',
	logo_url: '',
};

/**
 * Replace the brand and tell the subscribers.
 *
 * @param {Object} next `{ name, logo_url }` (either may be left out).
 * @return {void}
 */
export function setBrand( next ) {
	brand = { ...brand, ...next };
	if ( window.radius_hotel_booking_param ) {
		window.radius_hotel_booking_param.brand = brand;
	}
	listeners.forEach( ( listener ) => listener() );
}

/**
 * Brand from Settings → General's saved values: the name at once, the logo's
 * URL once the media library has it.
 *
 * @param {Object} general Saved general settings.
 * @return {void}
 */
export function setBrandFromSettings( general ) {
	const logoId = Number( general.logo ) || 0;
	setBrand( {
		name: general.companyName || '',
		...( logoId ? {} : { logo_url: '' } ),
	} );

	const media = window.wp?.media;
	if ( ! logoId || ! media ) {
		return;
	}
	const attachment = media.attachment( logoId );
	attachment
		.fetch()
		.then( () => {
			const data = attachment.toJSON();
			setBrand( {
				// Medium or full: never the thumbnail, which WordPress crops square.
				logo_url: data.sizes?.medium?.url || data.url || '',
			} );
		} )
		.catch( () => {} );
}

/**
 * The current brand; re-renders when it changes.
 *
 * @return {{name: string, logo_url: string}} Brand.
 */
export function useBrand() {
	return useSyncExternalStore(
		( listener ) => {
			listeners.add( listener );
			return () => listeners.delete( listener );
		},
		() => brand
	);
}
