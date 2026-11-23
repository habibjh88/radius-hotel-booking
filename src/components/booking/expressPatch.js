/**
 * What express booking puts in the guest form, shared by the details step
 * (GuestForm) and the account bar above the rooms (AccountBar), so a guest
 * can use it before choosing rooms and find their details ready later.
 */

// What a recognised guest's record supplies (cleared by "Not you?").
export const RECORD_FIELDS = {
	first_name: '',
	last_name: '',
	phone: '',
	email: '',
	id_type: '',
	id_number: '',
	id_on_file: false,
	id_hint: '',
	express_token: '',
	express_hint: null,
	details_locked: false,
};

// The popup opens by itself once per page (the details step remounts the
// form); using it from the bar counts too.
let offered = false;

/**
 * Whether the popup may still open by itself, and mark it as opened.
 *
 * @return {boolean} First time.
 */
export const takeExpressOffer = () => {
	if ( offered ) {
		return false;
	}
	offered = true;
	return true;
};

/**
 * The popup was used (or seen) elsewhere: never open it by itself.
 */
export const markExpressOffered = () => {
	offered = true;
};

/**
 * After the code: the record's details (`public/express/verify`).
 *
 * @param {Object} details The verified details.
 * @param {string} token   Express token.
 * @return {Object} Form patch.
 */
export function knownPatch( details, token ) {
	const locked = !! details.on_file;
	const next = { express_token: token, details_locked: locked };
	[ 'first_name', 'last_name', 'phone', 'email' ].forEach( ( key ) => {
		// A record's details replace whatever was typed.
		if ( details[ key ] || locked ) {
			next[ key ] = details[ key ] || '';
		}
	} );
	if ( details.id_on_file ) {
		next.id_on_file = true;
		next.id_type = details.id_type;
		next.id_number = '';
		next.id_hint = details.id_hint;
	}
	return next;
}

/**
 * Recognised without a code: the record's details, masked, shown read-only
 * and never sent back as typed.
 *
 * @param {Object} result       `{ token, hint, phone }`.
 * @param {string} currentPhone The phone already in the form.
 * @return {Object} Form patch.
 */
export function recognisedPatch( { token, hint, phone }, currentPhone = '' ) {
	const next = {
		express_token: token,
		express_hint: hint,
		phone: phone || currentPhone,
		first_name: hint?.first_name || '',
		last_name: hint?.last_name || '',
		email: hint?.email || '',
	};
	// Its document is used as it is on file (the server adds the number).
	if ( hint?.document ) {
		next.id_on_file = true;
		next.id_type = hint.document.id_type;
		next.id_number = '';
		next.id_hint = hint.document.id_hint;
	}
	return next;
}
