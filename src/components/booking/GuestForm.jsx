/**
 * The guest's own details on the website (M04, 4.6): name, phone, e-mail
 * (optional), the identity document (required, legacy), special requests.
 * Nothing is looked up while typing — returning guests are recognised by the
 * server, silently (no public guest lookup: the legacy PII leak). A guest
 * signed in to their account starts with their details filled in
 * (AccountBar); a document already on file shows as "•••• 4821" and is not
 * asked again (`id_on_file`: the server uses the one it has).
 *
 * The hidden `company_website` field is a honeypot: people never see it,
 * bots fill it, and the server then refuses the booking.
 */
import { Suspense, lazy, useState } from 'react';
import { __ } from '@wordpress/i18n';

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
};

/**
 * What still stops the guest from going on (client-side; the server decides).
 *
 * @param {Object} value Form values.
 * @return {Object} Field => message.
 */
export function guestFormErrors( value ) {
	const errors = {};
	if ( ! value.first_name.trim() ) {
		errors.first_name = __(
			'Enter your first name.',
			'radius-hotel-booking'
		);
	}
	if ( ! value.last_name.trim() ) {
		errors.last_name = __(
			'Enter your last name.',
			'radius-hotel-booking'
		);
	}
	if ( value.phone.replace( /\D/g, '' ).length < 8 ) {
		errors.phone = __(
			'Enter a phone number we can reach you on.',
			'radius-hotel-booking'
		);
	} else if ( ! isPhoneValid( value.phone ) ) {
		errors.phone = invalidPhoneMessage();
	}
	if ( ! isEmailValid( value.email ) ) {
		errors.email = __(
			'Enter a valid e-mail address, or leave it empty.',
			'radius-hotel-booking'
		);
	}
	if ( value.id_on_file ) {
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
 * @return {JSX.Element} Form.
 */
export default function GuestForm( {
	value,
	onChange,
	errors = {},
	idTypes = {},
} ) {
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
		const next = { express_token: token };
		[ 'first_name', 'last_name', 'phone', 'email' ].forEach( ( key ) => {
			if ( details[ key ] ) {
				next[ key ] = details[ key ];
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

	// A field is checked as soon as the guest leaves it, then live while
	// they fix it — not only on Continue.
	const [ touched, setTouched ] = useState( {} );
	const local = guestFormErrors( value );
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
						onNew={ ( phone ) => {
							setExpress( false );
							onChange( { phone } );
						} }
						onClose={ () => setExpress( false ) }
					/>
				</Suspense>
			) : null }
			<Field
				label={ __( 'First name', 'radius-hotel-booking' ) }
				error={ errorOf( 'first_name' ) }
				required
			>
				<Input
					value={ value.first_name }
					onChange={ text( 'first_name' ) }
					onBlur={ blur( 'first_name' ) }
					autoComplete="given-name"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Last name', 'radius-hotel-booking' ) }
				error={ errorOf( 'last_name' ) }
				required
			>
				<Input
					value={ value.last_name }
					onChange={ text( 'last_name' ) }
					onBlur={ blur( 'last_name' ) }
					autoComplete="family-name"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Phone', 'radius-hotel-booking' ) }
				error={ errorOf( 'phone' ) }
				required
			>
				<PhoneField
					value={ value.phone }
					onChange={ ( phone ) => onChange( { phone } ) }
					onBlur={ blur( 'phone' ) }
				/>
			</Field>
			<Field
				label={ __( 'E-mail', 'radius-hotel-booking' ) }
				description={ __(
					'For your confirmation and invoice.',
					'radius-hotel-booking'
				) }
				error={ errorOf( 'email' ) }
			>
				<Input
					type="email"
					value={ value.email }
					onChange={ text( 'email' ) }
					onBlur={ blur( 'email' ) }
					autoComplete="email"
					inputMode="email"
					className="h-11"
				/>
			</Field>
			{ value.id_on_file ? (
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
						{ __( 'Use another document', 'radius-hotel-booking' ) }
					</button>
				</div>
			) : (
				<>
					<Field
						label={ __(
							'Identity document',
							'radius-hotel-booking'
						) }
						error={ errorOf( 'id_type' ) }
						required
					>
						<select
							value={ value.id_type }
							onChange={ text( 'id_type' ) }
							onBlur={ blur( 'id_type' ) }
							className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
						>
							<option value="">
								{ __( 'Choose…', 'radius-hotel-booking' ) }
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
						label={ __(
							'Document number',
							'radius-hotel-booking'
						) }
						error={ errorOf( 'id_number' ) }
						required
					>
						<Input
							value={ value.id_number }
							onChange={ text( 'id_number' ) }
							onBlur={ blur( 'id_number' ) }
							autoComplete="off"
							className="h-11"
						/>
					</Field>
				</>
			) }
			<div className="sm:col-span-2">
				<Field
					label={ __( 'Special requests', 'radius-hotel-booking' ) }
					description={ __(
						'Optional: arrival time, a cot, anything the hotel should know.',
						'radius-hotel-booking'
					) }
				>
					<Textarea
						value={ value.special_requests }
						onChange={ text( 'special_requests' ) }
						maxLength={ 1000 }
						className="!min-h-[88px]"
					/>
				</Field>
			</div>
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
