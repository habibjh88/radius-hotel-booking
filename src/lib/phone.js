/**
 * Phone settings and checks that every screen can use without loading the
 * phone library (libphonenumber-js lives in the lazy PhoneInput chunk).
 *
 * - `phoneConfig()`: Settings → Phone numbers, from the page's params.
 * - `isPhoneValid( value )`: the full check once a PhoneInput has loaded
 *   (it registers it), a loose `+` and 8–15 digits check before that.
 *   The server checks again (Support\Phone).
 */
import { __ } from '@wordpress/i18n';

const DEFAULTS = {
	defaultCountry: 'US',
	allowedCountries: [],
	customCodes: [],
};

let validator = null;

/**
 * The page's params (admin or website).
 *
 * @return {Object} Params.
 */
function params() {
	return (
		window.radius_hotel_booking_param ||
		window.radius_hotel_booking_site_param ||
		{}
	);
}

/**
 * Settings → Phone numbers.
 *
 * @return {Object} `{ defaultCountry, allowedCountries, customCodes }`.
 */
export function phoneConfig() {
	return { ...DEFAULTS, ...( params().settings?.phone || {} ) };
}

/**
 * Change the settings in place (after Settings → Phone numbers is saved).
 *
 * @param {Object} phone New settings.
 */
export function setPhoneConfig( phone ) {
	const all = params();
	all.settings = { ...( all.settings || {} ), phone };
}

/**
 * A country's flag image URL ('' when unknown).
 *
 * @param {string} country ISO 3166 code.
 * @return {string} URL.
 */
export function flagUrl( country ) {
	const base = params().flags_url;
	return base && /^[A-Z]{2}$/.test( country || '' )
		? `${ base }${ country }.svg`
		: '';
}

let names = null;

/**
 * A country's name in the site's language (the browser knows them all).
 *
 * @param {string} country ISO 3166 code.
 * @return {string} Name.
 */
export function countryName( country ) {
	if ( ! names ) {
		try {
			names = new Intl.DisplayNames(
				[ document.documentElement.lang || 'en', 'en' ],
				{ type: 'region' }
			);
		} catch ( e ) {
			names = { of: ( code ) => code };
		}
	}
	try {
		return names.of( country ) || country;
	} catch ( e ) {
		return country;
	}
}

/**
 * Called by PhoneInput's module when it loads.
 *
 * @param {Function} check `( value ) => boolean`.
 */
export function registerPhoneValidator( check ) {
	validator = check;
}

/**
 * Whether a stored phone value (E.164) is a valid number.
 *
 * @param {string} value `+2250707123456`.
 * @return {boolean} Valid.
 */
export function isPhoneValid( value ) {
	if ( validator ) {
		return validator( value );
	}
	return /^\+[1-9]\d{7,14}$/.test( String( value || '' ) );
}

/**
 * The message for a phone that is not valid.
 *
 * @return {string} Message.
 */
export const invalidPhoneMessage = () =>
	__( 'Enter a valid phone number.', 'radius-hotel-booking' );
