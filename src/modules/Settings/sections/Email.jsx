/**
 * Settings → E-mail: the master switch, the sender, and an on/off switch per
 * e-mail template. Templates are registered in PHP (`rtbp_email_classes`)
 * and localised as `email_templates`; payments (M05) adds the first ones.
 */
import { __ } from '@wordpress/i18n';
import { Mail } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import { Input } from '@/components/ui/input';
import { ToggleRow } from './fields';

/**
 * @return {Array<Object>} `{ id, title, description, recipient, default }`.
 */
const templates = () =>
	window.radius_hotel_booking_param?.email_templates || [];

/**
 * Label of a recipient type.
 *
 * @param {string} recipient Recipient type from the template.
 * @return {string} Label.
 */
const recipientLabel = ( recipient ) =>
	( {
		guest: __( 'To the guest', 'radius-hotel-booking' ),
		customer: __( 'To the guest', 'radius-hotel-booking' ),
		admin: __( 'To the hotel', 'radius-hotel-booking' ),
		staff: __( 'To staff', 'radius-hotel-booking' ),
	} )[ recipient ] || '';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Email( { value, setField, errors } ) {
	const list = templates();
	const switches = value.templates || {};
	const text = ( name ) => ( {
		value: value[ name ] ?? '',
		onChange: ( event ) => setField( name )( event.target.value ),
	} );

	return (
		<>
			<SettingsSection
				title={ __( 'Sending', 'radius-hotel-booking' ) }
				description={ __(
					'Who e-mails come from, and where replies go.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __( 'Send e-mails', 'radius-hotel-booking' ) }
					description={ __(
						'Turn off to stop every e-mail from the booking system, for example while testing.',
						'radius-hotel-booking'
					) }
					checked={ value.enabled }
					onChange={ setField( 'enabled' ) }
					error={ errors.enabled }
				/>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Sender name', 'radius-hotel-booking' ) }
						error={ errors.senderName }
					>
						<Input { ...text( 'senderName' ) } maxLength={ 120 } />
					</Field>
					<Field
						label={ __( 'Sender e-mail', 'radius-hotel-booking' ) }
						description={ __(
							'Use an address on your own domain, so e-mails are not marked as spam.',
							'radius-hotel-booking'
						) }
						error={ errors.senderEmail }
					>
						<Input { ...text( 'senderEmail' ) } type="email" />
					</Field>
				</div>
				<Field
					label={ __( 'Reply-to e-mail', 'radius-hotel-booking' ) }
					description={ __(
						'Where guests’ replies go. Leave empty to use the sender e-mail.',
						'radius-hotel-booking'
					) }
					error={ errors.replyToEmail }
				>
					<Input
						{ ...text( 'replyToEmail' ) }
						type="email"
						className="sm:w-1/2"
					/>
				</Field>
			</SettingsSection>

			<SettingsSection
				title={ __( 'E-mails', 'radius-hotel-booking' ) }
				description={ __(
					'Turn each e-mail on or off.',
					'radius-hotel-booking'
				) }
			>
				{ list.length ? (
					<div className="divide-y divide-border">
						{ list.map( ( template ) => (
							<div
								key={ template.id }
								className="py-4 first:pt-0 last:pb-0"
							>
								<ToggleRow
									label={ template.title || template.id }
									description={ [
										recipientLabel( template.recipient ),
										template.description,
									]
										.filter( Boolean )
										.join( ' · ' ) }
									checked={
										switches[ template.id ] ??
										template.default
									}
									// The master switch overrides every template.
									disabled={ value.enabled === false }
									onChange={ ( on ) =>
										setField( 'templates' )( {
											...switches,
											[ template.id ]: on,
										} )
									}
								/>
							</div>
						) ) }
						{ errors.templates ? (
							<p
								role="alert"
								className="m-0 pt-3 text-xs font-medium text-destructive"
							>
								{ errors.templates }
							</p>
						) : null }
					</div>
				) : (
					<EmptyState
						icon={ Mail }
						title={ __( 'No e-mails yet', 'radius-hotel-booking' ) }
						description={ __(
							'Booking and payment e-mails appear here once they are available.',
							'radius-hotel-booking'
						) }
					/>
				) }
			</SettingsSection>
		</>
	);
}
