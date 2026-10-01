/**
 * Settings → Exports (M11): how export files are written.
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';

/**
 * @param {Object}   props          Props from the Settings screen.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Exports( { value, setField, errors } ) {
	return (
		<SettingsSection
			title={ __( 'CSV files', 'radius-hotel-booking' ) }
			description={ __(
				'Excel in French reads columns split by a semicolon; most other spreadsheets expect a comma.',
				'radius-hotel-booking'
			) }
		>
			<Field
				label={ __( 'Column separator', 'radius-hotel-booking' ) }
				description={ __(
					'Used by every CSV export from now on. Files already made keep theirs.',
					'radius-hotel-booking'
				) }
				error={ errors.csvSeparator }
			>
				<div>
					<SegmentedControl
						options={ [
							{
								value: ',',
								label: __(
									'Comma ( , )',
									'radius-hotel-booking'
								),
							},
							{
								value: ';',
								label: __(
									'Semicolon ( ; )',
									'radius-hotel-booking'
								),
							},
						] }
						value={ value.csvSeparator || ',' }
						onChange={ setField( 'csvSeparator' ) }
						label={ __(
							'Column separator',
							'radius-hotel-booking'
						) }
					/>
				</div>
			</Field>
		</SettingsSection>
	);
}
