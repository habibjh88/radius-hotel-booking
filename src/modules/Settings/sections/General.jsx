/**
 * Settings → General: the hotel's details, date and time formats, and the
 * currency (feature 17.10). Shared with e-mails, invoices (M05) and payslips
 * (M15). Dates and amounts preview with the unsaved values.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import { Field } from '@/components/common/Form';
import MediaField from '@/components/common/MediaField';
import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { formatDateAs, formatMoney } from '@/lib/format';
import { NumberInput, ToggleRow } from './fields';

/** PHP date formats offered in the list; anything else is "Custom". */
const DATE_FORMATS = [ 'j F Y', 'F j, Y', 'd/m/Y', 'm/d/Y', 'd.m.Y', 'Y-m-d' ];

/** A fixed sample date, so every preview shows day > 12 and a long month. */
const SAMPLE_DATE = '2026-12-24 20:30';

/**
 * Currency presets: the fields each one fills in.
 *
 * @return {Array<Object>} Presets.
 */
const currencyPresets = () => [
	{
		label: __( 'CFA franc (XOF)', 'radius-hotel-booking' ),
		values: {
			currencyCode: 'XOF',
			currencySymbol: 'CFA',
			currencyPosition: 'right_space',
			thousandSeparator: ' ',
			decimalSeparator: ',',
			decimals: 0,
		},
	},
	{
		label: __( 'Euro (EUR)', 'radius-hotel-booking' ),
		values: {
			currencyCode: 'EUR',
			currencySymbol: '€',
			currencyPosition: 'right_space',
			thousandSeparator: ' ',
			decimalSeparator: ',',
			decimals: 2,
		},
	},
	{
		label: __( 'US dollar (USD)', 'radius-hotel-booking' ),
		values: {
			currencyCode: 'USD',
			currencySymbol: '$',
			currencyPosition: 'left',
			thousandSeparator: ',',
			decimalSeparator: '.',
			decimals: 2,
		},
	},
];

/**
 * A text box bound to one key.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {string}   props.name     Key.
 * @return {Object} Input props.
 */
const bind = ( { value, setField, name } ) => ( {
	value: value[ name ] ?? '',
	onChange: ( event ) => setField( name )( event.target.value ),
} );

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema (limits).
 * @return {JSX.Element} Tab.
 */
