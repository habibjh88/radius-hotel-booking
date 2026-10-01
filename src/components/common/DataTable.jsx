/**
 * Server-driven data table: the list screens' workhorse (bookings, guests,
 * payments, activity log). The server pages, sorts and filters; this renders
 * a table on wide screens and cards on phones, with row actions, loading,
 * empty and error states, and a pager.
 *
 * Deliberately not TanStack Table: every list here is server-driven, so an
 * in-browser table engine would add ~50 KB for nothing (M00 T7b).
 *
 *   <DataTable
 *       columns={ [
 *           { id: 'reference', header: __( 'Ref', … ), cell: ( b ) => b.reference, sortable: true, mobile: 'title' },
 *           { id: 'guest', header: __( 'Guest', … ), cell: ( b ) => b.guest, mobile: 'subtitle' },
 *           { id: 'total', header: __( 'Total', … ), cell: ( b ) => <Money value={ b.total } />, align: 'right' },
 *       ] }
 *       rows={ data.items } total={ data.total }
 *       page={ page } perPage={ 20 } onPageChange={ setPage }
 *       sort={ { by: 'created_at', dir: 'desc' } } onSortChange={ setSort }
 *       rowActions={ ( b ) => [ { label: __( 'Approve', … ), onSelect: …, primary: true }, … ] }
 *       loading={ isPending } error={ error } onRetry={ refetch }
 *       search={ { value: q, onChange: setQ, placeholder: … } }
 *       toolbar={ <DateRangePicker … /> }
 *       empty={ <EmptyState … /> }
 *   />
 *
 * Column options: `id`, `header`, `cell( row )`, `sortable`, `align`
 * ('left' | 'right' | 'center'), `className`, `wrap` (long text wraps and, on
 * phones, spans the card's full width; cells are single-line otherwise), and
 * `mobile`: 'title' (card heading), 'subtitle' (under it), 'hidden', or
 * default (label: value row).
 */
import { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	AlertCircle,
	ArrowDown,
	ArrowUp,
	ArrowUpDown,
	ChevronLeft,
	ChevronRight,
	MoreHorizontal,
	Search,
} from 'lucide-react';

