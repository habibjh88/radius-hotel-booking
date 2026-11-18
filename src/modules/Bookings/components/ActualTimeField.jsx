/**
 * "Did it happen earlier?": a check-in or check-out recorded late (the desk
 * forgot) can carry the time it really happened. Folded into one line until
 * asked for; shown only with `bookings.set_time` (the server checks again,
 * and refuses a time in the future or before the stay / the check-in). The
 * time chosen goes in the activity log.
 *
 * The value is a local `YYYY-MM-DDTHH:MM` ('' = now).
 */
import { useId, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Clock } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useAccess } from '@/lib/access';
import { siteNowTime, siteToday } from '@/lib/format';

/**
 * A server ISO time (in the site's zone) as a `datetime-local` value.
 *
 * @param {string|null} iso ISO 8601 with the site's offset.
 * @return {string} `YYYY-MM-DDTHH:MM`, or ''.
 */
const localValue = ( iso ) => ( iso ? String( iso ).slice( 0, 16 ) : '' );

/**
 * @param {Object}   props          Props.
 * @param {string}   props.kind     `check_in` | `check_out`.
 * @param {string}   props.value    Local `YYYY-MM-DDTHH:MM`, '' for now.
 * @param {Function} props.onChange Called with the new value ('' = now).
 * @param {string}   props.earliest ISO time it cannot be before (the stay's start, the check-in).
 * @param {boolean}  props.disabled Busy.
 * @return {JSX.Element|null} Field.
 */
export default function ActualTimeField( {
	kind,
	value,
	onChange,
	earliest,
	disabled = false,
} ) {
	const id = useId();
	const [ open, setOpen ] = useState( Boolean( value ) );
	if ( 'locked' === useAccess( 'bookings.set_time' ) ) {
		return null;
	}
	const now = `${ siteToday() }T${ siteNowTime() }`;
	const isOut = 'check_out' === kind;

	if ( ! open ) {
		return (
			<p className="m-0 flex flex-wrap items-center gap-x-1.5 gap-y-1 rounded-lg border border-dashed border-border px-3 py-2 text-[13px] text-muted-foreground">
				<Clock className="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
				{ isOut
					? __(
							'Did the guest leave earlier?',
							'radius-hotel-booking'
					  )
					: __(
							'Did the guest arrive earlier?',
							'radius-hotel-booking'
					  ) }
				<button
					type="button"
					className="m-0 cursor-pointer border-0 bg-transparent p-0 font-semibold text-primary underline-offset-2 hover:underline"
					onClick={ () => {
						setOpen( true );
						onChange( now );
					} }
					disabled={ disabled }
				>
					{ isOut
						? __(
								'Change the check-out time',
								'radius-hotel-booking'
						  )
						: __(
								'Change the check-in time',
								'radius-hotel-booking'
						  ) }
				</button>
			</p>
		);
	}

	return (
		<div className="space-y-1.5 rounded-lg border border-border bg-muted/40 p-3">
			<Label
				htmlFor={ id }
				className="text-sm font-semibold text-heading"
			>
				{ isOut
					? __( 'Actual check-out time', 'radius-hotel-booking' )
					: __( 'Actual check-in time', 'radius-hotel-booking' ) }
			</Label>
			<Input
				id={ id }
				type="datetime-local"
				value={ value }
				min={ localValue( earliest ) || undefined }
				max={ now }
				step={ 60 }
				onChange={ ( event ) => onChange( event.target.value ) }
				disabled={ disabled }
				className="h-10"
			/>
			<p className="m-0 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
				<span>
					{ __(
						'When it really happened. It is recorded in the activity log.',
						'radius-hotel-booking'
					) }
				</span>
				<button
					type="button"
					className="m-0 cursor-pointer border-0 bg-transparent p-0 font-semibold text-primary underline-offset-2 hover:underline"
					onClick={ () => {
						setOpen( false );
						onChange( '' );
					} }
					disabled={ disabled }
				>
					{ __( 'Use the time now', 'radius-hotel-booking' ) }
				</button>
			</p>
		</div>
	);
}
