/**
 * Add or change a block (8.12): what it closes (the whole property, a floor,
 * a room type or one room), when, and why. Whole days by default — from the
 * first to the last day, inclusive — or exact times. The server validates
 * again and says how many bookings already fall inside, which a block does
 * not move.
 */
import { useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

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
import { Switch } from '@/components/ui/switch';
import { addDaysYmd, siteToday } from '@/lib/format';
import { toast, toastError } from '@/lib/toast';
import { useFloors, useRoomTypes, useTypeRooms } from '@/modules/Rooms/api';
import { useSaveBlock } from '../api';

const FIELDS = [ 'scope', 'scope_id', 'start_at', 'end_at', 'reason' ];

/**
 * The form's starting values.
 *
 * @param {Object|null} block Block being changed, or null for a new one.
 * @param {Object}      preset `{ scope, scope_id, date }` for a new block.
 * @return {Object} Values.
 */
function initialValues( block, preset ) {
	if ( ! block ) {
		const date =
			preset?.date && preset.date > siteToday()
				? preset.date
				: siteToday();
		return {
			scope: preset?.scope || 'room',
			scopeId: preset?.scope_id ? String( preset.scope_id ) : '',
			typeId: preset?.room_type_id ? String( preset.room_type_id ) : '',
			wholeDays: true,
			fromDate: date,
			fromTime: '00:00',
			toDate: date,
			toTime: '00:00',
			reason: '',
		};
	}
	return {
		scope: block.scope,
		scopeId: block.scope === 'property' ? '' : String( block.scope_id ),
		typeId:
			block.scope === 'room' ? String( block.room_type_id || '' ) : '',
		wholeDays: block.whole_days,
		fromDate: block.start_at.slice( 0, 10 ),
		fromTime: block.start_at.slice( 11, 16 ),
		// Whole days show the last day blocked, not the midnight after it.
		toDate: block.whole_days
			? addDaysYmd( block.end_at.slice( 0, 10 ), -1 )
			: block.end_at.slice( 0, 10 ),
		toTime: block.end_at.slice( 11, 16 ),
		reason: block.reason,
	};
}

/**
 * @param {Object}      props         Props.
 * @param {Object|null} props.block   Block to change, or null to add one.
 * @param {Object}      props.preset  For a new block: `{ scope, scope_id, room_type_id, date }`.
 * @param {Function}    props.onClose Close.
 * @return {JSX.Element} Dialog.
 */
export default function BlockEditor( { block, preset, onClose } ) {
	const [ values, setValues ] = useState( () =>
		initialValues( block, preset )
	);
	const [ errors, setErrors ] = useState( {} );
	const save = useSaveBlock();
	const floors = useFloors();
	const types = useRoomTypes();
	const rooms = useTypeRooms(
		values.scope === 'room' ? Number( values.typeId ) : 0
	);

	const set = ( changes ) => {
		setValues( ( prev ) => ( { ...prev, ...changes } ) );
		setErrors( {} );
	};

	const today = siteToday();
	const roomOptions = ( rooms.data || [] ).flatMap( ( floor ) =>
		floor.rooms.map( ( room ) => ( {
			id: room.id,
			label: floor.name
				? sprintf(
						/* translators: 1: room number, 2: floor name. */
						__( '%1$s · %2$s', 'radius-hotel-booking' ),
						room.number,
						floor.name
				  )
				: room.number,
		} ) )
	);

	const submit = async ( event ) => {
		event.preventDefault();
		const startAt = values.wholeDays
			? values.fromDate
			: `${ values.fromDate } ${ values.fromTime }`;
		const endAt = values.wholeDays
			? values.toDate && addDaysYmd( values.toDate, 1 )
			: `${ values.toDate } ${ values.toTime }`;
		try {
			const result = await save.mutateAsync( {
				id: block?.id,
				scope: values.scope,
				scope_id:
					values.scope === 'property' ? 0 : Number( values.scopeId ),
				start_at: startAt,
				end_at: endAt,
				reason: values.reason,
			} );
			toast.success(
				block
					? __( 'Block saved.', 'radius-hotel-booking' )
					: __( 'Dates blocked.', 'radius-hotel-booking' )
			);
			if ( result.bookings_inside ) {
				toast.warning(
					sprintf(
						/* translators: %d: booked rooms inside the blocked period. */
						_n(
							'%d booked room already falls in this period. The block does not move it.',
							'%d booked rooms already fall in this period. The block does not move them.',
							result.bookings_inside,
							'radius-hotel-booking'
						),
						result.bookings_inside
					),
					{ duration: 10000 }
				);
			}
			onClose();
		} catch ( err ) {
			const mapped = {};
			FIELDS.forEach( ( field ) => {
				const message = err?.errors?.[ field ]?.first_message;
				if ( message ) {
					mapped[ field ] = message;
				}
			} );
			if ( Object.keys( mapped ).length ) {
				setErrors( mapped );
			} else {
				toastError( err );
			}
		}
	};

	const errorText = ( field ) =>
		errors[ field ] ? (
			<p role="alert" className="m-0 text-sm text-destructive">
				{ errors[ field ] }
			</p>
		) : null;

	const scopes = [
		[ 'room', __( 'One room', 'radius-hotel-booking' ) ],
		[ 'room_type', __( 'A room type', 'radius-hotel-booking' ) ],
		[ 'floor', __( 'A floor', 'radius-hotel-booking' ) ],
		[ 'property', __( 'The whole property', 'radius-hotel-booking' ) ],
	];

	const typeSelect = ( id, value, onChange ) => (
		<Select value={ value || undefined } onValueChange={ onChange }>
			<SelectTrigger
				id={ id }
				aria-invalid={ errors.scope_id ? true : undefined }
			>
				<SelectValue
					placeholder={ __(
						'Choose a room type',
						'radius-hotel-booking'
					) }
				/>
			</SelectTrigger>
			<SelectContent>
				{ ( types.data || [] ).map( ( type ) => (
					<SelectItem key={ type.id } value={ String( type.id ) }>
						{ type.name }
					</SelectItem>
				) ) }
			</SelectContent>
		</Select>
	);

	return (
		<Dialog open onOpenChange={ ( next ) => ! next && onClose() }>
			<DialogContent className="max-h-[90vh] max-w-md overflow-y-auto">
				<form onSubmit={ submit } noValidate className="space-y-5">
					<DialogHeader>
						<DialogTitle>
							{ block
								? __(
										'Change the block',
										'radius-hotel-booking'
								  )
								: __( 'Block dates', 'radius-hotel-booking' ) }
						</DialogTitle>
						<DialogDescription>
							{ __(
								'Nothing in the block can be booked while it lasts. Existing bookings are kept.',
								'radius-hotel-booking'
							) }
						</DialogDescription>
					</DialogHeader>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-block-scope"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'What to block', 'radius-hotel-booking' ) }
						</Label>
						<Select
							value={ values.scope }
							onValueChange={ ( scope ) =>
								set( { scope, scopeId: '', typeId: '' } )
							}
						>
							<SelectTrigger id="rtbp-block-scope">
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								{ scopes.map( ( [ value, label ] ) => (
									<SelectItem key={ value } value={ value }>
										{ label }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
						{ errorText( 'scope' ) }
					</div>

					{ values.scope === 'floor' ? (
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-block-floor"
								className="text-sm font-semibold text-heading"
							>
								{ __( 'Floor', 'radius-hotel-booking' ) }
							</Label>
							<Select
								value={ values.scopeId || undefined }
								onValueChange={ ( scopeId ) =>
									set( { scopeId } )
								}
							>
								<SelectTrigger
									id="rtbp-block-floor"
									aria-invalid={
										errors.scope_id ? true : undefined
									}
								>
									<SelectValue
										placeholder={ __(
											'Choose a floor',
											'radius-hotel-booking'
										) }
									/>
								</SelectTrigger>
								<SelectContent>
									{ ( floors.data || [] ).map( ( floor ) => (
										<SelectItem
											key={ floor.id }
											value={ String( floor.id ) }
										>
											{ floor.name }
										</SelectItem>
									) ) }
								</SelectContent>
							</Select>
							{ errorText( 'scope_id' ) }
						</div>
					) : null }

					{ values.scope === 'room_type' ? (
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-block-type"
								className="text-sm font-semibold text-heading"
							>
								{ __( 'Room type', 'radius-hotel-booking' ) }
							</Label>
							{ typeSelect(
								'rtbp-block-type',
								values.scopeId,
								( scopeId ) => set( { scopeId } )
							) }
							{ errorText( 'scope_id' ) }
						</div>
					) : null }

					{ values.scope === 'room' ? (
						<div className="grid gap-3 sm:grid-cols-2">
							<div className="space-y-1.5">
								<Label
									htmlFor="rtbp-block-room-type"
									className="text-sm font-semibold text-heading"
								>
									{ __(
										'Room type',
										'radius-hotel-booking'
									) }
								</Label>
								{ typeSelect(
									'rtbp-block-room-type',
									values.typeId,
									( typeId ) => set( { typeId, scopeId: '' } )
								) }
							</div>
							<div className="space-y-1.5">
								<Label
									htmlFor="rtbp-block-room"
									className="text-sm font-semibold text-heading"
								>
									{ __( 'Room', 'radius-hotel-booking' ) }
								</Label>
								<Select
									value={ values.scopeId || undefined }
									onValueChange={ ( scopeId ) =>
										set( { scopeId } )
									}
									disabled={ ! values.typeId }
								>
									<SelectTrigger
										id="rtbp-block-room"
										aria-invalid={
											errors.scope_id ? true : undefined
										}
									>
										<SelectValue
											placeholder={ __(
												'Choose a room',
												'radius-hotel-booking'
											) }
										/>
									</SelectTrigger>
									<SelectContent>
										{ roomOptions.map( ( room ) => (
											<SelectItem
												key={ room.id }
												value={ String( room.id ) }
											>
												{ room.label }
											</SelectItem>
										) ) }
									</SelectContent>
								</Select>
							</div>
							<div className="sm:col-span-2">
								{ errorText( 'scope_id' ) }
							</div>
						</div>
					) : null }

					<div className="flex items-center justify-between gap-4 rounded-lg border border-border px-3 py-3">
						<Label
							htmlFor="rtbp-block-whole"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Whole days', 'radius-hotel-booking' ) }
						</Label>
						<Switch
							id="rtbp-block-whole"
							checked={ values.wholeDays }
							onCheckedChange={ ( wholeDays ) =>
								set( { wholeDays } )
							}
						/>
					</div>

					<div className="grid gap-3 sm:grid-cols-2">
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-block-from"
								className="text-sm font-semibold text-heading"
							>
								{ values.wholeDays
									? __( 'First day', 'radius-hotel-booking' )
									: __( 'Starts', 'radius-hotel-booking' ) }
							</Label>
							<div className="flex gap-2">
								<Input
									id="rtbp-block-from"
									type="date"
									value={ values.fromDate }
									onChange={ ( e ) => {
										const fromDate = e.target.value;
										set( {
											fromDate,
											toDate:
												values.toDate < fromDate
													? fromDate
													: values.toDate,
										} );
									} }
									aria-invalid={
										errors.start_at ? true : undefined
									}
								/>
								{ ! values.wholeDays ? (
									<Input
										type="time"
										aria-label={ __(
											'Start time',
											'radius-hotel-booking'
										) }
										value={ values.fromTime }
										onChange={ ( e ) =>
											set( { fromTime: e.target.value } )
										}
										className="w-28 shrink-0"
									/>
								) : null }
							</div>
							{ errorText( 'start_at' ) }
						</div>
						<div className="space-y-1.5">
							<Label
								htmlFor="rtbp-block-to"
								className="text-sm font-semibold text-heading"
							>
								{ values.wholeDays
									? __( 'Last day', 'radius-hotel-booking' )
									: __( 'Ends', 'radius-hotel-booking' ) }
							</Label>
							<div className="flex gap-2">
								<Input
									id="rtbp-block-to"
									type="date"
									min={ values.fromDate || today }
									value={ values.toDate }
									onChange={ ( e ) =>
										set( { toDate: e.target.value } )
									}
									aria-invalid={
										errors.end_at ? true : undefined
									}
								/>
								{ ! values.wholeDays ? (
									<Input
										type="time"
										aria-label={ __(
											'End time',
											'radius-hotel-booking'
										) }
										value={ values.toTime }
										onChange={ ( e ) =>
											set( { toTime: e.target.value } )
										}
										className="w-28 shrink-0"
									/>
								) : null }
							</div>
							{ errorText( 'end_at' ) }
						</div>
					</div>

					<div className="space-y-1.5">
						<Label
							htmlFor="rtbp-block-reason"
							className="text-sm font-semibold text-heading"
						>
							{ __( 'Reason', 'radius-hotel-booking' ) }
						</Label>
						<Input
							id="rtbp-block-reason"
							value={ values.reason }
							maxLength={ 191 }
							onChange={ ( e ) =>
								set( { reason: e.target.value } )
							}
							placeholder={ __(
								'Painting, water works, private event…',
								'radius-hotel-booking'
							) }
							aria-invalid={ errors.reason ? true : undefined }
						/>
						{ errorText( 'reason' ) }
					</div>

					<DialogFooter className="gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ onClose }
						>
							{ __( 'Cancel', 'radius-hotel-booking' ) }
						</Button>
						<Button type="submit" disabled={ save.isPending }>
							{ block
								? __( 'Save', 'radius-hotel-booking' )
								: __( 'Block', 'radius-hotel-booking' ) }
						</Button>
					</DialogFooter>
				</form>
			</DialogContent>
		</Dialog>
	);
}
