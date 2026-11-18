/**
 * The guest of the booking (2.7–2.9): find an existing guest by name, phone
 * or e-mail, or add a new one (name, phone, optional e-mail and identity
 * document). A banned guest shows a red banner and the booking cannot be
 * confirmed (the server refuses it too). A new guest whose phone or e-mail
 * is already on file is matched to that guest when the booking is made.
 */
import { useEffect, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	Ban,
	BedDouble,
	CalendarClock,
	ExternalLink,
	IdCard,
	Loader2,
	Mail,
	Phone,
	Search,
	UserPlus,
	UserRound,
} from 'lucide-react';

import { Field } from '@/components/common/Form';
import Money from '@/components/common/Money';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Popover,
	PopoverAnchor,
	PopoverContent,
} from '@/components/ui/popover';
import PhoneField from '@/components/common/Phone';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { useAccess } from '@/lib/access';
import { invalidEmailMessage, isEmailValid } from '@/lib/email';
import { formatDate, formatMoney } from '@/lib/format';
import { useGuestHistory, useGuestLookup } from './api';

export const EMPTY_GUEST = {
	first_name: '',
	last_name: '',
	phone: '',
	email: '',
	id_type: '',
	id_number: '',
};

/**
 * A name's initials for an avatar: "Abdallah Konate" → "AK".
 *
 * @param {string} name Name.
 * @return {string} Up to two letters.
 */
const initialsOf = ( name ) =>
	( name || '' )
		.split( /\s+/ )
		.filter( Boolean )
		.slice( 0, 2 )
		.map( ( word ) => word[ 0 ] )
		.join( '' )
		.toUpperCase();

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
 * The chosen guest: everything the desk needs before booking — contact
 * details, the identity document (masked), stays, the last stay, what they
 * still owe, a stay in progress and their latest bookings, with a link to
 * their record. The history needs the guest list's access (`page.guests`);
 * without it the card shows what the lookup gave.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.guest    Guest (lookup shape).
 * @param {Function} props.onChange Pick another guest.
 * @return {JSX.Element} Card.
 */
