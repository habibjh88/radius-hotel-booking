/**
 * Add a room, or rename one / put it on another floor. Opened from a room
 * type's Rooms tab (the type is fixed) or from All rooms (pick the type).
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import RoomTypeField from './RoomTypeField';
import { useAddRoom, useUpdateRoom } from '../api';

/**
 * Add a room, or rename one / put it on another floor.
 *
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Room type (omit and pass `types` to choose one).
 * @param {Object[]} props.types   Room types to choose from when adding outside a type.
 * @param {number}   props.typeId  Preselected room type when choosing one.
 * @param {Object[]} props.floors  Floors.
 * @param {Object}   props.room    Room being edited (omit to add).
 * @param {number}   props.floorId Preselected floor when adding.
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function RoomDialog( {
	type,
	types = [],
	typeId: initialTypeId,
	floors,
	room,
	floorId,
	onClose,
} ) {
	const [ typeId, setTypeId ] = useState(
		String( type?.id || initialTypeId || '' )
	);
	const chosen =
		type || types.find( ( option ) => String( option.id ) === typeId );
	const add = useAddRoom( Number( typeId ) );
	const update = useUpdateRoom( Number( typeId ) );
	const [ number, setNumber ] = useState( room?.number ?? '' );
	const [ floor, setFloor ] = useState(
		String( room?.floor_id || floorId || floors[ 0 ]?.id || '' )
	);
	const [ errors, setErrors ] = useState( {} );
	const busy = add.isPending || update.isPending;

	const submit = async ( event ) => {
		event.preventDefault();
		const next = {};
		if ( ! chosen ) {
			next.room_type_id = __(
				'Choose a room type.',
				'radius-hotel-booking'
			);
		}
		if ( ! number.trim() ) {
			next.number = __(
				'Give the room a number.',
				'radius-hotel-booking'
			);
		}
		if ( ! Number( floor ) ) {
			next.floor_id = __( 'Choose a floor.', 'radius-hotel-booking' );
		}
		setErrors( next );
		if ( Object.keys( next ).length ) {
			return;
		}
		const values = { number: number.trim(), floor_id: Number( floor ) };
		try {
			if ( room ) {
				await update.mutateAsync( { id: room.id, ...values } );
				toast.success( __( 'Room saved.', 'radius-hotel-booking' ) );
			} else {
				await add.mutateAsync( values );
				toast.success(
					sprintf(
						/* translators: %s: room number. */
						__( 'Room %s added.', 'radius-hotel-booking' ),
						values.number
					)
				);
			}
			onClose();
		} catch ( error ) {
			const fields = {};
			Object.entries( error?.errors || {} ).forEach(
				( [ key, detail ] ) => {
					fields[ key ] = detail?.first_message || '';
				}
			);
			if ( Object.keys( fields ).length ) {
				setErrors( fields );
			} else {
				toastError( error );
			}
		}
	};

	return (
		<Dialog open onOpenChange={ ( open ) => ! open && onClose() }>
			<DialogContent className="max-w-md">
				<form onSubmit={ submit } noValidate className="space-y-4">
					<DialogHeader>
						<DialogTitle>
							{ room
								? sprintf(
										/* translators: %s: room number. */
										__(
											'Edit room %s',
											'radius-hotel-booking'
										),
										room.number
								  )
								: type
								? sprintf(
										/* translators: %s: room type name. */
										__(
											'Add a room to %s',
											'radius-hotel-booking'
										),
										type.name
								  )
								: __( 'Add a room', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Room numbers are unique across the hotel.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					{ ! type ? (
						<RoomTypeField
							id="rtbp-room-type"
							types={ types }
							value={ typeId }
							onChange={ ( next ) => {
								setTypeId( next );
								setErrors( ( prev ) => ( {
									...prev,
									room_type_id: '',
								} ) );
							} }
							error={ errors.room_type_id }
						/>
					) : null }

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-room-number"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Room number', 'radius-hotel-booking' ) }
						</Label>
						<Input
							id="rtbp-room-number"
							value={ number }
							onChange={ ( event ) =>
								setNumber( event.target.value )
							}
							maxLength={ 20 }
							autoFocus
							placeholder={ __(
								'e.g. A1',
								'radius-hotel-booking'
							) }
							aria-invalid={ errors.number ? true : undefined }
							aria-describedby={
								errors.number
									? 'rtbp-room-number-error'
									: undefined
							}
							className={ cn(
								errors.number && '!border-destructive'
							) }
						/>
						{ errors.number ? (
							<p
								id="rtbp-room-number-error"
								role="alert"
								className="m-0 text-xs text-destructive"
							>
								{ errors.number }
							</p>
						) : null }
					</div>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-room-floor"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Floor', 'radius-hotel-booking' ) }
						</Label>
						<Select value={ floor } onValueChange={ setFloor }>
							<SelectTrigger
								id="rtbp-room-floor"
								aria-invalid={
									errors.floor_id ? true : undefined
								}
								className={ cn(
									errors.floor_id && '!border-destructive'
								) }
							>
								<SelectValue
									placeholder={ __(
										'Choose a floor',
										'radius-hotel-booking'
									) }
								/>
							</SelectTrigger>
							<SelectContent>
								{ floors.map( ( option ) => (
									<SelectItem
										key={ option.id }
										value={ String( option.id ) }
									>
										{ option.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
						{ errors.floor_id ? (
							<p
								role="alert"
								className="m-0 text-xs text-destructive"
							>
								{ errors.floor_id }
							</p>
						) : null }
					</div>

					<DialogFooter>
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ busy }>
							{ room
								? __( 'Save', 'radius-hotel-booking' )
								: __( 'Add room', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
