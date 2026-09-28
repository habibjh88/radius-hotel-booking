/**
 * An amount in the site currency: `<Money value={ 25000 } />` → "25 000 CFA".
 * Formatting comes from Settings (src/lib/format.js); never format by hand.
 * Numbers use tabular figures so columns of amounts line up.
 */
import { formatMoney } from '@/lib/format';
import { cn } from '@/lib/utils';

/**
 * @param {Object}  props           Props.
 * @param {number|string} props.value Amount.
 * @param {boolean} props.signed    Colour negatives red and prefix positives "+" (ledgers).
 * @param {boolean} props.symbol    Show the currency symbol (default true).
 * @param {string}  props.className Extra classes.
 * @return {JSX.Element} Amount.
 */
export default function Money( {
	value,
	signed = false,
	symbol = true,
	className,
} ) {
	const amount = Number( value ) || 0;
	const text = formatMoney( amount, { symbol } );

	return (
		<span
			className={ cn(
				'whitespace-nowrap tabular-nums',
				signed && amount < 0 && 'text-destructive',
				signed && amount > 0 && 'text-success',
				className
			) }
		>
			{ signed && amount > 0 ? `+${ text }` : text }
		</span>
	);
}
