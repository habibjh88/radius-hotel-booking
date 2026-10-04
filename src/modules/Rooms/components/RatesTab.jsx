/**
 * The Rates tab of a room type: the Price & Rates grid (features 7.6–7.9).
 * One row per rate plan of the library: on/off, price, sale price, and for
 * plans sold several days or nights in a row, the minimum and maximum.
 * Drag rows (or use the arrows) to order them. Edits stay local until Save;
 * the server validates the whole grid and its errors land on the cells.
 */
import { useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import { __, _n, sprintf } from '@wordpress/i18n';
import { ArrowDown, ArrowUp, Clock, GripVertical } from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import Panel from '@/components/common/Panel';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Switch } from '@/components/ui/switch';
import { useAccess } from '@/lib/access';
import { currencyInfo } from '@/lib/format';
import { describeWindow } from '@/lib/stayWindow';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { useSaveTypeRates, useTypeRates } from '../api';
import PriceSimulator from './PriceSimulator';

/**
 * Editable values of a server row (numbers as input strings).
 *
 * @param {Object} row Grid row from the API.
 * @return {Object} Draft row.
 */
const toDraft = ( row ) => ( {
	plan: row.rate_plan,
	enabled: row.enabled,
	price: row.price === null ? '' : String( row.price ),
	sale_price: row.sale_price === null ? '' : String( row.sale_price ),
	min_units: String( row.min_units ?? 1 ),
	max_units: row.max_units === null ? '' : String( row.max_units ),
} );

/**
 * The API row of a draft row.
 *
 * @param {Object} row Draft row.
 * @return {Object} Body row.
 */
const toBody = ( row ) => ( {
	rate_plan_id: row.plan.id,
	enabled: row.enabled,
	price: row.price.trim(),
	sale_price: row.sale_price.trim(),
	min_units: row.min_units.trim(),
	max_units: row.max_units.trim(),
} );

/**
 * Checks the browser can make before saving (the server checks again).
 *
 * @param {Object[]} rows Draft rows.
 * @return {Object} `{ "<index>.<field>": message }`.
 */
function localErrors( rows ) {
	const errors = {};
	// The server rounds to the currency's decimals: say so instead of rounding silently.
	const { decimals } = currencyInfo();
	const tooPrecise = ( raw ) =>
		raw.trim() !== '' &&
		Math.abs(
			Number( raw ) * 10 ** decimals -
				Math.round( Number( raw ) * 10 ** decimals )
		) > 1e-6;
	const precisionMessage = decimals
		? sprintf(
				/* translators: %d: number of decimals. */
				_n(
					'Use at most %d decimal.',
					'Use at most %d decimals.',
					decimals,
					'radius-hotel-booking'
				),
				decimals
		  )
		: __( 'Use a whole amount.', 'radius-hotel-booking' );
	rows.forEach( ( row, index ) => {
		[ 'price', 'sale_price' ].forEach( ( key ) => {
			if ( tooPrecise( row[ key ] ) ) {
				errors[ `${ index }.${ key }` ] = precisionMessage;
			}
		} );
		const price = row.price.trim() === '' ? null : Number( row.price );
		const sale =
			row.sale_price.trim() === '' ? null : Number( row.sale_price );
		if ( row.enabled && price === null ) {
			errors[ `${ index }.price` ] = __(
				'Enter the price to sell this rate plan.',
				'radius-hotel-booking'
			);
		}
		if ( sale !== null && price !== null && sale >= price ) {
			errors[ `${ index }.sale_price` ] = __(
				'The sale price must be lower than the price.',
				'radius-hotel-booking'
			);
		}
		if ( row.plan.multi_unit ) {
			const min = Number( row.min_units );
			const max =
				row.max_units.trim() === '' ? null : Number( row.max_units );
			if ( max !== null && max < min ) {
				errors[ `${ index }.max_units` ] = __(
					'The maximum must be at least the minimum.',
					'radius-hotel-booking'
				);
			}
		}
	} );
	return errors;
}

/**
 * @param {Object} props      Props.
 * @param {Object} props.type Room type.
 * @return {JSX.Element} Tab.
 */
