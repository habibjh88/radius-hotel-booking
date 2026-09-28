/**
 * Segmented control: a small pill of mutually exclusive options
 * (Arrivals / Departures, Week / Month / Year).
 */
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props          Props.
 * @param {Array<{value: string, label: string, count?: number}>} props.options Options.
 * @param {string}   props.value    Selected value.
 * @param {Function} props.onChange Called with the new value.
 * @param {string}   props.label    Accessible group name.
 * @return {JSX.Element} Control.
 */
export default function SegmentedControl( { options, value, onChange, label } ) {
	return (
		<div
			role="radiogroup"
			aria-label={ label }
			className="inline-flex items-center gap-0.5 rounded-lg bg-muted p-1"
		>
			{ options.map( ( option ) => {
				const selected = option.value === value;
				return (
					<button
						key={ option.value }
						type="button"
						role="radio"
						aria-checked={ selected }
						onClick={ () => onChange( option.value ) }
						className={ cn(
							'inline-flex h-8 items-center gap-1.5 rounded-md border-0 px-3 text-[13px] transition-colors',
							selected
								? 'bg-card font-semibold text-heading shadow-sm'
								: 'bg-transparent font-medium text-muted-foreground hover:text-heading'
						) }
					>
						{ option.label }
						{ typeof option.count === 'number' ? (
							<span
								className={ cn(
									'rounded-full px-1.5 text-[11px] font-semibold tabular-nums',
									selected
										? 'bg-primary-soft text-primary'
										: 'bg-card text-muted-foreground'
								) }
							>
								{ option.count }
							</span>
						) : null }
					</button>
				);
			} ) }
		</div>
	);
}
