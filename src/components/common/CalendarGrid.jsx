/**
 * CalendarGrid (design system §5): dates across, rows down, with the date
 * header stuck to the top and the row labels stuck to the left while the
 * grid scrolls both ways. The availability calendar (M08) is its first user;
 * reports and add-ons reuse it through `window.rtbp.ui`.
 *
 * A month is at most 31 columns, so the grid scrolls natively instead of
 * virtualising (the design system allowed for either). When today is in the
 * range, the grid opens scrolled so today is the first visible date.
 */
import { useEffect, useRef } from 'react';
import { __ } from '@wordpress/i18n';

import { formatDate, formatDateAs } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * Whether a `Y-m-d` date is a Saturday or Sunday.
 *
 * @param {string} ymd Date.
 * @return {boolean} Weekend.
 */
const isWeekend = ( ymd ) => {
	const day = new Date( `${ ymd }T12:00:00Z` ).getUTCDay();
	return day === 0 || day === 6;
};

/**
 * @param {Object}   props               Props.
 * @param {string[]} props.dates         `Y-m-d` columns.
 * @param {string}   props.today         Today (`Y-m-d`, site time), highlighted.
 * @param {Object[]} props.rows          `{ key, label, sublabel?, cells: any[] }`, one cell per date.
 * @param {Function} props.renderCell    `( row, cell, index ) => node`: a cell's content.
 * @param {Function} props.onCellClick   `( row, cell, index ) => void`; omit for a read-only grid.
 * @param {Function} props.cellClassName `( row, cell, index ) => string`: tone classes.
 * @param {Function} props.cellLabel     `( row, cell, index ) => string`: the cell's accessible name.
 * @param {Function} props.isDisabled    `( row, cell, index ) => boolean`: not clickable (a past date).
 * @param {string}   props.corner        Header of the label column.
 * @param {string}   props.caption       Table caption (screen readers).
 * @param {string}   props.className     Extra classes for the scroll container.
 * @return {JSX.Element} Grid.
 */
export default function CalendarGrid( {
	dates,
	today = '',
	rows,
	renderCell,
	onCellClick,
	cellClassName,
	cellLabel,
	isDisabled,
	corner = '',
	caption = '',
	className = '',
} ) {
	const scroller = useRef( null );
	const hasToday = dates.includes( today );
	useEffect( () => {
		const box = scroller.current;
		const cell = box?.querySelector( '[data-today="true"]' );
		const corner = box?.querySelector( 'thead th' );
		if ( box && cell && corner ) {
			box.scrollLeft = Math.max(
				0,
				cell.offsetLeft - corner.offsetWidth
			);
		}
	}, [ hasToday, dates[ 0 ] ] );

	return (
		<div
			ref={ scroller }
			className={ cn(
				'relative max-h-[70vh] overflow-auto rounded-lg border border-border',
				className
			) }
		>
			<table className="m-0 w-max min-w-full border-separate border-spacing-0 text-sm">
				{ caption ? (
					<caption className="sr-only">{ caption }</caption>
				) : null }
				<thead>
					<tr>
						<th
							scope="col"
							className="sticky left-0 top-0 z-30 min-w-[7.5rem] sm:min-w-[9rem] border-b border-r border-border bg-card px-3 py-2 text-left text-xs font-semibold text-muted-foreground"
						>
							{ corner }
						</th>
						{ dates.map( ( date ) => (
							<th
								key={ date }
								scope="col"
								data-today={ date === today || undefined }
								aria-label={ formatDate( date ) }
								className={ cn(
									'sticky top-0 z-20 min-w-[5.5rem] border-b border-border bg-card px-1 py-1.5 text-center font-normal',
									isWeekend( date ) && 'bg-muted',
									date === today && 'text-primary'
								) }
							>
								<span className="block text-[11px] uppercase text-muted-foreground">
									{ formatDateAs( date, 'D' ) }
								</span>
								<span
									className={ cn(
										'mx-auto mt-0.5 flex h-6 w-6 items-center justify-center rounded-full text-sm font-semibold text-heading',
										date === today &&
											'bg-primary text-primary-foreground'
									) }
								>
									{ Number( date.slice( 8, 10 ) ) }
								</span>
								{ date === today ? (
									<span className="sr-only">
										{ __(
											'Today',
											'radius-hotel-booking'
										) }
									</span>
								) : null }
							</th>
						) ) }
					</tr>
				</thead>
				<tbody>
					{ rows.map( ( row ) => (
						<tr key={ row.key }>
							<th
								scope="row"
								className="sticky left-0 z-10 border-b border-r border-border bg-card px-3 py-2 text-left align-middle font-normal"
							>
								<span className="block max-w-[6.5rem] truncate sm:max-w-[11rem] text-sm font-semibold text-heading">
									{ row.label }
								</span>
								{ row.sublabel ? (
									<span className="block max-w-[6.5rem] truncate sm:max-w-[11rem] text-xs text-muted-foreground">
										{ row.sublabel }
									</span>
								) : null }
							</th>
							{ row.cells.map( ( cell, index ) => {
								const disabled =
									! onCellClick ||
									( isDisabled &&
										isDisabled( row, cell, index ) );
								const classes = cn(
									'block h-full min-h-[3.25rem] w-full px-1 py-1.5 text-center',
									cellClassName &&
										cellClassName( row, cell, index )
								);
								const label = cellLabel
									? cellLabel( row, cell, index )
									: undefined;
								return (
									<td
										key={ dates[ index ] }
										// Weekends are marked in the header only, so a grey
										// cell always means the cell's own state (closed).
										className="border-b border-border p-0 align-middle"
									>
										{ disabled ? (
											<div
												className={ cn(
													classes,
													'opacity-60'
												) }
												aria-label={ label }
											>
												{ renderCell(
													row,
													cell,
													index
												) }
											</div>
										) : (
											<button
												type="button"
												onClick={ () =>
													onCellClick(
														row,
														cell,
														index
													)
												}
												aria-label={ label }
												className={ cn(
													// The cell's tone comes last so its background wins (tailwind-merge).
													'cursor-pointer border-0 bg-transparent hover:ring-2 hover:ring-inset hover:ring-ring focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring',
													classes
												) }
											>
												{ renderCell(
													row,
													cell,
													index
												) }
											</button>
										) }
									</td>
								);
							} ) }
						</tr>
					) ) }
				</tbody>
			</table>
		</div>
	);
}
