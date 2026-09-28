/**
 * Brand colour picker: preset swatches, a free colour picker and a hex field.
 * Every change previews live across the admin (applyPrimaryColor); the
 * Settings screen restores the saved colour if the page is left unsaved.
 */
import { useEffect, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Check } from 'lucide-react';

import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
	DEFAULT_PRIMARY,
	PRIMARY_PRESETS,
	applyPrimaryColor,
	isHexColor,
} from '@/lib/theme';
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props          Props.
 * @param {string}   props.value    Current colour.
 * @param {Function} props.onChange Called with a valid hex colour.
 * @return {JSX.Element} Field.
 */
export default function BrandColorField( { value, onChange } ) {
	const color = isHexColor( value ) ? value.toLowerCase() : DEFAULT_PRIMARY;
	const [ draft, setDraft ] = useState( color );

	useEffect( () => setDraft( color ), [ color ] );

	const pick = ( next ) => {
		applyPrimaryColor( next );
		onChange( next );
	};

	return (
		<div className="space-y-3">
			<div>
				<Label
					htmlFor="rtbp-color-hex"
					className="text-sm font-semibold text-heading"
				>
					{ __( 'Brand colour', 'radius-hotel-booking' ) }
				</Label>
				<p className="m-0 mt-0.5 text-[13px] text-muted-foreground">
					{ __(
						'Used for buttons, links, the logo and highlights across the dashboard and the booking pages.',
						'radius-hotel-booking'
					) }
				</p>
			</div>

			<div
				className="flex flex-wrap items-center gap-2"
				role="radiogroup"
				aria-label={ __( 'Suggested colours', 'radius-hotel-booking' ) }
			>
				{ PRIMARY_PRESETS.map( ( preset ) => {
					const selected = preset.value === color;
					return (
						<button
							key={ preset.value }
							type="button"
							role="radio"
							aria-checked={ selected }
							aria-label={ preset.name }
							title={ preset.name }
							onClick={ () => pick( preset.value ) }
							className={ cn(
								'flex h-9 w-9 items-center justify-center rounded-full border-2 p-0 transition-transform hover:scale-110',
								selected
									? 'border-heading'
									: 'border-transparent'
							) }
							style={ { background: preset.value } }
						>
							{ selected ? (
								<Check
									className="h-4 w-4 text-white"
									aria-hidden="true"
								/>
							) : null }
						</button>
					);
				} ) }
			</div>

			<div className="flex items-center gap-2">
				<input
					type="color"
					value={ color }
					onChange={ ( event ) => pick( event.target.value ) }
					className="h-10 w-12 cursor-pointer rounded-lg border border-border bg-card p-1"
					aria-label={ __(
						'Pick a custom colour',
						'radius-hotel-booking'
					) }
				/>
				<Input
					id="rtbp-color-hex"
					value={ draft }
					maxLength={ 7 }
					className="w-32 font-mono uppercase"
					onChange={ ( event ) => {
						const next = event.target.value.trim();
						setDraft( next );
						if ( isHexColor( next ) ) {
							pick( next.toLowerCase() );
						}
					} }
				/>
				<span
					className="inline-flex h-10 items-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground"
					aria-hidden="true"
				>
					{ __( 'Preview', 'radius-hotel-booking' ) }
				</span>
			</div>
		</div>
	);
}
