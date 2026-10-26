/**
 * Settings → Public booking (M04): how guests book on the website. The rules
 * shared with the front desk (booking window, same-day cut-off, approval,
 * unavailable rooms) are in Booking rules.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { FilePlus2, Plus, Trash2 } from 'lucide-react';

import { post } from '@/api/client';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { toast, toastError } from '@/lib/toast';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { NumberInput, ToggleRow } from './fields';

/**
 * The booking form's guest fields (WebsiteSettings::GUEST_FIELDS).
 *
 * @return {Object[]} `{ key, label, help?, fixed? }` (fixed: always required).
 */
function guestFieldRows() {
	return [
		{
			key: 'first_name',
			label: __( 'First name', 'radius-hotel-booking' ),
			fixed: true,
		},
		{ key: 'last_name', label: __( 'Last name', 'radius-hotel-booking' ) },
		{
			key: 'phone',
			label: __( 'Phone', 'radius-hotel-booking' ),
			help: __(
				'Express checkout needs it: hiding it turns Express checkout off.',
				'radius-hotel-booking'
			),
		},
		{
			key: 'email',
			label: __( 'E-mail', 'radius-hotel-booking' ),
			help: __(
				'Needed for the confirmation e-mail. The phone or the e-mail must be required.',
				'radius-hotel-booking'
			),
		},
		{
			key: 'id_document',
			label: __( 'Identity document', 'radius-hotel-booking' ),
			help: __(
				'The type and the number. When it is not required, a returning guest who leaves it empty is recognised by their phone or e-mail.',
				'radius-hotel-booking'
			),
		},
		{
			key: 'special_requests',
			label: __( 'Special requests', 'radius-hotel-booking' ),
		},
	];
}

/**
 * The inputs a guest field shows on the booking form, with their built-in
 * label and placeholder (WebsiteSettings::TEXT_FIELDS).
 *
 * @param {string} field A guestFieldRows() key.
 * @return {Object[]} `{ key, name, label, placeholder }`.
 */
function textInputsOf( field ) {
	const own = ( key, label, placeholder = '' ) => ( {
		key,
		name: null,
		label,
		placeholder,
	} );
	switch ( field ) {
		case 'first_name':
			return [ own( field, __( 'First name', 'radius-hotel-booking' ) ) ];
		case 'last_name':
			return [ own( field, __( 'Last name', 'radius-hotel-booking' ) ) ];
		case 'phone':
			return [ own( field, __( 'Phone', 'radius-hotel-booking' ) ) ];
		case 'email':
			return [ own( field, __( 'E-mail', 'radius-hotel-booking' ) ) ];
		case 'id_document':
			return [
				{
					...own(
						'id_type',
						__( 'Identity document', 'radius-hotel-booking' ),
						__( 'Choose…', 'radius-hotel-booking' )
					),
					name: __( 'Document type', 'radius-hotel-booking' ),
				},
				{
					...own(
						'id_number',
						__( 'Document number', 'radius-hotel-booking' )
					),
					name: __( 'Document number', 'radius-hotel-booking' ),
				},
			];
		case 'special_requests':
			return [
				own( field, __( 'Special requests', 'radius-hotel-booking' ) ),
			];
		default:
			return [];
	}
}

/**
 * The label and placeholder inputs of one booking-form input.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.input    A textInputsOf() row.
 * @param {Object}   props.value    `{ label, placeholder }` saved.
 * @param {Function} props.onChange Called with the changed `{ label?, placeholder? }`.
 * @return {JSX.Element} Inputs.
 */
function TextInputs( { input, value = {}, onChange } ) {
	const suffix = input.name ? ` (${ input.name })` : '';
	return (
		<div className="grid gap-3 sm:grid-cols-2">
			<Field label={ __( 'Label', 'radius-hotel-booking' ) + suffix }>
				<Input
					value={ value.label || '' }
					onChange={ ( event ) =>
						onChange( { label: event.target.value } )
					}
					placeholder={ input.label }
					maxLength={ 100 }
				/>
			</Field>
			<Field
				label={ __( 'Placeholder', 'radius-hotel-booking' ) + suffix }
			>
				<Input
					value={ value.placeholder || '' }
					onChange={ ( event ) =>
						onChange( { placeholder: event.target.value } )
					}
					placeholder={
						input.placeholder ||
						__( 'None', 'radius-hotel-booking' )
					}
					maxLength={ 100 }
				/>
			</Field>
		</div>
	);
}

/**
 * The identity documents guests can choose: add, rename or remove.
 *
 * @param {Object}   props          Props.
 * @param {Object[]} props.value    `[ { key, label } ]`; a new row has no key yet.
 * @param {Function} props.onChange Called with the new list.
 * @param {string}   props.error    Server message.
 * @return {JSX.Element} List.
 */
