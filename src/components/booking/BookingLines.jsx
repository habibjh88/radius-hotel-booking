/**
 * The rooms of the booking so far (2.12): one line per room and window,
 * with its price; each can be removed (its hold is released). The summary
 * and confirm step (T4b) builds on this list.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { BedDouble, X } from 'lucide-react';

import Money from '@/components/common/Money';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { windowLabel } from './rates';

/**
 * @param {Object}   props          Props.
 * @param {Object[]} props.lines    Lines.
 * @param {Function} props.onRemove Called with a line.
 * @param {number}   props.failed   Index of a line the server refused (highlighted).
 * @return {JSX.Element} List.
 */
export default function BookingLines( { lines, onRemove, failed = -1 } ) {
	return (
		<ul className="m-0 list-none space-y-2 p-0">
			{ lines.map( ( line, index ) => (
				<li
					key={ line.key }
					className={ cn(
						'flex flex-wrap items-center justify-between gap-3 rounded-lg border p-3',
						index === failed
							? 'border-destructive bg-destructive/5'
							: 'border-border'
					) }
				>
					<div className="flex min-w-0 items-center gap-3">
						<BedDouble
							className="h-5 w-5 shrink-0 text-muted-foreground"
							aria-hidden="true"
						/>
						<div className="min-w-0">
							<p className="m-0 text-sm font-semibold text-heading">
								{ sprintf(
									/* translators: 1: room number, 2: room type, 3: rate name. */
									__(
										'Room %1$s · %2$s · %3$s',
										'radius-hotel-booking'
									),
									line.room_number,
									line.room_type_name,
									line.rate_name
								) }
							</p>
							<p className="m-0 text-xs text-muted-foreground">
								{ [
									line.window?.units > 1
										? windowLabel( line.window )
										: `${
												line.arrival_label
										  } · ${ windowLabel( line.window ) }`,
									sprintf(
										/* translators: %d: number of adults. */
										_n(
											'%d adult',
											'%d adults',
											line.adults,
											'radius-hotel-booking'
										),
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
									.join( ' · ' ) }
							</p>
						</div>
					</div>
					<div className="flex items-center gap-2">
						<span className="text-sm font-semibold text-heading">
							<Money value={ line.total } />
						</span>
						<Button
							type="button"
							variant="ghost"
							size="icon"
							onClick={ () => onRemove( line ) }
							aria-label={ sprintf(
								/* translators: %s: room number. */
								__( 'Remove room %s', 'radius-hotel-booking' ),
								line.room_number
							) }
						>
							<X className="h-4 w-4" aria-hidden="true" />
						</Button>
					</div>
				</li>
			) ) }
		</ul>
	);
}
