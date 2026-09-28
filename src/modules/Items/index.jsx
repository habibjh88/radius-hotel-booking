import { useCallback, useEffect, useMemo, useState } from 'react';
import {
	flexRender,
	getCoreRowModel,
	useReactTable,
} from '@tanstack/react-table';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import { __, sprintf } from '@wordpress/i18n';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import {
	Table,
	TableBody,
	TableCell,
	TableHead,
	TableHeader,
	TableRow,
} from '@/components/ui/table';

import ItemFormDialog from './ItemFormDialog';
import { deleteItem, fetchItems } from './api';

const PER_PAGE = 10;

const STATUS_VARIANT = {
	published: 'default',
	draft: 'secondary',
	archived: 'outline',
};

/**
 * Items screen: a paginated, searchable table over `GET /items` plus a
 * create/edit dialog. This is the reference implementation for a CRUD screen —
 * copy the folder for your own resource.
 *
 * @return {JSX.Element} Screen.
 */
export default function Items() {
	const [ rows, setRows ] = useState( [] );
	const [ pagination, setPagination ] = useState( {
		current_page: 1,
		last_page: 1,
	} );
	const [ search, setSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ editing, setEditing ] = useState( null );
	const [ dialogOpen, setDialogOpen ] = useState( false );

	const load = useCallback( async () => {
		setLoading( true );
		setError( '' );

		try {
			const { data, meta } = await fetchItems( {
				page,
				per_page: PER_PAGE,
				search_column: search ? 'title' : undefined,
				search_data: search || undefined,
			} );

			setRows( Array.isArray( data ) ? data : [] );
			setPagination(
				meta.pagination ?? { current_page: 1, last_page: 1 }
			);
		} catch ( requestError ) {
			setError( requestError.message );
		} finally {
			setLoading( false );
		}
	}, [ page, search ] );

	useEffect( () => {
		load();
	}, [ load ] );

	const onDelete = async ( id ) => {
		// A real plugin should swap this for a confirmation dialog; the
		// hotel booking keeps the example dependency-free.
		// eslint-disable-next-line no-alert
		const confirmed = window.confirm(
			__( 'Delete this item?', 'radius-hotel-booking' )
		);

		if ( ! confirmed ) {
			return;
		}

		try {
			await deleteItem( id );
			load();
		} catch ( requestError ) {
			setError( requestError.message );
		}
	};

	const columns = useMemo(
		() => [
			{
				accessorKey: 'title',
				header: __( 'Title', 'radius-hotel-booking' ),
			},
			{
				accessorKey: 'status',
				header: __( 'Status', 'radius-hotel-booking' ),
				cell: ( { getValue } ) => (
					<Badge
						variant={ STATUS_VARIANT[ getValue() ] ?? 'secondary' }
					>
						{ getValue() }
					</Badge>
				),
			},
			{
				accessorKey: 'price',
				header: __( 'Price', 'radius-hotel-booking' ),
				cell: ( { getValue } ) =>
					Number( getValue() || 0 ).toFixed( 2 ),
			},
			{
				id: 'actions',
				header: '',
				cell: ( { row } ) => (
					<div className="flex justify-end gap-1">
						<Button
							size="icon"
							variant="ghost"
							aria-label={ __( 'Edit', 'radius-hotel-booking' ) }
							onClick={ () => {
								setEditing( row.original );
								setDialogOpen( true );
							} }
						>
							<Pencil className="h-4 w-4" />
						</Button>
						<Button
							size="icon"
							variant="ghost"
							aria-label={ __( 'Delete', 'radius-hotel-booking' ) }
							onClick={ () => onDelete( row.original.id ) }
						>
							<Trash2 className="h-4 w-4 text-destructive" />
						</Button>
					</div>
				),
			},
		],
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[]
	);

	const table = useReactTable( {
		data: rows,
		columns,
		getCoreRowModel: getCoreRowModel(),
	} );

	/**
	 * Table body: skeletons while loading, rows when there are any, an empty
	 * state otherwise. Extracted from the JSX so the three cases read as three
	 * cases rather than a nested ternary.
	 *
	 * @return {JSX.Element|JSX.Element[]} Rows.
	 */
	function renderRows() {
		if ( loading ) {
			return Array.from( { length: 3 } ).map( ( _, index ) => (
				// eslint-disable-next-line react/no-array-index-key
				<TableRow key={ index }>
					<TableCell colSpan={ columns.length }>
						<Skeleton className="h-5 w-full" />
					</TableCell>
				</TableRow>
			) );
		}

		if ( ! table.getRowModel().rows.length ) {
			return (
				<TableRow>
					<TableCell
						colSpan={ columns.length }
						className="py-10 text-center text-muted-foreground"
					>
						{ __( 'No items yet.', 'radius-hotel-booking' ) }
					</TableCell>
				</TableRow>
			);
		}

		return table.getRowModel().rows.map( ( row ) => (
			<TableRow key={ row.id }>
				{ row.getVisibleCells().map( ( cell ) => (
					<TableCell key={ cell.id }>
						{ flexRender(
							cell.column.columnDef.cell,
							cell.getContext()
						) }
					</TableCell>
				) ) }
			</TableRow>
		) );
	}

	return (
		<div className="space-y-4">
			<div className="flex flex-wrap items-center justify-between gap-3">
				<Input
					className="max-w-xs"
					placeholder={ __( 'Search items…', 'radius-hotel-booking' ) }
					value={ search }
					onChange={ ( event ) => {
						setPage( 1 );
						setSearch( event.target.value );
					} }
				/>

				<Button
					onClick={ () => {
						setEditing( null );
						setDialogOpen( true );
					} }
				>
					<Plus className="mr-1 h-4 w-4" />
					{ __( 'Add item', 'radius-hotel-booking' ) }
				</Button>
			</div>

			{ error ? (
				<p className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
					{ error }
				</p>
			) : null }

			<div className="rounded-md border border-border bg-card">
				<Table>
					<TableHeader>
						{ table.getHeaderGroups().map( ( headerGroup ) => (
							<TableRow key={ headerGroup.id }>
								{ headerGroup.headers.map( ( header ) => (
									<TableHead key={ header.id }>
										{ header.isPlaceholder
											? null
											: flexRender(
													header.column.columnDef
														.header,
													header.getContext()
											  ) }
									</TableHead>
								) ) }
							</TableRow>
						) ) }
					</TableHeader>

					<TableBody>{ renderRows() }</TableBody>
				</Table>
			</div>

			<div className="flex items-center justify-between text-sm text-muted-foreground">
				<span>
					{ sprintf(
						/* translators: 1: current page, 2: total pages. */
						__( 'Page %1$d of %2$d', 'radius-hotel-booking' ),
						pagination.current_page ?? 1,
						pagination.last_page ?? 1
					) }
				</span>

				<div className="flex gap-2">
					<Button
						size="sm"
						variant="outline"
						disabled={ ( pagination.current_page ?? 1 ) <= 1 }
						onClick={ () =>
							setPage( ( current ) => Math.max( 1, current - 1 ) )
						}
					>
						{ __( 'Previous', 'radius-hotel-booking' ) }
					</Button>
					<Button
						size="sm"
						variant="outline"
						disabled={
							( pagination.current_page ?? 1 ) >=
							( pagination.last_page ?? 1 )
						}
						onClick={ () => setPage( ( current ) => current + 1 ) }
					>
						{ __( 'Next', 'radius-hotel-booking' ) }
					</Button>
				</div>
			</div>

			<ItemFormDialog
				open={ dialogOpen }
				item={ editing }
				onClose={ () => setDialogOpen( false ) }
				onSaved={ load }
			/>
		</div>
	);
}
