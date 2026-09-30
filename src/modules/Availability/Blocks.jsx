/**
 * Blocked dates (`#/blocks`, feature 8.12): every closure of the property, a
 * floor, a room type or a room, coming ones first. Staff with
 * `availability.manage` add, change and remove their own blocks; blocks from
 * another source (Pro's calendar sync) are listed read-only.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Ban, Pencil, Plus, Trash2 } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import DataTable from '@/components/common/DataTable';
import EmptyState from '@/components/common/EmptyState';
import FilterTabs from '@/components/common/FilterTabs';
import Panel from '@/components/common/Panel';
import { usePageActions } from '@/components/layout/PageActions';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useAccess } from '@/lib/access';
import { toast } from '@/lib/toast';
import { useBlocks, useDeleteBlock } from './api';
import BlockEditor from './components/BlockEditor';
import { blockPeriod } from './blockText';

const PER_PAGE = 20;

export default function Blocks() {
	const canManage = useAccess( 'availability.manage' ) !== 'locked';
	const [ when, setWhen ] = useState( 'upcoming' );
	const [ page, setPage ] = useState( 1 );
	// null = closed, {} = new, a block = edit.
	const [ editing, setEditing ] = useState( null );
	const [ removing, setRemoving ] = useState( null );
	const list = useBlocks( { when, page, per_page: PER_PAGE } );
	const remove = useDeleteBlock();

	usePageActions(
		canManage ? (
			<Button onClick={ () => setEditing( {} ) }>
				<Plus className="h-4 w-4" aria-hidden="true" />
				<span className="sr-only sm:not-sr-only">
					{ __( 'Block dates', 'radius-hotel-booking' ) }
				</span>
			</Button>
		) : null,
		[ canManage ]
	);

	const columns = [
		{
			id: 'what',
			header: __( 'Blocked', 'radius-hotel-booking' ),
			mobile: 'title',
			cell: ( block ) => (
				<span className="inline-flex flex-wrap items-center gap-2">
					<span className="font-semibold text-heading">
						{ block.label }
					</span>
					{ block.source !== 'manual' ? (
						<Badge variant="secondary">
							{ block.source_label }
						</Badge>
					) : null }
				</span>
			),
		},
		{
			id: 'when',
			header: __( 'When', 'radius-hotel-booking' ),
			mobile: 'subtitle',
			cell: ( block ) => blockPeriod( block ),
		},
		{
			id: 'reason',
			header: __( 'Reason', 'radius-hotel-booking' ),
			wrap: true,
			cell: ( block ) => block.reason,
		},
		{
			id: 'by',
			header: __( 'Added by', 'radius-hotel-booking' ),
			cell: ( block ) =>
				block.created_by?.name ||
				( block.source === 'manual' ? '—' : block.source_label ),
		},
	];

	const empty = (
		<EmptyState
			icon={ Ban }
			title={
				when === 'past'
					? __( 'No past blocks', 'radius-hotel-booking' )
					: __( 'Nothing is blocked', 'radius-hotel-booking' )
			}
			description={ __(
				'Block a room for repairs, a floor for painting, or the whole property for a private event. Nothing in a block can be booked.',
				'radius-hotel-booking'
			) }
			action={
				canManage && when !== 'past' ? (
					<Button onClick={ () => setEditing( {} ) }>
						<Plus className="h-4 w-4" aria-hidden="true" />
						{ __( 'Block dates', 'radius-hotel-booking' ) }
					</Button>
				) : null
			}
			className="border-0 py-12"
		/>
	);

	return (
		<div className="mx-auto max-w-5xl space-y-4">
			<Panel
				title={ __( 'Blocked dates', 'radius-hotel-booking' ) }
				description={ __(
					'Existing bookings inside a block are kept; move them if needed.',
					'radius-hotel-booking'
				) }
			>
				<div className="space-y-3">
					<FilterTabs
						label={ __( 'Show', 'radius-hotel-booking' ) }
						value={ when }
						onChange={ ( next ) => {
							setWhen( next );
							setPage( 1 );
						} }
						tabs={ [
							{
								value: 'upcoming',
								label: __(
									'Current and coming',
									'radius-hotel-booking'
								),
							},
							{
								value: 'past',
								label: __( 'Past', 'radius-hotel-booking' ),
							},
							{
								value: 'all',
								label: __( 'All', 'radius-hotel-booking' ),
							},
						] }
					/>
					<DataTable
						columns={ columns }
						rows={ list.data?.blocks || [] }
						total={ list.data?.total || 0 }
						page={ page }
						perPage={ PER_PAGE }
						onPageChange={ setPage }
						loading={ list.isPending }
						error={ list.error }
						onRetry={ () => list.refetch() }
						empty={ empty }
						caption={ __(
							'Blocked dates',
							'radius-hotel-booking'
						) }
						rowActions={ ( block ) => {
							if ( ! canManage ) {
								return [];
							}
							const remove = {
								label: __( 'Remove', 'radius-hotel-booking' ),
								icon: Trash2,
								destructive: true,
								onSelect: () => setRemoving( block ),
							};
							if ( block.editable ) {
								return [
									{
										label: __(
											'Change',
											'radius-hotel-booking'
										),
										icon: Pencil,
										primary: true,
										onSelect: () => setEditing( block ),
									},
									remove,
								];
							}
							// A block from a source that is switched off.
							return block.removable ? [ remove ] : [];
						} }
					/>
				</div>
			</Panel>

			{ editing ? (
				<BlockEditor
					block={ editing.id ? editing : null }
					onClose={ () => setEditing( null ) }
				/>
			) : null }

			<ConfirmDialog
				open={ Boolean( removing ) }
				onOpenChange={ ( open ) => ! open && setRemoving( null ) }
				title={ __( 'Remove this block?', 'radius-hotel-booking' ) }
				description={
					removing
						? sprintf(
								/* translators: 1: what is blocked, 2: when. */
								__(
									'%1$s (%2$s) can be booked again straight away.',
									'radius-hotel-booking'
								),
								removing.label,
								blockPeriod( removing )
						  )
						: ''
				}
				confirmLabel={ __( 'Remove block', 'radius-hotel-booking' ) }
				destructive
				onConfirm={ () =>
					remove
						.mutateAsync( removing.id )
						.then( () =>
							toast.success(
								__( 'Block removed.', 'radius-hotel-booking' )
							)
						)
				}
			/>
		</div>
	);
}
