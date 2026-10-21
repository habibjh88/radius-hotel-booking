/**
 * The room type's highlighted amenities: the few its card on the booking
 * page shows, in the order picked. Suggestions are the amenities the type
 * offers (ticked above); the rest show as "+N more". The value is a list of
 * names; none = the card shows the first amenities, as before.
 */
import { __, sprintf } from '@wordpress/i18n';
import { ArrowLeft, Plus, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import LucideIcon from '@/components/icons/LucideIcon';
import { useAmenities } from '../api';

/** Most highlights (RoomTypeService::MAX_HIGHLIGHTS, the card's room). */
export const MAX_HIGHLIGHTS = 6;

const same = ( a, b ) => a.toLowerCase() === b.toLowerCase();

/**
 * @param {Object}   props           Props.
 * @param {string[]} props.value     Highlighted amenity names, in order.
 * @param {string[]} props.offered   The type's amenities (the suggestions).
 * @param {Function} props.onChange  Called with the new names.
 * @param {boolean}  props.disabled  Read-only.
 * @return {JSX.Element} Control.
 */
export default function HighlightPicker( {
	value = [],
	offered = [],
	onChange,
	disabled = false,
} ) {
	const { data: library } = useAmenities();
	const iconOf = ( name ) =>
		( library ?? [] ).find( ( amenity ) => same( amenity.name, name ) )
			?.icon || 'circle-check';

	// Only amenities the type still offers (one unticked above drops out).
	const chosen = value.filter( ( name ) =>
		offered.some( ( item ) => same( item, name ) )
	);
	const suggestions = offered.filter(
		( name ) => ! chosen.some( ( item ) => same( item, name ) )
	);
	const full = chosen.length >= MAX_HIGHLIGHTS;

	const moveEarlier = ( index ) => {
		const next = [ ...chosen ];
		[ next[ index - 1 ], next[ index ] ] = [
			next[ index ],
			next[ index - 1 ],
		];
		onChange( next );
	};

	if ( ! offered.length ) {
		return (
			<p className="m-0 rounded-lg border border-dashed border-border p-3 text-sm text-muted-foreground">
				{ __(
					'Tick the amenities this room type offers first; they are suggested here.',
					'radius-hotel-booking'
				) }
			</p>
		);
	}

	return (
		<div className="space-y-3 rounded-lg border border-border p-3">
			<div className="flex flex-wrap items-center gap-x-3 gap-y-2">
				<span className="text-sm font-medium text-heading">
					{ sprintf(
						/* translators: 1: highlighted amenities, 2: most allowed. */
						__(
							'%1$d of %2$d on the card',
							'radius-hotel-booking'
						),
						chosen.length,
						MAX_HIGHLIGHTS
					) }
				</span>
				{ ! disabled && chosen.length ? (
					<Button
						type="button"
						variant="ghost"
						size="sm"
						onClick={ () => onChange( [] ) }
					>
						{ __( 'Clear', 'radius-hotel-booking' ) }
					</Button>
				) : null }
			</div>

			{ chosen.length ? (
				<ol className="m-0 flex list-none flex-wrap gap-2 p-0">
					{ chosen.map( ( name, index ) => (
						<li
							key={ name }
							className="inline-flex items-center gap-1.5 rounded-full border border-primary bg-primary-soft py-1 pl-2.5 pr-1 text-sm text-foreground"
						>
							<span className="text-xs font-semibold text-muted-foreground">
								{ index + 1 }
							</span>
							<LucideIcon
								name={ iconOf( name ) }
								className="h-4 w-4 text-primary"
							/>
							<span>{ name }</span>
							{ ! disabled && index > 0 ? (
								<button
									type="button"
									onClick={ () => moveEarlier( index ) }
									className="inline-flex h-6 w-6 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
									aria-label={ sprintf(
										/* translators: %s: amenity name. */
										__(
											'Move %s earlier',
											'radius-hotel-booking'
										),
										name
									) }
								>
									<ArrowLeft
										className="h-3.5 w-3.5"
										aria-hidden="true"
									/>
								</button>
							) : null }
							{ ! disabled ? (
								<button
									type="button"
									onClick={ () =>
										onChange(
											chosen.filter(
												( item ) => item !== name
											)
										)
									}
									className="inline-flex h-6 w-6 items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
									aria-label={ sprintf(
										/* translators: %s: amenity name. */
										__(
											'Remove %s',
											'radius-hotel-booking'
										),
										name
									) }
								>
									<X
										className="h-3.5 w-3.5"
										aria-hidden="true"
									/>
								</button>
							) : null }
						</li>
					) ) }
				</ol>
			) : (
				<p className="m-0 text-[13px] leading-5 text-muted-foreground">
					{ __(
						'None picked: the card shows the first amenities of the list.',
						'radius-hotel-booking'
					) }
				</p>
			) }

			{ ! disabled && suggestions.length ? (
				<div className="space-y-2 border-t border-border pt-3">
					<p className="m-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
						{ __( 'Suggestions', 'radius-hotel-booking' ) }
					</p>
					<div className="flex flex-wrap gap-1.5">
						{ suggestions.map( ( name ) => (
							<button
								key={ name }
								type="button"
								disabled={ full }
								onClick={ () =>
									onChange( [ ...chosen, name ] )
								}
								className={ cn(
									'inline-flex items-center gap-1.5 rounded-full border border-border bg-card px-2.5 py-1 text-sm text-foreground',
									full
										? 'cursor-not-allowed opacity-50'
										: 'hover:border-primary hover:text-primary'
								) }
							>
								<Plus
									className="h-3.5 w-3.5"
									aria-hidden="true"
								/>
								<LucideIcon
									name={ iconOf( name ) }
									className="h-4 w-4 text-primary"
								/>
								{ name }
							</button>
						) ) }
					</div>
					{ full ? (
						<p className="m-0 text-xs text-muted-foreground">
							{ sprintf(
								/* translators: %d: most highlighted amenities. */
								__(
									'The card holds %d. Remove one to pick another.',
									'radius-hotel-booking'
								),
								MAX_HIGHLIGHTS
							) }
						</p>
					) : null }
				</div>
			) : null }
		</div>
	);
}
