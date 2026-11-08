/**
 * Settings → Booking rules: what guests may book online and how rooms turn
 * over between stays (features 17.7, 17.8, 17.9, 17.11, 8.9), and the payment
 * deadline of unpaid bookings (5.4, D6).
 */
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SegmentedControl from '@/components/common/SegmentedControl';
import { Input } from '@/components/ui/input';
import SettingsSection from '@/components/common/SettingsSection';
import { TimePicker } from '@/components/common/DatePicker';
import { NumberInput, ToggleRow } from './fields';
import RenumberBookings from './RenumberBookings';

const REFERENCE_KEYS = [
	'referencePrefix',
	'referenceYear',
	'referenceDigits',
];

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema (limits).
 * @param {Object}   props.saved    The values as saved.
 * @return {JSX.Element} Tab.
 */
export default function Booking( {
	value,
	setField,
	errors,
	schema,
	saved = {},
} ) {
	const minutes = __( 'minutes', 'radius-hotel-booking' );

	return (
		<>
			<SettingsSection
				title={ __( 'Online bookings', 'radius-hotel-booking' ) }
				description={ __(
					'What guests can book on your website. Staff at the front desk are not limited by these rules.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Booking window', 'radius-hotel-booking' ) }
					description={ __(
						'How many days ahead guests can book. 0 means no limit.',
						'radius-hotel-booking'
					) }
					error={ errors.bookingWindowDays }
				>
					<NumberInput
						value={ value.bookingWindowDays }
						onChange={ setField( 'bookingWindowDays' ) }
						unit={ __( 'days', 'radius-hotel-booking' ) }
						limits={ schema.bookingWindowDays }
					/>
				</Field>

				<ToggleRow
					label={ __( 'Same-day bookings', 'radius-hotel-booking' ) }
					description={ __(
						'Let guests book a stay that starts today.',
						'radius-hotel-booking'
					) }
					checked={ value.sameDayEnabled }
					onChange={ setField( 'sameDayEnabled' ) }
					error={ errors.sameDayEnabled }
				>
					<Field
						label={ __( 'Cut-off time', 'radius-hotel-booking' ) }
						description={ __(
							'The last time of day guests can book a stay that starts today on the website. After it, the calendar starts from tomorrow. Rates that have already started are never offered, so a late cut-off (such as your last check-in time) is safe. Staff can always book today at the desk.',
							'radius-hotel-booking'
						) }
						error={ errors.sameDayCutoff }
					>
						<TimePicker
							className="w-40"
							value={ value.sameDayCutoff ?? '' }
							onChange={ setField( 'sameDayCutoff' ) }
						/>
					</Field>
				</ToggleRow>

				<ToggleRow
					label={ __(
						'Approve bookings manually',
						'radius-hotel-booking'
					) }
					description={ __(
						'New bookings stay pending until a staff member approves them. The room is held meanwhile.',
						'radius-hotel-booking'
					) }
					checked={ value.manualApproval }
					onChange={ setField( 'manualApproval' ) }
					error={ errors.manualApproval }
				/>

				<Field
					label={ __(
						'Unavailable rooms in search results',
						'radius-hotel-booking'
					) }
					error={ errors.unavailableRooms }
				>
					<div>
						<SegmentedControl
							label={ __(
								'Unavailable rooms in search results',
								'radius-hotel-booking'
							) }
							value={ value.unavailableRooms }
							onChange={ setField( 'unavailableRooms' ) }
							options={ [
								{
									value: 'hide',
									label: __(
										'Hide them',
										'radius-hotel-booking'
									),
								},
								{
									value: 'disable',
									label: __(
										'Show as unavailable',
										'radius-hotel-booking'
									),
								},
							] }
						/>
					</div>
				</Field>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Guests', 'radius-hotel-booking' ) }
				description={ __(
					'How guests are counted when a room’s capacity is checked.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Maximum child age', 'radius-hotel-booking' ) }
					description={ __(
						'Guests up to this age count as children; older guests count as adults.',
						'radius-hotel-booking'
					) }
					error={ errors.maxChildAge }
				>
					<NumberInput
						value={ value.maxChildAge }
						onChange={ setField( 'maxChildAge' ) }
						unit={ __( 'years', 'radius-hotel-booking' ) }
						limits={ schema.maxChildAge }
					/>
				</Field>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Rooms between stays', 'radius-hotel-booking' ) }
				description={ __(
					'Holding a room while a booking is filled in, and cleaning time after a stay.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __(
						'Hold a selected room for',
						'radius-hotel-booking'
					) }
					description={ __(
						'While a booking is being filled in, nobody else can take the room. The hold ends after this time.',
						'radius-hotel-booking'
					) }
					error={ errors.holdMinutes }
				>
					<NumberInput
						value={ value.holdMinutes }
						onChange={ setField( 'holdMinutes' ) }
						unit={ minutes }
						limits={ schema.holdMinutes }
					/>
				</Field>
				<Field
					label={ __(
						'Cleaning time after a stay',
						'radius-hotel-booking'
					) }
					description={ __(
						'The room can be sold again only this long after the previous guest leaves. A room type can override it.',
						'radius-hotel-booking'
					) }
					error={ errors.bufferMinutes }
				>
					<NumberInput
						value={ value.bufferMinutes }
						onChange={ setField( 'bufferMinutes' ) }
						unit={ minutes }
						limits={ schema.bufferMinutes }
					/>
				</Field>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Rooms per booking', 'radius-hotel-booking' ) }
				description={ __(
					'The most rooms one booking can take, for groups. Rooms can be ticked and held together.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __(
							'At the front desk',
							'radius-hotel-booking'
						) }
						error={ errors.maxRoomsDesk }
					>
						<NumberInput
							value={ value.maxRoomsDesk }
							onChange={ setField( 'maxRoomsDesk' ) }
							unit={ __( 'rooms', 'radius-hotel-booking' ) }
							limits={ schema.maxRoomsDesk }
						/>
					</Field>
					<Field
						label={ __( 'On the website', 'radius-hotel-booking' ) }
						description={ __(
							'Also the most rooms one visitor can hold at once.',
							'radius-hotel-booking'
						) }
						error={ errors.maxRoomsWeb }
					>
						<NumberInput
							value={ value.maxRoomsWeb }
							onChange={ setField( 'maxRoomsWeb' ) }
							unit={ __( 'rooms', 'radius-hotel-booking' ) }
							limits={ schema.maxRoomsWeb }
						/>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Nightly stays', 'radius-hotel-booking' ) }
				description={ __(
					'Default times for rate plans sold by the night. A rate plan with its own times uses those instead.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Check-in from', 'radius-hotel-booking' ) }
						error={ errors.checkInTime }
					>
						<TimePicker
							className="w-40"
							value={ value.checkInTime ?? '' }
							onChange={ setField( 'checkInTime' ) }
						/>
					</Field>
					<Field
						label={ __( 'Check-out by', 'radius-hotel-booking' ) }
						error={ errors.checkOutTime }
					>
						<TimePicker
							className="w-40"
							value={ value.checkOutTime ?? '' }
							onChange={ setField( 'checkOutTime' ) }
						/>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Payment deadline', 'radius-hotel-booking' ) }
				description={ __(
					'When an unpaid booking must be paid: so many hours after it is made, but no later than so many hours before arrival. Guests see the deadline counted down.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __(
							'Due after booking',
							'radius-hotel-booking'
						) }
						description={ __(
							'0 means no deadline.',
							'radius-hotel-booking'
						) }
						error={ errors.paymentDueHours }
					>
						<NumberInput
							value={ value.paymentDueHours }
							onChange={ setField( 'paymentDueHours' ) }
							unit={ __( 'hours', 'radius-hotel-booking' ) }
							limits={ schema.paymentDueHours }
						/>
					</Field>
					<Field
						label={ __(
							'At the latest, before arrival',
							'radius-hotel-booking'
						) }
						error={ errors.paymentBeforeArrivalHours }
					>
						<NumberInput
							value={ value.paymentBeforeArrivalHours }
							onChange={ setField( 'paymentBeforeArrivalHours' ) }
							unit={ __( 'hours', 'radius-hotel-booking' ) }
							limits={ schema.paymentBeforeArrivalHours }
						/>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Booking reference', 'radius-hotel-booking' ) }
				description={ __(
					'How booking numbers look. Existing bookings keep theirs unless you renumber them below.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Prefix', 'radius-hotel-booking' ) }
						description={ __(
							'Letters, digits and - / _ . only.',
							'radius-hotel-booking'
						) }
						error={ errors.referencePrefix }
					>
						<Input
							value={ value.referencePrefix ?? '' }
							maxLength={ schema.referencePrefix?.maxLength }
							onChange={ ( event ) =>
								setField( 'referencePrefix' )(
									event.target.value.toUpperCase()
								)
							}
						/>
					</Field>
					<Field
						label={ __( 'Minimum digits', 'radius-hotel-booking' ) }
						description={ __(
							'Shorter numbers are padded with zeros.',
							'radius-hotel-booking'
						) }
						error={ errors.referenceDigits }
					>
						<NumberInput
							value={ value.referenceDigits }
							onChange={ setField( 'referenceDigits' ) }
							limits={ schema.referenceDigits }
						/>
					</Field>
				</div>
				<Field
					label={ __( 'Year', 'radius-hotel-booking' ) }
					error={ errors.referenceYear }
				>
					<div>
						<SegmentedControl
							label={ __( 'Year', 'radius-hotel-booking' ) }
							value={ value.referenceYear }
							onChange={ setField( 'referenceYear' ) }
							options={ [
								{
									value: 'full',
									label: __(
										'Full year',
										'radius-hotel-booking'
									),
								},
								{
									value: 'short',
									label: __(
										'Two digits',
										'radius-hotel-booking'
									),
								},
								{
									value: 'none',
									label: __(
										'No year',
										'radius-hotel-booking'
									),
								},
							] }
						/>
					</div>
				</Field>
				<p className="m-0 text-sm text-muted-foreground">
					{ value.referenceYear === 'none'
						? __(
								'The number keeps counting up and never restarts.',
								'radius-hotel-booking'
						  )
						: __(
								'The number restarts at 1 each year.',
								'radius-hotel-booking'
						  ) }{ ' ' }
					{ sprintf(
						/* translators: %s: example booking reference. */
						__( 'Example: %s', 'radius-hotel-booking' ),
						referenceExample( value )
					) }
				</p>
				<RenumberBookings
					dirty={ REFERENCE_KEYS.some(
						// eslint-disable-next-line eqeqeq -- a number input may hold "3" for 3.
						( key ) => value[ key ] != saved[ key ]
					) }
				/>
			</SettingsSection>
		</>
	);
}

/**
 * An example reference in the chosen format (as BookingService builds it):
 * the 17th booking of this year.
 *
 * @param {Object} value Booking settings.
 * @return {string} Example.
 */
function referenceExample( value ) {
	const year = String( new Date().getFullYear() );
	const digits = Math.min(
		8,
		Math.max( 1, Number( value.referenceDigits ) || 1 )
	);
	const number = String( 17 ).padStart( digits, '0' );
	const prefix = value.referencePrefix ?? '';
	if ( value.referenceYear === 'none' ) {
		return prefix + number;
	}
	return `${ prefix }${
		value.referenceYear === 'short' ? year.slice( -2 ) : year
	}-${ number }`;
}
