/**
 * A list of short text tags ("Wi-Fi", "Air conditioning"). Type and press
 * Enter or a comma to add; Backspace in an empty box removes the last tag.
 * Duplicates (ignoring case) and empty tags are skipped.
 *
 *   <Field label={ __( 'Amenities', … ) } name="amenities" form={ form }>
 *       <TagInput value={ tags } onChange={ setTags } max={ 40 } />
 *   </Field>
 *
 * Also on `window.rtbp.ui`.
 */
import { useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { X } from 'lucide-react';

import { cn } from '@/lib/utils';

/**
 * @param {Object}   props             Props.
 * @param {string[]} props.value       Tags.
 * @param {Function} props.onChange    Called with the new tags.
 * @param {number}   props.max         Most tags allowed.
 * @param {number}   props.maxLength   Longest tag.
 * @param {string}   props.placeholder Placeholder of the box.
 * @param {boolean}  props.disabled    Read-only.
 * @param {string}   props.id          Id of the text box (set by Field).
 * @param {string}   props.className   Extra classes (Field adds the error border).
 * @return {JSX.Element} Control.
 */
export default function TagInput( {
	value = [],
	onChange,
	max = 40,
	maxLength = 60,
	placeholder,
	disabled = false,
	id,
	className,
	'aria-invalid': ariaInvalid,
	'aria-describedby': ariaDescribedBy,
} ) {
	const tags = value || [];
	const [ draft, setDraft ] = useState( '' );

	const commit = ( text ) => {
		const parts = String( text )
			.split( ',' )
			.map( ( part ) => part.trim().slice( 0, maxLength ) )
			.filter( Boolean );
		const next = [ ...tags ];
		parts.forEach( ( part ) => {
			const taken = next.some(
				( tag ) => tag.toLowerCase() === part.toLowerCase()
			);
			if ( ! taken && next.length < max ) {
				next.push( part );
			}
		} );
		if ( next.length !== tags.length ) {
			onChange( next );
		}
		setDraft( '' );
	};

	const onKeyDown = ( event ) => {
		if ( event.key === 'Enter' || event.key === ',' ) {
			event.preventDefault();
			commit( draft );
		} else if ( event.key === 'Backspace' && draft === '' && tags.length ) {
			onChange( tags.slice( 0, -1 ) );
		}
	};

	return (
		<div
			className={ cn(
				'flex min-h-10 w-full flex-wrap items-center gap-1.5 rounded-md border border-input bg-background px-2 py-1.5 text-sm focus-within:ring-2 focus-within:ring-ring',
				disabled && 'opacity-60',
				className
			) }
		>
			{ tags.map( ( tag ) => (
				<span
					key={ tag }
					className="inline-flex max-w-full items-center gap-1 rounded-md bg-muted px-2 py-0.5 text-[13px] text-heading"
				>
					<span className="truncate">{ tag }</span>
					{ ! disabled ? (
						<button
							type="button"
							onClick={ () =>
								onChange(
									tags.filter( ( other ) => other !== tag )
								)
							}
							aria-label={ sprintf(
								/* translators: %s: tag text. */
								__( 'Remove %s', 'radius-hotel-booking' ),
								tag
							) }
							className="flex h-4 w-4 items-center justify-center rounded text-muted-foreground hover:text-heading"
						>
							<X className="h-3 w-3" aria-hidden="true" />
						</button>
					) : null }
				</span>
			) ) }
			{ ! disabled && tags.length < max ? (
				<input
					id={ id }
					type="text"
					value={ draft }
					maxLength={ maxLength }
					onChange={ ( event ) => setDraft( event.target.value ) }
					onKeyDown={ onKeyDown }
					onBlur={ () => draft && commit( draft ) }
					placeholder={
						tags.length
							? ''
							: placeholder ||
							  __(
									'Type and press Enter',
									'radius-hotel-booking'
							  )
					}
					aria-invalid={ ariaInvalid }
					aria-describedby={ ariaDescribedBy }
					className="min-w-[8rem] flex-1 border-0 bg-transparent p-1 shadow-none outline-none focus:shadow-none focus:outline-none"
				/>
			) : null }
		</div>
	);
}
