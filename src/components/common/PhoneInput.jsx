/**
 * Phone number field with a country picker (flag + calling code) and
 * validation by libphonenumber-js, the library behind
 * react-phone-number-input. Its own component rather than that package's, so
 * the list can carry the hotel's custom codes (Settings → Phone numbers).
 *
 *   <PhoneInput value={ phone } onChange={ setPhone } />
 *
 * The value is E.164 (`+2250707123456`), '' when empty. A stored number in
 * any older form (`07 07 12 34 56`) is read in the default country. Loaded
 * lazily (the library's metadata is ~40 KB): use `@/components/common/Phone`
 * or `rtbp.ui.PhoneInput` (add-ons).
 */
import { useEffect, useMemo, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { Check, ChevronDown, Globe, X } from 'lucide-react';
import {
	AsYouType,
	getCountries,
	getCountryCallingCode,
	isValidPhoneNumber,
	parsePhoneNumberFromString,
} from 'libphonenumber-js/min';

import {
	Command,
	CommandEmpty,
	CommandGroup,
	CommandInput,
	CommandItem,
	CommandList,
} from '@/components/ui/command';
import {
	Popover,
	PopoverContent,
	PopoverTrigger,
} from '@/components/ui/popover';
import {
	countryName,
	flagUrl,
	phoneConfig,
	registerPhoneValidator,
} from '@/lib/phone';
import { cn } from '@/lib/utils';

const KNOWN = new Set( getCountries() );

/**
 * Every country, by name.
 *
 * @return {Object[]} `{ key, label, dial }`.
 */
function allCountries() {
	return getCountries()
		.map( ( country ) => ( {
			key: country,
			label: countryName( country ),
			dial: getCountryCallingCode( country ),
		} ) )
		.sort( ( a, b ) => a.label.localeCompare( b.label ) );
}

/**
 * The choices of a phone field: the hotel's custom codes, then the allowed
 * countries (every country when none is set).
 *
 * @param {Object} config phoneConfig().
 * @return {Object[]} `{ key, label, dial, custom?, min?, max? }`.
 */
function phoneOptions( config ) {
	const allowed = ( config.allowedCountries || [] ).filter( ( c ) =>
		KNOWN.has( c )
	);
	const countries = allCountries().filter(
		( option ) => ! allowed.length || allowed.includes( option.key )
	);
	const custom = ( config.customCodes || [] ).map( ( code, index ) => ( {
		key: `custom:${ index }`,
		label: code.label,
		dial: String( code.code ),
		custom: true,
		min: Number( code.minDigits ),
		max: Number( code.maxDigits ),
	} ) );
	return [ ...custom, ...countries ];
}

/**
 * The custom code a number belongs to, if any.
 *
 * @param {string}   value   E.164.
 * @param {Object[]} options phoneOptions().
 * @return {Object|undefined} Option.
 */
function customMatch( value, options ) {
	const digits = String( value || '' ).replace( /\D+/g, '' );
	return options.find( ( option ) => {
		if ( ! option.custom || ! digits.startsWith( option.dial ) ) {
			return false;
		}
		const length = digits.length - option.dial.length;
		return length >= option.min && length <= option.max;
	} );
}

/**
 * Whether an E.164 value is valid under the settings ('' counts as valid:
 * "required" is the form's business).
 *
 * @param {string} value  E.164.
 * @param {Object} config phoneConfig() (default: the page's).
 * @return {boolean} Valid.
 */
export function validatePhone( value, config = phoneConfig() ) {
	if ( ! value ) {
		return true;
	}
	const options = phoneOptions( config );
	if ( customMatch( value, options ) ) {
		return true;
	}
	const phone = parsePhoneNumberFromString( String( value ) );
	if ( ! phone || ! phone.isValid() ) {
		return false;
	}
	const allowed = config.allowedCountries || [];
	return ! allowed.length || allowed.includes( phone.country );
}

registerPhoneValidator( ( value ) => validatePhone( value ) );

/**
 * A flag, or a globe for a custom code.
 *
 * @param {Object} props        Props.
 * @param {Object} props.option The choice.
 * @return {JSX.Element} Flag.
 */
function Flag( { option } ) {
	const url = option && ! option.custom ? flagUrl( option.key ) : '';
	if ( ! url ) {
		return (
			<span className="flex h-[14px] w-5 shrink-0 items-center justify-center rounded-sm bg-muted text-muted-foreground">
				<Globe className="h-3 w-3" aria-hidden="true" />
			</span>
		);
	}
	return (
		<img
			src={ url }
			alt=""
			width="20"
			height="14"
			loading="lazy"
			className="m-0 h-[14px] w-5 shrink-0 rounded-sm object-cover shadow-[0_0_0_1px_rgba(0,0,0,0.08)]"
		/>
	);
}

/**
 * The searchable list of choices, in a popover.
 *
 * @param {Object}      props          Props.
 * @param {Object[]}    props.options  Choices.
 * @param {Function}    props.isChosen `( option ) => boolean`.
 * @param {Function}    props.onPick   Called with the option.
 * @param {boolean}     props.showDial Show the calling codes.
 * @param {JSX.Element} props.children The trigger.
 * @param {boolean}     props.open     Open.
 * @param {Function}    props.setOpen  Open or close.
 * @return {JSX.Element} Picker.
 */
function OptionList( {
	options,
	isChosen,
	onPick,
	showDial = true,
	children,
	open,
	setOpen,
} ) {
	const custom = options.filter( ( option ) => option.custom );
	const countries = options.filter( ( option ) => ! option.custom );
	const item = ( option ) => (
		<CommandItem
			key={ option.key }
			value={ `${ option.label } +${ option.dial } ${ option.key }` }
			onSelect={ () => onPick( option ) }
			className="flex cursor-pointer items-center gap-2.5"
		>
			<Flag option={ option } />
			<span className="min-w-0 flex-1 truncate">{ option.label }</span>
			{ showDial ? (
				<span className="text-xs tabular-nums text-muted-foreground">
					+{ option.dial }
				</span>
			) : null }
			<Check
				className={ cn(
					'h-4 w-4 shrink-0 text-primary',
					isChosen( option ) ? 'opacity-100' : 'opacity-0'
				) }
				aria-hidden="true"
			/>
		</CommandItem>
	);

	return (
		<Popover open={ open } onOpenChange={ setOpen }>
			{ children }
			<PopoverContent
				align="start"
				className="w-72 max-w-[calc(100vw-2rem)] p-0"
			>
				<Command>
					<CommandInput
						placeholder={ __(
							'Search a country or code',
							'radius-hotel-booking'
						) }
					/>
					<CommandList className="max-h-64">
						<CommandEmpty>
							{ __(
								'No country found.',
								'radius-hotel-booking'
							) }
						</CommandEmpty>
						{ custom.length ? (
							<CommandGroup
								heading={ __(
									'Custom codes',
									'radius-hotel-booking'
								) }
							>
								{ custom.map( item ) }
							</CommandGroup>
						) : null }
						<CommandGroup
							heading={
								custom.length
									? __( 'Countries', 'radius-hotel-booking' )
									: undefined
							}
						>
							{ countries.map( item ) }
						</CommandGroup>
					</CommandList>
				</Command>
			</PopoverContent>
		</Popover>
	);
}

/**
 * The trigger look shared by the country pickers.
 *
 * @param {Object} props Button props.
 * @return {JSX.Element} Button.
 */
function PickerButton( { className, children, ...props } ) {
	return (
		<PopoverTrigger asChild>
			<button
				type="button"
				className={ cn(
					'flex min-h-[45px] w-full items-center gap-2 rounded-md border border-border bg-white px-3 py-1 text-left text-base font-normal normal-case tracking-normal text-foreground shadow-none transition-colors hover:bg-white hover:text-foreground focus:border-primary focus:bg-white focus:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring md:text-sm',
					className
				) }
				{ ...props }
			>
				{ children }
			</button>
		</PopoverTrigger>
	);
}

/**
 * Read a stored value into a choice and the typed national part.
 *
 * @param {string}   value    Stored value.
 * @param {Object[]} options  Choices.
 * @param {string}   fallback Default choice key.
 * @return {Object} `{ key, national }`.
 */
function read( value, options, fallback ) {
	const text = String( value || '' ).trim();
	if ( ! text ) {
		return { key: fallback, national: '' };
	}
	const has = ( key ) => options.some( ( option ) => option.key === key );
	if ( text.startsWith( '+' ) ) {
		const custom = customMatch( text, options );
		if ( custom && ! isValidPhoneNumber( text ) ) {
			return {
				key: custom.key,
				national: text
					.replace( /\D+/g, '' )
					.slice( custom.dial.length ),
			};
		}
		const phone = parsePhoneNumberFromString( text );
		if ( phone?.country && has( phone.country ) ) {
			return {
				key: phone.country,
				national: new AsYouType( phone.country ).input(
					phone.nationalNumber
				),
			};
		}
		if ( custom ) {
			return {
				key: custom.key,
				national: text
					.replace( /\D+/g, '' )
					.slice( custom.dial.length ),
			};
		}
		return { key: fallback, national: text };
	}
	// An older number typed without a code: read in the default country.
	const country = KNOWN.has( fallback ) ? fallback : undefined;
	const phone = country
		? parsePhoneNumberFromString( text, country )
		: undefined;
	return {
		key: fallback,
		national: phone
			? new AsYouType( country ).input( phone.nationalNumber )
			: text,
	};
}

/**
 * Turn a choice and the typed part into the stored E.164 value.
 *
 * @param {Object} option   Choice.
 * @param {string} national As typed.
 * @return {string} E.164, or ''.
 */
function write( option, national ) {
	const digits = String( national || '' ).replace( /\D+/g, '' );
	if ( ! digits || ! option ) {
		return '';
	}
	if ( ! option.custom ) {
		const phone = parsePhoneNumberFromString( national, option.key );
		if ( phone ) {
			return phone.number;
		}
	}
	return `+${ option.dial }${ digits }`;
}

/**
 * @param {Object}   props             Props.
 * @param {string}   props.value       E.164 ('' when empty).
 * @param {Function} props.onChange    Called with the new E.164 value.
 * @param {string}   props.id          Input id (for the label).
 * @param {string}   props.placeholder Placeholder.
 * @param {boolean}  props.disabled    Disabled.
 * @param {string}   props.className   Extra wrapper classes.
 * @param {Object}   props.config      Settings to use instead of the saved ones.
 * @return {JSX.Element} Field.
 */
export function PhoneInput( {
	value = '',
	onChange,
	id,
	placeholder,
	disabled = false,
	className,
	config: configProp,
	onBlur,
	'aria-invalid': ariaInvalid,
	...props
} ) {
	// `config`: unsaved settings (the Settings preview); else the page's.
	const config = configProp || phoneConfig();
	const options = useMemo(
		() => phoneOptions( config ),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[ JSON.stringify( config ) ]
	);
	const fallback = options.some( ( o ) => o.key === config.defaultCountry )
		? config.defaultCountry
		: options[ 0 ]?.key;

	const [ state, setState ] = useState( () =>
		read( value, options, fallback )
	);
	const [ open, setOpen ] = useState( false );
	const [ touched, setTouched ] = useState( false );
	const emitted = useRef( value );
	const inputRef = useRef( null );

	// A new value from outside (a reset, another guest) is read again.
	useEffect( () => {
		if ( value !== emitted.current ) {
			emitted.current = value;
			setState( read( value, options, fallback ) );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ value ] );

	const option = options.find( ( o ) => o.key === state.key ) || options[ 0 ];

	const emit = ( key, national ) => {
		const next = write(
			options.find( ( o ) => o.key === key ),
			national
		);
		setState( { key, national } );
		emitted.current = next;
		onChange?.( next );
	};

	const type = ( text ) => {
		// A full international number pasted in: switch to its country.
		if ( text.trim().startsWith( '+' ) ) {
			const found = read( text, options, state.key );
			if ( found.key !== state.key || found.national !== text ) {
				emit( found.key, found.national );
				return;
			}
		}
		emit( state.key, text );
	};

	const invalid =
		ariaInvalid === true ||
		ariaInvalid === 'true' ||
		( touched && value && ! validatePhone( value, config ) );

	return (
		<div className={ cn( 'w-full space-y-1', className ) }>
			<div
				className={ cn(
					'flex min-h-[45px] w-full items-stretch overflow-hidden rounded-md border bg-white transition-colors focus-within:border-primary focus-within:ring-2 focus-within:ring-ring',
					invalid ? 'border-destructive' : 'border-border',
					disabled && 'cursor-not-allowed opacity-50'
				) }
			>
				<OptionList
					options={ options }
					isChosen={ ( o ) => o.key === option?.key }
					onPick={ ( picked ) => {
						setOpen( false );
						emit( picked.key, state.national );
						window.requestAnimationFrame( () =>
							inputRef.current?.focus()
						);
					} }
					open={ open }
					setOpen={ setOpen }
				>
					<PopoverTrigger asChild>
						<button
							type="button"
							disabled={ disabled }
							className="flex shrink-0 items-center gap-1.5 rounded-none border-0 border-r border-border bg-muted/40 px-2.5 text-sm font-normal normal-case tracking-normal text-foreground shadow-none hover:bg-muted hover:text-foreground focus:bg-muted focus:text-foreground focus-visible:outline-none"
							aria-label={ sprintf(
								/* translators: 1: country or code name, 2: calling code. */
								__(
									'Country: %1$s (+%2$s). Change',
									'radius-hotel-booking'
								),
								option?.label || '',
								option?.dial || ''
							) }
						>
							<Flag option={ option } />
							<span className="tabular-nums">
								+{ option?.dial }
							</span>
							<ChevronDown
								className="h-3.5 w-3.5 text-muted-foreground"
								aria-hidden="true"
							/>
						</button>
					</PopoverTrigger>
				</OptionList>
				<input
					ref={ inputRef }
					id={ id }
					type="tel"
					inputMode="tel"
					autoComplete="tel-national"
					disabled={ disabled }
					value={ state.national }
					placeholder={ placeholder }
					aria-invalid={ invalid || undefined }
					onChange={ ( event ) => type( event.target.value ) }
					onBlur={ ( event ) => {
						setTouched( true );
						onBlur?.( event );
						// Tidy the spacing once the guest is done typing.
						if ( option && ! option.custom && state.national ) {
							const formatted = new AsYouType( option.key ).input(
								state.national
							);
							if ( formatted !== state.national ) {
								setState( ( prev ) => ( {
									...prev,
									national: formatted,
								} ) );
							}
						}
					} }
					className="min-w-0 flex-1 border-0 bg-transparent px-3 py-1 text-base text-foreground shadow-none outline-none placeholder:text-muted-foreground focus:border-0 focus:shadow-none focus:outline-none focus:ring-0 md:text-sm"
					{ ...props }
				/>
			</div>
			{ ! ariaInvalid &&
			touched &&
			value &&
			! validatePhone( value, config ) ? (
				<p className="m-0 text-xs text-destructive" role="alert">
					{ option?.custom
						? sprintf(
								/* translators: 1: fewest digits, 2: most digits. */
								__(
									'Enter %1$d to %2$d digits after the code.',
									'radius-hotel-booking'
								),
								option.min,
								option.max
						  )
						: sprintf(
								/* translators: %s: country name. */
								__(
									'This is not a valid number for %s.',
									'radius-hotel-booking'
								),
								option?.label || ''
						  ) }
				</p>
			) : null }
		</div>
	);
}

/**
 * One country (Settings → Phone numbers: the default country).
 *
 * @param {Object}   props           Props.
 * @param {string}   props.value     ISO code.
 * @param {Function} props.onChange  Called with `( iso, dialCode )`.
 * @param {string[]} props.countries Limit the list to these (empty: all).
 * @return {JSX.Element} Picker.
 */
export function CountryPicker( { value, onChange, countries = [] } ) {
	const [ open, setOpen ] = useState( false );
	const options = allCountries().filter(
		( o ) => ! countries.length || countries.includes( o.key )
	);
	const current = options.find( ( o ) => o.key === value ) ||
		allCountries().find( ( o ) => o.key === value ) || {
			key: value,
			label: value,
			dial: '',
		};

	return (
		<OptionList
			options={ options }
			isChosen={ ( o ) => o.key === value }
			onPick={ ( picked ) => {
				setOpen( false );
				onChange( picked.key, picked.dial );
			} }
			open={ open }
			setOpen={ setOpen }
		>
			<PickerButton>
				<Flag option={ current } />
				<span className="min-w-0 flex-1 truncate">
					{ current.label }
				</span>
				{ current.dial ? (
					<span className="text-xs tabular-nums text-muted-foreground">
						+{ current.dial }
					</span>
				) : null }
				<ChevronDown
					className="h-4 w-4 text-muted-foreground"
					aria-hidden="true"
				/>
			</PickerButton>
		</OptionList>
	);
}

/**
 * Several countries (Settings → Phone numbers: the allowed countries).
 *
 * @param {Object}   props          Props.
 * @param {string[]} props.value    ISO codes (empty: every country).
 * @param {Function} props.onChange Called with the new list.
 * @return {JSX.Element} Picker.
 */
export function CountryMultiPicker( { value = [], onChange } ) {
	const [ open, setOpen ] = useState( false );
	const options = allCountries();
	const toggle = ( key ) =>
		onChange(
			value.includes( key )
				? value.filter( ( k ) => k !== key )
				: [ ...value, key ]
		);

	return (
		<div className="space-y-2">
			<OptionList
				options={ options }
				isChosen={ ( o ) => value.includes( o.key ) }
				onPick={ ( picked ) => toggle( picked.key ) }
				open={ open }
				setOpen={ setOpen }
			>
				<PickerButton>
					<Globe
						className="h-4 w-4 text-muted-foreground"
						aria-hidden="true"
					/>
					<span className="min-w-0 flex-1 truncate">
						{ value.length
							? sprintf(
									/* translators: %d: number of countries. */
									__(
										'%d countries selected',
										'radius-hotel-booking'
									),
									value.length
							  )
							: __( 'Every country', 'radius-hotel-booking' ) }
					</span>
					<ChevronDown
						className="h-4 w-4 text-muted-foreground"
						aria-hidden="true"
					/>
				</PickerButton>
			</OptionList>
			{ value.length ? (
				<ul className="m-0 flex list-none flex-wrap gap-1.5 p-0">
					{ value.map( ( key ) => (
						<li
							key={ key }
							className="inline-flex items-center gap-1.5 rounded-full border border-border bg-muted/50 py-0.5 pl-2 pr-1 text-xs"
						>
							<Flag option={ { key } } />
							{ countryName( key ) }
							<button
								type="button"
								onClick={ () => toggle( key ) }
								className="flex h-5 w-5 items-center justify-center rounded-full border-0 bg-transparent p-0 text-muted-foreground hover:bg-primary-softer hover:text-heading focus:bg-primary-softer focus:text-heading"
								aria-label={ sprintf(
									/* translators: %s: country name. */
									__( 'Remove %s', 'radius-hotel-booking' ),
									countryName( key )
								) }
							>
								<X className="h-3 w-3" aria-hidden="true" />
							</button>
						</li>
					) ) }
					<li>
						<button
							type="button"
							onClick={ () => onChange( [] ) }
							className="rounded-full border-0 bg-transparent px-2 py-0.5 text-xs font-semibold normal-case text-primary hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary"
						>
							{ __(
								'Allow every country',
								'radius-hotel-booking'
							) }
						</button>
					</li>
				</ul>
			) : null }
		</div>
	);
}

export default PhoneInput;
