/**
 * "Add common amenities": the amenities most hotels offer, grouped, all
 * ticked. Untick what the hotel does not offer and add the rest to the
 * amenity list in one go. Amenities already in the list are shown as added.
 */
import { useEffect, useMemo, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
	Dialog,
	DialogContent,
	DialogDescription,
	DialogFooter,
	DialogHeader,
	DialogTitle,
} from '@/components/ui/dialog';
import { Skeleton } from '@/components/ui/skeleton';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { useCommonAmenities, useImportAmenities } from '../api';

/**
 * @param {Object}   props              Props.
 * @param {boolean}  props.open         Open state.
 * @param {Function} props.onOpenChange Open-state setter.
 * @return {JSX.Element} Dialog.
 */
export default function CommonAmenitiesDialog( { open, onOpenChange } ) {
	const {
		data: groups,
		isPending,
		error,
		refetch,
	} = useCommonAmenities( open );
	const importAmenities = useImportAmenities();
	const [ chosen, setChosen ] = useState( [] );

	// Names not in the list yet, in display order.
	const available = useMemo(
		() =>
			( groups ?? [] ).flatMap( ( group ) =>
				group.amenities
					.filter( ( amenity ) => ! amenity.added )
					.map( ( amenity ) => amenity.name )
			),
		[ groups ]
	);

	// Everything new starts ticked each time the list loads.
	useEffect( () => {
		setChosen( available );
	}, [ available ] );

	const toggle = ( name, on ) =>
		setChosen( ( prev ) =>
			on
				? available.filter(
						( item ) => item === name || prev.includes( item )
				  )
				: prev.filter( ( item ) => item !== name )
		);

	const submit = () =>
		importAmenities.mutate( chosen, {
			onSuccess: ( data ) => {
				toast.success( data.message );
				onOpenChange( false );
			},
			onError: ( e ) => toastError( e ),
		} );

	let body;
	if ( isPending ) {
		body = (
			<div className="space-y-2">
				{ [ 0, 1, 2, 3 ].map( ( key ) => (
					<Skeleton key={ key } className="h-9 w-full" />
				) ) }
			</div>
		);
	} else if ( error ) {
		body = (
			<div className="flex flex-wrap items-center gap-2 text-sm">
				<span className="text-destructive">{ error.message }</span>
				<Button
					type="button"
					variant="outline"
					size="sm"
					onClick={ () => refetch() }
				>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	} else if ( ! available.length ) {
		body = (
			<p className="m-0 text-sm text-muted-foreground">
				{ __(
					'Every common amenity is already in your list.',
					'radius-hotel-booking'
				) }
			</p>
		);
	} else {
		body = (
			<div className="space-y-4">
				<div className="flex flex-wrap items-center gap-x-3 gap-y-1">
					<span className="text-sm font-medium text-heading">
						{ sprintf(
							/* translators: 1: amenities ticked, 2: amenities offered. */
							__(
								'%1$d of %2$d selected',
								'radius-hotel-booking'
							),
							chosen.length,
							available.length
						) }
					</span>
					<div className="flex items-center gap-1">
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => setChosen( available ) }
							disabled={ chosen.length === available.length }
						>
							{ __( 'Select all', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => setChosen( [] ) }
							disabled={ ! chosen.length }
						>
							{ __( 'Clear', 'radius-hotel-booking' ) }
						</Button>
					</div>
				</div>

				{ groups.map( ( group, groupIndex ) => (
					<fieldset key={ group.label } className="m-0 border-0 p-0">
						<legend className="mb-1.5 p-0 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
							{ group.label }
						</legend>
						<ul className="m-0 grid list-none gap-1 p-0 sm:grid-cols-2">
							{ group.amenities.map( ( amenity, index ) => {
								const id = `rtbp-common-amenity-${ groupIndex }-${ index }`;
								const on =
									amenity.added ||
									chosen.includes( amenity.name );
								return (
									<li key={ amenity.name } className="m-0">
										<label
											htmlFor={ id }
											className={ cn(
												'flex min-h-9 items-center gap-2 rounded-md px-2 py-1.5 text-sm',
												amenity.added
													? 'cursor-default text-muted-foreground'
													: 'cursor-pointer hover:bg-muted',
												on &&
													! amenity.added &&
													'bg-primary-soft hover:bg-primary-soft'
											) }
										>
											<Checkbox
												id={ id }
												checked={ on }
												disabled={ amenity.added }
												onCheckedChange={ ( checked ) =>
													toggle(
														amenity.name,
														checked === true
													)
												}
											/>
											<span className="min-w-0 flex-1 break-words">
												{ amenity.name }
											</span>
											{ amenity.added ? (
												<span className="shrink-0 text-xs">
													{ __(
														'Added',
														'radius-hotel-booking'
													) }
												</span>
											) : null }
										</label>
									</li>
								);
							} ) }
						</ul>
					</fieldset>
				) ) }
			</div>
		);
	}

	return (
		<Dialog open={ open } onOpenChange={ onOpenChange }>
			<DialogContent className="flex max-h-[90vh] max-w-xl flex-col">
				<DialogHeader>
					<DialogTitle>
						{ __( 'Add common amenities', 'radius-hotel-booking' ) }
					</DialogTitle>
					<DialogDescription>
						{ __(
							'Amenities most hotels offer. Untick any your rooms do not have; you can rename or remove them later.',
							'radius-hotel-booking'
						) }
					</DialogDescription>
				</DialogHeader>

				<div className="-mx-1 min-h-0 flex-1 overflow-y-auto px-1">
					{ body }
				</div>

				<DialogFooter className="gap-2">
					<Button
						type="button"
						variant="ghost"
						onClick={ () => onOpenChange( false ) }
					>
						{ __( 'Cancel', 'radius-hotel-booking' ) }
					</Button>
					<Button
						type="button"
						onClick={ submit }
						disabled={
							! chosen.length || importAmenities.isPending
						}
					>
						{ chosen.length
							? sprintf(
									/* translators: %d: number of amenities. */
									_n(
										'Add %d amenity',
										'Add %d amenities',
										chosen.length,
										'radius-hotel-booking'
									),
									chosen.length
							  )
							: __( 'Add amenities', 'radius-hotel-booking' ) }
					</Button>
				</DialogFooter>
			</DialogContent>
		</Dialog>
	);
}
