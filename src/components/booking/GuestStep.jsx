/**
 * The guest of the booking (2.7–2.9): find an existing guest by name, phone
 * or e-mail, or add a new one (name, phone, optional e-mail and identity
 * document). A banned guest shows a red banner and the booking cannot be
 * confirmed (the server refuses it too). A new guest whose phone or e-mail
 * is already on file is matched to that guest when the booking is made.
 */
import { useEffect, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Ban, Search, UserPlus, UserRound } from 'lucide-react';

import { Field } from '@/components/common/Form';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { useAccess } from '@/lib/access';
import { useGuestLookup } from './api';

export const EMPTY_GUEST = {
	first_name: '',
	last_name: '',
	phone: '',
	email: '',
	id_type: '',
	id_number: '',
};

/**
 * The value a debounced input settles on.
 *
 * @param {string} value Value.
 * @param {number} delay Milliseconds.
 * @return {string} Settled value.
 */
function useDebounced( value, delay ) {
	const [ settled, setSettled ] = useState( value );
	useEffect( () => {
		const timer = window.setTimeout( () => setSettled( value ), delay );
		return () => window.clearTimeout( timer );
	}, [ value, delay ] );
	return settled;
}

/**
 * The new guest's first fields from the search text: an e-mail, a phone
 * (4+ digits, spaces allowed: `07 99 88 77 66`) or a name. Fields already
 * filled in are kept.
 *
 * @param {string} q      Search text.
 * @param {Object} fields Current fields.
 * @return {Object} Fields to set.
 */
export function startFrom( q, fields ) {
	const text = String( q || '' ).trim();
	if ( ! text ) {
		return {};
	}
	if ( text.includes( '@' ) ) {
		return { email: fields.email || text };
	}
	if ( text.replace( /\D/g, '' ).length >= 4 ) {
		return { phone: fields.phone || text };
	}
	return { last_name: fields.last_name || text };
}

/**
 * The chosen guest, with Change.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.guest    Guest (lookup shape).
 * @param {Function} props.onChange Pick another guest.
 * @return {JSX.Element} Card.
 */