import { Button } from '@/components/ui/button';
import {
	DropdownMenu,
	DropdownMenuContent,
	DropdownMenuItem,
	DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';

const ALIGN = { left: 'text-left', right: 'text-right', center: 'text-center' };

/**
 * Search box that reports changes after the user pauses typing.
 *
 * @param {Object}   props             Props.
 * @param {string}   props.value       Current search.
 * @param {Function} props.onChange    Called with the new search (debounced).
 * @param {string}   props.placeholder Placeholder.
 * @return {JSX.Element} Search input.
 */
function SearchBox( { value, onChange, placeholder } ) {
	const [ draft, setDraft ] = useState( value || '' );
	const timer = useRef();

	useEffect( () => setDraft( value || '' ), [ value ] );
	useEffect( () => () => clearTimeout( timer.current ), [] );

	return (
		<div className="relative w-full sm:w-64">
			<Search
				className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
				aria-hidden="true"
			/>
			<Input
				type="search"
				value={ draft }
				placeholder={
					placeholder || __( 'Search…', 'radius-hotel-booking' )
				}
				aria-label={
					placeholder || __( 'Search', 'radius-hotel-booking' )
				}
				className="h-10 pl-9"
				onChange={ ( event ) => {
					const next = event.target.value;
					setDraft( next );
					clearTimeout( timer.current );
					timer.current = setTimeout( () => onChange( next ), 300 );
				} }
			/>
		</div>
	);
}

/**
 * Row actions: the `primary` one as a button, the rest in a ⋯ menu.
 *
 * @param {Object} props         Props.
 * @param {Array}  props.actions `[{ label, onSelect, icon, primary, destructive, disabled }]`.
 * @param {boolean} props.stretch Full-width buttons (mobile cards).
 * @return {JSX.Element|null} Actions.
 */
function RowActions( { actions, stretch = false } ) {
	const visible = ( actions || [] ).filter( Boolean );
	if ( ! visible.length ) {
		return null;
	}
	const primary = visible.find( ( action ) => action.primary );
	const rest = visible.filter( ( action ) => action !== primary );

	return (
		<div
			className={ cn(
				'flex items-center justify-end gap-2',
				stretch && 'w-full'
			) }
		>
			{ primary ? (
				<Button
					size="sm"
					variant={ primary.destructive ? 'destructive' : 'outline' }
					disabled={ primary.disabled }
					onClick={ ( event ) => {
						event.stopPropagation();
						primary.onSelect();
					} }
					className={ cn( stretch && 'flex-1' ) }
				>
					{ primary.icon ? (
						<primary.icon aria-hidden="true" />
					) : null }
					{ primary.label }
				</Button>
			) : null }
			{ rest.length ? (
				<DropdownMenu>
					<DropdownMenuTrigger asChild>
						<Button
							size="sm"
							variant="ghost"
							className="w-8 px-0"
							aria-label={ __(
								'More actions',
								'radius-hotel-booking'
							) }
							onClick={ ( event ) => event.stopPropagation() }
						>
							<MoreHorizontal aria-hidden="true" />
						</Button>
					</DropdownMenuTrigger>
					<DropdownMenuContent
						align="end"
						className="min-w-[10rem]"
						// React bubbles clicks out of a portal to the row: an item must not open it too.
						onClick={ ( event ) => event.stopPropagation() }
					>
						{ rest.map( ( action ) => (
							<DropdownMenuItem
								key={ action.label }
								disabled={ action.disabled }
								onSelect={ () => action.onSelect() }
								className={ cn(
									'cursor-pointer',
									action.destructive &&
										'text-destructive focus:text-destructive'
								) }
							>
								{ action.icon ? (
									<action.icon aria-hidden="true" />
								) : null }
								{ action.label }
							</DropdownMenuItem>
						) ) }
					</DropdownMenuContent>
				</DropdownMenu>
			) : null }
		</div>
	);
}

/**
 * Sortable header cell.
 *
 * @param {Object}   props        Props.
 * @param {Object}   props.column Column.
 * @param {Object}   props.sort   `{ by, dir }`.
 * @param {Function} props.onSort Called with the new sort.
 * @return {JSX.Element} Header content.
 */
function HeaderCell( { column, sort, onSort } ) {
	if ( ! column.sortable || ! onSort ) {
		return column.header;
	}
	const activeDir = sort?.by === column.id ? sort.dir : null;
	const Icon =
		activeDir === 'asc'
			? ArrowUp
			: activeDir === 'desc'
			? ArrowDown
			: ArrowUpDown;

	return (
		<button
			type="button"
			onClick={ () =>
				onSort( {
					by: column.id,
					dir: activeDir === 'asc' ? 'desc' : 'asc',
				} )
			}
			className={ cn(
				'inline-flex items-center gap-1 border-0 bg-transparent p-0 text-xs font-semibold uppercase tracking-wide',
				activeDir
					? 'text-heading'
					: 'text-muted-foreground hover:text-heading'
			) }
		>
			{ column.header }
			<Icon className="h-3.5 w-3.5" aria-hidden="true" />
		</button>
	);
}

/**
 * @param {Object}   props              Props.
 * @param {Array}    props.columns      Column definitions (see file header).
 * @param {Array}    props.rows         Rows of the current page.
 * @param {Function} props.rowKey       Row → unique key (default: row.id).
 * @param {number}   props.total        Total rows on the server.
 * @param {number}   props.page         Current page (1-based).
 * @param {number}   props.perPage      Rows per page.
 * @param {Function} props.onPageChange Called with the new page.
 * @param {Object}   props.sort         `{ by, dir }`.
 * @param {Function} props.onSortChange Called with the new sort.
 * @param {Function} props.rowActions   Row → actions.
 * @param {Function} props.onRowClick   Row → void (opens a detail screen).
 * @param {boolean}  props.loading      Loading.
 * @param {Error}    props.error        Load error.
 * @param {Function} props.onRetry      Retry after an error.
 * @param {Object}   props.search       `{ value, onChange, placeholder }`.
 * @param {JSX.Element} props.toolbar   Filters shown next to the search.
 * @param {JSX.Element} props.empty     Shown when there are no rows.
 * @param {string}   props.caption      Accessible table caption.
 * @return {JSX.Element} Table.
 */
export default function DataTable( {
	columns,
	rows = [],
	rowKey = ( row ) => row.id,
	total = 0,
	page = 1,
	perPage = 20,
	onPageChange,
	sort,
	onSortChange,
	rowActions,
	onRowClick,
	loading = false,
	error = null,
	onRetry,
	search,
	toolbar,
	empty,
	caption,
} ) {
	const pages = Math.max( 1, Math.ceil( total / perPage ) );
	const firstRow = total ? ( page - 1 ) * perPage + 1 : 0;
	const lastRow = Math.min( total, page * perPage );
	const showSkeleton = loading && ! rows.length;
	const titleColumn =
		columns.find( ( c ) => c.mobile === 'title' ) || columns[ 0 ];
	const subtitleColumn = columns.find( ( c ) => c.mobile === 'subtitle' );
	const detailColumns = columns.filter(
		( c ) =>
			c !== titleColumn && c !== subtitleColumn && c.mobile !== 'hidden'
	);

	const rowProps = ( row ) =>
		onRowClick
			? {
					onClick: () => onRowClick( row ),
					onKeyDown: ( event ) => {
						if ( event.key === 'Enter' ) {
							onRowClick( row );
						}
					},
					tabIndex: 0,
					className: 'cursor-pointer',
			  }
			: {};

	return (
		<div className="min-w-0 space-y-3">
			{ search || toolbar ? (
				<div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center">
					{ search ? <SearchBox { ...search } /> : null }
					{ toolbar ? (
						<div className="flex flex-wrap items-center gap-2">
							{ toolbar }
						</div>
					) : null }
				</div>
			) : null }

			<div className="min-w-0 overflow-hidden rounded-xl border border-border bg-card shadow-sm">
				{ error ? (
					<div role="alert" className="flex items-center gap-3 p-5">
						<AlertCircle
							className="h-5 w-5 shrink-0 text-destructive"
							aria-hidden="true"
						/>
						<p className="m-0 flex-1 text-sm text-heading">
							{ error.message ||
								__(
									'The list could not be loaded.',
									'radius-hotel-booking'
								) }
						</p>
						{ onRetry ? (
							<Button
								variant="outline"
								size="sm"
								onClick={ () => onRetry() }
							>
								{ __( 'Try again', 'radius-hotel-booking' ) }
							</Button>
						) : null }
					</div>
				) : null }

				{ ! error && showSkeleton ? (
					<div
						className="space-y-2 p-4"
						role="status"
						aria-live="polite"
					>
						{ Array.from( { length: 5 } ).map( ( _, index ) => (
							<Skeleton key={ index } className="h-11 w-full" />
						) ) }
					</div>
				) : null }

				{ ! error && ! loading && ! rows.length ? (
					<div className="p-5">{ empty }</div>
				) : null }

				{ ! error && rows.length ? (
					<div
						className={ cn(
							'transition-opacity',
							loading && 'pointer-events-none opacity-60'
						) }
					>
						{ /* Wide screens: a real table. `relative` keeps absolutely positioned
							   children (the sr-only "Actions" label) inside the scroller;
							   otherwise they escape it and widen the whole page. */ }
						<div className="relative hidden overflow-x-auto md:block">
							<table className="w-full border-collapse text-sm">
								{ caption ? (
									<caption className="sr-only">
										{ caption }
									</caption>
								) : null }
								<thead>
									<tr className="bg-muted">
										{ columns.map( ( column ) => (
											<th
												key={ column.id }
												scope="col"
												aria-sort={
													sort?.by === column.id
														? sort.dir === 'asc'
															? 'ascending'
															: 'descending'
														: undefined
												}
												className={ cn(
													'whitespace-nowrap px-4 py-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground',
													ALIGN[
														column.align || 'left'
													]
												) }
											>
												<HeaderCell
													column={ column }
													sort={ sort }
													onSort={ onSortChange }
												/>
											</th>
										) ) }
										{ rowActions ? (
											<th
												scope="col"
												className="sticky right-0 bg-muted px-4 py-3"
											>
												<span className="sr-only">
													{ __(
														'Actions',
														'radius-hotel-booking'
													) }
												</span>
											</th>
										) : null }
									</tr>
								</thead>
								<tbody>
									{ rows.map( ( row ) => {
										const extra = rowProps( row );
										return (
											<tr
												key={ rowKey( row ) }
												{ ...extra }
												className={ cn(
													'group border-t border-border hover:bg-primary-softer',
													extra.className
												) }
											>
												{ columns.map( ( column ) => (
													<td
														key={ column.id }
														className={ cn(
															'px-4 py-3 align-middle text-foreground',
															// One line by default (references, amounts); the table
															// scrolls inside its card. `wrap: true` for long text.
															column.wrap
																? 'min-w-[12rem]'
																: 'whitespace-nowrap',
															ALIGN[
																column.align ||
																	'left'
															],
															column.className
														) }
													>
														{ column.cell( row ) }
													</td>
												) ) }
												{ rowActions ? (
													<td className="sticky right-0 whitespace-nowrap bg-card px-4 py-2 text-right shadow-[-8px_0_8px_-8px_rgba(0,0,0,0.12)] group-hover:bg-primary-softer">
														<RowActions
															actions={ rowActions(
																row
															) }
														/>
													</td>
												) : null }
											</tr>
										);
									} ) }
								</tbody>
							</table>
						</div>

						{ /* Phones: one card per row. */ }
						<ul className="m-0 list-none divide-y divide-border p-0 md:hidden">
							{ rows.map( ( row ) => {
								const extra = rowProps( row );
								return (
									<li key={ rowKey( row ) } className="m-0">
										<div
											{ ...extra }
											className={ cn(
												'space-y-2 p-4',
												extra.className
											) }
										>
											<div className="min-w-0">
												<p className="m-0 truncate text-sm font-semibold text-heading">
													{ titleColumn.cell( row ) }
												</p>
												{ subtitleColumn ? (
													<p className="m-0 mt-0.5 truncate text-[13px] text-muted-foreground">
														{ subtitleColumn.cell(
															row
														) }
													</p>
												) : null }
											</div>
											{ detailColumns.length ? (
												<dl className="m-0 grid grid-cols-2 gap-x-3 gap-y-1.5">
													{ detailColumns.map(
														( column ) => (
															<div
																key={
																	column.id
																}
																className={ cn(
																	'min-w-0',
																	column.wrap &&
																		'col-span-2'
																) }
															>
																<dt className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
																	{
																		column.header
																	}
																</dt>
																<dd className="m-0 break-words text-[13px] text-foreground">
																	{ column.cell(
																		row
																	) }
																</dd>
															</div>
														)
													) }
												</dl>
											) : null }
										</div>
										{ rowActions ? (
											<div className="px-4 pb-4">
												<RowActions
													actions={ rowActions(
														row
													) }
													stretch
												/>
											</div>
										) : null }
									</li>
								);
							} ) }
						</ul>
					</div>
				) : null }

				{ ! error && total > 0 && onPageChange ? (
					<div className="flex flex-wrap items-center justify-between gap-2 border-t border-border px-4 py-3">
						<p className="m-0 text-[13px] text-muted-foreground">
							{ sprintf(
								/* translators: 1: first row number, 2: last row number, 3: total rows. */
								__(
									'Showing %1$d–%2$d of %3$d',
									'radius-hotel-booking'
								),
								firstRow,
								lastRow,
								total
							) }
						</p>
						<div className="flex items-center gap-2">
							<Button
								variant="outline"
								size="sm"
								disabled={ page <= 1 || loading }
								onClick={ () => onPageChange( page - 1 ) }
								aria-label={ __(
									'Previous page',
									'radius-hotel-booking'
								) }
							>
								<ChevronLeft aria-hidden="true" />
							</Button>
							<span className="text-[13px] tabular-nums text-heading">
								{ sprintf(
									/* translators: 1: current page, 2: number of pages. */
									__(
										'Page %1$d of %2$d',
										'radius-hotel-booking'
									),
									page,
									pages
								) }
							</span>
							<Button
								variant="outline"
								size="sm"
								disabled={ page >= pages || loading }
								onClick={ () => onPageChange( page + 1 ) }
								aria-label={ __(
									'Next page',
									'radius-hotel-booking'
								) }
							>
								<ChevronRight aria-hidden="true" />
							</Button>
						</div>
					</div>
				) : null }
			</div>
		</div>
	);
}
