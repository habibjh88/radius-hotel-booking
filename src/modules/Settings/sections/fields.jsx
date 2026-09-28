/**
 * Small controls the settings tabs share: a number with a unit, and an on/off
 * row. Both work inside or beside `Field` (which passes `id`, `aria-*` and
 * the error class to its child).
 */
import { useId } from 'react';

import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { cn } from '@/lib/utils';

/**
 * `''` while the box is empty, otherwise a number (or the raw text, which the
 * server refuses with a field error).
 *
 * @param {string} raw Input value.
 * @return {number|string} Value to store.
 */
const toNumber = ( raw ) =>
	raw === '' || Number.isNaN( Number( raw ) ) ? raw : Number( raw );

/**
 * A whole number with its unit after it ("15 minutes").
 *
 * @param {Object}   props                  Props.
 * @param {number}   props.value            Value.
 * @param {Function} props.onChange         Called with the new value.
 * @param {string}   props.unit             Unit shown after the box.
 * @param {Object}   props.limits           The key's schema (`min`, `max`).
 * @param {string}   props.id               Set by Field.
 * @param {string}   props.className        Set by Field (error border).
 * @param {boolean}  props.aria-invalid     Set by Field.
 * @param {string}   props.aria-describedby Set by Field.
 * @return {JSX.Element} Control.
 */
export function NumberInput( {
	value,
	onChange,
	unit,
	limits = {},
	id,
	className,
	'aria-invalid': ariaInvalid,
	'aria-describedby': ariaDescribedBy,
} ) {
	return (
		<div className="flex items-center gap-2">
			<Input
				id={ id }
				type="number"
				inputMode="numeric"
				step="1"
				min={ limits.min }
				max={ limits.max }
				value={ value ?? '' }
				onChange={ ( event ) =>
					onChange( toNumber( event.target.value ) )
				}
				aria-invalid={ ariaInvalid }
				aria-describedby={ ariaDescribedBy }
				className={ cn( 'w-28', className ) }
			/>
			{ unit ? (
				<span className="text-sm text-muted-foreground">{ unit }</span>
			) : null }
		</div>
	);
}

/**
 * An on/off setting: label and one line of help on the left, the switch on
 * the right.
 *
 * @param {Object}      props             Props.
 * @param {string}      props.label       Label.
 * @param {string}      props.description Help text.
 * @param {boolean}     props.checked     Value.
 * @param {Function}    props.onChange    Called with the new value.
 * @param {string}      props.error       Server message.
 * @param {boolean}     props.disabled    Greyed out and not switchable.
 * @param {JSX.Element} props.children    Controls shown under the row while on.
 * @return {JSX.Element} Row.
 */
export function ToggleRow( {
	label,
	description,
	checked,
	onChange,
	error,
	disabled = false,
	children,
} ) {
	const id = useId();
	return (
		<div className={ cn( 'space-y-4', disabled && 'opacity-60' ) }>
			<div className="flex items-start justify-between gap-4">
				<div className="min-w-0">
					<label
						htmlFor={ id }
						className="m-0 block text-sm font-semibold text-heading"
					>
						{ label }
					</label>
					{ description ? (
						<p className="m-0 mt-0.5 text-[13px] leading-5 text-muted-foreground">
							{ description }
						</p>
					) : null }
					{ error ? (
						<p
							role="alert"
							className="m-0 mt-1 text-xs font-medium text-destructive"
						>
							{ error }
						</p>
					) : null }
				</div>
				<Switch
					id={ id }
					className="mt-0.5 shrink-0"
					checked={ !! checked }
					onCheckedChange={ onChange }
					disabled={ disabled }
				/>
			</div>
			{ checked && children ? (
				<div className="border-l-2 border-border pl-4">
					{ children }
				</div>
			) : null }
		</div>
	);
}
