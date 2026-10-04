/**
 * The room type's Details form: create (no `type`) or edit. Server field
 * errors land on their fields; only changed values are sent on edit.
 */
import { Controller } from 'react-hook-form';
import { __ } from '@wordpress/i18n';

import { Field, FormSection } from '@/components/common/Form';
import GalleryField from '@/components/common/GalleryField';
import Panel from '@/components/common/Panel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { applyServerErrors, useZodForm, z } from '@/lib/forms';
import { toast, toastError } from '@/lib/toast';
import { useSaveRoomType } from '../api';
import AmenityPicker from './AmenityPicker';

/**
 * A whole number typed in a text box: '' allowed when `optional`.
 *
 * @param {number}  min      Minimum.
 * @param {number}  max      Maximum.
 * @param {string}  message  Error message.
 * @param {boolean} optional Whether empty is allowed.
 * @return {import('zod').ZodTypeAny} Rule.
 */
const whole = ( min, max, message, optional = false ) =>
	z
		.string()
		.refine(
			( raw ) =>
				( optional && raw.trim() === '' ) ||
				( /^\d+$/.test( raw.trim() ) &&
					Number( raw ) >= min &&
					Number( raw ) <= max ),
			{ message }
		);

const schema = z.object( {
	name: z
		.string()
		.trim()
		.min( 1, __( 'Give the room type a name.', 'radius-hotel-booking' ) )
		.max(
			120,
			__( 'Use 120 characters or fewer.', 'radius-hotel-booking' )
		),
	short_description: z
		.string()
		.max(
			500,
			__( 'Use 500 characters or fewer.', 'radius-hotel-booking' )
		),
	description: z.string(),
	gallery: z.array( z.number() ),
	featured_image_id: z.number(),
	amenities: z.array( z.string() ),
	bed_info: z
		.string()
		.max(
			191,
			__( 'Use 191 characters or fewer.', 'radius-hotel-booking' )
		),
	size_m2: z
		.string()
		.refine(
			( raw ) =>
				raw.trim() === '' ||
				( Number( raw ) > 0 && Number( raw ) <= 10000 ),
			{
				message: __(
					'Give the size in square metres.',
					'radius-hotel-booking'
				),
			}
		),
	max_adults: whole(
		1,
		20,
		__( 'Between 1 and 20 adults.', 'radius-hotel-booking' )
	),
	max_children: whole(
		0,
		20,
		__( 'Between 0 and 20 children.', 'radius-hotel-booking' )
	),
	buffer_minutes: whole(
		0,
		720,
		__(
			'Between 0 and 720 minutes, or empty to use the default.',
			'radius-hotel-booking'
		),
		true
	),
	is_active: z.boolean(),
} );

/**
 * Form values from a room type (or the defaults for a new one).
 *
 * @param {Object|undefined} type Room type from the API.
 * @return {Object} Values.
 */
const toValues = ( type ) => ( {
	name: type?.name ?? '',
	short_description: type?.short_description ?? '',
	description: type?.description ?? '',
	gallery: ( type?.gallery ?? [] ).map( ( image ) => image.id ),
	featured_image_id: type?.featured_image?.id ?? 0,
	amenities: type?.amenities ?? [],
	bed_info: type?.bed_info ?? '',
	size_m2:
		type?.size_m2 === null || type?.size_m2 === undefined
			? ''
			: String( type.size_m2 ),
	max_adults: String( type?.max_adults ?? 2 ),
	max_children: String( type?.max_children ?? 0 ),
	buffer_minutes:
		type?.buffer_minutes === null || type?.buffer_minutes === undefined
			? ''
			: String( type.buffer_minutes ),
	is_active: type?.is_active ?? true,
} );

/**
 * The API body from form values.
 *
 * @param {Object} values Values.
 * @return {Object} Body.
 */
const toBody = ( values ) => ( {
	...values,
	name: values.name.trim(),
	size_m2: values.size_m2.trim() === '' ? null : Number( values.size_m2 ),
	max_adults: Number( values.max_adults ),
	max_children: Number( values.max_children ),
	buffer_minutes:
		values.buffer_minutes.trim() === ''
			? null
			: Number( values.buffer_minutes ),
} );

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.type     Room type (omit to create one).
 * @param {boolean}  props.readOnly Show the values without saving.
 * @param {Function} props.onSaved  Called with the saved type.
 * @return {JSX.Element} Form.
 */
