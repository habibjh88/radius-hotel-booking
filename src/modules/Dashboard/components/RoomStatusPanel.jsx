/**
 * Rooms by operational state: available, maintenance, out of service.
 */
import { Link } from 'react-router-dom';
import { __ } from '@wordpress/i18n';
import { DoorOpen } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';

const STATES = [
	{
		key: 'available',
		label: __( 'Available', 'radius-hotel-booking' ),
		color: 'var(--success)',
	},
	{
		key: 'maintenance',
		label: __( 'Maintenance', 'radius-hotel-booking' ),
		color: 'var(--warning)',
	},
	{
		key: 'out_of_service',
		label: __( 'Out of service', 'radius-hotel-booking' ),
		color: 'var(--muted-foreground)',
	},
];

/**
 * @param {Object}  props         Props.
 * @param {Object}  props.counts  `{ available, maintenance, out_of_service }`.
 * @param {boolean} props.loading Loading.
 * @return {JSX.Element} Panel.
 */
export default function RoomStatusPanel( { counts = {}, loading } ) {
	const total = STATES.reduce( ( sum, state ) => sum + ( counts[ state.key ] || 0 ), 0 );

	return (
		<Panel
			className="h-full"
			title={ __( 'Rooms by status', 'radius-hotel-booking' ) }
			description={ __( 'Which rooms can be sold right now', 'radius-hotel-booking' ) }
		>
			{ loading ? <Skeleton className="h-36 w-full" /> : null }

			{ ! loading && total ? (
				<>
					<div className="mb-5 flex h-2.5 w-full overflow-hidden rounded-full bg-muted">
						{ STATES.map( ( state ) =>
							counts[ state.key ] ? (
								<span
									key={ state.key }
									style={ {
										width: `${ ( counts[ state.key ] / total ) * 100 }%`,
										background: state.color,
									} }
								/>
							) : null
						) }
					</div>
					<ul className="m-0 list-none space-y-3 p-0">
						{ STATES.map( ( state ) => (
							<li key={ state.key } className="m-0 flex items-center gap-3 text-sm">
								<span
									className="h-2.5 w-2.5 shrink-0 rounded-full"
									style={ { background: state.color } }
									aria-hidden="true"
								/>
								<span className="flex-1 text-heading">{ state.label }</span>
								<span className="font-semibold tabular-nums text-heading">
									{ counts[ state.key ] || 0 }
								</span>
							</li>
						) ) }
					</ul>
				</>
			) : null }

			{ ! loading && ! total ? (
				<EmptyState
					icon={ DoorOpen }
					title={ __( 'No rooms yet', 'radius-hotel-booking' ) }
					description={ __(
						'Add your floors and rooms to start selling them.',
						'radius-hotel-booking'
					) }
					action={
						<Button asChild variant="outline" size="sm">
							<Link to="/rooms">{ __( 'Add rooms', 'radius-hotel-booking' ) }</Link>
						</Button>
					}
				/>
			) : null }
		</Panel>
	);
}
