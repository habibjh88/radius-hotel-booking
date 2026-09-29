/**
 * The rate plan editor (a Sheet): name, window type and times with a live
 * preview in words, several-units switch, features, policy, active. The
 * server validates everything; its field errors land on the fields.
 */
import { useMemo, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Trash2 } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import { Field } from '@/components/common/Form';
import SegmentedControl from '@/components/common/SegmentedControl';
import TagInput from '@/components/common/TagInput';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import {
	Sheet,
	SheetContent,
	SheetDescription,
	SheetFooter,
	SheetHeader,
	SheetTitle,
} from '@/components/ui/sheet';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import { describeWindow, halfHourOptions } from '@/lib/stayWindow';
import { toast, toastError } from '@/lib/toast';
import { usageText } from '../usage';
import { useDeleteRatePlan, useSaveRatePlan } from '../api';

/**
 * Form values from a plan (or the defaults for a new one).
 *
 * @param {Object|null} plan Plan.
 * @return {Object} Values.
 */
const toValues = ( plan ) => ( {
	name: plan?.name ?? '',
	type: plan?.type ?? 'fixed',
	start_time: plan?.start_time ?? '08:30',
	end_time: plan?.end_time ?? '17:00',
	// The flexible length is edited in hours.
	hours:
		plan?.type === 'flexible' ? String( plan.duration_minutes / 60 ) : '24',
	checkin_from: plan?.checkin_from ?? '08:00',
	checkin_until: plan?.checkin_until ?? '20:00',
	multi_unit: plan?.multi_unit ?? false,
	features: plan?.features ?? [],
	policy: plan?.policy ?? '',
	is_active: plan?.is_active ?? true,
} );

/**
 * The API body: only the fields of the chosen window type.
 *
 * @param {Object} values Values.
 * @return {Object} Body.
 */
const toBody = ( values ) => {
	const body = {
		name: values.name.trim(),
		type: values.type,
		multi_unit: values.multi_unit,
		features: values.features,
		policy: values.policy,
		is_active: values.is_active,
	};
	if ( values.type === 'flexible' ) {
		body.duration_minutes = Math.round( Number( values.hours ) * 60 );
		body.checkin_from = values.checkin_from;
		body.checkin_until = values.checkin_until;
	} else {
		body.start_time = values.start_time;
		body.end_time = values.end_time;
	}
	return body;
};

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.plan     Plan to edit, or null to add one.
 * @param {boolean}  props.readOnly Show without saving.
 * @param {Function} props.onClose  Close.
 * @return {JSX.Element} Sheet.
 */