function IdTypesList( { value, onChange, error } ) {
	const rows = Array.isArray( value ) ? value : [];
	const change = ( index, label ) =>
		onChange(
			rows.map( ( row, i ) => ( i === index ? { ...row, label } : row ) )
		);
	return (
		<div className="space-y-2">
			<p className="m-0 text-sm font-medium text-heading">
				{ __( 'Document types', 'radius-hotel-booking' ) }
			</p>
			<p className="m-0 text-[13px] leading-5 text-muted-foreground">
				{ __(
					'The documents guests choose from. Guests already on file keep the one they gave.',
					'radius-hotel-booking'
				) }
			</p>
			<ul className="m-0 list-none space-y-2 p-0">
				{ rows.map( ( row, index ) => (
					<li
						// A new row has no key until it is saved.
						key={ row.key || `new-${ index }` }
						className="m-0 flex items-center gap-2"
					>
						<Input
							value={ row.label }
							onChange={ ( event ) =>
								change( index, event.target.value )
							}
							maxLength={ 100 }
							placeholder={ __(
								'Document name',
								'radius-hotel-booking'
							) }
							aria-label={ sprintf(
								/* translators: %d: row number. */
								__(
									'Document type %d',
									'radius-hotel-booking'
								),
								index + 1
							) }
						/>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							className="shrink-0 text-muted-foreground hover:text-destructive"
							disabled={ rows.length <= 1 }
							onClick={ () =>
								onChange(
									rows.filter( ( _, i ) => i !== index )
								)
							}
							aria-label={ sprintf(
								/* translators: %s: document name. */
								__( 'Remove %s', 'radius-hotel-booking' ),
								row.label ||
									__( 'this row', 'radius-hotel-booking' )
							) }
						>
							<Trash2 aria-hidden="true" />
						</Button>
					</li>
				) ) }
			</ul>
			<Button
				type="button"
				variant="outline"
				size="sm"
				onClick={ () =>
					onChange( [ ...rows, { key: '', label: '' } ] )
				}
			>
				<Plus aria-hidden="true" />
				{ __( 'Add document type', 'radius-hotel-booking' ) }
			</Button>
			{ error ? (
				<p
					role="alert"
					className="m-0 text-xs font-medium text-destructive"
				>
					{ error }
				</p>
			) : null }
		</div>
	);
}

/**
 * @param {Object}   props          Props from the Settings screen.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema.
 * @param {Function} props.accept   Take values the server already saved.
 * @return {JSX.Element} Tab.
 */