function ChosenGuest( { guest, onChange } ) {
	const banned = 'banned' === guest.standing;
	return (
		<div className="space-y-3">
			{ banned ? (
				<div
					className="flex items-start gap-3 rounded-lg border border-destructive bg-destructive/5 p-3 text-sm text-destructive"
					role="alert"
				>
					<Ban
						className="mt-0.5 h-4 w-4 shrink-0"
						aria-hidden="true"
					/>
					<div>
						<p className="m-0 font-semibold">
							{ __(
								'This guest is banned',
								'radius-hotel-booking'
							) }
						</p>
						<p className="m-0">
							{ __(
								'A booking cannot be made for them. The guest record says why.',
								'radius-hotel-booking'
							) }
						</p>
					</div>
				</div>
			) : null }
			<div className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border p-3">
				<div className="flex min-w-0 items-center gap-3">
					<UserRound
						className="h-5 w-5 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<div className="min-w-0">
						<p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
							{ guest.name }
							{ banned ? (
								<StatusBadge domain="standing" value="banned" />
							) : null }
						</p>
						<p className="m-0 text-xs text-muted-foreground">
							{ [
								guest.reference,
								guest.phone,
								guest.email,
								guest.id_number_masked
									? `${ guest.id_type_label } ${ guest.id_number_masked }`
									: '',
							]
								.filter( Boolean )
								.join( ' · ' ) }
						</p>
					</div>
				</div>
				<Button
					type="button"
					variant="outline"
					size="sm"
					onClick={ onChange }
				>
					{ __( 'Change', 'radius-hotel-booking' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    `{ mode: 'find'|'new', guest: Object|null, fields: Object }`.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   props.errors   Server errors on the new guest's fields.
 * @return {JSX.Element} Step.
 */
export default function GuestStep( { value, onChange, errors = {} } ) {
	const [ q, setQ ] = useState( '' );
	const canCreate = 'locked' !== useAccess( 'guests.create' );
	const term = useDebounced( q, 300 );
	const lookup = useGuestLookup( term );
	const idTypes = lookup.data?.id_types || {};
	const results = term.trim().length >= 2 ? lookup.data?.guests || [] : [];

	if ( value.guest ) {
		return (
			<ChosenGuest
				guest={ value.guest }
				onChange={ () => onChange( { ...value, guest: null } ) }
			/>
		);
	}

	const set = ( field, next ) =>
		onChange( { ...value, fields: { ...value.fields, [ field ]: next } } );
	const input = ( name, props = {} ) => (
		<Input
			value={ value.fields[ name ] }
			onChange={ ( e ) => set( name, e.target.value ) }
			autoComplete="off"
			{ ...props }
		/>
	);

	if ( 'new' === value.mode ) {
		return (
			<div className="space-y-4">
				<div className="grid gap-4 sm:grid-cols-2">
					<Field
						label={ __( 'First name', 'radius-hotel-booking' ) }
						error={ errors.first_name }
						required
					>
						{ input( 'first_name', { maxLength: 100 } ) }
					</Field>
					<Field
						label={ __( 'Last name', 'radius-hotel-booking' ) }
						error={ errors.last_name }
						required
					>
						{ input( 'last_name', { maxLength: 100 } ) }
					</Field>
					<Field
						label={ __( 'Phone', 'radius-hotel-booking' ) }
						error={ errors.phone }
						required
					>
						{ input( 'phone', { type: 'tel', inputMode: 'tel' } ) }
					</Field>
					<Field
						label={ __( 'E-mail', 'radius-hotel-booking' ) }
						error={ errors.email }
						description={ __(
							'For the confirmation. Optional.',
							'radius-hotel-booking'
						) }
					>
						{ input( 'email', { type: 'email' } ) }
					</Field>
					<Field
						label={ __(
							'Identity document',
							'radius-hotel-booking'
						) }
						error={ errors.id_type }
					>
						<Select
							value={ value.fields.id_type || 'none' }
							onValueChange={ ( next ) =>
								set( 'id_type', 'none' === next ? '' : next )
							}
						>
							<SelectTrigger>
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								<SelectItem value="none">
									{ __(
										'Not shown',
										'radius-hotel-booking'
									) }
								</SelectItem>
								{ Object.entries( idTypes ).map(
									( [ key, label ] ) => (
										<SelectItem key={ key } value={ key }>
											{ label }
										</SelectItem>
									)
								) }
							</SelectContent>
						</Select>
					</Field>
					<Field
						label={ __(
							'Document number',
							'radius-hotel-booking'
						) }
						error={ errors.id_number }
					>
						{ input( 'id_number', {
							maxLength: 64,
							disabled: ! value.fields.id_type,
						} ) }
					</Field>
				</div>
				<p className="m-0 text-xs text-muted-foreground">
					{ __(
						'If this phone or e-mail is already on file, the booking goes to that guest.',
						'radius-hotel-booking'
					) }
				</p>
				<Button
					type="button"
					variant="ghost"
					size="sm"
					onClick={ () => onChange( { ...value, mode: 'find' } ) }
				>
					<Search className="h-4 w-4" aria-hidden="true" />
					{ __(
						'Find an existing guest instead',
						'radius-hotel-booking'
					) }
				</Button>
			</div>
		);
	}

	return (
		<div className="space-y-3">
			<div className="flex flex-wrap items-end gap-3">
				<Field
					label={ __( 'Find the guest', 'radius-hotel-booking' ) }
					description={ __(
						'Name, phone or e-mail — at least two characters.',
						'radius-hotel-booking'
					) }
					className="min-w-[14rem] flex-1"
				>
					<Input
						type="search"
						value={ q }
						onChange={ ( e ) => setQ( e.target.value ) }
						placeholder={ __(
							'e.g. Koné or 07 01 02',
							'radius-hotel-booking'
						) }
						autoComplete="off"
						aria-controls="rtbp-guest-results"
					/>
				</Field>
				{ canCreate ? (
					<Button
						type="button"
						variant="outline"
						onClick={ () =>
							onChange( {
								...value,
								mode: 'new',
								// Start the new guest from what was typed.
								fields: {
									...value.fields,
									...startFrom( q, value.fields ),
								},
							} )
						}
					>
						<UserPlus className="h-4 w-4" aria-hidden="true" />
						{ __( 'New guest', 'radius-hotel-booking' ) }
					</Button>
				) : null }
			</div>

			<div id="rtbp-guest-results" aria-live="polite">
				{ term.trim().length >= 2 &&
				lookup.isFetching &&
				! results.length ? (
					<p className="m-0 text-sm text-muted-foreground">
						{ __( 'Searching…', 'radius-hotel-booking' ) }
					</p>
				) : null }
				{ term.trim().length >= 2 &&
				! lookup.isFetching &&
				! results.length ? (
					<p className="m-0 text-sm text-muted-foreground">
						{ __(
							'No guest found. Add them as a new guest.',
							'radius-hotel-booking'
						) }
					</p>
				) : null }
				{ results.length ? (
					<ul className="m-0 list-none space-y-2 p-0">
						{ results.map( ( guest ) => (
							<li key={ guest.id }>
								<button
									type="button"
									onClick={ () =>
										onChange( { ...value, guest } )
									}
									className="flex w-full flex-wrap items-center justify-between gap-2 rounded-lg border border-border bg-white p-3 text-left hover:border-primary hover:bg-primary-softer focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
								>
									<span className="min-w-0">
										<span className="flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
											{ guest.name }
											{ 'banned' === guest.standing ? (
												<StatusBadge
													domain="standing"
													value="banned"
												/>
											) : null }
										</span>
										<span className="block text-xs text-muted-foreground">
											{ [
												guest.phone,
												guest.email,
												guest.reference,
											]
												.filter( Boolean )
												.join( ' · ' ) }
										</span>
									</span>
									<span className="text-xs text-muted-foreground">
										{ sprintf(
											/* translators: %d: number of past stays. */
											_n(
												'%d stay',
												'%d stays',
												guest.stays_count,
												'radius-hotel-booking'
											),
											guest.stays_count
										) }
									</span>
								</button>
							</li>
						) ) }
					</ul>
				) : null }
			</div>
		</div>
	);
}