export default function RatePlanSheet( { plan, readOnly = false, onClose } ) {
	const save = useSaveRatePlan();
	const remove = useDeleteRatePlan();
	const [ values, setValues ] = useState( () => toValues( plan ) );
	const [ errors, setErrors ] = useState( {} );
	const [ confirmDelete, setConfirmDelete ] = useState( false );
	const times = useMemo( halfHourOptions, [] );

	const set = ( key ) => ( value ) => {
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setErrors( ( prev ) => ( { ...prev, [ key ]: undefined } ) );
	};

	const preview = describeWindow(
		values.type === 'flexible'
			? {
					...values,
					duration_minutes: Math.round( Number( values.hours ) * 60 ),
			  }
			: values
	);
	const inUse = Boolean( plan?.usage?.room_types || plan?.usage?.bookings );

	const submit = async ( event ) => {
		event.preventDefault();
		if ( ! values.name.trim() ) {
			setErrors( {
				name: __(
					'Give the rate plan a name.',
					'radius-hotel-booking'
				),
			} );
			return;
		}
		try {
			await save.mutateAsync( {
				id: plan?.id,
				values: toBody( values ),
			} );
			toast.success(
				plan
					? __( 'Rate plan saved.', 'radius-hotel-booking' )
					: __( 'Rate plan added.', 'radius-hotel-booking' )
			);
			onClose();
		} catch ( error ) {
			const fields = {};
			Object.entries( error?.errors || {} ).forEach(
				( [ key, detail ] ) => {
					// The flexible length is shown in hours.
					fields[ key === 'duration_minutes' ? 'hours' : key ] =
						detail?.first_message || '';
				}
			);
			if ( Object.keys( fields ).length ) {
				setErrors( fields );
			} else {
				toastError( error );
			}
		}
	};

	const timeSelect = ( key, label ) => (
		<Field label={ label } error={ errors[ key ] }>
			<Select
				value={ values[ key ] }
				onValueChange={ set( key ) }
				disabled={ readOnly }
			>
				<SelectTrigger>
					<SelectValue />
				</SelectTrigger>
				<SelectContent className="max-h-72">
					{ times.map( ( option ) => (
						<SelectItem key={ option.value } value={ option.value }>
							{ option.label }
						</SelectItem>
					) ) }
				</SelectContent>
			</Select>
		</Field>
	);

	return (
		<Sheet open onOpenChange={ ( open ) => ! open && onClose() }>
			<SheetContent className="flex w-full flex-col gap-0 p-0 sm:max-w-lg">
				<form
					onSubmit={ submit }
					noValidate
					className="flex min-h-0 flex-1 flex-col"
				>
					<SheetHeader className="border-b border-border px-5 py-4 pr-14 text-left">
						<SheetTitle>
							{ plan
								? plan.name
								: __(
										'New rate plan',
										'radius-hotel-booking'
								  ) }
						</SheetTitle>
						<SheetDescription>
							{ plan && usageText( plan.usage )
								? usageText( plan.usage )
								: __(
										'A stay window you can price on each room type.',
										'radius-hotel-booking'
								  ) }
						</SheetDescription>
					</SheetHeader>

					<fieldset
						disabled={ readOnly }
						className="m-0 min-h-0 flex-1 space-y-5 overflow-y-auto border-0 px-5 py-5"
					>
						<Field
							label={ __( 'Name', 'radius-hotel-booking' ) }
							error={ errors.name }
							required
						>
							<Input
								value={ values.name }
								onChange={ ( event ) =>
									set( 'name' )( event.target.value )
								}
								maxLength={ 120 }
								placeholder={ __(
									'e.g. Half Day',
									'radius-hotel-booking'
								) }
							/>
						</Field>

						<div className="space-y-1.5">
							<p className="m-0 text-sm font-semibold text-heading">
								{ __( 'Stay window', 'radius-hotel-booking' ) }
							</p>
							<SegmentedControl
								label={ __(
									'Stay window',
									'radius-hotel-booking'
								) }
								value={ values.type }
								onChange={ readOnly ? () => {} : set( 'type' ) }
								options={ [
									{
										value: 'fixed',
										label: __(
											'Fixed times',
											'radius-hotel-booking'
										),
									},
									{
										value: 'flexible',
										label: __(
											'Flexible check-in',
											'radius-hotel-booking'
										),
									},
								] }
							/>
							{ errors.type ? (
								<p
									role="alert"
									className="m-0 text-xs text-destructive"
								>
									{ errors.type }
								</p>
							) : null }
						</div>

						{ values.type === 'fixed' ? (
							<div className="grid grid-cols-2 gap-3">
								{ timeSelect(
									'start_time',
									__( 'Check-in', 'radius-hotel-booking' )
								) }
								{ timeSelect(
									'end_time',
									__( 'Check-out', 'radius-hotel-booking' )
								) }
							</div>
						) : (
							<div className="space-y-3">
								<Field
									label={ __(
										'Stay length (hours)',
										'radius-hotel-booking'
									) }
									error={ errors.hours }
									description={ __(
										'In half hours, e.g. 24 or 8.5.',
										'radius-hotel-booking'
									) }
								>
									<Input
										type="number"
										inputMode="decimal"
										min={ 0.5 }
										max={ 168 }
										step={ 0.5 }
										value={ values.hours }
										onChange={ ( event ) =>
											set( 'hours' )( event.target.value )
										}
										className="w-32"
									/>
								</Field>
								<div className="grid grid-cols-2 gap-3">
									{ timeSelect(
										'checkin_from',
										__(
											'Earliest check-in',
											'radius-hotel-booking'
										)
									) }
									{ timeSelect(
										'checkin_until',
										__(
											'Latest check-in',
											'radius-hotel-booking'
										)
									) }
								</div>
							</div>
						) }

						<p
							className="m-0 rounded-lg border border-border bg-muted px-3 py-2.5 text-sm text-heading"
							aria-live="polite"
						>
							{ preview ||
								__(
									'Choose the times to see the window.',
									'radius-hotel-booking'
								) }
						</p>

						<label className="flex items-start justify-between gap-4">
							<span className="min-w-0">
								<span className="block text-sm font-semibold text-heading">
									{ __(
										'Sold for several days or nights',
										'radius-hotel-booking'
									) }
								</span>
								<span className="mt-0.5 block text-[13px] leading-5 text-muted-foreground">
									{ __(
										'On for Overnight or 24 h plans a guest can book several times in a row. Off for part-day plans such as Half Day.',
										'radius-hotel-booking'
									) }
								</span>
							</span>
							<Switch
								checked={ values.multi_unit }
								onCheckedChange={ set( 'multi_unit' ) }
								disabled={ readOnly }
								className="mt-0.5 shrink-0"
							/>
						</label>

						<Field
							label={ __( 'Features', 'radius-hotel-booking' ) }
							error={ errors.features }
							description={ __(
								'Short tags shown to guests: Non-refundable, Flexible check-in…',
								'radius-hotel-booking'
							) }
						>
							<TagInput
								value={ values.features }
								onChange={ set( 'features' ) }
								max={ 10 }
								disabled={ readOnly }
							/>
						</Field>

						<Field
							label={ __( 'Policy', 'radius-hotel-booking' ) }
							error={ errors.policy }
							description={ __(
								'Check-in and check-out rules shown on the booking page. Basic formatting only.',
								'radius-hotel-booking'
							) }
						>
							<Textarea
								value={ values.policy }
								onChange={ ( event ) =>
									set( 'policy' )( event.target.value )
								}
								rows={ 4 }
							/>
						</Field>

						<label className="flex items-start justify-between gap-4">
							<span className="min-w-0">
								<span className="block text-sm font-semibold text-heading">
									{ __( 'Active', 'radius-hotel-booking' ) }
								</span>
								<span className="mt-0.5 block text-[13px] leading-5 text-muted-foreground">
									{ __(
										'Off stops new bookings of this plan; existing bookings keep it.',
										'radius-hotel-booking'
									) }
								</span>
							</span>
							<Switch
								checked={ values.is_active }
								onCheckedChange={ set( 'is_active' ) }
								disabled={ readOnly }
								className="mt-0.5 shrink-0"
							/>
						</label>
					</fieldset>

					{ ! readOnly ? (
						<SheetFooter className="flex-row flex-wrap items-center justify-between gap-2 border-t border-border px-5 py-3 sm:justify-between">
							{ plan ? (
								<Button
									type="button"
									variant="ghost"
									className="text-destructive"
									onClick={ () => setConfirmDelete( true ) }
									disabled={ inUse }
									title={
										inUse
											? __(
													'A rate plan in use cannot be deleted. Deactivate it instead.',
													'radius-hotel-booking'
											  )
											: undefined
									}
								>
									<Trash2
										className="h-4 w-4"
										aria-hidden="true"
									/>
									{ __( 'Delete', 'radius-hotel-booking' ) }
								</Button>
							) : (
								<span />
							) }
							<div className="flex gap-2">
								<Button
									type="button"
									variant="ghost"
									onClick={ onClose }
								>
									{ __( 'Cancel', 'radius-hotel-booking' ) }
								</Button>
								<Button
									type="submit"
									disabled={ save.isPending }
								>
									{ plan
										? __( 'Save', 'radius-hotel-booking' )
										: __(
												'Add rate plan',
												'radius-hotel-booking'
										  ) }
								</Button>
							</div>
						</SheetFooter>
					) : null }
				</form>

				{ plan ? (
					<ConfirmDialog
						open={ confirmDelete }
						onOpenChange={ setConfirmDelete }
						title={ __(
							'Delete this rate plan?',
							'radius-hotel-booking'
						) }
						description={ __(
							'It disappears from the library. Past bookings keep its name and window.',
							'radius-hotel-booking'
						) }
						confirmLabel={ __(
							'Delete rate plan',
							'radius-hotel-booking'
						) }
						destructive
						onConfirm={ () =>
							remove.mutateAsync( plan.id ).then( () => {
								toast.success(
									__(
										'Rate plan deleted.',
										'radius-hotel-booking'
									)
								);
								onClose();
							} )
						}
					/>
				) : null }
			</SheetContent>
		</Sheet>
	);
}