export default function RoomTypeForm( { type, readOnly = false, onSaved } ) {
	const form = useZodForm( schema, { defaultValues: toValues( type ) } );
	const save = useSaveRoomType();
	const { register, control, handleSubmit, reset, formState } = form;
	const { isDirty, dirtyFields } = formState;

	const submit = handleSubmit( async ( values ) => {
		let body = toBody( values );
		if ( type ) {
			// Only the changed fields (the cover travels with the photos).
			const changed = Object.keys( dirtyFields );
			if ( changed.includes( 'gallery' ) ) {
				changed.push( 'featured_image_id' );
			}
			body = Object.fromEntries(
				Object.entries( body ).filter( ( [ key ] ) =>
					changed.includes( key )
				)
			);
		}
		try {
			const saved = await save.mutateAsync( {
				id: type?.id,
				values: body,
			} );
			reset( toValues( saved ) );
			toast.success(
				type
					? __( 'Room type saved.', 'radius-hotel-booking' )
					: __( 'Room type added.', 'radius-hotel-booking' )
			);
			onSaved?.( saved );
		} catch ( error ) {
			if ( ! applyServerErrors( form.setError, error ) ) {
				toastError( error );
			}
		}
	} );

	return (
		<form onSubmit={ submit } noValidate>
			<fieldset
				disabled={ readOnly }
				className="m-0 min-w-0 border-0 p-0"
			>
				<Panel bodyClassName="divide-y divide-border">
					<FormSection
						title={ __( 'About the room', 'radius-hotel-booking' ) }
						description={ __(
							'Shown to guests on the booking form.',
							'radius-hotel-booking'
						) }
					>
						<Field
							label={ __( 'Name', 'radius-hotel-booking' ) }
							name="name"
							form={ form }
							required
						>
							<Input
								{ ...register( 'name' ) }
								maxLength={ 120 }
								placeholder={ __(
									'e.g. Standard Room',
									'radius-hotel-booking'
								) }
							/>
						</Field>
						<Field
							label={ __(
								'Short description',
								'radius-hotel-booking'
							) }
							name="short_description"
							form={ form }
							description={ __(
								'One or two lines for the room list.',
								'radius-hotel-booking'
							) }
						>
							<Textarea
								{ ...register( 'short_description' ) }
								rows={ 2 }
								maxLength={ 500 }
							/>
						</Field>
						<Field
							label={ __(
								'Description',
								'radius-hotel-booking'
							) }
							name="description"
							form={ form }
							description={ __(
								'The full text on the room page. Basic HTML is allowed.',
								'radius-hotel-booking'
							) }
						>
							<Textarea
								{ ...register( 'description' ) }
								rows={ 6 }
							/>
						</Field>
					</FormSection>

					<FormSection
						title={ __( 'Photos', 'radius-hotel-booking' ) }
						description={ __(
							'From the media library. The cover is shown first.',
							'radius-hotel-booking'
						) }
					>
						<Field
							label={ __( 'Gallery', 'radius-hotel-booking' ) }
							name="gallery"
							form={ form }
						>
							<Controller
								control={ control }
								name="gallery"
								render={ ( { field } ) => (
									<GalleryField
										value={ field.value }
										onChange={ field.onChange }
										cover={ form.watch(
											'featured_image_id'
										) }
										onCoverChange={ ( id ) =>
											form.setValue(
												'featured_image_id',
												id,
												{
													shouldDirty: true,
												}
											)
										}
										images={ type?.gallery ?? [] }
										disabled={ readOnly }
									/>
								) }
							/>
						</Field>
					</FormSection>

					<FormSection
						title={ __( 'Room facts', 'radius-hotel-booking' ) }
						description={ __(
							'Guests and bedding. Occupancy limits apply to every booking of this type.',
							'radius-hotel-booking'
						) }
					>
						<div className="grid gap-4 sm:grid-cols-2">
							<Field
								label={ __(
									'Max adults',
									'radius-hotel-booking'
								) }
								name="max_adults"
								form={ form }
								required
							>
								<Input
									{ ...register( 'max_adults' ) }
									type="number"
									inputMode="numeric"
									min={ 1 }
									max={ 20 }
								/>
							</Field>
							<Field
								label={ __(
									'Max children',
									'radius-hotel-booking'
								) }
								name="max_children"
								form={ form }
							>
								<Input
									{ ...register( 'max_children' ) }
									type="number"
									inputMode="numeric"
									min={ 0 }
									max={ 20 }
								/>
							</Field>
							<Field
								label={ __( 'Beds', 'radius-hotel-booking' ) }
								name="bed_info"
								form={ form }
							>
								<Input
									{ ...register( 'bed_info' ) }
									maxLength={ 191 }
									placeholder={ __(
										'e.g. 1 king bed',
										'radius-hotel-booking'
									) }
								/>
							</Field>
							<Field
								label={ __(
									'Size (m²)',
									'radius-hotel-booking'
								) }
								name="size_m2"
								form={ form }
							>
								<Input
									{ ...register( 'size_m2' ) }
									type="number"
									inputMode="decimal"
									min={ 0 }
									step="0.01"
								/>
							</Field>
						</div>
						<Field
							label={ __( 'Amenities', 'radius-hotel-booking' ) }
							name="amenities"
							form={ form }
							description={ __(
								'Tick what this room type offers. The list is shared by every room type.',
								'radius-hotel-booking'
							) }
						>
							<Controller
								control={ control }
								name="amenities"
								render={ ( { field } ) => (
									<AmenityPicker
										value={ field.value }
										onChange={ field.onChange }
										disabled={ readOnly }
										canManage={ ! readOnly }
									/>
								) }
							/>
						</Field>
					</FormSection>

					<FormSection
						title={ __( 'Selling', 'radius-hotel-booking' ) }
						description={ __(
							'Whether the type can be booked, and the cleaning time between stays.',
							'radius-hotel-booking'
						) }
					>
						<Controller
							control={ control }
							name="is_active"
							render={ ( { field } ) => (
								<label className="flex items-start justify-between gap-4">
									<span className="min-w-0">
										<span className="block text-sm font-semibold text-heading">
											{ __(
												'Available for booking',
												'radius-hotel-booking'
											) }
										</span>
										<span className="mt-0.5 block text-[13px] leading-5 text-muted-foreground">
											{ __(
												'Off hides the type from the booking form and the desk.',
												'radius-hotel-booking'
											) }
										</span>
									</span>
									<Switch
										checked={ field.value }
										onCheckedChange={ field.onChange }
										className="mt-0.5 shrink-0"
									/>
								</label>
							) }
						/>
						<Field
							label={ __(
								'Buffer between stays',
								'radius-hotel-booking'
							) }
							name="buffer_minutes"
							form={ form }
							description={ __(
								'Minutes the room stays blocked after checkout. Leave empty to use the Booking rules setting.',
								'radius-hotel-booking'
							) }
						>
							<Input
								{ ...register( 'buffer_minutes' ) }
								type="number"
								inputMode="numeric"
								min={ 0 }
								max={ 720 }
								placeholder={ __(
									'Default',
									'radius-hotel-booking'
								) }
								className="w-32"
							/>
						</Field>
					</FormSection>
				</Panel>
			</fieldset>

			{ ! readOnly ? (
				<div className="sticky bottom-[4.5rem] z-10 mt-4 flex flex-wrap items-center justify-end gap-2 rounded-xl border border-border bg-card px-4 py-3 shadow-lg md:bottom-4">
					{ type && isDirty ? (
						<Button
							type="button"
							variant="ghost"
							onClick={ () => reset( toValues( type ) ) }
							disabled={ save.isPending }
						>
							{ __( 'Discard', 'radius-hotel-booking' ) }
						</Button>
					) : null }
					<Button
						type="submit"
						disabled={ save.isPending || ( type && ! isDirty ) }
					>
						{ save.isPending
							? __( 'Saving…', 'radius-hotel-booking' )
							: type
							? __( 'Save changes', 'radius-hotel-booking' )
							: __( 'Add room type', 'radius-hotel-booking' ) }
					</Button>
				</div>
			) : null }
		</form>
	);
}
