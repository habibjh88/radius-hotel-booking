/**
 * The guest's own details on the website (M04, 4.6): name, phone, e-mail
 * (optional), the identity document (required, legacy), special requests.
 * Nothing is looked up while typing — returning guests are recognised by the
 * server, silently (no public guest lookup: the legacy PII leak).
 *
 * The hidden `company_website` field is a honeypot: people never see it,
 * bots fill it, and the server then refuses the booking.
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';

export const EMPTY_GUEST_FORM = {
	first_name: '',
	last_name: '',
	phone: '',
	email: '',
	id_type: '',
	id_number: '',
	special_requests: '',
	company_website: '',
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
	}
	if ( value.email && ! /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( value.email ) ) {
		errors.email = __(
			'Enter a valid e-mail address, or leave it empty.',
			'radius-hotel-booking'
		);
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

	return (
		<div className="grid gap-4 sm:grid-cols-2">
			<Field
				label={ __( 'First name', 'radius-hotel-booking' ) }
				error={ errors.first_name }
				required
			>
				<Input
					value={ value.first_name }
					onChange={ text( 'first_name' ) }
					autoComplete="given-name"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Last name', 'radius-hotel-booking' ) }
				error={ errors.last_name }
				required
			>
				<Input
					value={ value.last_name }
					onChange={ text( 'last_name' ) }
					autoComplete="family-name"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Phone', 'radius-hotel-booking' ) }
				error={ errors.phone }
				required
			>
				<Input
					type="tel"
					value={ value.phone }
					onChange={ text( 'phone' ) }
					autoComplete="tel"
					inputMode="tel"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'E-mail', 'radius-hotel-booking' ) }
				description={ __(
					'For your confirmation and invoice.',
					'radius-hotel-booking'
				) }
				error={ errors.email }
			>
				<Input
					type="email"
					value={ value.email }
					onChange={ text( 'email' ) }
					autoComplete="email"
					inputMode="email"
					className="h-11"
				/>
			</Field>
			<Field
				label={ __( 'Identity document', 'radius-hotel-booking' ) }
				error={ errors.id_type }
				required
			>
				<select
					value={ value.id_type }
					onChange={ text( 'id_type' ) }
					className="h-11 w-full rounded-md border border-input bg-background px-3 text-sm"
				>
					<option value="">
						{ __( 'Choose…', 'radius-hotel-booking' ) }
					</option>
					{ Object.entries( idTypes ).map( ( [ key, label ] ) => (
						<option key={ key } value={ key }>
							{ label }
						</option>
					) ) }
				</select>
			</Field>
			<Field
				label={ __( 'Document number', 'radius-hotel-booking' ) }
				error={ errors.id_number }
				required
			>
				<Input
					value={ value.id_number }
					onChange={ text( 'id_number' ) }
					autoComplete="off"
					className="h-11"
				/>
			</Field>
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
