/**
 * Bookings: the front desk list (M01, 1.5–1.9), one row per booked room —
 * reference, guest, room type and rate, floor and room, arrival and
 * departure, payment and stay status.
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
import { __, sprintf } from '@wordpress/i18n';
import {
	Ban,
	CalendarSearch,
	Check,
	ExternalLink,
	LogIn,
	LogOut,
	Plus,
	Wallet,
	X,
} from 'lucide-react';
import { useQueryClient } from '@tanstack/react-query';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import DateRangePicker from '@/components/common/DateRangePicker';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { usePageActions } from '@/components/layout/PageActions';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/lib/access';
import { formatDateRange, formatMoney } from '@/lib/format';
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
const TABS = [
	'all',
	'awaiting',
	'arriving',
	'leaving',
	'in_house',
	'overdue',
];

/**
 * @return {JSX.Element} Screen.
 */
export default function Bookings() {
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

	const list = useBookingLines( {
		tab,
		from,
		to,
		mode,
		q,
		page,
		per_page: PER_PAGE,
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
		canCreate ? (
			<Button asChild>
				<Link to="/bookings/new">
					<Plus aria-hidden="true" />
					<span className="hidden sm:inline">
						{ __( 'New booking', 'radius-hotel-booking' ) }
					</span>
				</Link>
			</Button>
		) : null,
		[ canCreate ]
	);

	const tabs = [
		{ value: 'all', label: __( 'All', 'radius-hotel-booking' ) },
		{
			value: 'awaiting',
			label: __( 'Awaiting approval', 'radius-hotel-booking' ),
		},
		{
			value: 'arriving',
			label: __( 'Arriving today', 'radius-hotel-booking' ),
		},
		{
			value: 'leaving',
			label: __( 'Leaving today', 'radius-hotel-booking' ),
		},
		{ value: 'in_house', label: __( 'In house', 'radius-hotel-booking' ) },
		{
			value: 'overdue',
			label: __( 'Payment overdue', 'radius-hotel-booking' ),
		},
	].map( ( item ) => ( { ...item, count: counts[ item.value ] } ) );

	const columns = [
		{
			// The reference with the guest under it: one column keeps the row on a laptop screen.
			id: 'reference',
			header: __( 'Booking', 'radius-hotel-booking' ),
			mobile: 'title',
			cell: ( line ) => (
				<span className="block min-w-0">
					<Link
						to={ `/bookings/${ line.booking_id }` }
						className="font-semibold text-heading no-underline hover:underline"
						onClick={ ( e ) => e.stopPropagation() }
					>
						{ line.reference }
					</Link>
					<span className="block truncate text-[13px] text-heading">
						{ line.guest.name || '—' }
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
			// Arrival and departure in one column: "1 October 2026 8:30 AM – 5:00 PM".
			id: 'stay',
			header: __( 'Stay', 'radius-hotel-booking' ),
			wrap: true,
			cell: ( line ) => (
				<span className="text-[13px]">
					{ formatDateRange( line.start, line.end ) }
				</span>
			),
		},
		{
			// The stay status, and the payment status under it: one column keeps
			// the table inside a laptop screen.
			id: 'status',
			header: __( 'Status', 'radius-hotel-booking' ),
			cell: ( line ) => (
				<span className="flex flex-col items-start gap-1">
					<StatusBadge domain="stay" value={ line.status } />
					<StatusBadge
						domain="payment"
						value={ line.overdue ? 'overdue' : line.payment_status }
					/>
				</span>
			),
		},
	];

	// The row's next step as its button (approve, check in or check out); the rest in the ⋯ menu.
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
		const primary = [ 'approve', 'check_in', 'check_out' ].find( can );
		return [
			...[ 'approve', 'check_in', 'check_out', 'decline', 'cancel' ]
				.filter( can )
				.map( ( action ) => ( {
					label: actionLabel( action ),
					icon: icons[ action ],
					primary: action === primary,
					destructive: [ 'decline', 'cancel' ].includes( action ),
					onSelect: () =>
						[ 'decline', 'cancel', 'check_out' ].includes( action )
							? setDialog( { action, line } )
							: run( line, action ).catch( () => {} ),
				} ) ),
			can( 'record_payment' )
				? {
						label: __( 'Record payment', 'radius-hotel-booking' ),
						icon: Wallet,
						onSelect: () => setPaying( line ),
				  }
				: null,
			{
				label: __( 'Open booking', 'radius-hotel-booking' ),
				icon: ExternalLink,
				onSelect: () => navigate( `/bookings/${ line.booking_id }` ),
			},
		].filter( Boolean );
	};

	const filtered = Boolean( q || from || to || tab !== 'all' );

	return (
		<>
			<Panel>
				<div className="space-y-4">
					<div className="flex flex-col gap-3 2xl:flex-row 2xl:items-center 2xl:justify-between">
						<FilterTabs
							tabs={ tabs }
							value={ tab }
							onChange={ ( value ) => setView( { tab: value } ) }
							label={ __(
								'Booking list',
								'radius-hotel-booking'
							) }
						/>
						<DateRangePicker
							value={ { from, to } }
							onChange={ ( range ) =>
								setView( {
									from: range?.from || '',
									to: range?.to || '',
								} )
							}
							mode={ mode }
							onModeChange={ ( next ) =>
								setView( { mode: next } )
							}
							align="start"
						/>
					</div>
					<DataTable
						columns={ columns }
						rows={ list.data?.lines || [] }
						total={ list.data?.total || 0 }
						page={ page }
						perPage={ PER_PAGE }
						onPageChange={ ( next ) =>
							setView( { page: next > 1 ? next : '' } )
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
