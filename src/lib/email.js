/**
 * E-mail checks shared by the guest forms (the server checks again with
 * WordPress's is_email()).
 */
import { __ } from '@wordpress/i18n';

/**
 * Whether an e-mail address looks valid ('' counts as valid: "required" is
 * the form's business).
 *
 * @param {string} value Address.
 * @return {boolean} Valid.
 */
export const isEmailValid = ( value ) =>
	! value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( String( value ).trim() );

/**
 * The message for an address that is not valid.
 *
 * @return {string} Message.
 */
export const invalidEmailMessage = () =>
	__( 'Enter a valid e-mail address.', 'radius-hotel-booking' );
