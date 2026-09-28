/**
 * Form helpers: react-hook-form + zod, wired to the API's error envelope.
 *
 *   const form = useZodForm( schema, { defaultValues } );
 *   try { await save( form.getValues() ); }
 *   catch ( error ) { applyServerErrors( form.setError, error ) || toastError( error ); }
 */
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';

// Re-exported so add-ons use this copy (via rtbp.lib.loadForms()).
export { z } from 'zod';

/**
 * react-hook-form with a zod schema. Validates on blur, then on change once a
 * field has been touched — errors appear when the user leaves a field, not
 * while they are still typing.
 *
 * @param {import('zod').ZodTypeAny} schema  Schema.
 * @param {Object}                   options useForm options.
 * @return {Object} The form (useForm return value).
 */
export function useZodForm( schema, options = {} ) {
	return useForm( {
		resolver: zodResolver( schema ),
		mode: 'onBlur',
		reValidateMode: 'onChange',
		...options,
	} );
}

/**
 * Put the server's field errors on the form's fields. The API sends
 * `errors: { field: { first_message } }` (ApiResponse::validationError /
 * DomainException::invalid).
 *
 * @param {Function} setError react-hook-form setError.
 * @param {Error}    error    Error from src/api/client.js.
 * @return {boolean} Whether at least one field error was applied (when false,
 *                   show the error some other way, e.g. a toast).
 */
export function applyServerErrors( setError, error ) {
	const errors = error?.errors;
	if ( ! errors || typeof errors !== 'object' ) {
		return false;
	}

	let applied = false;
	Object.entries( errors ).forEach( ( [ field, detail ] ) => {
		const message =
			detail?.first_message ||
			( Array.isArray( detail?.messages ) ? detail.messages[ 0 ] : '' ) ||
			( typeof detail === 'string' ? detail : '' );
		if ( message ) {
			setError(
				field,
				{ type: 'server', message },
				{ shouldFocus: ! applied }
			);
			applied = true;
		}
	} );

	return applied;
}
