/**
 * Settings → Display: the brand colour.
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import BrandColorField from '../BrandColorField';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Display( { value, setField, errors } ) {
	return (
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
	);
}