export default function Website( { value, setField, errors, schema, accept } ) {
	const params = window.radius_hotel_booking_param || {};
	const guestFields = {
		...( schema?.guestFields?.default || {} ),
		...( value.guestFields || {} ),
	};
	const fieldText = {
		...( schema?.guestFieldText?.default || {} ),
		...( value.guestFieldText || {} ),
	};
	const changeText = ( key, patch ) =>
		setField( 'guestFieldText' )( {
			...fieldText,
			[ key ]: { ...fieldText[ key ], ...patch },
		} );
	const [ pages, setPages ] = useState( params.site_pages || [] );
	const [ creating, setCreating ] = useState( false );
	const chosen = pages.some(
		( page ) => page.id === Number( value.resultsPageId )
	);

	// Create *Book a room* with [rtbp_booking] and choose it (saved on the server at once).
	const createPage = () => {
		setCreating( true );
		post( 'settings/website/booking-page' )
			.then( ( { data, message } ) => {
				const page = { id: data.page.id, title: data.page.title };
				const next = [
					...pages.filter( ( p ) => p.id !== page.id ),
					page,
				];
				setPages( next );
				params.site_pages = next;
				// Saved on the server already: take it as saved, not as an unsaved change.
				if ( accept && data.data ) {
					accept( data.data );
				} else {
					setField( 'resultsPageId' )( page.id );
				}
				toast.success( message );
			} )
			.catch( toastError )
			.finally( () => setCreating( false ) );
	};
	const privacyUrl = params.privacy_url || '';

	return (
		<>
			<SettingsSection
				title={ __( 'Search and results', 'radius-hotel-booking' ) }
				description={ __(
					'The search bar sends guests to the booking page, which shows the rooms free for their dates.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Booking page', 'radius-hotel-booking' ) }
					description={ __(
						'The page with the [rtbp_booking] shortcode or the Booking block.',
						'radius-hotel-booking'
					) }
					error={ errors.resultsPageId }
				>
					<Select
						value={ String( value.resultsPageId || 0 ) }
						onValueChange={ ( next ) =>
							setField( 'resultsPageId' )( Number( next ) )
						}
					>
						<SelectTrigger
							className="w-full sm:w-80"
							aria-label={ __(
								'Booking page',
								'radius-hotel-booking'
							) }
						>
							<SelectValue />
						</SelectTrigger>
						<SelectContent className="rtbp-root">
							<SelectItem value="0">
								{ __(
									'Not chosen yet',
									'radius-hotel-booking'
								) }
							</SelectItem>
							{ pages.map( ( page ) => (
								<SelectItem
									key={ page.id }
									value={ String( page.id ) }
								>
									{ page.title }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</Field>
				{ /* Outside the Field: it passes one child (the control) to its label slot. */ }
				{ ! chosen ? (
					<div>
						<Button
							type="button"
							variant="outline"
							disabled={ creating }
							onClick={ createPage }
						>
							<FilePlus2 aria-hidden="true" />
							{ __(
								'Create the booking page',
								'radius-hotel-booking'
							) }
						</Button>
					</div>
				) : null }

				<Field
					label={ __( 'Adults preselected', 'radius-hotel-booking' ) }
					error={ errors.defaultAdults }
				>
					<NumberInput
						value={ value.defaultAdults }
						onChange={ setField( 'defaultAdults' ) }
						unit={ __( 'adults', 'radius-hotel-booking' ) }
						limits={ schema.defaultAdults }
					/>
				</Field>

				<ToggleRow
					label={ __( 'Ask how many rooms', 'radius-hotel-booking' ) }
					description={ __(
						'Adds a rooms field to the search bar, for guests booking for a group.',
						'radius-hotel-booking'
					) }
					checked={ value.showRoomsField }
					onChange={ setField( 'showRoomsField' ) }
					error={ errors.showRoomsField }
				/>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Booking form', 'radius-hotel-booking' ) }
				description={ __(
					'What guests choose and agree to before they book.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __(
						'Guests choose their room',
						'radius-hotel-booking'
					) }
					description={ __(
						'Guests pick the room number. When off, the first free room is given to them.',
						'radius-hotel-booking'
					) }
					checked={ value.guestPicksRoom }
					onChange={ setField( 'guestPicksRoom' ) }
					error={ errors.guestPicksRoom }
				/>
				<ToggleRow
					label={ __(
						'Ask guests to accept the privacy policy',
						'radius-hotel-booking'
					) }
					description={
						privacyUrl
							? __(
									'A checkbox linking to your privacy policy page.',
									'radius-hotel-booking'
							  )
							: __(
									'A checkbox before booking. Set your privacy policy page in Settings → Privacy (WordPress) so the box can link to it.',
									'radius-hotel-booking'
							  )
					}
					checked={ value.privacyConsent }
					onChange={ setField( 'privacyConsent' ) }
					error={ errors.privacyConsent }
				/>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Guest details', 'radius-hotel-booking' ) }
				description={ __(
					'Which fields the booking form asks for, and which ones guests must fill in. The first name is always required.',
					'radius-hotel-booking'
				) }
			>
				<div className="divide-y divide-border">
					{ guestFieldRows().map( ( row ) => (
						<div
							key={ row.key }
							className="space-y-3 py-4 first:pt-0 last:pb-0"
						>
							<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
								<div className="min-w-0 flex-1">
									<p className="m-0 text-sm font-semibold text-heading">
										{ row.label }
									</p>
									{ row.help ? (
										<p className="m-0 mt-0.5 text-[13px] leading-5 text-muted-foreground">
											{ row.help }
										</p>
									) : null }
								</div>
								<Select
									value={
										row.fixed
											? 'required'
											: guestFields[ row.key ]
									}
									disabled={ row.fixed }
									onValueChange={ ( mode ) =>
										setField( 'guestFields' )( {
											...guestFields,
											[ row.key ]: mode,
										} )
									}
								>
									<SelectTrigger
										className="w-full sm:w-44 sm:shrink-0"
										aria-label={ row.label }
									>
										<SelectValue />
									</SelectTrigger>
									<SelectContent className="rtbp-root">
										<SelectItem value="required">
											{ __(
												'Required',
												'radius-hotel-booking'
											) }
										</SelectItem>
										<SelectItem value="optional">
											{ __(
												'Optional',
												'radius-hotel-booking'
											) }
										</SelectItem>
										<SelectItem value="hidden">
											{ __(
												'Hidden',
												'radius-hotel-booking'
											) }
										</SelectItem>
									</SelectContent>
								</Select>
							</div>
							{ /* A hidden field has nothing to word. */ }
							{ row.fixed ||
							'hidden' !== guestFields[ row.key ] ? (
								<>
									{ textInputsOf( row.key ).map(
										( input ) => (
											<TextInputs
												key={ input.key }
												input={ input }
												value={ fieldText[ input.key ] }
												onChange={ ( patch ) =>
													changeText(
														input.key,
														patch
													)
												}
											/>
										)
									) }
									{ 'id_document' === row.key ? (
										<IdTypesList
											value={
												value.idTypes ??
												schema?.idTypes?.default
											}
											onChange={ setField( 'idTypes' ) }
											error={ errors.idTypes }
										/>
									) : null }
								</>
							) : null }
						</div>
					) ) }
				</div>
				{ errors.guestFieldText ? (
					<p
						role="alert"
						className="m-0 text-xs font-medium text-destructive"
					>
						{ errors.guestFieldText }
					</p>
				) : null }
				{ errors.guestFields ? (
					<p
						role="alert"
						className="m-0 text-xs font-medium text-destructive"
					>
						{ errors.guestFields }
					</p>
				) : null }
			</SettingsSection>
		</>
	);
}