export default function General( { value, setField, errors, schema } ) {
	const text = ( name ) => bind( { value, setField, name } );
	const [ confirmDelete, setConfirmDelete ] = useState( false );

	const dateFormat = value.dateFormat ?? '';
	const isPreset = DATE_FORMATS.includes( dateFormat );
	const timeFormat = value.timeSystem === '24h' ? 'H:i' : 'g:i A';

	const currency = {
		symbol: value.currencySymbol ?? '',
		position: value.currencyPosition,
		thousand: value.thousandSeparator ?? '',
		decimal: value.decimalSeparator ?? '',
		decimals: Number.isInteger( value.decimals ) ? value.decimals : 0,
	};
	const money = ( amount, position = currency.position ) =>
		formatMoney( amount, { currency: { ...currency, position } } );

	const applyPreset = ( values ) =>
		Object.entries( values ).forEach( ( [ key, next ] ) =>
			setField( key )( next )
		);

	return (
		<>
			<SettingsSection
				title={ __( 'Hotel details', 'radius-hotel-booking' ) }
				description={ __(
					'Shown on e-mails, invoices, payslips and the booking pages.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Hotel name', 'radius-hotel-booking' ) }
						error={ errors.companyName }
					>
						<Input
							{ ...text( 'companyName' ) }
							maxLength={ schema.companyName?.maxLength }
						/>
					</Field>
					<Field
						label={ __( 'Legal name', 'radius-hotel-booking' ) }
						description={ __(
							'The registered company name on invoices. Leave empty to use the hotel name.',
							'radius-hotel-booking'
						) }
						error={ errors.legalName }
					>
						<Input
							{ ...text( 'legalName' ) }
							maxLength={ schema.legalName?.maxLength }
						/>
					</Field>
				</div>
				<Field
					label={ __( 'Address', 'radius-hotel-booking' ) }
					error={ errors.address }
				>
					<Textarea
						{ ...text( 'address' ) }
						rows={ 3 }
						maxLength={ schema.address?.maxLength }
					/>
				</Field>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Phone', 'radius-hotel-booking' ) }
						error={ errors.phone }
					>
						<Input
							{ ...text( 'phone' ) }
							type="tel"
							inputMode="tel"
							maxLength={ schema.phone?.maxLength }
						/>
					</Field>
					<Field
						label={ __( 'Contact e-mail', 'radius-hotel-booking' ) }
						error={ errors.contactEmail }
					>
						<Input { ...text( 'contactEmail' ) } type="email" />
					</Field>
				</div>
				<Field
					label={ __( 'Logo', 'radius-hotel-booking' ) }
					description={ __(
						'Shown at the top of e-mails and documents. A square image works best.',
						'radius-hotel-booking'
					) }
					error={ errors.logo }
				>
					<MediaField
						value={ value.logo }
						onChange={ setField( 'logo' ) }
						accept={ schema.logo?.mime || [ 'image/' ] }
						title={ __( 'Choose a logo', 'radius-hotel-booking' ) }
					/>
				</Field>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Registration numbers', 'radius-hotel-booking' ) }
				description={ __(
					'Printed on invoices and payslips when filled in.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Tax number', 'radius-hotel-booking' ) }
						error={ errors.taxNumber }
					>
						<Input
							{ ...text( 'taxNumber' ) }
							maxLength={ schema.taxNumber?.maxLength }
						/>
					</Field>
					<Field
						label={ __( 'CNPS number', 'radius-hotel-booking' ) }
						description={ __(
							'The employer social-security number, printed on payslips.',
							'radius-hotel-booking'
						) }
						error={ errors.cnpsNumber }
					>
						<Input
							{ ...text( 'cnpsNumber' ) }
							maxLength={ schema.cnpsNumber?.maxLength }
						/>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Date and time', 'radius-hotel-booking' ) }
				description={ __(
					'How dates and times appear across the dashboard, e-mails and documents.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Date format', 'radius-hotel-booking' ) }
					error={ errors.dateFormat }
				>
					<div className="space-y-2">
						<Select
							value={ isPreset ? dateFormat : 'custom' }
							onValueChange={ ( next ) =>
								setField( 'dateFormat' )(
									next === 'custom' ? '' : next
								)
							}
						>
							<SelectTrigger
								className="w-full sm:w-72"
								aria-label={ __(
									'Date format',
									'radius-hotel-booking'
								) }
							>
								<SelectValue />
							</SelectTrigger>
							<SelectContent className="rtbp-root">
								{ DATE_FORMATS.map( ( format ) => (
									<SelectItem key={ format } value={ format }>
										{ formatDateAs( SAMPLE_DATE, format ) }
									</SelectItem>
								) ) }
								<SelectItem value="custom">
									{ __( 'Custom…', 'radius-hotel-booking' ) }
								</SelectItem>
							</SelectContent>
						</Select>
						{ ! isPreset ? (
							<div className="flex flex-wrap items-center gap-3">
								<Input
									{ ...text( 'dateFormat' ) }
									className="w-40 font-mono"
									maxLength={ schema.dateFormat?.maxLength }
									placeholder="j F Y"
									aria-label={ __(
										'Custom date format',
										'radius-hotel-booking'
									) }
								/>
								<span className="text-sm text-muted-foreground">
									{ dateFormat
										? formatDateAs(
												SAMPLE_DATE,
												dateFormat
										  )
										: __(
												'Empty: the WordPress date format is used.',
												'radius-hotel-booking'
										  ) }
								</span>
							</div>
						) : null }
					</div>
				</Field>
				<Field
					label={ __( 'Time format', 'radius-hotel-booking' ) }
					error={ errors.timeSystem }
				>
					<div>
						<SegmentedControl
							label={ __(
								'Time format',
								'radius-hotel-booking'
							) }
							value={ value.timeSystem }
							onChange={ setField( 'timeSystem' ) }
							options={ [
								{
									value: '12h',
									label: formatDateAs( SAMPLE_DATE, 'g:i A' ),
								},
								{
									value: '24h',
									label: formatDateAs( SAMPLE_DATE, 'H:i' ),
								},
							] }
						/>
					</div>
				</Field>
				<p className="m-0 text-sm text-muted-foreground">
					{ sprintf(
						/* translators: %s: a sample date and time in the chosen formats. */
						__( 'Preview: %s', 'radius-hotel-booking' ),
						formatDateAs(
							SAMPLE_DATE,
							`${ dateFormat || 'j F Y' } ${ timeFormat }`
						)
					) }
				</p>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Currency', 'radius-hotel-booking' ) }
				description={ __(
					'Every price, payment, invoice and report uses this currency.',
					'radius-hotel-booking'
				) }
				actions={
					<span className="rounded-lg bg-muted px-3 py-1.5 text-sm font-semibold tabular-nums text-heading">
						{ money( 1234567.89 ) }
					</span>
				}
			>
				<div className="flex flex-wrap items-center gap-2">
					<span className="text-sm text-muted-foreground">
						{ __( 'Quick setup:', 'radius-hotel-booking' ) }
					</span>
					{ currencyPresets().map( ( preset ) => (
						<Button
							key={ preset.label }
							type="button"
							variant="outline"
							size="sm"
							onClick={ () => applyPreset( preset.values ) }
						>
							{ preset.label }
						</Button>
					) ) }
				</div>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'Currency code', 'radius-hotel-booking' ) }
						description={ __(
							'Three letters, e.g. XOF.',
							'radius-hotel-booking'
						) }
						error={ errors.currencyCode }
					>
						<Input
							value={ value.currencyCode ?? '' }
							onChange={ ( event ) =>
								setField( 'currencyCode' )(
									event.target.value.toUpperCase()
								)
							}
							maxLength={ 3 }
							className="w-24 uppercase"
						/>
					</Field>
					<Field
						label={ __( 'Symbol', 'radius-hotel-booking' ) }
						error={ errors.currencySymbol }
					>
						<Input
							{ ...text( 'currencySymbol' ) }
							maxLength={ schema.currencySymbol?.maxLength }
							className="w-24"
						/>
					</Field>
				</div>
				<Field
					label={ __( 'Symbol position', 'radius-hotel-booking' ) }
					error={ errors.currencyPosition }
				>
					<div>
						<Select
							value={ value.currencyPosition }
							onValueChange={ setField( 'currencyPosition' ) }
						>
							<SelectTrigger
								className="w-full sm:w-72"
								aria-label={ __(
									'Symbol position',
									'radius-hotel-booking'
								) }
							>
								<SelectValue />
							</SelectTrigger>
							<SelectContent className="rtbp-root">
								{ [
									[
										'left',
										__( 'Before', 'radius-hotel-booking' ),
									],
									[
										'left_space',
										__(
											'Before, with a space',
											'radius-hotel-booking'
										),
									],
									[
										'right',
										__( 'After', 'radius-hotel-booking' ),
									],
									[
										'right_space',
										__(
											'After, with a space',
											'radius-hotel-booking'
										),
									],
								].map( ( [ position, label ] ) => (
									<SelectItem
										key={ position }
										value={ position }
									>
										{ `${ label } (${ money(
											1500,
											position
										) })` }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					</div>
				</Field>
				<div className="grid gap-4 sm:grid-cols-3">
					<Field
						label={ __(
							'Thousands separator',
							'radius-hotel-booking'
						) }
						description={
							// A space is invisible in the box, so say it.
							value.thousandSeparator === ' '
								? __(
										'Currently a space.',
										'radius-hotel-booking'
								  )
								: __(
										'A space is allowed.',
										'radius-hotel-booking'
								  )
						}
						error={ errors.thousandSeparator }
					>
						<Input
							{ ...text( 'thousandSeparator' ) }
							maxLength={ 2 }
							className="w-20 font-mono"
						/>
					</Field>
					<Field
						label={ __(
							'Decimal separator',
							'radius-hotel-booking'
						) }
						error={ errors.decimalSeparator }
					>
						<Input
							{ ...text( 'decimalSeparator' ) }
							maxLength={ 2 }
							className="w-20 font-mono"
						/>
					</Field>
					<Field
						label={ __( 'Decimals', 'radius-hotel-booking' ) }
						error={ errors.decimals }
					>
						<NumberInput
							value={ value.decimals }
							onChange={ setField( 'decimals' ) }
							limits={ schema.decimals }
						/>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Uninstall', 'radius-hotel-booking' ) }
				description={ __(
					'What happens to your data when the plugin is deleted.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __(
						'Delete all data when the plugin is deleted',
						'radius-hotel-booking'
					) }
					description={ __(
						'Bookings, guests, rooms, payments, settings and files are removed for good. Leave this off to keep them for a reinstall.',
						'radius-hotel-booking'
					) }
					checked={ value.deleteDataOnUninstall }
					onChange={ ( on ) =>
						on
							? setConfirmDelete( true )
							: setField( 'deleteDataOnUninstall' )( false )
					}
					error={ errors.deleteDataOnUninstall }
				/>
				<ConfirmDialog
					open={ confirmDelete }
					onOpenChange={ setConfirmDelete }
					title={ __(
						'Delete all data on uninstall?',
						'radius-hotel-booking'
					) }
					description={ __(
						'If the plugin is later deleted from the Plugins screen, every booking, guest, payment and setting is erased and cannot be recovered. Deactivating the plugin never deletes anything.',
						'radius-hotel-booking'
					) }
					confirmLabel={ __( 'Turn on', 'radius-hotel-booking' ) }
					destructive
					onConfirm={ () =>
						setField( 'deleteDataOnUninstall' )( true )
					}
				/>
			</SettingsSection>
		</>
	);
}
