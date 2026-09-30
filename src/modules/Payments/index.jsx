/**
 * Payments → the bookings to follow up (5.14): unpaid or partly paid
 * bookings past their payment deadline, the oldest first, with the guest's
 * phone to call, what is still due and how late it is. From here the desk
 * **reminds** the guest (e-mail) or **releases** the booking (its rooms are
 * free again at once and the guest is told). The list refreshes every minute.
 */
import { useState } from 'react';
import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { BellRing, CheckCircle2, Unlock } from 'lucide-react';

import PaymentDue from '@/components/booking/PaymentDue';
import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import EmptyState from '@/components/common/EmptyState';
import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import StatusBadge from '@/components/common/StatusBadge';
import { useAccess } from '@/lib/access';
import { formatDate } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useFollowUp, useOverdue } from '@/modules/Bookings/api';

const PER_PAGE = 20;

/**
 * @return {JSX.Element} Screen.
 */
export default function Payments() {
	const [ params, setParams ] = useSearchParams();
	const page = Math.max( 1, Number( params.get( 'page' ) ) || 1 );
	const list = useOverdue( page, PER_PAGE );
	const act = useFollowUp();
	const navigate = useNavigate();
	const canRemind = 'locked' !== useAccess( 'invoices.send' );
	const canRelease = 'locked' !== useAccess( 'bookings.cancel' );
	const [ releasing, setReleasing ] = useState( null );

	const run = ( id, action, reason = '' ) =>
		act
			.mutateAsync( { id, action, reason } )
			.then( ( { message } ) => toast.success( message ) )
			.catch( ( err ) => {
				toastError( err );
				throw err;
			} );

	const columns = [
		{
			id: 'reference',
			header: __( 'Booking', 'radius-hotel-booking' ),
			mobile: 'title',
			cell: ( b ) => (
				<Link
					to={ `/bookings/${ b.id }` }
					className="font-semibold text-heading no-underline hover:underline"
					onClick={ ( e ) => e.stopPropagation() }
				>
					{ b.reference }
				</Link>
			),
		},
		{
			id: 'guest',
			header: __( 'Guest', 'radius-hotel-booking' ),
			mobile: 'subtitle',
			cell: ( b ) =>
				[ b.guest_name, b.guest_phone ].filter( Boolean ).join( ' · ' ),
		},
		{
			id: 'stay',
			header: __( 'Arrival', 'radius-hotel-booking' ),
			cell: ( b ) =>
				b.first_start ? formatDate( b.first_start ) : '—',
		},
		{
			id: 'due',
			header: __( 'Still due', 'radius-hotel-booking' ),
			align: 'right',
			cell: ( b ) => (
				<span className="font-semibold text-warning">
					<Money value={ b.balance_due } />
				</span>
			),
		},
		{
			id: 'late',
			header: __( 'Deadline', 'radius-hotel-booking' ),
			wrap: true,
			cell: ( b ) => (
				<span className="inline-flex flex-wrap items-center gap-2">
					<PaymentDue booking={ b } compact />
					{ b.on_hold ? (
						<StatusBadge domain="payment" value="on_hold" />
					) : null }
				</span>
			),
		},
	];

	const rowActions = ( b ) =>
		[
			canRemind
				? {
						label: __( 'Remind', 'radius-hotel-booking' ),
						icon: BellRing,
						primary: true,
						onSelect: () => run( b.id, 'remind' ).catch( () => {} ),
				  }
				: null,
			canRelease
				? {
						label: __( 'Release', 'radius-hotel-booking' ),
						icon: Unlock,
						destructive: true,
						onSelect: () => setReleasing( b ),
				  }
				: null,
		].filter( Boolean );

	return (
		<div className="space-y-4">
			<Panel
				title={ __( 'Payments to follow up', 'radius-hotel-booking' ) }
				description={ __(
					'Bookings past their payment deadline. Remind the guest, or release the booking to free its rooms.',
					'radius-hotel-booking'
				) }
			>
				<DataTable
					columns={ columns }
					rows={ list.data?.bookings || [] }
					total={ list.data?.total || 0 }
					page={ page }
					perPage={ PER_PAGE }
					onPageChange={ ( next ) =>
						setParams( next > 1 ? { page: String( next ) } : {} )
					}
					rowActions={ rowActions }
					onRowClick={ ( b ) => navigate( `/bookings/${ b.id }` ) }
					loading={ list.isPending }
					error={ list.error }
					onRetry={ () => list.refetch() }
					empty={
						<EmptyState
							icon={ CheckCircle2 }
							title={ __(
								'Nothing to follow up',
								'radius-hotel-booking'
							) }
							description={ __(
								'No unpaid booking is past its payment deadline.',
								'radius-hotel-booking'
							) }
						/>
					}
					caption={ __(
						'Payments to follow up',
						'radius-hotel-booking'
					) }
				/>
			</Panel>

			<ConfirmDialog
				open={ Boolean( releasing ) }
				onOpenChange={ ( open ) => ! open && setReleasing( null ) }
				title={
					releasing
						? sprintf(
								/* translators: %s: booking reference. */
								__(
									'Release booking %s?',
									'radius-hotel-booking'
								),
								releasing.reference
						  )
						: ''
				}
				description={ __(
					'Its rooms are free again at once, and the guest is told by e-mail that the booking was released for non-payment.',
					'radius-hotel-booking'
				) }
				confirmLabel={ __( 'Release booking', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					run( releasing.id, 'release' ).then( () =>
						setReleasing( null )
					)
				}
			/>
		</div>
	);
}
