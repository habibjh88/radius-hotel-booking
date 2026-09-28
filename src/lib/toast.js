/**
 * Toasts (sonner). The <Toaster /> is mounted once in the app shell.
 *
 *   toast.success( __( 'Booking approved.', 'radius-hotel-booking' ) );
 *   toastError( error );   // any error from src/api/client.js
 */
import { toast } from 'sonner';
import { __ } from '@wordpress/i18n';

export { toast };

/**
 * Show an API error: its server message when it has one, a generic line
 * otherwise. Field errors are shown on the form (applyServerErrors), not here.
 *
 * @param {Error}  error    Error.
 * @param {string} fallback Message when the error has none.
 * @return {void}
 */
export function toastError( error, fallback ) {
	toast.error(
		error?.message ||
			fallback ||
			__(
				'Something went wrong. Please try again.',
				'radius-hotel-booking'
			)
	);
}