function ChosenGuest( { guest, onChange } ) {
	const banned = 'banned' === guest.standing;
	const canSeeHistory = 'locked' !== useAccess( 'page.guests' );
	const history = useGuestHistory( guest.id, canSeeHistory );
	const stays = history.data || [];
	const live = stays.filter( ( stay ) => 'cancelled' !== stay.status );
	const owed = live.reduce(
		( sum, stay ) => sum + Math.max( 0, Number( stay.balance_due ) || 0 ),
		0
	);
	const spent = live.reduce(
		( sum, stay ) =>
			sum +
			Math.max(
				0,
				( Number( stay.total ) || 0 ) -
					( Number( stay.balance_due ) || 0 )
			),
		0
	);
	const inHouse = live.find( ( stay ) => 'checked_in' === stay.status );
	const initials = initialsOf( guest.name );
	const stayCount = history.data ? live.length : guest.stays_count;

	const row = ( Icon, label, value ) =>
		value ? (
			<div className="flex min-w-0 items-start gap-2.5">
				<Icon
					className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground"
					aria-hidden="true"
				/>
				<div className="min-w-0">
					<p className="m-0 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
						{ label }
					</p>
					<div className="break-words text-sm text-heading">
						{ value }
					</div>
				</div>
			</div>
		) : null;

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

			<div className="overflow-hidden rounded-xl border border-border">
				<div className="flex items-start gap-3 bg-muted/50 p-3">
					<span
						className="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-bold text-primary-foreground"
						aria-hidden="true"
					>
						{ initials || (
							<UserRound className="h-5 w-5" aria-hidden="true" />
						) }
					</span>
					<div className="min-w-0 flex-1">
						<p className="m-0 flex flex-wrap items-center gap-2 text-base font-bold leading-tight text-heading">
							{ guest.name }
							{ banned ? (
								<StatusBadge domain="standing" value="banned" />
							) : null }
						</p>
						<p className="m-0 mt-0.5 text-xs text-muted-foreground">
							{ [
								guest.reference,
								guest.stays_count
									? sprintf(
											/* translators: %d: number of stays. */
											_n(
												'%d stay',
												'%d stays',
												stayCount,
												'radius-hotel-booking'
											),
											stayCount
									  )
									: __(
											'First stay',
											'radius-hotel-booking'
									  ),
							]
								.filter( Boolean )
								.join( ' · ' ) }
						</p>
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

				<div className="grid gap-3 border-t border-border p-3">
					{ row(
						Phone,
						__( 'Phone', 'radius-hotel-booking' ),
						guest.phone ? (
							<a
								href={ `tel:${ guest.phone.replace(
									/[^\d+]/g,
									''
								) }` }
								className="text-heading no-underline hover:text-primary hover:underline"
							>
								{ guest.phone }
							</a>
						) : null
					) }
					{ row(
						Mail,
						__( 'E-mail', 'radius-hotel-booking' ),
						guest.email ? (
							<a
								href={ `mailto:${ guest.email }` }
								className="text-heading no-underline hover:text-primary hover:underline"
							>
								{ guest.email }
							</a>
						) : null
					) }
					{ row(
						IdCard,
						__( 'Identity document', 'radius-hotel-booking' ),
						guest.id_number_masked
							? `${ guest.id_type_label || '' } ${
									guest.id_number_masked
							  }`.trim()
							: null
					) }
					{ ! guest.id_number_masked && guest.id ? (
						<p className="m-0 flex items-center gap-2 rounded-md bg-warning-soft px-2.5 py-1.5 text-xs text-heading">
							<AlertTriangle
								className="h-3.5 w-3.5 shrink-0 text-warning"
								aria-hidden="true"
							/>
							{ __(
								'No identity document on file: ask for it at check-in.',
								'radius-hotel-booking'
							) }
						</p>
					) : null }
					{ row(
						CalendarClock,
						__( 'Last stay', 'radius-hotel-booking' ),
						guest.last_stay_at
							? formatDate( guest.last_stay_at )
							: null
					) }
				</div>

				{ canSeeHistory && history.data ? (
					<div className="grid grid-cols-2 border-t border-border">
						<div className="p-3">
							<p className="m-0 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
								{ __( 'Paid so far', 'radius-hotel-booking' ) }
							</p>
							<p className="m-0 text-sm font-semibold text-heading">
								<Money value={ spent } />
							</p>
						</div>
						<div className="border-l border-border p-3">
							<p className="m-0 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
								{ __( 'Still owes', 'radius-hotel-booking' ) }
							</p>
							<p
								className={
									owed > 0
										? 'm-0 text-sm font-semibold text-destructive'
										: 'm-0 text-sm font-semibold text-success'
								}
							>
								<Money value={ owed } />
							</p>
						</div>
					</div>
				) : null }

				{ inHouse ? (
					<p className="m-0 flex items-center gap-2 border-t border-border bg-success-soft px-3 py-2 text-xs font-medium text-heading">
						<BedDouble
							className="h-3.5 w-3.5 shrink-0 text-success"
							aria-hidden="true"
						/>
						{ sprintf(
							/* translators: 1: room numbers, 2: booking reference. */
							_n(
								'Staying now: room %1$s (%2$s)',
								'Staying now: rooms %1$s (%2$s)',
								inHouse.lines.length,
								'radius-hotel-booking'
							),
							inHouse.lines
								.map( ( line ) => line.room_number )
								.join( ', ' ),
							inHouse.reference
						) }
					</p>
				) : null }

				{ canSeeHistory && stays.length ? (
					<div className="border-t border-border p-3">
						<p className="m-0 mb-2 text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
							{ __( 'Latest bookings', 'radius-hotel-booking' ) }
						</p>
						<ul className="m-0 list-none space-y-2 p-0">
							{ stays.slice( 0, 3 ).map( ( stay ) => (
								<li
									key={ stay.id }
									className="flex items-start justify-between gap-2 text-xs"
								>
									<span className="min-w-0">
										<a
											href={ `#/bookings/${ stay.id }` }
											className="font-semibold text-heading no-underline hover:text-primary hover:underline"
										>
											{ stay.reference }
										</a>
										<span className="block text-muted-foreground">
											{ [
												stay.arrival
													? formatDate( stay.arrival )
													: '',
												stay.lines
													.map(
														( line ) =>
															line.room_number
													)
													.join( ', ' ),
											]
												.filter( Boolean )
												.join( ' · ' ) }
										</span>
									</span>
									<span className="flex shrink-0 flex-col items-end gap-1">
										<StatusBadge
											domain="stay"
											value={ stay.status }
										/>
										{ Number( stay.balance_due ) > 0 &&
										'cancelled' !== stay.status ? (
											<span className="font-semibold text-destructive">
												{ sprintf(
													/* translators: %s: amount due. */
													__(
														'Due %s',
														'radius-hotel-booking'
													),
													formatMoney(
														stay.balance_due
													)
												) }
											</span>
										) : null }
									</span>
								</li>
							) ) }
						</ul>
					</div>
				) : null }

				{ canSeeHistory ? (
					<a
						href={ `#/guests/${ guest.id }` }
						target="_blank"
						rel="noopener noreferrer"
						className="flex items-center justify-center gap-1.5 border-t border-border px-3 py-2 text-xs font-semibold text-primary no-underline hover:bg-muted/50"
					>
						{ __(
							'Open the guest record',
							'radius-hotel-booking'
						) }
						<ExternalLink
							className="h-3.5 w-3.5"
							aria-hidden="true"
						/>
					</a>
				) : null }
			</div>
		</div>
	);
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    `{ mode: 'find'|'new', guest: Object|null, fields: Object }`.
 * @param {Function} props.onChange Called with the new value.
 * @param {Object}   props.errors   Server errors on the new guest's fields.
 * @param {boolean}  props.compact  One column (the desk's side panel).
 * @return {JSX.Element} Step.
 */
export default function GuestStep( {
	value,
	onChange,
	errors = {},
	compact = false,
} ) {
	const [ q, setQ ] = useState( '' );
	const [ open, setOpen ] = useState( false );
	const [ active, setActive ] = useState( 0 );
	const anchorRef = useRef( null );
	const [ emailLeft, setEmailLeft ] = useState( false );
	const canCreate = 'locked' !== useAccess( 'guests.create' );
	const term = useDebounced( q, 300 );
	const lookup = useGuestLookup( term );
	const idTypes = lookup.data?.id_types || {};
	const results = term.trim().length >= 2 ? lookup.data?.guests || [] : [];

	// A new search starts from its first match.
	useEffect( () => setActive( 0 ), [ term ] );

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
				<div
					className={
						compact ? 'grid gap-3' : 'grid gap-4 sm:grid-cols-2'
					}
				>
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
						<PhoneField
							value={ value.fields.phone }
							onChange={ ( phone ) => set( 'phone', phone ) }
						/>
					</Field>
					<Field
						label={ __( 'E-mail', 'radius-hotel-booking' ) }
						error={
							errors.email ||
							( emailLeft && ! isEmailValid( value.fields.email )
								? invalidEmailMessage()
								: '' )
						}
						description={ __(
							'For the confirmation. Optional.',
							'radius-hotel-booking'
						) }
					>
						{ input( 'email', {
							type: 'email',
							// Checked once left, then live while it is fixed.
							onBlur: () => setEmailLeft( true ),
						} ) }
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

	const searching = term.trim().length >= 2;
	const pick = ( guest ) => {
		setOpen( false );
		onChange( { ...value, guest } );
	};
	const onKeyDown = ( event ) => {
		if ( 'ArrowDown' === event.key || 'ArrowUp' === event.key ) {
			event.preventDefault();
			setOpen( true );
			if ( results.length ) {
				const step = 'ArrowDown' === event.key ? 1 : -1;
				setActive(
					( index ) =>
						( index + step + results.length ) % results.length
				);
			}
		} else if ( 'Enter' === event.key && open && results[ active ] ) {
			event.preventDefault();
			pick( results[ active ] );
		} else if ( 'Escape' === event.key && open ) {
			event.preventDefault();
			setOpen( false );
		}
	};

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
					{ /* An advanced select: the matches open in a list under
					   the field, chosen with the mouse or the arrow keys. */ }
					<Popover
						open={ open && searching }
						onOpenChange={ setOpen }
					>
						<PopoverAnchor asChild>
							<div ref={ anchorRef } className="relative">
								<Search
									className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
									aria-hidden="true"
								/>
								<Input
									type="search"
									role="combobox"
									value={ q }
									onChange={ ( e ) => {
										setQ( e.target.value );
										setOpen( true );
									} }
									onFocus={ () => setOpen( true ) }
									onClick={ () => setOpen( true ) }
									onKeyDown={ onKeyDown }
									placeholder={ __(
										'e.g. Koné or 07 01 02',
										'radius-hotel-booking'
									) }
									autoComplete="off"
									aria-expanded={ open && searching }
									aria-controls="rtbp-guest-results"
									aria-autocomplete="list"
									aria-activedescendant={
										open && results[ active ]
											? `rtbp-guest-option-${ results[ active ].id }`
											: undefined
									}
									className="pl-9"
								/>
								{ searching && lookup.isFetching ? (
									<Loader2
										className="absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 animate-spin text-muted-foreground"
										aria-hidden="true"
									/>
								) : null }
							</div>
						</PopoverAnchor>
						<PopoverContent
							align="start"
							sideOffset={ 6 }
							className="w-[var(--radix-popover-trigger-width)] min-w-[18rem] overflow-hidden p-0"
							onOpenAutoFocus={ ( event ) =>
								event.preventDefault()
							}
							onCloseAutoFocus={ ( event ) =>
								event.preventDefault()
							}
							onInteractOutside={ ( event ) => {
								// Clicks in the field keep the list open.
								if (
									anchorRef.current?.contains( event.target )
								) {
									event.preventDefault();
								}
							} }
						>
							<div
								id="rtbp-guest-results"
								role="listbox"
								aria-label={ __(
									'Guests found',
									'radius-hotel-booking'
								) }
								className="max-h-80 overflow-y-auto overscroll-contain p-1"
							>
								{ ! results.length ? (
									<p className="m-0 px-3 py-4 text-center text-sm text-muted-foreground">
										{ lookup.isFetching
											? __(
													'Searching…',
													'radius-hotel-booking'
											  )
											: __(
													'No guest found. Add them as a new guest.',
													'radius-hotel-booking'
											  ) }
									</p>
								) : (
									results.map( ( guest, index ) => (
										<div
											key={ guest.id }
											id={ `rtbp-guest-option-${ guest.id }` }
											role="option"
											aria-selected={ index === active }
											tabIndex={ -1 }
											ref={ ( node ) =>
												index === active &&
												node?.scrollIntoView( {
													block: 'nearest',
												} )
											}
											onMouseEnter={ () =>
												setActive( index )
											}
											onMouseDown={ ( event ) =>
												event.preventDefault()
											}
											onClick={ () => pick( guest ) }
											className={ `flex cursor-pointer items-center gap-3 rounded-md px-2.5 py-2 ${
												index === active
													? 'bg-primary-softer'
													: ''
											}` }
										>
											<span
												className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-soft text-xs font-bold uppercase text-primary"
												aria-hidden="true"
											>
												{ initialsOf( guest.name ) }
											</span>
											<span className="min-w-0 flex-1">
												<span className="flex items-center gap-2 text-sm font-semibold text-heading">
													<span className="truncate">
														{ guest.name }
													</span>
													{ 'banned' ===
													guest.standing ? (
														<StatusBadge
															domain="standing"
															value="banned"
														/>
													) : null }
												</span>
												<span className="block truncate text-xs text-muted-foreground">
													{ [
														guest.phone,
														guest.email,
														guest.reference,
													]
														.filter( Boolean )
														.join( ' · ' ) }
												</span>
											</span>
											<span className="shrink-0 rounded-full bg-muted px-2 py-0.5 text-[11px] font-semibold text-muted-foreground">
												{ guest.stays_count > 0
													? sprintf(
															/* translators: %d: number of past stays. */
															_n(
																'%d stay',
																'%d stays',
																guest.stays_count,
																'radius-hotel-booking'
															),
															guest.stays_count
													  )
													: __(
															'New',
															'radius-hotel-booking'
													  ) }
											</span>
										</div>
									) )
								) }
							</div>
						</PopoverContent>
					</Popover>
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
		</div>
	);
}
