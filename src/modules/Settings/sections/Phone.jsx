/**
 * Settings → Phone numbers: how every phone field works — the country it
 * starts on, the countries it offers, and the hotel's own calling codes
 * (checked by their number of digits only), with a field to try them out.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Plus, Trash2 } from 'lucide-react';

import { Field } from '@/components/common/Form';
import {
	CountryMultiPicker,
	CountryPicker,
	PhoneInput,
} from '@/components/common/PhoneInput';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const NEW_CODE = { label: '', code: '', minDigits: 8, maxDigits: 10 };

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Phone( { value, setField, errors } ) {
	const allowed = Array.isArray( value.allowedCountries )
		? value.allowedCountries
		: [];
	const codes = Array.isArray( value.customCodes ) ? value.customCodes : [];
	const saveCodes = setField( 'customCodes' );
	const [ sample, setSample ] = useState( '' );

	const change = ( index, fields ) =>
		saveCodes(
			codes.map( ( code, i ) =>
				i === index ? { ...code, ...fields } : code
			)
		);

	const digits = ( text ) => text.replace( /\D+/g, '' ).slice( 0, 4 );
	const count = ( text ) => {
		const number = parseInt( text, 10 );
		return Number.isNaN( number ) ? '' : number;
	};

	return (
		<>
			<SettingsSection
				title={ __( 'Countries', 'radius-hotel-booking' ) }
				description={ __(
					'Every phone field — the booking form, guests, staff — starts on the default country and offers the allowed ones.',
					'radius-hotel-booking'
				) }
			>
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __(
							'Default country',
							'radius-hotel-booking'
						) }
						description={ __(
							'Numbers typed without a code are read in this country.',
							'radius-hotel-booking'
						) }
						error={ errors.defaultCountry }
					>
						<div>
							<CountryPicker
								value={ value.defaultCountry || 'US' }
								countries={ allowed }
								onChange={ ( country ) => {
									setField( 'defaultCountry' )( country );
								} }
							/>
						</div>
					</Field>
					<Field
						label={ __(
							'Allowed countries',
							'radius-hotel-booking'
						) }
						description={ __(
							'Only these countries are offered. Leave it empty to allow every country.',
							'radius-hotel-booking'
						) }
						error={ errors.allowedCountries }
					>
						<div>
							<CountryMultiPicker
								value={ allowed }
								onChange={ ( next ) => {
									setField( 'allowedCountries' )( next );
									// The default country must stay on the list.
									if (
										next.length &&
										! next.includes( value.defaultCountry )
									) {
										setField( 'defaultCountry' )(
											next[ 0 ]
										);
									}
								} }
							/>
						</div>
					</Field>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Custom codes', 'radius-hotel-booking' ) }
				description={ __(
					'Calling codes the country list does not cover, such as a private network or an old numbering plan. A number with one of these codes is checked by its number of digits only.',
					'radius-hotel-booking'
				) }
			>
				{ errors.customCodes ? (
					<p className="m-0 text-sm text-destructive" role="alert">
						{ errors.customCodes }
					</p>
				) : null }
				{ codes.length ? (
					<div className="space-y-3">
						<div className="hidden grid-cols-[minmax(0,2fr)_7rem_6rem_6rem_2.5rem] gap-3 px-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground md:grid">
							<span>
								{ __( 'Name', 'radius-hotel-booking' ) }
							</span>
							<span>
								{ __( 'Code', 'radius-hotel-booking' ) }
							</span>
							<span>
								{ __(
									'Fewest digits',
									'radius-hotel-booking'
								) }
							</span>
							<span>
								{ __( 'Most digits', 'radius-hotel-booking' ) }
							</span>
							<span className="sr-only">
								{ __( 'Remove', 'radius-hotel-booking' ) }
							</span>
						</div>
						{ codes.map( ( code, index ) => (
							<div
								key={ index }
								className="grid grid-cols-2 gap-3 rounded-lg border border-border p-3 md:grid-cols-[minmax(0,2fr)_7rem_6rem_6rem_2.5rem] md:items-center md:border-0 md:p-0"
							>
								<Input
									className="col-span-2 md:col-span-1"
									value={ code.label }
									maxLength={ 60 }
									placeholder={ __(
										'Name, e.g. Hotel network',
										'radius-hotel-booking'
									) }
									aria-label={ __(
										'Name',
										'radius-hotel-booking'
									) }
									onChange={ ( e ) =>
										change( index, {
											label: e.target.value,
										} )
									}
								/>
								<div className="relative">
									<span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">
										+
									</span>
									<Input
										className="pl-6 tabular-nums"
										inputMode="numeric"
										value={ code.code }
										placeholder="44"
										aria-label={ __(
											'Calling code',
											'radius-hotel-booking'
										) }
										onChange={ ( e ) =>
											change( index, {
												code: digits( e.target.value ),
											} )
										}
									/>
								</div>
								<Input
									type="number"
									min={ 4 }
									max={ 14 }
									className="tabular-nums"
									value={ code.minDigits }
									aria-label={ __(
										'Fewest digits',
										'radius-hotel-booking'
									) }
									onChange={ ( e ) =>
										change( index, {
											minDigits: count( e.target.value ),
										} )
									}
								/>
								<Input
									type="number"
									min={ 4 }
									max={ 14 }
									className="tabular-nums"
									value={ code.maxDigits }
									aria-label={ __(
										'Most digits',
										'radius-hotel-booking'
									) }
									onChange={ ( e ) =>
										change( index, {
											maxDigits: count( e.target.value ),
										} )
									}
								/>
								<Button
									type="button"
									variant="ghost"
									size="icon"
									className="justify-self-end text-muted-foreground hover:text-destructive"
									onClick={ () =>
										saveCodes(
											codes.filter(
												( c, i ) => i !== index
											)
										)
									}
									aria-label={ sprintf(
										/* translators: %s: custom code name. */
										__(
											'Remove %s',
											'radius-hotel-booking'
										),
										code.label ||
											__(
												'this code',
												'radius-hotel-booking'
											)
									) }
								>
									<Trash2 aria-hidden="true" />
								</Button>
							</div>
						) ) }
					</div>
				) : (
					<p className="m-0 rounded-lg border border-dashed border-border p-4 text-center text-sm text-muted-foreground">
						{ __(
							'No custom codes. The country list covers most numbers.',
							'radius-hotel-booking'
						) }
					</p>
				) }
				<Button
					type="button"
					variant="outline"
					disabled={ codes.length >= 30 }
					onClick={ () => saveCodes( [ ...codes, { ...NEW_CODE } ] ) }
				>
					<Plus aria-hidden="true" />
					{ __( 'Add a code', 'radius-hotel-booking' ) }
				</Button>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Try it', 'radius-hotel-booking' ) }
				description={ __(
					'A phone field with the settings above, before you save. Nothing typed here is kept.',
					'radius-hotel-booking'
				) }
			>
				<div className="max-w-sm space-y-1.5">
					<PhoneInput
						key={ JSON.stringify( [
							value.defaultCountry,
							allowed,
							codes,
						] ) }
						value={ sample }
						onChange={ setSample }
						config={ {
							defaultCountry: value.defaultCountry || 'US',
							allowedCountries: allowed,
							customCodes: codes.filter(
								( c ) => c.label && c.code
							),
						} }
					/>
					{ sample ? (
						<p className="m-0 text-xs text-muted-foreground">
							{ sprintf(
								/* translators: %s: the number as it is saved, e.g. +2250707123456. */
								__( 'Saved as %s', 'radius-hotel-booking' ),
								sample
							) }
						</p>
					) : null }
				</div>
			</SettingsSection>
		</>
	);
}
