/**
 * Bookings: the front desk list (M01, 1.5–1.9), one row per booked room —
 * reference, guest, room type and rate, floor and room, arrival and
 * departure, payment and stay status. A booking's rooms sit together under
 * one booking cell (the server orders them by booking within each sort).
 *
 * Tabs with their counts (All, Awaiting approval, Arriving today, Leaving
 * today, In house, Payment overdue), a date range that means the arrival or
 * the booking's creation, and a search by reference, guest name or phone, or
 * room number. Every filter lives in the URL, so a dashboard counter opens
 * its tab and a filtered list can be bookmarked or shared. A table on a
 * desktop, cards on a phone (DataTable). Row actions come in T3.
 */
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Ban,
	CalendarSearch,
	Check,
	Eye,
	FileText,
	LogIn,
	LogOut,
	Pencil,
	Plus,
	Wallet,
	X,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import GuestLink from '@/components/common/GuestLink';
import PeriodFilter from '@/components/common/PeriodFilter';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/lib/access';
import {
	formatDateAs,
	formatDateTime,
	formatMoney,
	formatTime,
	formatTimeAgo,
	siteToday,
} from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import {
	useBookingLines,
	useLineAction,
	usePaymentAction,
	usePayments,
} from './api';
import RecordPaymentDialog from './components/RecordPaymentDialog';
import { actionLabel, lineQuestion } from './moves';

const PER_PAGE = 20;
// Page sizes the desk can pick under the list; the server caps at 100.
const PER_PAGE_OPTIONS = [ 20, 40, 60, 100 ];
const TABS = [
	'all',
	'awaiting',
	'arriving',
	'leaving',
	'in_house',
	'overdue',
];

/**
 * A stay date in the list: without the year when it is this year
 * ("5 Nov"), with it otherwise ("2 Jan 2027"); the full date is the tooltip.
 *
 * @param {string} value Date-time (`Y-m-d …`).
 * @return {string} Date.
 */
const listDate = ( value ) =>
	formatDateAs(
		value,
		String( value || '' ).slice( 0, 4 ) === siteToday().slice( 0, 4 )
			? 'j M'
			: 'j M Y'
	);

/**
 * The Bookings screen.
 *
 * @return {JSX.Element} Screen.
 */
export default function Bookings() {
	return <BookingList />;
}

/**
 * The booking list with its filters and row actions, on the Bookings screen
 * and on the dashboard under the counters (R1-T10). Its filters live in the
 * URL of whichever screen shows it.
 *
 * @param {Object}  props                 Props.
 * @param {boolean} props.withPageActions Put "New booking" in the page header
 *                                        (the dashboard has its own).
 * @return {JSX.Element} List.
 */
