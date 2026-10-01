/**
 * Exports (M11, the legacy "Booking Backup"): make a file of the bookings
 * (or another kind) for a period, and keep the library of past files.
 * Files hold every guest's identity document: they live in protected
 * storage and need `exports.download`.
 *
 * Add-ons add panels between the form and the library with the
 * `rtbp.exports.panels` filter: `{ key, Component( { kinds, formats } ) }`
 * (Pro: archive and remove). The library refreshes on the `[ 'exports' ]`
 * query key.
 */
import { Suspense, useMemo, useState } from 'react';
import { applyFilters } from '@wordpress/hooks';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Download, FileSpreadsheet, ShieldAlert, Trash2 } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import DateRangePicker from '@/components/common/DateRangePicker';
import DateTime from '@/components/common/DateTime';
import EmptyState from '@/components/common/EmptyState';
import { Field } from '@/components/common/Form';
import Panel from '@/components/common/Panel';
import SegmentedControl from '@/components/common/SegmentedControl';
import { Button } from '@/components/ui/button';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { useAccess } from '@/lib/access';
import { siteToday } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useDeleteExport, useExports, useGenerateExport } from './api';

/**
 * A file size in words.
 *
 * @param {number} bytes Bytes.
 * @return {string} e.g. "1.2 MB".
 */
const size = ( bytes ) => {
	if ( bytes >= 1048576 ) {
		return sprintf(
			/* translators: %s: size in megabytes. */
			__( '%s MB', 'radius-hotel-booking' ),
			( bytes / 1048576 ).toFixed( 1 )
		);
	}
	return sprintf(
		/* translators: %s: size in kilobytes. */
		__( '%s KB', 'radius-hotel-booking' ),
		Math.max( 1, Math.round( bytes / 1024 ) )
	);
};

/**
 * @return {JSX.Element} Screen.
 */
