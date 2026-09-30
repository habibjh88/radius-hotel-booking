/**
 * Settings → Invoices (17.13): how invoice numbers look (prefix, year and a
 * sequence that never skips, 5.8), the tax included in the prices, and the
 * text at the foot of every invoice and receipt. The hotel's name, address,
 * tax and CNPS numbers and logo come from General.
 */
import { __, sprintf } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { NumberInput } from './fields';

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema (limits).
 * @return {JSX.Element} Tab.
 */
export default function Invoices( { value, setField, errors, schema } ) {
	const year = new Date().getFullYear();
	const start = Math.max( 1, Number( value.startNumber ) || 1 );
	const example = `${ value.prefix ?? '' }${ year }-${ String(
		start
	).padStart( 6, '0' ) }`;

	return (
		<>
			<SettingsSection
				title={ __( 'Numbering', 'radius-hotel-booking' ) }
				description={ __(
					'Every booking gets an invoice with the next number. Numbers never skip, and start again each year.',
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
						error={ errors.prefix }
					>
						<Input
							value={ value.prefix ?? '' }
							maxLength={ schema.prefix?.maxLength }
							onChange={ ( e ) =>
								setField( 'prefix' )( e.target.value )
							}
						/>
					</Field>
					<Field
						label={ __( 'First number', 'radius-hotel-booking' ) }
						description={ __(
							'Where each year’s numbering starts.',
							'radius-hotel-booking'
						) }
						error={ errors.startNumber }
					>
						<NumberInput
							value={ value.startNumber }
							onChange={ setField( 'startNumber' ) }
							limits={ schema.startNumber }
						/>
					</Field>
				</div>
				<p className="m-0 text-sm text-muted-foreground">
					{ sprintf(
						/* translators: %s: example invoice number. */
						__(
							'The first invoice of the year will be %s.',
							'radius-hotel-booking'
						),
						example
					) }
				</p>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Tax', 'radius-hotel-booking' ) }
				description={ __(
					'Your prices include the tax. The invoice shows how much of the total it is. Leave the rate at 0 for no tax line.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Tax name', 'radius-hotel-booking' ) }
						description={ __(
							'For example VAT.',
							'radius-hotel-booking'
						) }
						error={ errors.taxLabel }
					>
						<Input
							value={ value.taxLabel ?? '' }
							maxLength={ schema.taxLabel?.maxLength }
							onChange={ ( e ) =>
								setField( 'taxLabel' )( e.target.value )
							}
						/>
					</Field>
					<Field
						label={ __( 'Rate', 'radius-hotel-booking' ) }
						error={ errors.taxRate }
					>
						<div className="flex items-center gap-2">
							<Input
								type="number"
								inputMode="decimal"
								step="0.01"
								min={ schema.taxRate?.min }
								max={ schema.taxRate?.max }
								className="w-28"
								value={ value.taxRate ?? '' }
								onChange={ ( e ) =>
									setField( 'taxRate' )(
										'' === e.target.value
											? ''
											: Number( e.target.value )
									)
								}
							/>
							<span className="text-sm text-muted-foreground">
								%
							</span>
						</div>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Footer', 'radius-hotel-booking' ) }
				description={ __(
					'Printed at the bottom of every invoice and receipt. Your hotel’s name, address, tax and CNPS numbers and logo come from General.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Footer text', 'radius-hotel-booking' ) }
					error={ errors.footerText }
				>
					<Textarea
						rows={ 3 }
						className="!min-h-[96px]"
						maxLength={ schema.footerText?.maxLength }
						value={ value.footerText ?? '' }
						onChange={ ( e ) =>
							setField( 'footerText' )( e.target.value )
						}
					/>
				</Field>
			</SettingsSection>
		</>
	);
}