export function BookingList( { withPageActions = true } ) {
	const [ params, setParams ] = useSearchParams();
	const navigate = useNavigate();
	const canCreate = useAccess( 'bookings.create' ) !== 'locked';

	const tab = TABS.includes( params.get( 'tab' ) )
		? params.get( 'tab' )
		: 'all';
	const from = params.get( 'from' ) || '';
	const to = params.get( 'to' ) || '';
	const mode = params.get( 'mode' ) === 'created' ? 'created' : 'arrival';
	const q = params.get( 'q' ) || '';
	const page = Math.max( 1, Number( params.get( 'page' ) ) || 1 );
	const perPage = PER_PAGE_OPTIONS.includes(
		Number( params.get( 'per_page' ) )
	)
		? Number( params.get( 'per_page' ) )
		: PER_PAGE;

	const list = useBookingLines( {
		tab,
		from,
		to,
		mode,
		q,
		page,
		per_page: perPage,
	} );
	const counts = list.data?.counts || {};

	// Row actions (1.10): each hidden when its key is locked; a passcode key
	// is shown, and the API client asks for the PIN.
	const access = {
		approve: useAccess( 'bookings.approve' ),
		decline: useAccess( 'bookings.decline' ),
		cancel: useAccess( 'bookings.cancel' ),
		check_in: useAccess( 'bookings.check_in' ),
		check_out: useAccess( 'bookings.check_out' ),
		record_payment: useAccess( 'payments.record' ),
		line_edit: useAccess( 'bookings.line_edit' ),
	};
	const allowed = ( action ) =>
		access[ action ] && access[ action ] !== 'locked';
	const move = useLineAction();
	const client = useQueryClient();
	// `{ action, line }` while a move waits for its confirmation; `paying` = the row recording money.
	const [ dialog, setDialog ] = useState( null );
	const [ paying, setPaying ] = useState( null );

	const run = ( line, action, reason = '' ) =>
		move
			.mutateAsync( {
				bookingId: line.booking_id,
				lineId: line.id,
				action,
				reason,
			} )
			.then( ( { message } ) => toast.success( message ) )
			.catch( ( err ) => {
				toastError( err );
				throw err;
			} );

	// Change some filters; any change but the page goes back to page 1.
	const setView = ( changes ) =>
		setParams(
			( prev ) => {
				const next = new URLSearchParams( prev );
				const all =
					'page' in changes ? changes : { ...changes, page: '' };
				Object.entries( all ).forEach( ( [ key, value ] ) =>
					value &&
					! ( key === 'tab' && value === 'all' ) &&
					! ( key === 'mode' && value === 'arrival' )
						? next.set( key, String( value ) )
						: next.delete( key )
				);
				return next;
			},
			{ replace: true }
		);

	usePageActions(
		withPageActions && canCreate ? (
			<Button asChild>
				<Link to="/bookings/new">
					<Plus aria-hidden="true" />
					<span className="hidden sm:inline">
						{ __( 'New booking', 'radius-hotel-booking' ) }
					</span>
				</Link>
			</Button>
		) : null,
		[ canCreate, withPageActions ]
	);

	const tabs = [
		{ value: 'all', label: __( 'All', 'radius-hotel-booking' ) },
		{
			value: 'awaiting',
			// Short tab names, so the row fits (client review).
			label: __( 'Pending', 'radius-hotel-booking' ),
		},
		{
			value: 'arriving',
			label: __( 'Arrivals', 'radius-hotel-booking' ),
		},
		{
			value: 'leaving',
			label: __( 'Departures', 'radius-hotel-booking' ),
		},
		{ value: 'in_house', label: __( 'In house', 'radius-hotel-booking' ) },
		{
			value: 'overdue',
			label: __( 'Overdue', 'radius-hotel-booking' ),
		},
	].map( ( item ) => ( { ...item, count: counts[ item.value ] } ) );

	// Where the booking came from: the desk (with who took it) or the website (R1-T7).
	const sourceLabel = ( line ) => {
		if ( 'desk' === line.source ) {
			return line.created_by
				? sprintf(
						/* translators: %s: staff member who took the booking. */
						__( 'At the desk · %s', 'radius-hotel-booking' ),
						line.created_by
				  )
				: __( 'At the desk', 'radius-hotel-booking' );
		}
		return (
			{
				web: __( 'Online', 'radius-hotel-booking' ),
				import: __( 'Imported', 'radius-hotel-booking' ),
				ical: __( 'Calendar sync', 'radius-hotel-booking' ),
			}[ line.source ] || ''
		);
	};
	// Made in the last hour: flagged NEW, as on the old dashboard.
	const isNew = ( line ) =>
		Date.now() - new Date( line.created_at ).getTime() < 60 * 60 * 1000;

	const columns = [
		{
			id: 'reference',
			header: __( 'Booking', 'radius-hotel-booking' ),
			mobile: 'title',
			// Once per booking, across its rooms.
			grouped: true,
			cell: ( line, { size = 1 } = {} ) => (
				<span className="block min-w-0">
					<span className="flex flex-wrap items-center gap-1.5">
						<Link
							to={ `/bookings/${ line.booking_id }` }
							className="font-semibold text-heading no-underline hover:underline"
							onClick={ ( e ) => e.stopPropagation() }
						>
							{ line.reference }
						</Link>
						{ isNew( line ) ? (
							<span className="rounded-full bg-success-soft px-1.5 py-px text-[10px] font-bold uppercase tracking-wide text-success">
								{ __( 'New', 'radius-hotel-booking' ) }
							</span>
						) : null }
					</span>
					{ sourceLabel( line ) ? (
						<span className="block truncate text-xs text-muted-foreground">
							{ sourceLabel( line ) }
						</span>
					) : null }
					{ size > 1 ? (
						<span className="mt-1.5 inline-flex rounded-full bg-primary-soft px-2 py-0.5 text-[11px] font-semibold text-primary">
							{ sprintf(
								/* translators: %d: number of rooms in the booking shown here. */
								_n(
									'%d room',
									'%d rooms',
									size,
									'radius-hotel-booking'
								),
								size
							) }
						</span>
					) : null }
				</span>
			),
		},
		{
			// The guest, and when the booking was made ("2 hours ago").
			id: 'guest',
			header: __( 'Guest', 'radius-hotel-booking' ),
			grouped: true,
			cell: ( line ) => (
				<span className="block min-w-0">
					<GuestLink
						id={ line.guest.id }
						name={ line.guest.name }
						className="block truncate text-[13px] font-medium text-heading"
					/>
					<span
						className="block text-xs text-muted-foreground"
						title={ formatDateTime( line.created_at ) }
					>
						{ formatTimeAgo( line.created_at ) }
					</span>
					{ line.guest.phone ? (
						<span className="block text-xs text-muted-foreground">
							{ line.guest.phone }
						</span>
					) : null }
				</span>
			),
		},
		{
			id: 'room',
			header: __( 'Room', 'radius-hotel-booking' ),
			wrap: true,
			cell: ( line ) => (
				<span className="block">
					<span className="block font-medium text-heading">
						{ sprintf(
							/* translators: 1: room number, 2: floor name. */
							__( '%1$s · %2$s', 'radius-hotel-booking' ),
							line.room,
							line.floor
						) }
					</span>
					<span className="block text-xs text-muted-foreground">
						{ sprintf(
							/* translators: 1: room type, 2: rate plan. */
							__( '%1$s · %2$s', 'radius-hotel-booking' ),
							line.room_type,
							line.rate_plan
						) }
					</span>
				</span>
			),
		},
		{
			id: 'check_in',
			header: __( 'Check-in', 'radius-hotel-booking' ),
			cell: ( line ) => (
				<span className="block whitespace-nowrap text-[13px]">
					<span
						className="block text-heading"
						title={ formatDateAs( line.start, 'j M Y' ) }
					>
						{ listDate( line.start ) }
					</span>
					<span className="block text-xs text-muted-foreground">
						{ formatTime( line.start ) }
					</span>
				</span>
			),
		},
		{
			id: 'check_out',
			header: __( 'Check-out', 'radius-hotel-booking' ),
			cell: ( line ) => (
				<span className="block whitespace-nowrap text-[13px]">
					<span
						className="block text-heading"
						title={ formatDateAs( line.end, 'j M Y' ) }
					>
						{ listDate( line.end ) }
					</span>
					<span className="block text-xs text-muted-foreground">
						{ formatTime( line.end ) }
					</span>
				</span>
			),
		},
		{
			// The payment status, and how it was paid.
			id: 'payment',
			header: __( 'Payment', 'radius-hotel-booking' ),
			cell: ( line ) => (
				<span className="flex flex-col items-start gap-1">
					<StatusBadge
						domain="payment"
						value={ line.overdue ? 'overdue' : line.payment_status }
					/>
					{ line.payment_method ? (
						<span className="text-xs text-muted-foreground">
							{ line.payment_method }
						</span>
					) : null }
				</span>
			),
		},
		{
			id: 'status',
			header: __( 'Status', 'radius-hotel-booking' ),
			cell: ( line ) => (
				<StatusBadge domain="stay" value={ line.status } />
			),
		},
	];

	// Only the next stay step (Check in / Check out) is a button on the row;
	// everything else sits in the ⋯ menu with its icon and label.
	const rowActions = ( line ) => {
		const can = ( action ) =>
			( line.actions || [] ).includes( action ) && allowed( action );
		const icons = {
			approve: Check,
			check_in: LogIn,
			check_out: LogOut,
			decline: X,
			cancel: Ban,
		};
		const confirmFirst = [ 'decline', 'cancel', 'check_out' ];
		const moveAction = ( action, extra = {} ) => ( {
			label: actionLabel( action ),
			icon: icons[ action ],
			destructive: [ 'decline', 'cancel' ].includes( action ),
			onSelect: () =>
				confirmFirst.includes( action )
					? setDialog( { action, line } )
					: run( line, action ).catch( () => {} ),
			...extra,
		} );
		const primary = [ 'check_in', 'check_out' ].find( can );
		return [
			primary ? moveAction( primary, { primary: true } ) : null,
			can( 'approve' ) ? moveAction( 'approve' ) : null,
			{
				label: __( 'View booking', 'radius-hotel-booking' ),
				icon: Eye,
				onSelect: () => navigate( `/bookings/${ line.booking_id }` ),
			},
			line.invoice_url
				? {
						label: __( 'Invoice', 'radius-hotel-booking' ),
						icon: FileText,
						onSelect: () =>
							window.open(
								line.invoice_url,
								'_blank',
								'noopener'
							),
				  }
				: null,
			can( 'line_edit' )
				? {
						label: __( 'Edit', 'radius-hotel-booking' ),
						icon: Pencil,
						onSelect: () =>
							navigate(
								`/bookings/${ line.booking_id }?edit=${ line.id }`
							),
				  }
				: null,
			...[ 'check_in', 'check_out' ]
				.filter( ( action ) => action !== primary && can( action ) )
				.map( ( action ) => moveAction( action ) ),
			can( 'record_payment' )
				? {
						label: __( 'Record payment', 'radius-hotel-booking' ),
						icon: Wallet,
						onSelect: () => setPaying( line ),
				  }
				: null,
			can( 'decline' ) ? moveAction( 'decline' ) : null,
			can( 'cancel' ) ? moveAction( 'cancel' ) : null,
		].filter( Boolean );
	};

	const filtered = Boolean( q || from || to || tab !== 'all' );

	return (
		<>
			<Panel>
				<div className="space-y-4">
					{ /* The date filter on top, then search and the quick filters above the table (R1-T9). */ }
					<PeriodFilter
						modes={ [ 'arrival', 'created' ] }
						mode={ mode }
						from={ from }
						to={ to }
						idPrefix="rtbp-booking-filter"
						onApply={ ( next ) => setView( next ) }
					/>
					<DataTable
						columns={ columns }
						rows={ list.data?.lines || [] }
						groupBy={ ( line ) => line.booking_id }
						total={ list.data?.total || 0 }
						page={ page }
						perPage={ perPage }
						onPageChange={ ( next ) =>
							setView( { page: next > 1 ? next : '' } )
						}
						perPageOptions={ PER_PAGE_OPTIONS }
						onPerPageChange={ ( size ) =>
							setView( {
								per_page: size === PER_PAGE ? '' : size,
							} )
						}
						loading={ list.isPending }
						error={ list.error }
						onRetry={ () => list.refetch() }
						search={ {
							value: q,
							onChange: ( value ) => setView( { q: value } ),
							placeholder: __(
								'Reference, guest, phone or room',
								'radius-hotel-booking'
							),
						} }
						toolbarEnd={
							<FilterTabs
								tabs={ tabs }
								value={ tab }
								onChange={ ( value ) =>
									setView( { tab: value } )
								}
								label={ __(
									'Booking list',
									'radius-hotel-booking'
								) }
							/>
						}
						rowActions={ rowActions }
						onRowClick={ ( line ) =>
							navigate( `/bookings/${ line.booking_id }` )
						}
						empty={
							<EmptyState
								icon={ CalendarSearch }
								title={
									filtered
										? __(
												'No booking matches',
												'radius-hotel-booking'
										  )
										: __(
												'No bookings yet',
												'radius-hotel-booking'
										  )
								}
								description={
									filtered
										? __(
												'Try another tab, date range or search.',
												'radius-hotel-booking'
										  )
										: __(
												'Bookings taken at the desk or on the website appear here.',
												'radius-hotel-booking'
										  )
								}
							/>
						}
						caption={ __( 'Bookings', 'radius-hotel-booking' ) }
					/>
				</div>
			</Panel>

			<ConfirmDialog
				open={ [ 'decline', 'cancel' ].includes( dialog?.action ) }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={
					dialog
						? lineQuestion( dialog.action, dialog.line.room )
						: ''
				}
				description={ __(
					'This room is free again at once and no longer counts towards the total.',
					'radius-hotel-booking'
				) }
				confirmLabel={
					'decline' === dialog?.action
						? __( 'Decline room', 'radius-hotel-booking' )
						: __( 'Cancel room', 'radius-hotel-booking' )
				}
				destructive
				requireReason
				reasonLabel={ __( 'Reason', 'radius-hotel-booking' ) }
				onConfirm={ ( reason ) =>
					run( dialog.line, dialog.action, reason ).then( () =>
						setDialog( null )
					)
				}
			/>
			<ConfirmDialog
				open={ 'check_out' === dialog?.action }
				onOpenChange={ ( open ) => ! open && setDialog( null ) }
				title={
					dialog ? lineQuestion( 'check_out', dialog.line.room ) : ''
				}
				description={
					Number( dialog?.line?.balance_due ) > 0
						? sprintf(
								/* translators: %s: amount still due. */
								__(
									'%s is still due on this booking. Check out anyway?',
									'radius-hotel-booking'
								),
								formatMoney( dialog.line.balance_due )
						  )
						: __(
								'The room is free again once the guest has left.',
								'radius-hotel-booking'
						  )
				}
				confirmLabel={ actionLabel( 'check_out' ) }
				destructive={ Number( dialog?.line?.balance_due ) > 0 }
				onConfirm={ () =>
					run( dialog.line, 'check_out' ).then( () =>
						setDialog( null )
					)
				}
			/>
			{ paying ? (
				<RowPayment
					line={ paying }
					onClose={ () => setPaying( null ) }
					onDone={ () => {
						client.invalidateQueries( {
							queryKey: [ 'bookings', 'lines' ],
						} );
						client.invalidateQueries( {
							queryKey: [ 'dashboard' ],
						} );
					} }
				/>
			) : null }
		</>
	);
}

/**
 * Record a payment from a row: the booking's own ledger (methods, what is
 * due) and the same dialog as the booking screen.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.line    The row.
 * @param {Function} props.onClose Close.
 * @param {Function} props.onDone  After a payment is saved.
 * @return {JSX.Element} Dialog.
 */
function RowPayment( { line, onClose, onDone } ) {
	const ledger = usePayments( line.booking_id );
	const act = usePaymentAction( line.booking_id );
	return (
		<RecordPaymentDialog
			open={ Boolean( ledger.data ) }
			ledger={ ledger.data }
			onClose={ onClose }
			onSubmit={ ( body ) =>
				act
					.mutateAsync( { kind: 'record', body } )
					.then( ( { message } ) => {
						toast.success( message );
						onDone();
						onClose();
					} )
			}
		/>
	);
}