export default function RatesTab( { type } ) {
	const { data: rows, isPending, error, refetch } = useTypeRates( type.id );
	const save = useSaveTypeRates( type.id );
	const readOnly = useAccess( 'rates.manage' ) === 'locked';
	const [ draft, setDraft ] = useState( [] );
	const [ errors, setErrors ] = useState( {} );
	const [ dragIndex, setDragIndex ] = useState( null );
	const { symbol, decimals } = currencyInfo();

	const original = useMemo( () => ( rows || [] ).map( toDraft ), [ rows ] );
	useEffect( () => {
		setDraft( original );
		setErrors( {} );
	}, [ original ] );

	const dirty =
		JSON.stringify( draft.map( toBody ) ) !==
		JSON.stringify( original.map( toBody ) );

	const setCell = ( index, key, value ) => {
		setDraft( ( prev ) =>
			prev.map( ( row, i ) =>
				i === index ? { ...row, [ key ]: value } : row
			)
		);
		setErrors( ( prev ) => ( {
			...prev,
			[ `${ index }.${ key }` ]: undefined,
		} ) );
	};

	const move = ( from, to ) => {
		if ( to < 0 || to >= draft.length || from === to ) {
			return;
		}
		setDraft( ( prev ) => {
			const next = [ ...prev ];
			const [ row ] = next.splice( from, 1 );
			next.splice( to, 0, row );
			return next;
		} );
		// Cell errors are keyed by position: they no longer match.
		setErrors( {} );
	};

	const submit = async () => {
		const local = localErrors( draft );
		if ( Object.keys( local ).length ) {
			setErrors( local );
			return;
		}
		try {
			await save.mutateAsync( draft.map( toBody ) );
			toast.success( __( 'Prices saved.', 'radius-hotel-booking' ) );
		} catch ( e ) {
			const fields = {};
			Object.entries( e?.errors || {} ).forEach( ( [ key, detail ] ) => {
				fields[ key.replace( /^rates\./, '' ) ] =
					detail?.first_message || '';
			} );
			setErrors( fields );
			if ( ! Object.keys( fields ).length ) {
				toastError( e );
			}
		}
	};

	if ( isPending ) {
		return <Skeleton className="h-64 w-full rounded-xl" />;
	}
	if ( error ) {
		return (
			<Panel>
				<EmptyState
					icon={ Clock }
					title={ __(
						'The prices could not be loaded',
						'radius-hotel-booking'
					) }
					description={ error.message }
					action={
						<Button variant="outline" onClick={ () => refetch() }>
							{ __( 'Try again', 'radius-hotel-booking' ) }
						</Button>
					}
					className="border-0"
				/>
			</Panel>
		);
	}
	if ( ! draft.length ) {
		return (
			<Panel>
				<EmptyState
					icon={ Clock }
					title={ __( 'No rate plans yet', 'radius-hotel-booking' ) }
					description={ __(
						'Create your stay windows first, then price them here.',
						'radius-hotel-booking'
					) }
					action={
						<Button asChild>
							<Link to="/rate-plans">
								{ __(
									'Go to rate plans',
									'radius-hotel-booking'
								) }
							</Link>
						</Button>
					}
					className="border-0"
				/>
			</Panel>
		);
	}

	const money = ( index, key, label ) => {
		const message = errors[ `${ index }.${ key }` ];
		return (
			<label className="block min-w-0 space-y-1">
				<span className="block text-xs font-medium text-muted-foreground md:sr-only">
					{ label }
				</span>
				<div className="relative">
					<Input
						type="number"
						inputMode="decimal"
						min={ 0 }
						step={ decimals ? 10 ** -decimals : 1 }
						value={ draft[ index ][ key ] }
						onChange={ ( event ) =>
							setCell( index, key, event.target.value )
						}
						disabled={ readOnly }
						aria-label={ `${ label } — ${ draft[ index ].plan.name }` }
						aria-invalid={ message ? true : undefined }
						placeholder={
							key === 'sale_price'
								? __( 'None', 'radius-hotel-booking' )
								: ''
						}
						className={ cn(
							'pr-12 tabular-nums',
							message && '!border-destructive'
						) }
					/>
					{ symbol ? (
						<span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-xs text-muted-foreground">
							{ symbol }
						</span>
					) : null }
				</div>
				{ message ? (
					<span
						role="alert"
						className="block text-xs text-destructive"
					>
						{ message }
					</span>
				) : null }
			</label>
		);
	};

	const units = ( index, key, label ) => {
		const message = errors[ `${ index }.${ key }` ];
		return (
			<label className="block min-w-0 space-y-1">
				<span className="block text-xs font-medium text-muted-foreground md:sr-only">
					{ label }
				</span>
				<Input
					type="number"
					inputMode="numeric"
					min={ 1 }
					step={ 1 }
					value={ draft[ index ][ key ] }
					onChange={ ( event ) =>
						setCell( index, key, event.target.value )
					}
					disabled={ readOnly }
					aria-label={ `${ label } — ${ draft[ index ].plan.name }` }
					aria-invalid={ message ? true : undefined }
					placeholder={
						key === 'max_units'
							? __( 'Any', 'radius-hotel-booking' )
							: ''
					}
					className={ cn(
						'tabular-nums',
						message && '!border-destructive'
					) }
				/>
				{ message ? (
					<span
						role="alert"
						className="block text-xs text-destructive"
					>
						{ message }
					</span>
				) : null }
			</label>
		);
	};

	const columns =
		'md:grid-cols-[2.5rem_3rem_minmax(0,1fr)_8.5rem_8.5rem_5rem_5rem]';

	return (
		<div className="space-y-4">
			<Panel
				title={ __( 'Price & Rates', 'radius-hotel-booking' ) }
				description={ __(
					'Switch on the rate plans this room type sells and set their prices. The order here is the order guests see.',
					'radius-hotel-booking'
				) }
				bodyClassName="p-0"
			>
				<div
					className={ cn(
						'hidden gap-3 border-b border-border px-5 py-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground md:grid',
						columns
					) }
					aria-hidden="true"
				>
					<span />
					<span>{ __( 'Sell', 'radius-hotel-booking' ) }</span>
					<span>{ __( 'Rate plan', 'radius-hotel-booking' ) }</span>
					<span>{ __( 'Price', 'radius-hotel-booking' ) }</span>
					<span>{ __( 'Sale price', 'radius-hotel-booking' ) }</span>
					<span>{ __( 'Min', 'radius-hotel-booking' ) }</span>
					<span>{ __( 'Max', 'radius-hotel-booking' ) }</span>
				</div>
				<ol className="m-0 list-none divide-y divide-border p-0">
					{ draft.map( ( row, index ) => (
						<li
							key={ row.plan.id }
							className={ cn(
								'm-0 grid grid-cols-2 items-start gap-3 px-5 py-3',
								columns,
								dragIndex === index && 'opacity-50',
								! row.enabled && 'bg-muted'
							) }
							draggable={ ! readOnly }
							onDragStart={ ( event ) => {
								event.dataTransfer.effectAllowed = 'move';
								setDragIndex( index );
							} }
							onDragEnd={ () => setDragIndex( null ) }
							onDragOver={ ( event ) =>
								! readOnly && event.preventDefault()
							}
							onDrop={ ( event ) => {
								event.preventDefault();
								if ( dragIndex !== null ) {
									move( dragIndex, index );
								}
								setDragIndex( null );
							} }
						>
							<div className="col-span-2 flex items-center gap-1 md:col-span-1 md:flex-col md:pt-1">
								{ ! readOnly ? (
									<>
										<GripVertical
											className="hidden h-4 w-4 cursor-grab text-muted-foreground md:block"
											aria-hidden="true"
										/>
										<Button
											type="button"
											variant="ghost"
											size="icon"
											className="h-7 w-7 md:hidden"
											onClick={ () =>
												move( index, index - 1 )
											}
											disabled={ index === 0 }
											aria-label={ sprintf(
												/* translators: %s: rate plan name. */
												__(
													'Move %s up',
													'radius-hotel-booking'
												),
												row.plan.name
											) }
										>
											<ArrowUp
												className="h-4 w-4"
												aria-hidden="true"
											/>
										</Button>
										<Button
											type="button"
											variant="ghost"
											size="icon"
											className="h-7 w-7 md:hidden"
											onClick={ () =>
												move( index, index + 1 )
											}
											disabled={
												index === draft.length - 1
											}
											aria-label={ sprintf(
												/* translators: %s: rate plan name. */
												__(
													'Move %s down',
													'radius-hotel-booking'
												),
												row.plan.name
											) }
										>
											<ArrowDown
												className="h-4 w-4"
												aria-hidden="true"
											/>
										</Button>
									</>
								) : null }
								<Switch
									className="ml-auto md:hidden"
									checked={ row.enabled }
									onCheckedChange={ ( on ) =>
										setCell( index, 'enabled', on )
									}
									disabled={ readOnly }
									aria-label={ sprintf(
										/* translators: %s: rate plan name. */
										__( 'Sell %s', 'radius-hotel-booking' ),
										row.plan.name
									) }
								/>
							</div>
							<div className="hidden pt-2 md:block">
								<Switch
									checked={ row.enabled }
									onCheckedChange={ ( on ) =>
										setCell( index, 'enabled', on )
									}
									disabled={ readOnly }
									aria-label={ sprintf(
										/* translators: %s: rate plan name. */
										__( 'Sell %s', 'radius-hotel-booking' ),
										row.plan.name
									) }
								/>
							</div>
							<div className="col-span-2 min-w-0 md:col-span-1 md:pt-1">
								<p className="m-0 flex flex-wrap items-center gap-2 text-sm font-semibold text-heading">
									{ row.plan.name }
									{ ! row.plan.is_active ? (
										<Badge variant="outline">
											{ __(
												'Plan inactive',
												'radius-hotel-booking'
											) }
										</Badge>
									) : null }
								</p>
								<p className="m-0 mt-0.5 text-xs text-muted-foreground">
									{ describeWindow( row.plan ) }
								</p>
								{ errors[ `${ index }.rate_plan_id` ] ? (
									<p
										role="alert"
										className="m-0 mt-1 text-xs text-destructive"
									>
										{ errors[ `${ index }.rate_plan_id` ] }
									</p>
								) : null }
							</div>
							{ money(
								index,
								'price',
								__( 'Price', 'radius-hotel-booking' )
							) }
							{ money(
								index,
								'sale_price',
								__( 'Sale price', 'radius-hotel-booking' )
							) }
							{ row.plan.multi_unit ? (
								<>
									{ units(
										index,
										'min_units',
										__( 'Min', 'radius-hotel-booking' )
									) }
									{ units(
										index,
										'max_units',
										__( 'Max', 'radius-hotel-booking' )
									) }
								</>
							) : (
								<p className="col-span-2 m-0 self-center text-xs text-muted-foreground md:pt-2.5">
									{ __( 'One stay', 'radius-hotel-booking' ) }
								</p>
							) }
						</li>
					) ) }
				</ol>
			</Panel>

			{ ! readOnly && dirty ? (
				<div className="sticky bottom-[4.5rem] z-10 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border bg-card px-4 py-3 shadow-lg md:bottom-4">
					<span className="text-sm text-heading">
						{ __(
							'You have unsaved price changes.',
							'radius-hotel-booking'
						) }
					</span>
					<div className="flex gap-2">
						<Button
							type="button"
							variant="ghost"
							onClick={ () => {
								setDraft( original );
								setErrors( {} );
							} }
							disabled={ save.isPending }
						>
							{ __( 'Discard', 'radius-hotel-booking' ) }
						</Button>
						<Button
							type="button"
							onClick={ submit }
							disabled={ save.isPending }
						>
							{ save.isPending
								? __( 'Saving…', 'radius-hotel-booking' )
								: __( 'Save prices', 'radius-hotel-booking' ) }
						</Button>
					</div>
				</div>
			) : null }

			<PriceSimulator type={ type } rates={ rows } dirty={ dirty } />
		</div>
	);
}
