/**
 * Shortcodes (System): every shortcode the plugin and its add-ons register,
 * with what it shows and a copy button. A shortcode with `attributes` gets a
 * builder: a select or number field per attribute, starting at its default,
 * and the shortcode in the box follows what is picked.
 *
 * The list comes from PHP (`Shortcodes::catalog()`, the
 * `rtbp_shortcode_catalog` filter) through `radius_hotel_booking_param.shortcodes`,
 * so a new shortcode appears here once it describes itself on that filter.
 */
import { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import {
	Check,
	Code,
	Copy,
	Layers,
	RotateCcw,
	SlidersHorizontal,
} from 'lucide-react';

import EmptyState from '@/components/common/EmptyState';
import { Field } from '@/components/common/Form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { toast } from '@/lib/toast';
import { cn } from '@/lib/utils';

/**
 * Copy text to the clipboard. `navigator.clipboard` only exists on HTTPS
 * (and localhost); plain-HTTP sites fall back to a hidden textarea.
 *
 * @param {string} text Text.
 * @return {Promise<boolean>} Whether it was copied.
 */
async function copyText( text ) {
	try {
		if ( window.isSecureContext && navigator.clipboard?.writeText ) {
			await navigator.clipboard.writeText( text );
			return true;
		}
	} catch ( e ) {
		// Fall through to the textarea.
	}
	const area = document.createElement( 'textarea' );
	area.value = text;
	area.setAttribute( 'readonly', '' );
	area.style.position = 'fixed';
	area.style.opacity = '0';
	document.body.appendChild( area );
	area.select();
	let done = false;
	try {
		done = document.execCommand( 'copy' );
	} catch ( e ) {
		done = false;
	}
	area.remove();
	return done;
}

/**
 * The shortcode in a code box, with its copy button on the right.
 *
 * @param {Object} props      Props.
 * @param {string} props.code Shortcode, brackets included.
 * @return {JSX.Element} Box.
 */
function CopyBox( { code } ) {
	const [ copied, setCopied ] = useState( false );
	const timer = useRef( 0 );
	useEffect( () => () => window.clearTimeout( timer.current ), [] );

	const copy = async () => {
		if ( ! ( await copyText( code ) ) ) {
			toast.error(
				__(
					'Could not copy. Select the shortcode and copy it by hand.',
					'radius-hotel-booking'
				)
			);
			return;
		}
		setCopied( true );
		window.clearTimeout( timer.current );
		timer.current = window.setTimeout( () => setCopied( false ), 2000 );
	};

	return (
		<div className="flex items-stretch overflow-hidden rounded-lg border border-border bg-muted/40">
			<code className="flex min-w-0 flex-1 select-all items-center bg-transparent px-3 py-2 font-mono leading-relaxed [overflow-wrap:anywhere] text-sm font-semibold text-heading">
				{ code }
			</code>
			<Button
				type="button"
				variant="ghost"
				onClick={ copy }
				className="h-auto shrink-0 rounded-none border-l border-border px-3"
				aria-label={ sprintf(
					/* translators: %s: shortcode. */
					__( 'Copy %s', 'radius-hotel-booking' ),
					code
				) }
			>
				{ copied ? (
					<Check className="text-success" aria-hidden="true" />
				) : (
					<Copy aria-hidden="true" />
				) }
				<span
					className={ copied ? 'text-success' : undefined }
					aria-live="polite"
				>
					{ copied
						? __( 'Copied', 'radius-hotel-booking' )
						: __( 'Copy', 'radius-hotel-booking' ) }
				</span>
			</Button>
		</div>
	);
}

/**
 * Whether an attribute applies with the current values (`when`).
 *
 * @param {Object} attribute Attribute.
 * @param {Object} values    Current values.
 * @return {boolean} Shown.
 */
const applies = ( attribute, values ) =>
	Object.entries( attribute.when || {} ).every(
		( [ name, value ] ) => values[ name ] === value
	);

/**
 * The shortcode for the picked values: every attribute that applies, in the
 * catalogue order, defaults included; empty values are left out.
 *
 * @param {string}   tag        Tag.
 * @param {Object[]} attributes Attributes.
 * @param {Object}   values     Values.
 * @return {string} Shortcode.
 */
const build = ( tag, attributes, values ) => {
	const parts = attributes
		.filter( ( attribute ) => applies( attribute, values ) )
		.map( ( attribute ) => [ attribute.name, values[ attribute.name ] ] )
		.filter( ( [ , value ] ) => '' !== value && undefined !== value )
		.map( ( [ name, value ] ) => `${ name }="${ value }"` );
	return `[${ [ tag, ...parts ].join( ' ' ) }]`;
};

/**
 * One attribute's field.
 *
 * @param {Object}   props           Props.
 * @param {Object}   props.attribute Attribute.
 * @param {string}   props.value     Value.
 * @param {Function} props.onChange  Called with the new value.
 * @return {JSX.Element} Field.
 */
function AttributeField( { attribute, value, onChange } ) {
	const { type, label, options = [], help } = attribute;

	if ( 'multiselect' === type ) {
		const picked = value ? value.split( ',' ) : [];
		const toggle = ( option ) =>
			onChange(
				( picked.includes( option )
					? picked.filter( ( v ) => v !== option )
					: // Keep the catalogue order.
					  options
							.map( ( o ) => o.value )
							.filter(
								( v ) => v === option || picked.includes( v )
							)
				).join( ',' )
			);
		return (
			<fieldset className="m-0 min-w-0 space-y-1.5 border-0 p-0 sm:col-span-2">
				<legend className="p-0 text-sm font-medium text-heading">
					{ label }
				</legend>
				<div className="flex flex-wrap gap-2">
					{ options.map( ( option ) => {
						const on = picked.includes( option.value );
						return (
							<button
								key={ option.value }
								type="button"
								aria-pressed={ on }
								onClick={ () => toggle( option.value ) }
								className={ cn(
									'inline-flex h-9 items-center gap-1.5 rounded-full border px-3 text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
									on
										? 'border-primary bg-primary-soft text-primary'
										: 'border-border bg-card text-heading hover:border-primary hover:bg-primary-softer'
								) }
							>
								{ on ? (
									<Check
										className="h-3.5 w-3.5"
										aria-hidden="true"
									/>
								) : null }
								{ option.label }
							</button>
						);
					} ) }
					{ ! options.length ? (
						<span className="text-sm text-muted-foreground">
							{ __(
								'Nothing to choose yet.',
								'radius-hotel-booking'
							) }
						</span>
					) : null }
				</div>
				{ help ? (
					<p className="m-0 text-xs text-muted-foreground">
						{ help }
					</p>
				) : null }
			</fieldset>
		);
	}

	return (
		<Field label={ label } description={ help || undefined }>
			{ 'select' === type ? (
				<Select value={ value } onValueChange={ onChange }>
					<SelectTrigger className="w-full" aria-label={ label }>
						<SelectValue />
					</SelectTrigger>
					<SelectContent className="rtbp-root">
						{ options.map( ( option ) => (
							<SelectItem
								key={ option.value }
								value={ option.value }
							>
								{ option.label }
							</SelectItem>
						) ) }
					</SelectContent>
				</Select>
			) : (
				<Input
					type={ 'number' === type ? 'number' : 'text' }
					inputMode={ 'number' === type ? 'numeric' : undefined }
					min={ attribute.min ?? undefined }
					max={ attribute.max ?? undefined }
					value={ value }
					onChange={ ( event ) => onChange( event.target.value ) }
					onBlur={ () => {
						if ( 'number' !== type ) {
							return;
						}
						// Back inside the limits (empty = the default).
						let next = parseInt( value, 10 );
						if ( Number.isNaN( next ) ) {
							next = parseInt( attribute.default, 10 ) || 0;
						}
						if (
							null !== attribute.min &&
							undefined !== attribute.min
						) {
							next = Math.max( attribute.min, next );
						}
						if (
							null !== attribute.max &&
							undefined !== attribute.max
						) {
							next = Math.min( attribute.max, next );
						}
						onChange( String( next ) );
					} }
				/>
			) }
		</Field>
	);
}

/**
 * Where a builder's last choices are kept (this browser only).
 *
 * @param {string} tag Shortcode tag.
 * @return {string} Storage key.
 */
const storageKey = ( tag ) => `rtbp_shortcode_builder_${ tag }`;

/**
 * The builder's last choices for a shortcode, over its defaults. A choice
 * the catalogue no longer offers (an option or attribute removed since, a
 * room type deleted) falls back to the default.
 *
 * @param {string}   tag        Shortcode tag.
 * @param {Object[]} attributes Catalogue attributes.
 * @param {Object}   defaults   Name => default.
 * @return {Object} Name => value.
 */
function savedValues( tag, attributes, defaults ) {
	let saved = {};
	try {
		saved =
			JSON.parse( window.localStorage.getItem( storageKey( tag ) ) ) ||
			{};
	} catch ( e ) {
		return defaults;
	}
	const values = { ...defaults };
	attributes.forEach( ( attribute ) => {
		const value = saved[ attribute.name ];
		if ( 'string' !== typeof value ) {
			return;
		}
		const allowed = ( attribute.options || [] ).map( ( option ) =>
			String( option.value )
		);
		if ( 'select' === attribute.type ) {
			if ( allowed.includes( value ) ) {
				values[ attribute.name ] = value;
			}
		} else if ( 'multiselect' === attribute.type ) {
			values[ attribute.name ] = value
				.split( ',' )
				.filter( ( id ) => allowed.includes( id ) )
				.join( ',' );
		} else {
			values[ attribute.name ] = value;
		}
	} );
	return values;
}

/**
 * Keep (or, at the defaults, forget) a builder's choices.
 *
 * @param {string}  tag     Shortcode tag.
 * @param {Object}  values  Name => value.
 * @param {boolean} changed Anything differs from the defaults.
 * @return {void}
 */
function saveValues( tag, values, changed ) {
	try {
		if ( changed ) {
			window.localStorage.setItem(
				storageKey( tag ),
				JSON.stringify( values )
			);
		} else {
			window.localStorage.removeItem( storageKey( tag ) );
		}
	} catch ( e ) {
		// Not kept; the builder still works for this visit.
	}
}

/**
 * One shortcode's card; with attributes, its builder. The last choices are
 * kept in this browser, so the shortcode built last time is still there;
 * *Reset to defaults* clears them.
 *
 * @param {Object} props      Props.
 * @param {Object} props.item Catalogue entry.
 * @return {JSX.Element} Card.
 */
function ShortcodeCard( { item } ) {
	const attributes = item.attributes || [];
	const defaults = Object.fromEntries(
		attributes.map( ( attribute ) => [ attribute.name, attribute.default ] )
	);
	const [ values, setValues ] = useState( () =>
		savedValues( item.tag, attributes, defaults )
	);
	const changed = attributes.some(
		( attribute ) => values[ attribute.name ] !== attribute.default
	);
	useEffect( () => {
		if ( attributes.length ) {
			saveValues( item.tag, values, changed );
		}
	}, [ item.tag, values, changed, attributes.length ] );
	const shown = attributes.filter( ( attribute ) =>
		applies( attribute, values )
	);

	return (
		<section className="flex min-w-0 flex-col gap-4 rounded-xl border border-border bg-card p-5 shadow-sm">
			<div className="flex items-start gap-3">
				<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary">
					<Code className="h-4 w-4" aria-hidden="true" />
				</span>
				<div className="min-w-0">
					<h2 className="m-0 p-0 text-base font-semibold leading-6 text-heading">
						{ item.title }
					</h2>
					{ item.description ? (
						<p className="m-0 mt-1 text-sm text-muted-foreground">
							{ item.description }
						</p>
					) : null }
				</div>
			</div>

			{ attributes.length ? (
				<div className="rounded-lg border border-border bg-muted/30 p-4">
					<div className="mb-3 flex flex-wrap items-center justify-between gap-2">
						<span className="inline-flex items-center gap-2 text-sm font-semibold text-heading">
							<SlidersHorizontal
								className="h-4 w-4 text-muted-foreground"
								aria-hidden="true"
							/>
							{ __(
								'Build your shortcode',
								'radius-hotel-booking'
							) }
						</span>
						{ changed ? (
							<Button
								type="button"
								variant="ghost"
								size="sm"
								onClick={ () => setValues( defaults ) }
							>
								<RotateCcw aria-hidden="true" />
								{ __(
									'Reset to defaults',
									'radius-hotel-booking'
								) }
							</Button>
						) : null }
					</div>
					<div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
						{ shown.map( ( attribute ) => (
							<AttributeField
								key={ attribute.name }
								attribute={ attribute }
								value={ values[ attribute.name ] ?? '' }
								onChange={ ( value ) =>
									setValues( ( prev ) => ( {
										...prev,
										[ attribute.name ]: value,
									} ) )
								}
							/>
						) ) }
					</div>
				</div>
			) : null }

			<CopyBox code={ build( item.tag, attributes, values ) } />

			{ item.also?.length ? (
				<div className="flex flex-wrap items-center gap-1.5">
					<span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
						<Layers className="h-3.5 w-3.5" aria-hidden="true" />
						{ __( 'Also as', 'radius-hotel-booking' ) }
					</span>
					{ item.also.map( ( way ) => (
						<span
							key={ way }
							className="rounded-full border border-border bg-muted/40 px-2 py-0.5 text-[11px] font-medium text-heading"
						>
							{ way }
						</span>
					) ) }
				</div>
			) : null }
		</section>
	);
}

/**
 * @return {JSX.Element} Screen.
 */
export default function Shortcodes() {
	const list = window.radius_hotel_booking_param?.shortcodes || [];

	if ( ! list.length ) {
		return (
			<EmptyState
				icon={ Code }
				title={ __( 'No shortcodes yet', 'radius-hotel-booking' ) }
				description={ __(
					'Shortcodes the plugin and its add-ons add will be listed here.',
					'radius-hotel-booking'
				) }
			/>
		);
	}

	return (
		<div className="space-y-4">
			<p className="m-0 max-w-3xl text-sm text-muted-foreground">
				{ __(
					'Paste a shortcode into a Shortcode block, the classic editor or an Elementor Shortcode widget. Each one shows the same thing wherever it is placed.',
					'radius-hotel-booking'
				) }
			</p>
			<div className="grid gap-4">
				{ list.map( ( item ) => (
					<ShortcodeCard key={ item.tag } item={ item } />
				) ) }
			</div>
		</div>
	);
}
