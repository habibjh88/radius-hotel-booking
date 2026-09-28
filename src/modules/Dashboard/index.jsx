import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Skeleton } from '@/components/ui/skeleton';
import { fetchItems } from '@/modules/Items/api';

/**
 * Dashboard screen.
 *
 * Deliberately small: it exists to show a screen reading from the REST API and
 * to give the admin app a landing route.
 *
 * @return {JSX.Element} Screen.
 */
export default function Dashboard() {
	const [ stats, setStats ] = useState( null );

	useEffect( () => {
		let cancelled = false;

		fetchItems( { per_page: 1 } )
			.then( ( { meta } ) => {
				if ( ! cancelled ) {
					setStats( { total: meta.pagination?.total ?? 0 } );
				}
			} )
			.catch( () => ! cancelled && setStats( { total: 0 } ) );

		return () => {
			cancelled = true;
		};
	}, [] );

	const user = window.radius_hotel_booking_param?.current_user;

	return (
		<div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
			<Card>
				<CardHeader>
					<CardTitle className="text-sm font-medium text-muted-foreground">
						{ __( 'Total items', 'radius-hotel-booking' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					{ stats ? (
						<span className="text-3xl font-semibold">
							{ stats.total }
						</span>
					) : (
						<Skeleton className="h-9 w-16" />
					) }
				</CardContent>
			</Card>

			<Card>
				<CardHeader>
					<CardTitle className="text-sm font-medium text-muted-foreground">
						{ __( 'Signed in as', 'radius-hotel-booking' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					<span className="text-base font-medium">
						{ user?.name ?? '—' }
					</span>
					<p className="m-0 text-sm text-muted-foreground">
						{ user?.email }
					</p>
				</CardContent>
			</Card>

			<Card>
				<CardHeader>
					<CardTitle className="text-sm font-medium text-muted-foreground">
						{ __( 'Plugin version', 'radius-hotel-booking' ) }
					</CardTitle>
				</CardHeader>
				<CardContent>
					<span className="text-base font-medium">
						{ window.radius_hotel_booking_param?.version ?? '—' }
					</span>
				</CardContent>
			</Card>
		</div>
	);
}
