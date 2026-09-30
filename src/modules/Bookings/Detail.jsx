/**
 * One booking (`#/bookings/:id`, features 3.1, 3.2): a header card with the
 * reference, the stay and payment status, when and by whom it was made;
 * the room lines (each with its own status, window, guests, room and frozen
 * price); the money summary; the guest; the booking's note; and the panels
 * add-ons register on `rtbp.booking.panels` (Pro: History). Two columns on
 * a desktop, one on a phone. Actions (3.5–3.10): approve / decline / cancel
 * the booking in the header; per room, check in (optionally into another
 * room) or check out as a button, the rest in a menu; each shown only when
 * the status allows it and the viewer holds the key.
 */
import { useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	ArrowLeft,
	BedDouble,
	CalendarCheck,
	Check,
	LogIn,
	LogOut,
	Mail,
	MoreHorizontal,
	Plus,
	Phone,
	X,
} from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import EmptyState from '@/components/common/EmptyState';
import NotesPanel from '@/components/common/NotesPanel';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import { PriceBreakdownPopover } from '@/components/common/PriceBreakdown';
import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Skeleton } from '@/components/ui/skeleton';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { useAccess } from '@/lib/access';
import { formatDateRange, formatDateTime, formatMoney } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { useBooking, useBookingAction, useLineChange } from './api';
import CheckInDialog from './components/CheckInDialog';
import EditBookingGuest from './components/EditBookingGuest';
import LineEditor from './components/LineEditor';

/**
 * Where a booking came from, in words.
 *
 * @param {string} source Source.
 * @return {string} Label.
 */
const sourceLabel = ( source ) =>
	( {
		desk: __( 'At the desk', 'radius-hotel-booking' ),
		web: __( 'On the website', 'radius-hotel-booking' ),
		import: __( 'Imported', 'radius-hotel-booking' ),
		ical: __( 'From a calendar feed', 'radius-hotel-booking' ),
	} )[ source ] || source;

/**
 * Guests on a line: `2 adults · 1 child`.
 *
 * @param {Object} line Line.
 * @return {string} Text.
 */
const guestsLabel = ( line ) =>
	[
		sprintf(
			/* translators: %d: number of adults. */
			_n( '%d adult', '%d adults', line.adults, 'radius-hotel-booking' ),
			line.adults
		),
		line.children
			? sprintf(
					/* translators: %d: number of children. */
					_n(
						'%d child',
						'%d children',
						line.children,
						'radius-hotel-booking'
					),
					line.children
			  )
			: '',
	]
		.filter( Boolean )
		.join( ' · ' );

/**
 * An action in words.
 *
 * @param {string} action Action.
 * @return {string} Label.
 */
const actionLabel = ( action ) =>
	( {
		approve: __( 'Approve', 'radius-hotel-booking' ),
		decline: __( 'Decline', 'radius-hotel-booking' ),
		cancel: __( 'Cancel', 'radius-hotel-booking' ),
		check_in: __( 'Check in', 'radius-hotel-booking' ),
		check_out: __( 'Check out', 'radius-hotel-booking' ),
		no_show: __( 'No-show', 'radius-hotel-booking' ),
	} )[ action ] || action;

/**
 * The question asked before a move on one room.
 *
 * @param {string} action Action.
 * @param {string} room   Room number.
 * @return {string} Question.
 */
