/**
 * Rate plan library (`#/rate-plans`, features 7.1–7.5, 7.10): every stay
 * window the hotel sells, with its window in words, its features and where
 * it is used. A row opens the editor.
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { ChevronRight, Clock, Plus } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { usePageActions } from '@/components/layout/PageActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { useAccess } from '@/lib/access';
import { describeWindow } from '@/lib/stayWindow';
import RatePlanSheet from './components/RatePlanSheet';
import { useRatePlans } from './api';
import { usageText } from './usage';

/**
 * @return {JSX.Element} Screen.
 */
export default function RatePlans() {
	const { data: plans, isPending, error, refetch } = useRatePlans();
	const canManage = useAccess( 'rates.manage' ) !== 'locked';
	// null = closed, {} = new, a plan = edit.
	const [ editing, setEditing ] = useState( null );

	usePageActions(
		canManage ? (
			<Button onClick={ () => setEditing( {} ) }>
				<Plus className="h-4 w-4" aria-hidden="true" />
				<span className="sr-only sm:not-sr-only">
					{ __( 'Add rate plan', 'radius-hotel-booking' ) }
				</span>
			</Button>
		) : null,
		[ canManage ]
	);

	let body;
	if ( isPending ) {
		body = (
			<div className="space-y-2">
				{ [ 0, 1, 2 ].map( ( key ) => (
					<Skeleton key={ key } className="h-16 w-full" />
				) ) }
			</div>
		);
	} else if ( error ) {
		body = (
			<EmptyState
				icon={ Clock }
				title={ __(
					'The rate plans could not be loaded',
					'radius-hotel-booking'
				) }
				description={ error.message }
				action={
					<Button variant="outline" onClick={ () => refetch() }>
						{ __( 'Try again', 'radius-hotel-booking' ) }
					</Button>
				}
				className="border-0"
			/>
		);
	} else if ( ! plans.length ) {
		body = (
			<EmptyState
				icon={ Clock }
				title={ __( 'No rate plans yet', 'radius-hotel-booking' ) }
				description={ __(
					'A rate plan is a stay window you sell, such as Half Day 08:30–17:00 or Overnight 20:00–08:00.',
					'radius-hotel-booking'
				) }
				action={
					canManage ? (
						<Button onClick={ () => setEditing( {} ) }>
							<Plus className="h-4 w-4" aria-hidden="true" />
							{ __( 'Add rate plan', 'radius-hotel-booking' ) }
						</Button>
					) : null
				}
				className="border-0 py-16"
			/>
		);
	} else {
		body = (
			<ul className="m-0 list-none divide-y divide-border rounded-lg border border-border p-0">
				{ plans.map( ( plan ) => (
					<li key={ plan.id } className="m-0">
						<button
							type="button"
							onClick={ () => setEditing( plan ) }
							className="flex w-full items-center gap-3 bg-transparent px-4 py-3 text-left hover:bg-muted focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
						>
							<div className="min-w-0 flex-1 space-y-1">
								<div className="flex flex-wrap items-center gap-2">
									<span className="text-sm font-semibold text-heading">
										{ plan.name }
									</span>
									{ plan.type === 'flexible' ? (
										<Badge variant="secondary">
											{ __(
												'Flexible',
												'radius-hotel-booking'
											) }
										</Badge>
									) : null }
									{ plan.multi_unit ? (
										<Badge variant="secondary">
											{ __(
												'Several days or nights',
												'radius-hotel-booking'
											) }
										</Badge>
									) : null }
									{ ! plan.is_active ? (
										<Badge variant="outline">
											{ __(
												'Inactive',
												'radius-hotel-booking'
											) }
										</Badge>
									) : null }
								</div>
								<p className="m-0 text-[13px] text-muted-foreground">
									{ describeWindow( plan ) }
								</p>
								{ plan.features.length ||
								usageText( plan.usage ) ? (
									<p className="m-0 text-xs text-muted-foreground">
										{ [
											plan.features.join( ' · ' ),
											usageText( plan.usage ),
										]
											.filter( Boolean )
											.join( ' — ' ) }
									</p>
								) : null }
							</div>
							<ChevronRight
								className="h-4 w-4 shrink-0 text-muted-foreground"
								aria-hidden="true"
							/>
						</button>
					</li>
				) ) }
			</ul>
		);
	}

	return (
		<div className="mx-auto max-w-4xl space-y-4">
			<Panel
				title={ __( 'Rate plan library', 'radius-hotel-booking' ) }
				description={ __(
					'Defined once, then priced per room type on each room type’s Rates tab.',
					'radius-hotel-booking'
				) }
			>
				{ body }
			</Panel>

			{ editing ? (
				<RatePlanSheet
					plan={ editing.id ? editing : null }
					readOnly={ ! canManage }
					onClose={ () => setEditing( null ) }
				/>
			) : null }
		</div>
	);
}
