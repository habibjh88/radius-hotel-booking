/**
 * The price simulator (feature 7.16): pick a rate plan this room type sells,
 * an arrival date, the nights or days and the day the booking is made, and
 * see the price with every step that made it. It quotes the saved prices.
 */
import { useMemo, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Calculator } from 'lucide-react';

import Money from '@/components/common/Money';
import Panel from '@/components/common/Panel';
import PriceBreakdown from '@/components/common/PriceBreakdown';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { formatDate, siteToday } from '@/lib/format';
import { describeWindow, halfHourOptions, minutesOf } from '@/lib/stayWindow';
import { useQuote } from '../api';

/**
 * @param {Object}   props       Props.
 * @param {Object}   props.type  Room type.
 * @param {Object[]} props.rates Saved grid rows.
 * @param {boolean}  props.dirty The grid has unsaved changes.
 * @return {JSX.Element} Panel.
 */
export default function PriceSimulator( { type, rates, dirty } ) {
	const sold = useMemo(
		() =>
			( rates || [] ).filter(
				( row ) => row.enabled && row.rate_plan.is_active
			),
		[ rates ]
	);
	const quote = useQuote();
	const today = siteToday();
	const [ values, setValues ] = useState( {
		rate_plan_id: '',
		arrival: today,
		units: '1',
		checkin_time: '',
		booked_at: today,
	} );
	const [ result, setResult ] = useState( null );
	const [ message, setMessage ] = useState( '' );

	const planId =
		values.rate_plan_id || String( sold[ 0 ]?.rate_plan.id ?? '' );
	const plan = sold.find(
		( row ) => String( row.rate_plan.id ) === planId
	)?.rate_plan;
	const checkins = useMemo( () => {
		if ( plan?.type !== 'flexible' ) {
			return [];
		}
		const from = minutesOf( plan.checkin_from ) ?? 0;
		const until = minutesOf( plan.checkin_until ) ?? 1410;
		return halfHourOptions().filter( ( option ) => {
			const at = minutesOf( option.value );
			return at >= from && at <= until;
		} );
	}, [ plan ] );

	const set = ( key ) => ( value ) => {
		setValues( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setResult( null );
		setMessage( '' );
	};

	const run = async ( event ) => {
		event.preventDefault();
		setMessage( '' );
		try {
			const data = await quote.mutateAsync( {
				room_type_id: type.id,
				rate_plan_id: Number( planId ),
				arrival: values.arrival,
				units: plan?.multi_unit ? Number( values.units ) || 1 : 1,
				checkin_time:
					plan?.type === 'flexible'
						? values.checkin_time || plan.checkin_from
						: '',
				booked_at: values.booked_at,
			} );
			setResult( { data, plan, values: { ...values } } );
		} catch ( e ) {
			setResult( null );
			setMessage(
				Object.values( e?.errors || {} )[ 0 ]?.first_message ||
					e?.message ||
					__(
						'The price could not be worked out.',
						'radius-hotel-booking'
					)
			);
		}
	};

	if ( ! sold.length ) {
		return (
			<Panel title={ __( 'Price simulator', 'radius-hotel-booking' ) }>
				<p className="m-0 text-sm text-muted-foreground">
					{ __(
						'Switch on and save at least one rate plan to try prices here.',
						'radius-hotel-booking'
					) }
				</p>
			</Panel>
		);
	}

	const field = ( id, label, control ) => (
		<div className="min-w-0 space-y-1.5">
			<Label
				htmlFor={ id }
				className="text-sm font-semibold text-heading"
			>
				{ label }
			</Label>
			{ control }
		</div>
	);

	return (
		<Panel
			title={ __( 'Price simulator', 'radius-hotel-booking' ) }
			description={ __(
				'See what a stay costs and why, with the saved prices and every pricing rule.',
				'radius-hotel-booking'
			) }
		>
			<form onSubmit={ run } noValidate className="space-y-4">
				<div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
					{ field(
						'rtbp-sim-plan',
						__( 'Rate plan', 'radius-hotel-booking' ),
						<Select
							value={ planId }
							onValueChange={ set( 'rate_plan_id' ) }
						>
							<SelectTrigger id="rtbp-sim-plan">
								<SelectValue />
							</SelectTrigger>
							<SelectContent>
								{ sold.map( ( row ) => (
									<SelectItem
										key={ row.rate_plan.id }
										value={ String( row.rate_plan.id ) }
									>
										{ row.rate_plan.name }
									</SelectItem>
								) ) }
							</SelectContent>
						</Select>
					) }
					{ field(
						'rtbp-sim-arrival',
						__( 'Arrival', 'radius-hotel-booking' ),
						<Input
							id="rtbp-sim-arrival"
							type="date"
							value={ values.arrival }
							onChange={ ( event ) =>
								set( 'arrival' )( event.target.value )
							}
							required
						/>
					) }
					{ plan?.multi_unit
						? field(
								'rtbp-sim-units',
								__( 'Nights or days', 'radius-hotel-booking' ),
								<Input
									id="rtbp-sim-units"
									type="number"
									inputMode="numeric"
									min={ 1 }
									max={ 365 }
									value={ values.units }
									onChange={ ( event ) =>
										set( 'units' )( event.target.value )
									}
								/>
						  )
						: null }
					{ plan?.type === 'flexible'
						? field(
								'rtbp-sim-checkin',
								__( 'Check-in', 'radius-hotel-booking' ),
								<Select
									value={
										values.checkin_time || plan.checkin_from
									}
									onValueChange={ set( 'checkin_time' ) }
								>
									<SelectTrigger id="rtbp-sim-checkin">
										<SelectValue />
									</SelectTrigger>
									<SelectContent className="max-h-72">
										{ checkins.map( ( option ) => (
											<SelectItem
												key={ option.value }
												value={ option.value }
											>
												{ option.label }
											</SelectItem>
										) ) }
									</SelectContent>
								</Select>
						  )
						: null }
					{ field(
						'rtbp-sim-booked',
						__( 'Booked on', 'radius-hotel-booking' ),
						<Input
							id="rtbp-sim-booked"
							type="date"
							value={ values.booked_at }
							onChange={ ( event ) =>
								set( 'booked_at' )( event.target.value )
							}
						/>
					) }
				</div>

				{ plan ? (
					<p className="m-0 text-xs text-muted-foreground">
						{ describeWindow( plan ) }
					</p>
				) : null }

				<div className="flex flex-wrap items-center gap-3">
					<Button
						type="submit"
						disabled={ quote.isPending || ! values.arrival }
					>
						<Calculator className="h-4 w-4" aria-hidden="true" />
						{ __( 'Show the price', 'radius-hotel-booking' ) }
					</Button>
					{ dirty ? (
						<span className="text-xs text-warning">
							{ __(
								'Save your price changes to include them.',
								'radius-hotel-booking'
							) }
						</span>
					) : null }
				</div>

				{ message ? (
					<p role="alert" className="m-0 text-sm text-destructive">
						{ message }
					</p>
				) : null }

				{ result ? (
					<div
						className="space-y-3 rounded-lg border border-border bg-muted p-4"
						aria-live="polite"
					>
						<p className="m-0 flex flex-wrap items-baseline justify-between gap-2 text-sm text-heading">
							<span>
								{ sprintf(
									/* translators: 1: room type, 2: rate plan, 3: arrival date, 4: booking date. */
									__(
										'%1$s × %2$s, arriving %3$s, booked %4$s',
										'radius-hotel-booking'
									),
									type.name,
									result.plan.name,
									formatDate( result.values.arrival ),
									result.values.booked_at === today
										? __( 'today', 'radius-hotel-booking' )
										: formatDate( result.values.booked_at )
								) }
							</span>
							<span className="text-lg font-bold">
								<Money value={ result.data.total } />
							</span>
						</p>
						<PriceBreakdown quote={ result.data } />
					</div>
				) : null }
			</form>
		</Panel>
	);
}