const lineQuestion = ( action, room ) =>
	sprintf(
		{
			/* translators: %s: room number. */
			decline: __( 'Decline room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			cancel: __( 'Cancel room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			check_out: __( 'Check out room %s?', 'radius-hotel-booking' ),
			/* translators: %s: room number. */
			no_show: __( 'Mark room %s as a no-show?', 'radius-hotel-booking' ),
		}[ action ] || '%s',
		room
	);

/**
 * One room line.
 *
 * @param {Object}   props          Props.
 * @param {Object}   props.line     Line.
 * @param {string[]} props.actions  The actions this viewer may take on it.
 * @param {Function} props.onAction Called with `( action, line )`.
 * @return {JSX.Element} Line.
 */
function Line( { line, actions = [], onAction } ) {
	// The main move of the stay as a button; the rest in a menu.
	const primary = [ 'check_in', 'check_out' ].find( ( a ) =>
		actions.includes( a )
	);
	// In the menu: change first, remove last.
	const others = [
		...actions.filter( ( a ) => 'line_edit' === a ),
		...actions.filter(
			( a ) =>
				a !== primary && ! [ 'line_edit', 'line_remove' ].includes( a )
		),
		...actions.filter( ( a ) => 'line_remove' === a ),
	];
	const ended = [ 'cancelled', 'declined', 'no_show' ].includes(
		line.status
	);
	return (
		<li
			className={ cn(
				'space-y-2 rounded-lg border border-border p-3',
				ended && 'bg-muted'
			) }
		>
			<div className="flex flex-wrap items-start justify-between gap-2">
				<div className="flex min-w-0 items-start gap-3">
					<BedDouble
						className="mt-0.5 h-5 w-5 shrink-0 text-muted-foreground"
						aria-hidden="true"
					/>
					<div className="min-w-0">
						<p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
							{ sprintf(
								/* translators: %s: room number. */
								__( 'Room %s', 'radius-hotel-booking' ),
								line.room_number
							) }
							<StatusBadge domain="stay" value={ line.status } />
							{ 'removed' === line.room_state ? (
								<span className="text-xs font-normal text-muted-foreground">
									{ __(
										'(room since removed)',
										'radius-hotel-booking'
									) }
								</span>
							) : null }
						</p>
						<p className="m-0 text-sm text-muted-foreground">
							{ [
								line.room_type,
								line.rate_plan_name,
								line.floor_name,
							]
								.filter( Boolean )
								.join( ' · ' ) }
						</p>
					</div>
				</div>
				<div className="text-right">
					<p
						className={ cn(
							'm-0 text-sm font-semibold text-heading',
							ended && 'text-muted-foreground',
							// A no-show is still charged; a declined or cancelled room is not.
							[ 'cancelled', 'declined' ].includes(
								line.status
							) && 'line-through'
						) }
					>
						<Money value={ line.total } />
					</p>
					<PriceBreakdownPopover
						quote={ { total: line.total, steps: line.price_steps } }
						label={ __( 'Price details', 'radius-hotel-booking' ) }
					/>
				</div>
			</div>
			<p className="m-0 flex flex-wrap gap-x-4 gap-y-1 pl-8 text-xs text-muted-foreground">
				<span>{ formatDateRange( line.start, line.end ) }</span>
				<span>{ guestsLabel( line ) }</span>
				{ line.checked_in_at ? (
					<span>
						{ sprintf(
							/* translators: 1: date and time, 2: staff name. */
							__( 'In %1$s by %2$s', 'radius-hotel-booking' ),
							formatDateTime( line.checked_in_at ),
							line.checked_in_by || '—'
						) }
					</span>
				) : null }
				{ line.checked_out_at ? (
					<span>
						{ sprintf(
							/* translators: 1: date and time, 2: staff name. */
							__( 'Out %1$s by %2$s', 'radius-hotel-booking' ),
							formatDateTime( line.checked_out_at ),
							line.checked_out_by || '—'
						) }
					</span>
				) : null }
			</p>
			{ primary || others.length ? (
				<div className="flex flex-wrap justify-end gap-2 pl-8">
					{ others.length ? (
						<DropdownMenu>
							<DropdownMenuTrigger asChild>
								<Button
									type="button"
									variant="ghost"
									size="sm"
									aria-label={ sprintf(
										/* translators: %s: room number. */
										__(
											'More for room %s',
											'radius-hotel-booking'
										),
										line.room_number
									) }
								>
									<MoreHorizontal
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</Button>
							</DropdownMenuTrigger>
							<DropdownMenuContent
								align="end"
								className="rtbp-root"
							>
								{ others.map( ( action ) => (
									<DropdownMenuItem
										key={ action }
										onSelect={ () =>
											onAction( action, line )
										}
										className={
											[
												'cancel',
												'decline',
												'no_show',
												'line_remove',
											].includes( action )
												? 'text-destructive'
												: ''
										}
									>
										{ 'cancel' === action
											? __(
													'Cancel this room',
													'radius-hotel-booking'
											  )
											: 'decline' === action
											? __(
													'Decline this room',
													'radius-hotel-booking'
											  )
											: 'approve' === action
											? __(
													'Approve this room',
													'radius-hotel-booking'
											  )
											: 'line_edit' === action
											? __(
													'Change room, rate or dates',
													'radius-hotel-booking'
											  )
											: 'line_remove' === action
											? __(
													'Remove this room',
													'radius-hotel-booking'
											  )
											: actionLabel( action ) }
									</DropdownMenuItem>
								) ) }
							</DropdownMenuContent>
						</DropdownMenu>
					) : null }
					{ primary ? (
						<Button
							type="button"
							size="sm"
							variant={
								'check_in' === primary ? 'default' : 'outline'
							}
							onClick={ () => onAction( primary, line ) }
						>
							{ 'check_in' === primary ? (
								<LogIn className="h-4 w-4" aria-hidden="true" />
							) : (
								<LogOut
									className="h-4 w-4"
									aria-hidden="true"
								/>
							) }
							{ actionLabel( primary ) }
						</Button>
					) : null }
				</div>
			) : null }
		</li>
	);
}

/**
 * The money summary (3.2): only the rows that carry something, then total,
 * paid and balance.
 *
 * @param {Object} props       Props.
 * @param {Object} props.money `{ subtotal, discount_total, tax_total, total, paid_total, balance_due }`.
 * @return {JSX.Element} Summary.
 */
function MoneySummary( { money } ) {
	const row = ( label, value, className = '' ) => (
		<div
			className={ cn(
				'flex items-baseline justify-between gap-3',
				className
			) }
		>
			<dt className="text-sm text-muted-foreground">{ label }</dt>
			<dd className="m-0 text-sm text-heading">
				<Money value={ value } />
			</dd>
		</div>
	);
	return (
		<dl className="m-0 space-y-2">
			{ row( __( 'Rooms', 'radius-hotel-booking' ), money.subtotal ) }
			{ Number( money.discount_total )
				? row(
						__( 'Discount', 'radius-hotel-booking' ),
						-Math.abs( money.discount_total )
				  )
				: null }
			{ Number( money.tax_total )
				? row( __( 'Tax', 'radius-hotel-booking' ), money.tax_total )
				: null }
			{ row(
				__( 'Total', 'radius-hotel-booking' ),
				money.total,
				'border-t border-border pt-2 font-semibold [&_dd]:font-semibold [&_dt]:text-heading'
			) }
			{ row( __( 'Paid', 'radius-hotel-booking' ), money.paid_total ) }
			<div className="flex items-baseline justify-between gap-3 border-t border-border pt-2">
				<dt className="text-sm font-semibold text-heading">
					{ __( 'Balance due', 'radius-hotel-booking' ) }
				</dt>
				<dd
					className={ cn(
						'm-0 text-base font-semibold',
						Number( money.balance_due ) > 0
							? 'text-warning'
							: 'text-success'
					) }
				>
					<Money value={ money.balance_due } />
				</dd>
			</div>
		</dl>
	);
}

/**
 * @return {JSX.Element} Screen.
 */
export default function BookingDetail() {
	const { id: param } = useParams();
	const id = Number( param ) || 0;
	const { data: booking, isPending, error, refetch } = useBooking( id );
	const canSeeGuest = useAccess( 'page.guests' ) !== 'locked';
	const guestEditLevel = useAccess( 'guests.edit' );
	const canEditGuest = canSeeGuest && 'locked' !== guestEditLevel;
	const act = useBookingAction( id );
	// Each action shows only when the viewer holds its key (a PIN level still shows it).
	const levels = {
		approve: useAccess( 'bookings.approve' ),
		decline: useAccess( 'bookings.decline' ),
		cancel: useAccess( 'bookings.cancel' ),
		check_in: useAccess( 'bookings.check_in' ),
		check_out: useAccess( 'bookings.check_out' ),
		no_show: useAccess( 'bookings.no_show' ),
		line_add: useAccess( 'bookings.line_add' ),
		line_edit: useAccess( 'bookings.line_edit' ),
		line_remove: useAccess( 'bookings.line_remove' ),
	};
	const allowed = ( list ) =>
		( list || [] ).filter( ( action ) => 'locked' !== levels[ action ] );
	// The dialog open: `{ action, line? }`.
	const [ dialog, setDialog ] = useState( null );
	// The room editor: `{ line }` (line null = add a room).
	const [ editor, setEditor ] = useState( null );
	const lineChange = useLineChange( id );

	const run = ( action, { line = null, reason = '', roomId = 0 } = {} ) =>
		act
			.mutateAsync( { action, lineId: line?.id, reason, roomId } )
			.then( ( { message } ) => {
				toast.success( message );
			} )
			.catch( ( err ) => {
				toastError( err );
				throw err;
			} );

	const onLineAction = ( action, line ) => {
		if ( 'approve' === action ) {
			run( 'approve', { line } ).catch( () => {} );
			return;
		}
		if ( 'line_edit' === action ) {
			setEditor( { line } );
			return;
		}
		setDialog( { action, line } );
	};
	const [ tab, setTab ] = useState( '' );

	// Side panels: the booking's notes (3.13), the guest's notes (3.15), then
	// the ones add-ons register on `rtbp.booking.panels` (Pro: History).
	const panels = useMemo( () => {
		if ( ! booking ) {
			return [];
		}
		const base = [
			{
				key: 'notes',
				label: __( 'Notes', 'radius-hotel-booking' ),
				order: 10,
				render: () => <NotesPanel type="booking" id={ booking.id } />,
			},
		];
		if ( booking.guest && canSeeGuest ) {
			base.push( {
				key: 'guest-notes',
				label: __( 'Guest notes', 'radius-hotel-booking' ),
				order: 20,
				render: () => (
					<NotesPanel type="guest" id={ booking.guest.id } />
				),
			} );
		}
		const filtered = applyFilters( 'rtbp.booking.panels', base, {
			booking,
		} );
		return ( Array.isArray( filtered ) ? filtered : base )
			.filter( ( panel ) => panel && panel.key && panel.render )
			.sort( ( a, b ) => ( a.order ?? 50 ) - ( b.order ?? 50 ) );
	}, [ booking, canSeeGuest ] );

	// The header's buttons: the whole booking's status moves.
	const headerActions = allowed( booking?.actions ).filter( ( action ) =>
		[ 'approve', 'decline', 'cancel' ].includes( action )
	);

	const back = (
		<Link
			to="/bookings"
			className="inline-flex items-center gap-1.5 text-sm font-medium text-muted-foreground no-underline hover:text-heading"
		>
			<ArrowLeft className="h-4 w-4" aria-hidden="true" />
			{ __( 'Bookings', 'radius-hotel-booking' ) }
		</Link>
	);

	if ( isPending ) {
		return (
			<div className="mx-auto max-w-6xl space-y-4">
				{ back }
				<Skeleton className="h-28 w-full rounded-xl" />
				<Skeleton className="h-64 w-full rounded-xl" />
			</div>
		);
	}
	if ( error || ! booking ) {
		return (
			<div className="mx-auto max-w-6xl space-y-4">
				{ back }
				<Panel>
					<EmptyState
						icon={ CalendarCheck }
						title={
							404 === error?.status
								? __(
										'This booking does not exist',
										'radius-hotel-booking'
								  )
								: __(
										'The booking could not be loaded',
										'radius-hotel-booking'
								  )
						}
						description={
							404 === error?.status ? '' : error?.message
						}
						action={
							404 === error?.status ? null : (
								<Button
									variant="outline"
									onClick={ () => refetch() }
								>
									{ __(
										'Try again',
										'radius-hotel-booking'
									) }
								</Button>
							)
						}
						className="border-0"
					/>
				</Panel>
			</div>
		);
	}

	const guest = booking.guest;
	const activeTab = panels.some( ( p ) => p.key === tab )
		? tab
		: panels[ 0 ]?.key;

	return (
		<div className="mx-auto max-w-6xl space-y-4">
			{ back }

			<Panel>
				<div className="flex flex-wrap items-start justify-between gap-3">
					<div className="min-w-0 space-y-1">
						<div className="flex flex-wrap items-center gap-2">
							<h2 className="m-0 text-xl font-semibold text-heading">
								{ booking.reference }
							</h2>
							<StatusBadge
								domain="stay"
								value={ booking.status }
							/>
							<StatusBadge
								domain="payment"
								value={ booking.payment_status }
							/>
						</div>
						<p className="m-0 text-sm text-muted-foreground">
							{ [
								booking.created_at
									? sprintf(
											/* translators: %s: date and time. */
											__(
												'Made %s',
												'radius-hotel-booking'
											),
											formatDateTime( booking.created_at )
									  )
									: '',
								booking.created_by
									? sprintf(
											/* translators: %s: staff name. */
											__(
												'by %s',
												'radius-hotel-booking'
											),
											booking.created_by
									  )
									: '',
								sourceLabel( booking.source ),
								sprintf(
									/* translators: %d: number of rooms. */
									_n(
										'%d room',
										'%d rooms',
										booking.rooms_count,
										'radius-hotel-booking'
									),
									booking.rooms_count
								),
							]
								.filter( Boolean )
								.join( ' · ' ) }
						</p>
						{ booking.cancelled_reason ? (
							<p className="m-0 text-sm text-destructive">
								{ sprintf(
									/* translators: %s: reason. */
									__( 'Reason: %s', 'radius-hotel-booking' ),
									booking.cancelled_reason
								) }
							</p>
						) : null }
					</div>
					{ headerActions.length ? (
						<div className="flex flex-wrap gap-2">
							{ headerActions.includes( 'approve' ) ? (
								<Button
									type="button"
									disabled={ act.isPending }
									onClick={ () =>
										run( 'approve' ).catch( () => {} )
									}
								>
									<Check
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __( 'Approve', 'radius-hotel-booking' ) }
								</Button>
							) : null }
							{ headerActions.includes( 'decline' ) ? (
								<Button
									type="button"
									variant="outline"
									className="text-destructive"
									onClick={ () =>
										setDialog( { action: 'decline' } )
									}
								>
									{ __( 'Decline', 'radius-hotel-booking' ) }
								</Button>
							) : null }
							{ headerActions.includes( 'cancel' ) ? (
								<Button
									type="button"
									variant="outline"
									className="text-destructive"
									onClick={ () =>
										setDialog( { action: 'cancel' } )
									}
								>
									<X className="h-4 w-4" aria-hidden="true" />
									{ __(
										'Cancel booking',
										'radius-hotel-booking'
									) }
								</Button>
							) : null }
						</div>
					) : null }
				</div>
			</Panel>

			<div className="grid gap-4 lg:grid-cols-3">
				<div className="space-y-4 lg:col-span-2">
					<Panel
						title={ __( 'Rooms', 'radius-hotel-booking' ) }
						actions={
							allowed( booking.actions ).includes(
								'line_add'
							) ? (
								<Button
									type="button"
									variant="outline"
									size="sm"
									onClick={ () =>
										setEditor( { line: null } )
									}
								>
									<Plus
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __(
										'Add a room',
										'radius-hotel-booking'
									) }
								</Button>
							) : null
						}
					>
						<ul className="m-0 list-none space-y-2 p-0">
							{ booking.lines.map( ( line ) => (
								<Line
									key={ line.id }
									line={ line }
									actions={ allowed( line.actions ) }
									onAction={ onLineAction }
								/>
							) ) }
						</ul>
					</Panel>
					<Panel title={ __( 'Money', 'radius-hotel-booking' ) }>
						<MoneySummary money={ booking.money } />
					</Panel>
				</div>

				<div className="space-y-4">
					<Panel
						title={ __( 'Guest', 'radius-hotel-booking' ) }
						actions={
							guest && canEditGuest ? (
								<EditBookingGuest guestId={ guest.id } />
							) : null
						}
					>
						{ guest ? (
							<div className="space-y-3">
								<div>
									<p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
										{ canSeeGuest ? (
											<Link
												to={ `/guests/${ guest.id }` }
												className="text-heading no-underline hover:underline"
											>
												{ guest.name }
											</Link>
										) : (
											guest.name
										) }
										{ 'banned' === guest.standing ? (
											<StatusBadge
												domain="standing"
												value="banned"
											/>
										) : null }
									</p>
									<p className="m-0 text-xs text-muted-foreground">
										{ guest.reference }
									</p>
								</div>
								<p className="m-0 flex items-center gap-2 text-sm text-heading">
									<Phone
										className="h-4 w-4 shrink-0 text-muted-foreground"
										aria-hidden="true"
									/>
									{ guest.phone || '—' }
								</p>
								<p className="m-0 flex items-center gap-2 break-all text-sm text-heading">
									<Mail
										className="h-4 w-4 shrink-0 text-muted-foreground"
										aria-hidden="true"
									/>
									{ guest.email || (
										<span className="text-muted-foreground">
											{ __(
												'None given',
												'radius-hotel-booking'
											) }
										</span>
									) }
								</p>
								{ guest.id_number_masked ? (
									<p className="m-0 text-sm text-muted-foreground">
										{ `${ guest.id_type_label } ${ guest.id_number_masked }` }
									</p>
								) : null }
							</div>
						) : (
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'No guest on this booking.',
									'radius-hotel-booking'
								) }
							</p>
						) }
					</Panel>

					{ booking.special_requests ? (
						<Panel
							title={ __(
								'Note from booking',
								'radius-hotel-booking'
							) }
						>
							<p
								className="m-0 text-sm text-heading"
								style={ { whiteSpace: 'pre-line' } }
							>
								{ booking.special_requests }
							</p>
						</Panel>
					) : null }

					{ panels.length ? (
						<Panel>
							{ panels.length > 1 ? (
								<Tabs
									value={ activeTab }
									onValueChange={ setTab }
								>
									<TabsList className="h-auto flex-wrap justify-start">
										{ panels.map( ( panel ) => (
											<TabsTrigger
												key={ panel.key }
												value={ panel.key }
											>
												{ panel.label }
											</TabsTrigger>
										) ) }
									</TabsList>
									{ panels.map( ( panel ) => (
										<TabsContent
											key={ panel.key }
											value={ panel.key }
											className="pt-2"
										>
											{ panel.render() }
										</TabsContent>
									) ) }
								</Tabs>
							) : (
								<div className="space-y-2">
									<h3 className="m-0 text-base font-semibold text-heading">
										{ panels[ 0 ].label }
									</h3>
									{ panels[ 0 ].render() }
								</div>
							) }
						</Panel>
					) : null }
				</div>
			</div>

			<LineEditor
				booking={ booking }
				line={ editor?.line || null }
				open={ Boolean( editor ) }
				onClose={ () => setEditor( null ) }
			/>
			<ConfirmDialog
				open={ 'line_remove' === dialog?.action }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={
					dialog?.line
						? sprintf(
								/* translators: %s: room number. */
								__(
									'Remove room %s from the booking?',
									'radius-hotel-booking'
								),
								dialog.line.room_number
						  )
						: ''
				}
				description={ __(
					'For a room booked by mistake: it is taken off the booking, free again at once, and no longer counts towards the total. The history keeps it.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Remove room', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					lineChange
						.mutateAsync( {
							kind: 'remove',
							lineId: dialog.line.id,
						} )
						.then( ( { message } ) => {
							toast.success( message );
							setDialog( null );
						} )
						.catch( ( err ) => {
							toastError( err );
							throw err;
						} )
				}
			/>
			<CheckInDialog
				line={ 'check_in' === dialog?.action ? dialog.line : null }
				onClose={ () => setDialog( null ) }
				onConfirm={ ( roomId ) =>
					run( 'check_in', { line: dialog.line, roomId } )
				}
			/>
			<ConfirmDialog
				open={ [ 'decline', 'cancel' ].includes( dialog?.action ) }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={
					dialog?.line
						? lineQuestion( dialog.action, dialog.line.room_number )
						: 'decline' === dialog?.action
						? __( 'Decline this booking?', 'radius-hotel-booking' )
						: __( 'Cancel this booking?', 'radius-hotel-booking' )
				}
				description={
					dialog?.line
						? __(
								'This room is free again at once and no longer counts towards the total.',
								'radius-hotel-booking'
						  )
						: __(
								'The rooms are free again at once. A guest with an e-mail address is sent the reason.',
								'radius-hotel-booking'
						  )
				}
				confirmLabel={
					'decline' === dialog?.action
						? __( 'Decline', 'radius-hotel-booking' )
						: __( 'Cancel booking', 'radius-hotel-booking' )
				}
				destructive
				requireReason
				reasonLabel={ __( 'Reason', 'radius-hotel-booking' ) }
				onConfirm={ ( reason ) =>
					run( dialog.action, { line: dialog.line, reason } ).then(
						() => setDialog( null )
					)
				}
			/>
			<ConfirmDialog
				open={ [ 'check_out', 'no_show' ].includes( dialog?.action ) }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={
					dialog?.line
						? lineQuestion( dialog.action, dialog.line.room_number )
						: ''
				}
				description={
					'no_show' === dialog?.action
						? __(
								'The guest did not come. The room is free again from now.',
								'radius-hotel-booking'
						  )
						: Number( booking.money.balance_due ) > 0
						? sprintf(
								/* translators: %s: amount still due. */
								__(
									'%s is still due on this booking. Check out anyway?',
									'radius-hotel-booking'
								),
								formatMoney( booking.money.balance_due )
						  )
						: __(
								'The room is free again once the guest has left.',
								'radius-hotel-booking'
						  )
				}
				confirmLabel={ actionLabel( dialog?.action ) }
				destructive={
					'no_show' === dialog?.action ||
					Number( booking.money.balance_due ) > 0
				}
				onConfirm={ () =>
					run( dialog.action, { line: dialog.line } ).then( () =>
						setDialog( null )
					)
				}
			/>
		</div>
	);
}
