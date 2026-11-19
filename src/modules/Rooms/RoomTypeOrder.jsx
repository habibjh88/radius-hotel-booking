/**
 * The room types' display order (`#/rooms` → Reorder): drag a row, or use
 * the arrows. Each move saves at once. The same order is used everywhere
 * room types are listed: the booking page, New booking, availability and
 * the reports.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ArrowDown, ArrowUp, GripVertical, ImageOff } from 'lucide-react';

import StatusBadge from '@/components/common/StatusBadge';
import { Button } from '@/components/ui/button';
import { toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { useReorderRoomTypes } from './api';

/**
 * @param {Object}   props       Props.
 * @param {Object[]} props.types Room types, in the current order.
 * @return {JSX.Element} List.
 */
export default function RoomTypeOrder( { types } ) {
	const reorder = useReorderRoomTypes();
	const [ dragId, setDragId ] = useState( null );
	const [ overId, setOverId ] = useState( null );

	const saveOrder = ( ids ) =>
		reorder.mutate( ids, { onError: ( e ) => toastError( e ) } );

	const move = ( index, step ) => {
		const ids = types.map( ( type ) => type.id );
		const [ id ] = ids.splice( index, 1 );
		ids.splice( index + step, 0, id );
		saveOrder( ids );
	};

	const dropOn = ( targetId ) => {
		setOverId( null );
		if ( ! dragId || dragId === targetId ) {
			return;
		}
		const from = types.findIndex( ( type ) => type.id === dragId );
		const to = types.findIndex( ( type ) => type.id === targetId );
		const ids = types.map( ( type ) => type.id );
		ids.splice( from, 1 );
		// Takes the target's place: the target moves down (dragged up) or up (dragged down).
		ids.splice( to, 0, dragId );
		saveOrder( ids );
	};

	return (
		<div className="space-y-3">
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'Drag a room type, or use the arrows. The booking page, New booking and the reports show room types in this order. Each move is saved at once.',
					'radius-hotel-booking'
				) }
			</p>
			<ol className="m-0 list-none space-y-2 p-0">
				{ types.map( ( type, index ) => {
					const cover = type.featured_image || type.gallery?.[ 0 ];
					return (
						<li
							key={ type.id }
							draggable
							onDragStart={ ( event ) => {
								setDragId( type.id );
								event.dataTransfer.effectAllowed = 'move';
							} }
							onDragEnd={ () => {
								setDragId( null );
								setOverId( null );
							} }
							onDragOver={ ( event ) => {
								event.preventDefault();
								setOverId( type.id );
							} }
							onDrop={ ( event ) => {
								event.preventDefault();
								dropOn( type.id );
							} }
							className={ cn(
								'm-0 flex items-center gap-3 rounded-xl border border-solid border-border bg-card p-2.5 pr-3 shadow-sm transition-colors',
								dragId === type.id && 'opacity-50',
								overId === type.id &&
									dragId !== type.id &&
									'border-primary'
							) }
						>
							<GripVertical
								className="h-5 w-5 shrink-0 cursor-grab text-muted-foreground"
								aria-hidden="true"
							/>
							<span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary text-xs font-bold text-primary-foreground">
								{ index + 1 }
							</span>
							<span className="relative h-10 w-14 shrink-0 overflow-hidden rounded-md bg-muted">
								{ cover ? (
									<img
										src={ cover.thumb || cover.url }
										alt=""
										className="absolute inset-0 h-full w-full object-cover"
									/>
								) : (
									<span className="absolute inset-0 flex items-center justify-center text-muted-foreground">
										<ImageOff
											className="h-4 w-4"
											aria-hidden="true"
										/>
									</span>
								) }
							</span>
							<span className="min-w-0 flex-1">
								<span className="block truncate text-sm font-semibold text-heading">
									{ type.name }
								</span>
								{ ! type.is_active ? (
									<StatusBadge
										domain="readiness"
										value="hidden"
										className="mt-1"
									/>
								) : null }
							</span>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								className="shrink-0 text-muted-foreground"
								disabled={ 0 === index || reorder.isPending }
								onClick={ () => move( index, -1 ) }
								aria-label={ sprintf(
									/* translators: %s: room type name. */
									__( 'Move %s up', 'radius-hotel-booking' ),
									type.name
								) }
							>
								<ArrowUp aria-hidden="true" />
							</Button>
							<Button
								type="button"
								variant="ghost"
								size="icon"
								className="shrink-0 text-muted-foreground"
								disabled={
									index === types.length - 1 ||
									reorder.isPending
								}
								onClick={ () => move( index, 1 ) }
								aria-label={ sprintf(
									/* translators: %s: room type name. */
									__(
										'Move %s down',
										'radius-hotel-booking'
									),
									type.name
								) }
							>
								<ArrowDown aria-hidden="true" />
							</Button>
						</li>
					);
				} ) }
			</ol>
		</div>
	);
}
