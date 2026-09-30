/**
 * Settings → Booking rules: what guests may book online and how rooms turn
 * over between stays (features 17.7, 17.8, 17.9, 17.11, 8.9), and the payment
 * deadline of unpaid bookings (5.4, D6).
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';
import { Input } from '@/components/ui/input';
import { NumberInput, ToggleRow } from './fields';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema (limits).
 * @return {JSX.Element} Tab.
 */
export default function Booking( { value, setField, errors, schema } ) {
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
							'After this time, guests can only book from tomorrow.',
							'radius-hotel-booking'
						) }
						error={ errors.sameDayCutoff }
					>
						<Input
							type="time"
							className="w-36"
							value={ value.sameDayCutoff ?? '' }
							onChange={ ( event ) =>
								setField( 'sameDayCutoff' )(
									event.target.value
								)
							}
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
						<Input
							type="time"
							className="w-36"
							value={ value.checkInTime ?? '' }
							onChange={ ( event ) =>
								setField( 'checkInTime' )( event.target.value )
							}
						/>
					</Field>
					<Field
						label={ __( 'Check-out by', 'radius-hotel-booking' ) }
						error={ errors.checkOutTime }
					>
						<Input
							type="time"
							className="w-36"
							value={ value.checkOutTime ?? '' }
							onChange={ ( event ) =>
								setField( 'checkOutTime' )( event.target.value )
							}
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
		</>
	);
}
