/**
 * Settings → Display: the brand colour and the room type page's width.
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import BrandColorField from '../BrandColorField';
import { NumberInput } from './fields';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema.
 * @return {JSX.Element} Tab.
 */
export default function Display( { value, setField, errors, schema } ) {
	return (
		<>
			<SettingsSection
				title={ __( 'Appearance', 'radius-hotel-booking' ) }
				description={ __(
					'How the dashboard and the booking pages look.',
					'radius-hotel-booking'
				) }
			>
				<Field error={ errors.primaryColor }>
					<div>
						<BrandColorField
							value={ value.primaryColor }
							onChange={ setField( 'primaryColor' ) }
						/>
					</div>
				</Field>
			</SettingsSection>
			<SettingsSection
				title={ __( 'Room type page', 'radius-hotel-booking' ) }
				description={ __(
					'The page each room type gets on the website (/rooms/…).',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Content width', 'radius-hotel-booking' ) }
					description={ __(
						'The widest the page content grows. Your theme may still keep it narrower.',
						'radius-hotel-booking'
					) }
					error={ errors.roomPageWidth }
				>
					<NumberInput
						value={ value.roomPageWidth }
						onChange={ setField( 'roomPageWidth' ) }
						unit={ __( 'px', 'radius-hotel-booking' ) }
						limits={ schema?.roomPageWidth }
					/>
				</Field>
			</SettingsSection>
		</>
	);
}
