/**
 * Amenities of a room type, picked from the hotel's shared amenity library
 * (`#/rooms/amenities`): one checkbox per amenity, select all / clear, a
 * filter for long lists, and "add new", which adds to the library and ticks
 * it. The value is a list of names, kept in library order.
 */
import { useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { Plus, Search, Settings2 } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import { useAddAmenity, useAmenities } from '../api';

/** Show the filter box from this many amenities. */
const FILTER_FROM = 12;

const same = ( a, b ) => a.toLowerCase() === b.toLowerCase();

/**
 * @param {Object}   props           Props.
 * @param {string[]} props.value     Chosen amenity names.
 * @param {Function} props.onChange  Called with the new names.
 * @param {boolean}  props.disabled  Read-only.
 * @param {boolean}  props.canManage May add to the library.
 * @param {number}   props.max       Most amenities a room type may have.
 * @return {JSX.Element} Control.
 */
export default function AmenityPicker( {
	value = [],
	onChange,
	disabled = false,
	canManage = false,
	max = 40,
} ) {
	const { data: library, isPending, error, refetch } = useAmenities();
	const [ filter, setFilter ] = useState( '' );

	// Library names, plus any chosen name the library does not hold (yet).
	const names = useMemo( () => {
		const list = ( library ?? [] ).map( ( amenity ) => amenity.name );
		value.forEach( ( name ) => {
			if ( ! list.some( ( item ) => same( item, name ) ) ) {
				list.push( name );
			}
		} );
		return list;
	}, [ library, value ] );

	const isChosen = ( name ) => value.some( ( item ) => same( item, name ) );

	// Keep the value in library order; a name just added (not in this
	// render's list yet) goes last.
	const choose = ( chosen ) =>
		onChange( [
			...names.filter( ( name ) =>
				chosen.some( ( item ) => same( item, name ) )
			),
			...chosen.filter(
				( item ) => ! names.some( ( name ) => same( item, name ) )
			),
		] );

	const toggle = ( name, on ) =>
		on
			? choose( [ ...value, name ] )
			: onChange( value.filter( ( item ) => ! same( item, name ) ) );

	if ( isPending ) {
		return (
			<div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
				{ [ 0, 1, 2, 3, 4, 5 ].map( ( key ) => (
					<Skeleton key={ key } className="h-9 w-full" />
				) ) }
			</div>
		);
	}

	if ( error ) {
		return (
			<div className="flex flex-wrap items-center gap-2 rounded-lg border border-border p-3 text-sm">
				<span className="text-destructive">
					{ __(
						'The amenity list could not be loaded.',
						'radius-hotel-booking'
					) }
				</span>
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
	}

	const term = filter.trim().toLowerCase();
	const shown = term
		? names.filter( ( name ) => name.toLowerCase().includes( term ) )
		: names;
	const full = value.length >= max;

	return (
		<div className="space-y-3 rounded-lg border border-border p-3">
			<div className="flex flex-wrap items-center gap-x-3 gap-y-2">
				<span className="text-sm font-medium text-heading">
					{ sprintf(
						/* translators: 1: chosen amenities, 2: amenities in the library. */
						__( '%1$d of %2$d selected', 'radius-hotel-booking' ),
						value.length,
						names.length
					) }
				</span>
				{ ! disabled && names.length ? (
					<div className="flex items-center gap-1">
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => choose( names.slice( 0, max ) ) }
							disabled={ value.length === names.length }
						>
							{ __( 'Select all', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ () => onChange( [] ) }
							disabled={ ! value.length }
						>
							{ __( 'Clear', 'radius-hotel-booking' ) }
						</Button>
					</div>
				) : null }
				<Link
					to="/rooms/amenities"
					className="ml-auto inline-flex items-center gap-1.5 text-sm font-medium text-primary no-underline hover:underline"
				>
					<Settings2 className="h-4 w-4" aria-hidden="true" />
					{ __( 'Manage list', 'radius-hotel-booking' ) }
				</Link>
			</div>

			{ names.length > FILTER_FROM ? (
				<div className="relative">
					<Search
						className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground"
						aria-hidden="true"
					/>
					<Input
						type="search"
						value={ filter }
						onChange={ ( event ) =>
							setFilter( event.target.value )
						}
						placeholder={ __(
							'Filter amenities',
							'radius-hotel-booking'
						) }
						aria-label={ __(
							'Filter amenities',
							'radius-hotel-booking'
						) }
						className="pl-8"
					/>
				</div>
			) : null }

			{ names.length ? (
				<ul className="m-0 grid list-none gap-1 p-0 sm:grid-cols-2 lg:grid-cols-3">
					{ shown.map( ( name, index ) => {
						const id = `rtbp-amenity-${ index }-${ name.length }`;
						const on = isChosen( name );
						return (
							<li key={ name } className="m-0">
								<label
									htmlFor={ id }
									className={ cn(
										'flex min-h-9 cursor-pointer items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-muted',
										on &&
											'bg-primary-soft hover:bg-primary-soft',
										( disabled || ( full && ! on ) ) &&
											'cursor-not-allowed opacity-60'
									) }
								>
									<Checkbox
										id={ id }
										checked={ on }
										disabled={
											disabled || ( full && ! on )
										}
										onCheckedChange={ ( checked ) =>
											toggle( name, checked === true )
										}
									/>
									<span className="min-w-0 break-words">
										{ name }
									</span>
								</label>
							</li>
						);
					} ) }
					{ ! shown.length ? (
						<li className="m-0 px-2 py-1.5 text-sm text-muted-foreground">
							{ __(
								'No amenity matches.',
								'radius-hotel-booking'
							) }
						</li>
					) : null }
				</ul>
			) : (
				<p className="m-0 text-sm text-muted-foreground">
					{ __(
						'No amenities yet. Add the first one below.',
						'radius-hotel-booking'
					) }
				</p>
			) }

			{ full ? (
				<p className="m-0 text-xs text-muted-foreground">
					{ sprintf(
						/* translators: %d: most amenities per room type. */
						_n(
							'A room type can have up to %d amenity.',
							'A room type can have up to %d amenities.',
							max,
							'radius-hotel-booking'
						),
						max
					) }
				</p>
			) : null }

			{ canManage && ! disabled ? (
				<AddAmenity
					names={ names }
					onAdded={ ( name ) =>
						! full &&
						! isChosen( name ) &&
						choose( [ ...value, name ] )
					}
				/>
			) : null }
		</div>
	);
}

/**
 * "Add a new amenity": adds it to the library and ticks it. A name that is
 * already in the list is just ticked.
 *
 * @param {Object}   props         Props.
 * @param {string[]} props.names   Names already offered.
 * @param {Function} props.onAdded Called with the name to tick.
 * @return {JSX.Element} Form row.
 */
function AddAmenity( { names, onAdded } ) {
	const add = useAddAmenity();
	const [ name, setName ] = useState( '' );
	const [ error, setError ] = useState( '' );

	const submit = () => {
		const clean = name.trim();
		if ( ! clean ) {
			return;
		}
		const existing = names.find( ( item ) => same( item, clean ) );
		if ( existing ) {
			onAdded( existing );
			setName( '' );
			setError( '' );
			return;
		}
		add.mutate( clean, {
			onSuccess: ( data ) => {
				onAdded( data.amenity.name );
				setName( '' );
				setError( '' );
			},
			onError: ( e ) =>
				setError( e?.errors?.name?.first_message || e?.message || '' ),
		} );
	};

	// Not a <form>: this sits inside the room type form.
	return (
		<div className="space-y-1.5 border-t border-border pt-3">
			<div className="flex gap-2">
				<Input
					value={ name }
					onChange={ ( event ) => setName( event.target.value ) }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							submit();
						}
					} }
					maxLength={ 60 }
					placeholder={ __(
						'New amenity, e.g. Balcony',
						'radius-hotel-booking'
					) }
					aria-label={ __(
						'New amenity name',
						'radius-hotel-booking'
					) }
					aria-invalid={ error ? true : undefined }
					className={ cn(
						'min-w-0 flex-1',
						error && '!border-destructive'
					) }
				/>
				<Button
					type="button"
					variant="outline"
					onClick={ submit }
					disabled={ add.isPending || ! name.trim() }
				>
					<Plus className="h-4 w-4" aria-hidden="true" />
					{ __( 'Add', 'radius-hotel-booking' ) }
				</Button>
			</div>
			{ error ? (
				<p role="alert" className="m-0 text-xs text-destructive">
					{ error }
				</p>
			) : null }
		</div>
	);
}
