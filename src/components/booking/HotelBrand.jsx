/**
 * The hotel's logo and name (Settings → General → Logo / Hotel name) at the
 * top of the website's popups (sign-in, Express checkout). Renders nothing
 * when neither is set; `hasHotelBrand()` lets a popup keep its own header then.
 */
import { __ } from '@wordpress/i18n';

/**
 * The brand the public app was given (`radius_hotel_booking_site_param.brand`).
 *
 * @return {{name: string, logo_url: string}} Brand.
 */
export function hotelBrand() {
	const brand = window.radius_hotel_booking_site_param?.brand || {};
	return { name: brand.name || '', logo_url: brand.logo_url || '' };
}

/**
 * @return {boolean} A logo or a hotel name is set.
 */
export function hasHotelBrand() {
	const brand = hotelBrand();
	return Boolean( brand.logo_url || brand.name );
}

/**
 * The popup's header band: logo and hotel name on the left, the close button
 * on the right, a divider below. It bleeds to the popup's edges, so pass the
 * popup's padding as `bleed` (Tailwind negative-margin classes).
 *
 * @param {Object}      props          Props.
 * @param {JSX.Element} props.close    The close button.
 * @param {string}      [props.bleed]  Negative margins matching the popup's padding.
 * @param {string}      [props.inset]  Horizontal padding matching the popup's.
 * @return {JSX.Element|null} Header, or null when neither logo nor name is set.
 */
export default function HotelBrand( {
	close,
	bleed = '-mx-6 -mt-6',
	inset = 'px-6',
} ) {
	const brand = hotelBrand();
	if ( ! brand.logo_url && ! brand.name ) {
		return null;
	}
	return (
		<div
			className={ `${ bleed } ${ inset } mb-6 flex items-center justify-between gap-4 rounded-t-2xl border-0 border-b border-solid border-border py-4` }
		>
			<span className="flex min-w-0 items-center gap-3">
				{ brand.logo_url ? (
					<img
						src={ brand.logo_url }
						// The name is beside it; without one, the image says it.
						alt={
							brand.name
								? ''
								: __( 'Logo', 'radius-hotel-booking' )
						}
						className="m-0 block h-10 w-auto max-w-[7rem] shrink-0 object-contain"
					/>
				) : null }
				{ brand.logo_url && brand.name ? (
					<span
						className="h-8 w-px shrink-0 bg-border"
						aria-hidden="true"
					/>
				) : null }
				{ brand.name ? (
					<span className="min-w-0 truncate text-base font-semibold tracking-tight text-heading">
						{ brand.name }
					</span>
				) : null }
			</span>
			{ close }
		</div>
	);
}
