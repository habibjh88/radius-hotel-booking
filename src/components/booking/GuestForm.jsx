/**
 * The guest's own details on the website (M04, 4.6): name, phone, e-mail
 * (optional), the identity document (required, legacy), special requests.
 * Nothing is looked up while typing — returning guests are recognised by the
 * server, silently (no public guest lookup: the legacy PII leak). A guest
 * signed in to their account starts with their details filled in
 * (AccountBar); a document already on file shows as "•••• 4821" and is not
 * asked again (`id_on_file`: the server uses the one it has), also after
 * Express checkout without a code. When those
 * details are a guest record's (signed in, or Express checkout with a code),
 * the booking goes to that record as it is, so the name, phone and e-mail
 * are shown locked (`details_locked`) rather than edited for nothing.
 *
 * The hidden `company_website` field is a honeypot: people never see it,
 * bots fill it, and the server then refuses the booking.
 */
import { Suspense, lazy, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import PhoneField from '@/components/common/Phone';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { isEmailValid } from '@/lib/email';
import { invalidPhoneMessage, isPhoneValid } from '@/lib/phone';
import { accountSettings } from './AccountBar';

const ExpressCheckout = lazy( () => import( './ExpressCheckout' ) );

// The popup opens by itself once per page (the details step remounts the form).
let expressShown = false;

export const EMPTY_GUEST_FORM = {
	first_name: '',
	last_name: '',
	phone: '',
	email: '',
	id_type: '',
	id_number: '',
	special_requests: '',
	company_website: '',
	// Signed in with a document on file: the server uses it (never sent here).
	id_on_file: false,
	id_hint: '',
	// Express checkout's proof that the visitor owns the phone of a guest on file.
	express_token: '',
	// Express checkout without a code: `{ name, email }` masked; the server
	// fills the name and e-mail left empty from the record.
	express_hint: null,
	// The name, phone and e-mail are a guest record's: shown, not edited.
	details_locked: false,
};

// What a recognised guest's record supplies (cleared by "Not you?").
const RECORD_FIELDS = {
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

// How each guest field is asked (Settings → Website), when the page has no rules.
export const GUEST_FIELD_DEFAULTS = {
	last_name: 'required',
	phone: 'required',
	email: 'optional',
	id_document: 'required',
	special_requests: 'optional',
};

/**
 * Every field's mode: the page's rules over the defaults.
 *
 * @param {Object} fields Field => required|optional|hidden.
 * @return {Object} Modes.
 */
const modesOf = ( fields ) => ( {
	...GUEST_FIELD_DEFAULTS,
	...( fields || {} ),
} );

/**
 * What still stops the guest from going on (client-side; the server decides).
 *
 * @param {Object} value  Form values.
 * @param {Object} fields Field => required|optional|hidden (`rules.guestFields`).
 * @return {Object} Field => message.
 */
export function guestFormErrors( value, fields ) {
	const modes = modesOf( fields );
	const extra = {
		...( 'hidden' === modes.id_document || value.id_on_file
			? {}
			: documentErrors( value, 'required' === modes.id_document ) ),
	};
	if (
		'required' === modes.special_requests &&
		! value.special_requests.trim()
	) {
		extra.special_requests = __(
			'Please fill in your special requests.',
			'radius-hotel-booking'
		);
	}
	if ( value.details_locked ) {
		// The record's own details: the server uses them as they are.
		return extra;
	}
	const errors = {};
	// Recognised without a code: an empty name and e-mail come from the record.
	const onFile = !! value.express_hint;
	if ( ! onFile && ! value.first_name.trim() ) {
		errors.first_name = __(
			'Enter your first name.',
			'radius-hotel-booking'
		);
	}
	if (
		'required' === modes.last_name &&
		! onFile &&
		! value.last_name.trim()
	) {
		errors.last_name = __(
			'Enter your last name.',
			'radius-hotel-booking'
		);
	}
	const digits = value.phone.replace( /\D/g, '' ).length;
	if ( 'hidden' === modes.phone ) {
		// Not asked.
	} else if ( ( digits || 'required' === modes.phone ) && digits < 8 ) {
		errors.phone = __(
			'Enter a phone number we can reach you on.',
			'radius-hotel-booking'
		);
	} else if ( digits && ! isPhoneValid( value.phone ) ) {
		errors.phone = invalidPhoneMessage();
	}
	if ( 'hidden' === modes.email ) {
		// Not asked.
	} else if (
		'required' === modes.email &&
		! onFile &&
		! value.email.trim()
	) {
		errors.email = __(
			'Enter your e-mail address.',
			'radius-hotel-booking'
		);
	} else if ( ! isEmailValid( value.email ) ) {
		errors.email =
			'required' === modes.email
				? __( 'Enter a valid e-mail address.', 'radius-hotel-booking' )
				: __(
						'Enter a valid e-mail address, or leave it empty.',
						'radius-hotel-booking'
				  );
	}
	return { ...errors, ...extra };
}

/**
 * The identity document's own errors: a type and a number together, when
 * required or once either is given.
 *
 * @param {Object}  value    Form values.
 * @param {boolean} required The form requires a document.
 * @return {Object} Field => message.
 */
function documentErrors( value, required ) {
	const errors = {};
	if ( ! required && ! value.id_type && ! value.id_number.trim() ) {
		return errors;
	}
	if ( ! value.id_type ) {
		errors.id_type = __(
			'Choose your identity document type.',
			'radius-hotel-booking'
		);
	}
	if ( ! value.id_number.trim() ) {
		errors.id_number = __(
			'Enter the number of your identity document.',
			'radius-hotel-booking'
		);
	}
	return errors;
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Form values (`EMPTY_GUEST_FORM` shape).
 * @param {Function} props.onChange Called with the changed fields.
 * @param {Object}   props.errors   Field => message (client or server).
 * @param {Object}   props.idTypes  Key => label (Settings).
 * @param {Object}   props.fields   Field => required|optional|hidden (Settings → Website).
 * @param {Object}   props.texts    Input => `{ label, placeholder }`, '' = built-in (Settings → Website).
 * @return {JSX.Element} Form.
 */
export default function GuestForm( {
	value,
	onChange,
	errors = {},
	idTypes = {},
	fields,
	texts = {},
} ) {
	const modes = modesOf( fields );
	// The hotel's own label and placeholder, else the built-in one.
	const labelOf = ( key, fallback ) => texts[ key ]?.label || fallback;
	const placeholderOf = ( key, fallback ) =>
		texts[ key ]?.placeholder || fallback;
	const shows = ( key ) => 'hidden' !== modes[ key ];
	const text = ( key ) => ( event ) =>
		onChange( { [ key ]: event.target.value } );

	// Express checkout: offered while the details are still empty (not signed
	// in, nothing typed, not done already); opens by itself the first time.
	const offerExpress =
		accountSettings().express &&
		! value.express_token &&
		! value.id_on_file &&
		! value.first_name &&
		! value.last_name;
	const [ express, setExpress ] = useState( () => {
		if ( offerExpress && ! value.phone && ! expressShown ) {
			expressShown = true;
			return true;
		}
		return false;
	} );
	const known = ( details, token ) => {
		setExpress( false );
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
		onChange( next );
	};
	const recognised = ( { token, hint, phone } ) => {
		setExpress( false );
		const next = { express_token: token, express_hint: hint, phone };
		// Its document is used as it is on file (the server adds the number).
		if ( hint?.document ) {
			next.id_on_file = true;
			next.id_type = hint.document.id_type;
			next.id_number = '';
			next.id_hint = hint.document.id_hint;
		}
		onChange( next );
	};
	// Another number than the one recognised: the record no longer applies.
	const changePhone = ( phone ) =>
		onChange(
			value.express_hint && phone !== value.phone
				? {
						phone,
						express_token: '',
						express_hint: null,
						...( value.express_hint.document
							? {
									id_on_file: false,
									id_type: '',
									id_number: '',
									id_hint: '',
							  }
							: {} ),
				  }
				: { phone }
		);
	const hint = value.express_hint;
	const locked = value.details_locked;

	// A field is checked as soon as the guest leaves it, then live while
	// they fix it — not only on Continue.
	const [ touched, setTouched ] = useState( {} );
	const local = guestFormErrors( value, fields );
	const blur = ( key ) => () =>
		setTouched( ( prev ) =>
			prev[ key ] ? prev : { ...prev, [ key ]: true }
		);
	const errorOf = ( key ) =>
		errors[ key ] || ( touched[ key ] ? local[ key ] : '' ) || '';

	return (
		<div className="grid gap-4 sm:grid-cols-2">
			{ offerExpress ? (
				<p className="m-0 text-sm text-muted-foreground sm:col-span-2">
					{ __( 'Booked with us before?', 'radius-hotel-booking' ) }{ ' ' }
					<button
						type="button"
						className="m-0 cursor-pointer border-0 bg-transparent p-0 font-medium text-primary underline-offset-2 hover:underline"
						onClick={ () => setExpress( true ) }
					>
						{ __( 'Use express checkout', 'radius-hotel-booking' ) }
					</button>
				</p>
			) : null }
			{ express ? (
				<Suspense fallback={ null }>
					<ExpressCheckout
						phone={ value.phone }
						onKnown={ known }
						onRecognised={ recognised }
						onNew={ ( phone ) => {
							setExpress( false );
							onChange( { phone } );
						} }
						onClose={ () => setExpress( false ) }
					/>
				</Suspense>
			) : null }
			{ hint ? (
				<p className="m-0 rounded-lg border border-border bg-muted px-3 py-2.5 text-sm text-foreground sm:col-span-2">
					<span className="font-semibold text-heading">
						{ hint.name
							? sprintf(
									/* translators: %s: masked guest name, e.g. "H••• R•••". */
									__(
										'Welcome back, %s!',
										'radius-hotel-booking'
									),
									hint.name
							  )
							: __( 'Welcome back!', 'radius-hotel-booking' ) }
					</span>{ ' ' }
					{ __(
						'Leave your name and e-mail empty to use the ones we have on file.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }
			{ locked ? (
				<div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2.5 sm:col-span-2">
					<p className="m-0 text-sm text-foreground">
						<span className="font-semibold text-heading">
							{ __(
								'Your details on file',
								'radius-hotel-booking'
							) }
						</span>
						{ ' · ' }
						{ __(
							'To change them, please contact the hotel.',
							'radius-hotel-booking'
						) }
					</p>
					{ value.express_token ? (
						<button
							type="button"
							className="m-0 cursor-pointer border-0 bg-transparent p-0 text-sm font-medium text-primary underline-offset-2 hover:underline"
							onClick={ () => {
								// A fresh start: no "required" messages yet.
								setTouched( {} );
								onChange( RECORD_FIELDS );
							} }
						>
							{ __(
								'Not you? Use other details',
								'radius-hotel-booking'
							) }
						</button>
					) : null }
				</div>
			) : null }
			<Field
				label={ labelOf(
					'first_name',
					__( 'First name', 'radius-hotel-booking' )
				) }
				error={ errorOf( 'first_name' ) }
				required={ ! hint && ! locked }
			>
				<Input
					value={ value.first_name }
					readOnly={ locked }
					onChange={ text( 'first_name' ) }
					onBlur={ blur( 'first_name' ) }
					placeholder={ placeholderOf( 'first_name' ) }
					autoComplete="given-name"
					className={ locked ? 'h-11 bg-muted' : 'h-11' }
				/>
			</Field>
			{ shows( 'last_name' ) ? (
				<Field
					label={ labelOf(
						'last_name',
						__( 'Last name', 'radius-hotel-booking' )
					) }
					error={ errorOf( 'last_name' ) }
					required={
						'required' === modes.last_name && ! hint && ! locked
					}
				>
					<Input
						value={ value.last_name }
						readOnly={ locked }
						onChange={ text( 'last_name' ) }
						onBlur={ blur( 'last_name' ) }
						placeholder={ placeholderOf( 'last_name' ) }
						autoComplete="family-name"
						className={ locked ? 'h-11 bg-muted' : 'h-11' }
					/>
				</Field>
			) : null }
			{ shows( 'phone' ) ? (
				<Field
					label={ labelOf(
						'phone',
						__( 'Phone', 'radius-hotel-booking' )
					) }
					error={ errorOf( 'phone' ) }
					required={ 'required' === modes.phone && ! locked }
				>
					<PhoneField
						value={ value.phone }
						disabled={ locked }
						onChange={ changePhone }
						onBlur={ blur( 'phone' ) }
						placeholder={ placeholderOf( 'phone' ) }
					/>
				</Field>
			) : null }
			{ shows( 'email' ) ? (
				<Field
					label={ labelOf(
						'email',
						__( 'E-mail', 'radius-hotel-booking' )
					) }
					description={ __(
						'For your confirmation and invoice.',
						'radius-hotel-booking'
					) }
					error={ errorOf( 'email' ) }
					required={
						'required' === modes.email && ! hint && ! locked
					}
				>
					<Input
						type="email"
						value={ value.email }
						readOnly={ locked }
						onChange={ text( 'email' ) }
						onBlur={ blur( 'email' ) }
						placeholder={ hint?.email || placeholderOf( 'email' ) }
						autoComplete="email"
						inputMode="email"
						className={ locked ? 'h-11 bg-muted' : 'h-11' }
					/>
				</Field>
			) : null }
			{ ! shows( 'id_document' ) ? null : value.id_on_file ? (
				<div className="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-muted px-3 py-2.5 sm:col-span-2">
					<p className="m-0 text-sm text-foreground">
						<span className="font-semibold text-heading">
							{ __(
								'Identity document on file',
								'radius-hotel-booking'
							) }
						</span>
						{ ': ' }
						{ [ idTypes[ value.id_type ], value.id_hint ]
							.filter( Boolean )
							.join( ' ' ) }
					</p>
					{ /* A record's booking keeps its own document. */ }
					{ ! locked ? (
						<button
							type="button"
							className="m-0 cursor-pointer border-0 bg-transparent p-0 text-sm font-medium text-primary underline-offset-2 hover:underline"
							onClick={ () =>
								onChange( {
									id_on_file: false,
									id_type: '',
									id_number: '',
								} )
							}
						>
							{ __(
								'Use another document',
								'radius-hotel-booking'
							) }
						</button>
					) : null }
				</div>
			) : (
				<>
					<Field
						label={ labelOf(
							'id_type',
							__( 'Identity document', 'radius-hotel-booking' )
						) }
						error={ errorOf( 'id_type' ) }
						required={ 'required' === modes.id_document }
					>
						<select
							value={ value.id_type }
							onChange={ text( 'id_type' ) }
							onBlur={ blur( 'id_type' ) }
							className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
						>
							<option value="">
								{ placeholderOf(
									'id_type',
									__( 'Choose…', 'radius-hotel-booking' )
								) }
							</option>
							{ Object.entries( idTypes ).map(
								( [ key, label ] ) => (
									<option key={ key } value={ key }>
										{ label }
									</option>
								)
							) }
						</select>
					</Field>
					<Field
						label={ labelOf(
							'id_number',
							__( 'Document number', 'radius-hotel-booking' )
						) }
						error={ errorOf( 'id_number' ) }
						required={ 'required' === modes.id_document }
					>
						<Input
							value={ value.id_number }
							onChange={ text( 'id_number' ) }
							onBlur={ blur( 'id_number' ) }
							placeholder={ placeholderOf( 'id_number' ) }
							autoComplete="off"
							className="h-11"
						/>
					</Field>
				</>
			) }
			{ shows( 'special_requests' ) ? (
				<div className="sm:col-span-2">
					<Field
						label={ labelOf(
							'special_requests',
							__( 'Special requests', 'radius-hotel-booking' )
						) }
						description={
							'required' === modes.special_requests
								? __(
										'Arrival time, a cot, anything the hotel should know.',
										'radius-hotel-booking'
								  )
								: __(
										'Optional: arrival time, a cot, anything the hotel should know.',
										'radius-hotel-booking'
								  )
						}
						error={ errorOf( 'special_requests' ) }
						required={ 'required' === modes.special_requests }
					>
						<Textarea
							value={ value.special_requests }
							onChange={ text( 'special_requests' ) }
							onBlur={ blur( 'special_requests' ) }
							placeholder={ placeholderOf( 'special_requests' ) }
							maxLength={ 1000 }
							className="!min-h-[88px]"
						/>
					</Field>
				</div>
			) : null }
			{ /* Honeypot: hidden from people (and from screen readers), filled by bots. */ }
			<div className="hidden" aria-hidden="true">
				<label>
					Website
					<input
						type="text"
						tabIndex={ -1 }
						autoComplete="off"
						value={ value.company_website }
						onChange={ text( 'company_website' ) }
					/>
				</label>
			</div>
		</div>
	);
}