export default function Exports() {
	const today = siteToday();
	const [ page, setPage ] = useState( 1 );
	const [ form, setForm ] = useState( {
		kind: 'bookings',
		from: `${ today.slice( 0, 8 ) }01`,
		to: today,
		mode: 'arrival',
		format: 'csv',
	} );
	const library = useExports( page );
	const generate = useGenerateExport();
	const canGenerate = useAccess( 'exports.generate' ) !== 'locked';
	const canDelete = useAccess( 'exports.delete' ) !== 'locked';
	const remove = useDeleteExport();
	// The row waiting for the delete confirmation.
	const [ deleting, setDeleting ] = useState( null );
	// Add-on panels between the form and the library (Pro: archive and remove).
	const panels = useMemo(
		() =>
			( applyFilters( 'rtbp.exports.panels', [] ) || [] ).filter(
				( panel ) => panel?.key && panel.Component
			),
		[]
	);

	const kinds = library.data?.kinds || [];
	const formats = library.data?.formats || [];
	const set = ( changes ) =>
		setForm( ( prev ) => ( { ...prev, ...changes } ) );

	const run = () =>
		generate
			.mutateAsync( form )
			.then( ( row ) =>
				toast.success(
					sprintf(
						/* translators: 1: file name, 2: number of rows. */
						_n(
							'%1$s is ready: %2$d row.',
							'%1$s is ready: %2$d rows.',
							row.row_count,
							'radius-hotel-booking'
						),
						row.file?.name || '',
						row.row_count
					)
				)
			)
			.catch( toastError );

	const columns = [
		{
			id: 'created',
			header: __( 'Made', 'radius-hotel-booking' ),
			cell: ( row ) => <DateTime value={ row.created_at } />,
			mobile: 'title',
		},
		{
			id: 'kind',
			header: __( 'Contents', 'radius-hotel-booking' ),
			cell: ( row ) => row.kind_label,
			mobile: 'subtitle',
		},
		{
			id: 'period',
			header: __( 'Period', 'radius-hotel-booking' ),
			cell: ( row ) =>
				row.params?.from
					? sprintf(
							/* translators: 1: first day, 2: last day, 3: "by arrival" or "by booking date". */
							__( '%1$s → %2$s, %3$s', 'radius-hotel-booking' ),
							row.params.from,
							row.params.to,
							'created' === row.params.mode
								? __(
										'by booking date',
										'radius-hotel-booking'
								  )
								: __( 'by arrival', 'radius-hotel-booking' )
					  )
					: __( 'Whole list', 'radius-hotel-booking' ),
			wrap: true,
		},
		{
			id: 'format',
			header: __( 'Format', 'radius-hotel-booking' ),
			cell: ( row ) => row.format.toUpperCase(),
		},
		{
			id: 'rows',
			header: __( 'Rows', 'radius-hotel-booking' ),
			cell: ( row ) => row.row_count.toLocaleString(),
			align: 'right',
		},
		{
			id: 'size',
			header: __( 'Size', 'radius-hotel-booking' ),
			cell: ( row ) => ( row.file ? size( row.file.size ) : '—' ),
			align: 'right',
		},
		{
			id: 'by',
			header: __( 'Made by', 'radius-hotel-booking' ),
			cell: ( row ) =>
				'scheduled' === row.source
					? __( 'Scheduled', 'radius-hotel-booking' )
					: row.created_by || '—',
		},
	];

	return (
		<div className="space-y-5">
			{ canGenerate ? (
				<Panel
					title={ __( 'New export', 'radius-hotel-booking' ) }
					description={ __(
						'Bookings: one row per booked room, every status included. Guests: the whole list.',
						'radius-hotel-booking'
					) }
				>
					<div className="space-y-4">
						<div className="grid gap-4 md:grid-cols-2">
							<Field
								label={ __(
									'Contents',
									'radius-hotel-booking'
								) }
							>
								<Select
									value={ form.kind }
									onValueChange={ ( kind ) =>
										set( { kind } )
									}
								>
									<SelectTrigger
										className="w-full"
										aria-label={ __(
											'Contents',
											'radius-hotel-booking'
										) }
									>
										<SelectValue />
									</SelectTrigger>
									<SelectContent className="rtbp-root">
										{ ( kinds.length
											? kinds
											: [
													{
														key: 'bookings',
														label: __(
															'Bookings',
															'radius-hotel-booking'
														),
													},
											  ]
										).map( ( kind ) => (
											<SelectItem
												key={ kind.key }
												value={ kind.key }
											>
												{ kind.label }
											</SelectItem>
										) ) }
									</SelectContent>
								</Select>
							</Field>
							<Field
								label={ __( 'Format', 'radius-hotel-booking' ) }
							>
								<div>
									<SegmentedControl
										options={ ( formats.length
											? formats
											: [ { key: 'csv', label: 'CSV' } ]
										).map( ( format ) => ( {
											value: format.key,
											label: format.label,
										} ) ) }
										value={ form.format }
										onChange={ ( format ) =>
											set( { format } )
										}
										label={ __(
											'Format',
											'radius-hotel-booking'
										) }
									/>
								</div>
							</Field>
						</div>
						{ false !==
						kinds.find( ( k ) => k.key === form.kind )?.period ? (
							<Field
								label={ __( 'Period', 'radius-hotel-booking' ) }
							>
								<div>
									<DateRangePicker
										value={ {
											from: form.from,
											to: form.to,
										} }
										onChange={ ( next ) =>
											set( {
												from: next?.from || form.from,
												to: next?.to || form.to,
											} )
										}
										mode={ form.mode }
										onModeChange={ ( mode ) =>
											set( { mode } )
										}
									/>
								</div>
							</Field>
						) : (
							<p className="m-0 text-sm text-muted-foreground">
								{ __(
									'The whole list, whatever the date.',
									'radius-hotel-booking'
								) }
							</p>
						) }
						<div className="flex flex-wrap items-center gap-3">
							<Button
								type="button"
								onClick={ run }
								disabled={ generate.isPending }
							>
								<FileSpreadsheet aria-hidden="true" />
								{ generate.isPending
									? __(
											'Making the file…',
											'radius-hotel-booking'
									  )
									: __(
											'Make the file',
											'radius-hotel-booking'
									  ) }
							</Button>
							<p className="m-0 flex items-center gap-2 text-sm text-muted-foreground">
								<ShieldAlert
									className="h-4 w-4 shrink-0"
									aria-hidden="true"
								/>
								{ __(
									'Export files hold guests’ identity documents. Keep downloaded copies safe.',
									'radius-hotel-booking'
								) }
							</p>
						</div>
					</div>
				</Panel>
			) : null }

			{ panels.map( ( { key, Component } ) => (
				<Suspense key={ key } fallback={ null }>
					<Component kinds={ kinds } formats={ formats } />
				</Suspense>
			) ) }

			<Panel
				title={ __( 'File library', 'radius-hotel-booking' ) }
				description={ __(
					'Every file made, newest first.',
					'radius-hotel-booking'
				) }
			>
				<DataTable
					columns={ columns }
					rows={ library.data?.items || [] }
					total={ library.data?.total || 0 }
					page={ page }
					perPage={ 20 }
					onPageChange={ setPage }
					loading={ library.isPending }
					error={ library.error }
					onRetry={ () => library.refetch() }
					caption={ __( 'Export files', 'radius-hotel-booking' ) }
					rowActions={ ( row ) =>
						[
							row.download_url
								? {
										label: __(
											'Download',
											'radius-hotel-booking'
										),
										icon: Download,
										primary: true,
										onSelect: () =>
											window.location.assign(
												row.download_url
											),
								  }
								: null,
							canDelete
								? {
										label: __(
											'Delete',
											'radius-hotel-booking'
										),
										icon: Trash2,
										destructive: true,
										onSelect: () => setDeleting( row ),
								  }
								: null,
						].filter( Boolean )
					}
					empty={
						<EmptyState
							icon={ Download }
							title={ __(
								'No export yet',
								'radius-hotel-booking'
							) }
							description={ __(
								'Files you make appear here.',
								'radius-hotel-booking'
							) }
						/>
					}
				/>
			</Panel>

			<ConfirmDialog
				open={ Boolean( deleting ) }
				onOpenChange={ ( open ) => ! open && setDeleting( null ) }
				title={ __(
					'Delete this export file?',
					'radius-hotel-booking'
				) }
				description={ sprintf(
					/* translators: %s: file name. */
					__(
						'%s will be deleted for good. The data it copied stays in the system.',
						'radius-hotel-booking'
					),
					deleting?.file?.name || ''
				) }
				confirmLabel={ __( 'Delete the file', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove
						.mutateAsync( deleting.id )
						.then( () => {
							toast.success(
								__(
									'The export file was deleted.',
									'radius-hotel-booking'
								)
							);
							setDeleting( null );
						} )
						.catch( toastError )
				}
			/>
		</div>
	);
}
