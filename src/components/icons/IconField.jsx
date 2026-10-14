/**
 * An icon setting: shows the chosen Lucide icon and opens the icon library
 * (IconPicker, loaded on first use). The value is the icon's name ('' = none).
 *
 *   <IconField value={ icon } onChange={ setIcon } />
 *   <IconField value={ icon } onChange={ save } compact label={ name } />
 *   <IconField value={ icon } onChange={ setIcon } addon />  (inside an input group's right edge)
 */
import { Suspense, lazy, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronDown, ImagePlus } from 'lucide-react';

import { cn } from '@/lib/utils';
import LucideIcon from './LucideIcon';

const IconPicker = lazy( () => import( './IconPicker' ) );

/**
 * @param {Object}   props           Props.
 * @param {string}   props.value     Icon name ('' = none).
 * @param {Function} props.onChange  Called with the new name ('' when removed).
 * @param {boolean}  props.compact   A square button only (lists, rows).
 * @param {boolean}  props.addon     A segment for the right edge of an input group.
 * @param {string}   props.label     What the icon is for (e.g. the amenity's name).
 * @param {boolean}  props.disabled  Disabled.
 * @param {string}   props.className Extra classes.
 * @return {JSX.Element} Field.
 */
export default function IconField( {
	value = '',
	onChange,
	compact = false,
	addon = false,
	label = '',
	disabled = false,
	className,
} ) {
	const [ open, setOpen ] = useState( false );
	const [ loaded, setLoaded ] = useState( false );
	const show = () => {
		setLoaded( true );
		setOpen( true );
	};
	const title = label
		? sprintf(
				/* translators: %s: what the icon is for, e.g. an amenity name. */
				__( 'Icon for %s', 'radius-hotel-booking' ),
				label
		  )
		: __( 'Choose an icon', 'radius-hotel-booking' );

	const preview = value ? (
		<LucideIcon name={ value } className="h-4 w-4" />
	) : (
		<ImagePlus className="h-4 w-4" aria-hidden="true" />
	);

	return (
		<>
			{ addon ? (
				<button
					type="button"
					disabled={ disabled }
					onClick={ show }
					onPointerEnter={ () => import( './IconPicker' ) }
					title={ title }
					aria-label={ title }
					className={ cn(
						'flex shrink-0 items-center gap-2 rounded-none border-0 border-l border-border bg-muted/40 px-2.5 text-sm font-medium normal-case tracking-normal text-heading shadow-none transition-colors hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50 sm:px-3',
						className
					) }
				>
					<span
						className={ cn(
							'flex h-7 w-7 items-center justify-center rounded-md border',
							value
								? 'border-primary/30 bg-primary-soft text-primary'
								: 'border-dashed border-border bg-white text-muted-foreground'
						) }
					>
						{ preview }
					</span>
					<span className="hidden sm:inline">
						{ value
							? __( 'Change icon', 'radius-hotel-booking' )
							: __( 'Add icon', 'radius-hotel-booking' ) }
					</span>
					<ChevronDown
						className="h-3.5 w-3.5 text-muted-foreground"
						aria-hidden="true"
					/>
				</button>
			) : compact ? (
				<button
					type="button"
					disabled={ disabled }
					onClick={ show }
					onPointerEnter={ () => import( './IconPicker' ) }
					title={ title }
					aria-label={ title }
					className={ cn(
						'flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-dashed p-0 shadow-none transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
						value
							? 'border-border bg-primary-softer text-primary hover:border-primary hover:bg-primary-soft hover:text-primary focus:bg-primary-soft focus:text-primary'
							: 'border-border bg-white text-muted-foreground hover:border-primary hover:bg-primary-softer hover:text-primary focus:bg-primary-softer focus:text-primary',
						className
					) }
				>
					{ value ? (
						<LucideIcon name={ value } className="h-5 w-5" />
					) : (
						<ImagePlus className="h-4 w-4" aria-hidden="true" />
					) }
				</button>
			) : (
				<button
					type="button"
					disabled={ disabled }
					onClick={ show }
					onPointerEnter={ () => import( './IconPicker' ) }
					aria-label={ title }
					className={ cn(
						'flex min-h-[45px] w-full items-center gap-3 rounded-md border border-border bg-white px-3 py-1 text-left text-sm font-normal normal-case tracking-normal text-foreground shadow-none transition-colors hover:border-primary hover:bg-white hover:text-foreground focus:border-primary focus:bg-white focus:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50',
						className
					) }
				>
					<span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary-softer text-primary">
						{ value ? (
							<LucideIcon name={ value } className="h-5 w-5" />
						) : (
							<ImagePlus className="h-4 w-4" aria-hidden="true" />
						) }
					</span>
					<span className="min-w-0 flex-1 truncate">
						{ value ||
							__( 'Choose an icon', 'radius-hotel-booking' ) }
					</span>
					<span className="text-xs font-semibold text-primary">
						{ value
							? __( 'Change', 'radius-hotel-booking' )
							: __( 'Browse', 'radius-hotel-booking' ) }
					</span>
				</button>
			) }
			{ loaded ? (
				<Suspense fallback={ null }>
					<IconPicker
						open={ open }
						onOpenChange={ setOpen }
						value={ value }
						title={ title }
						onSelect={ ( name ) => {
							setOpen( false );
							if ( name !== value ) {
								onChange?.( name );
							}
						} }
					/>
				</Suspense>
			) : null }
		</>
	);
}
