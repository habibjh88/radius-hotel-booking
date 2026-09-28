/**
 * "Get your hotel ready": the setup checklist from `dashboard/summary`.
 * Each module marks its step done on the server as it is built.
 */
import { Link } from 'react-router-dom';
import { __, sprintf } from '@wordpress/i18n';
import { Check, ChevronRight, PartyPopper } from 'lucide-react';

import Panel from '@/components/common/Panel';
import { Progress } from '@/components/ui/progress';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

/**
 * @param {Object}  props         Props.
 * @param {Array}   props.steps   `[{ label, description, path, done }]`.
 * @param {boolean} props.loading Loading.
 * @return {JSX.Element} Panel.
 */
export default function SetupPanel( { steps = [], loading } ) {
	const done = steps.filter( ( step ) => step.done ).length;
	const percent = steps.length ? Math.round( ( done / steps.length ) * 100 ) : 0;

	return (
		<Panel
			className="h-full"
			title={ __( 'Get your hotel ready', 'radius-hotel-booking' ) }
			description={
				loading
					? ' '
					: sprintf(
							/* translators: 1: steps done, 2: total steps. */
							__( '%1$d of %2$d steps done', 'radius-hotel-booking' ),
							done,
							steps.length
					  )
			}
		>
			{ loading ? (
				<div className="space-y-3">
					{ [ 0, 1, 2, 3 ].map( ( key ) => (
						<Skeleton key={ key } className="h-11 w-full" />
					) ) }
				</div>
			) : (
				<>
					<Progress
						value={ percent }
						className="mb-4 h-2"
						aria-label={ __( 'Setup progress', 'radius-hotel-booking' ) }
					/>

					{ steps.length && done === steps.length ? (
						<p className="m-0 flex items-center gap-2 text-sm font-medium text-success">
							<PartyPopper className="h-4 w-4" aria-hidden="true" />
							{ __( 'Your hotel is ready to take bookings.', 'radius-hotel-booking' ) }
						</p>
					) : null }

					<ol className="m-0 list-none space-y-1 p-0">
						{ steps.map( ( step, index ) => (
							<li key={ step.label } className="m-0">
								<Link
									to={ step.path }
									className="group flex items-center gap-3 rounded-lg px-2 py-2.5 no-underline transition-colors hover:bg-primary-softer"
								>
									<span
										className={ cn(
											'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
											step.done
												? 'bg-success text-white'
												: 'border border-border bg-card text-muted-foreground group-hover:border-primary group-hover:text-primary'
										) }
									>
										{ step.done ? (
											<Check className="h-4 w-4" aria-hidden="true" />
										) : (
											index + 1
										) }
									</span>
									<span className="min-w-0 flex-1">
										<span
											className={ cn(
												'block truncate text-sm font-medium',
												step.done
													? 'text-muted-foreground line-through'
													: 'text-heading'
											) }
										>
											{ step.label }
										</span>
										<span className="block truncate text-xs text-muted-foreground">
											{ step.description }
										</span>
									</span>
									<ChevronRight
										className="h-4 w-4 shrink-0 text-muted-foreground group-hover:text-primary"
										aria-hidden="true"
									/>
								</Link>
							</li>
						) ) }
					</ol>
				</>
			) }
		</Panel>
	);
}
