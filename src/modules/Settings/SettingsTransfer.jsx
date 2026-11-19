/**
 * Settings → Import / Export: the settings as a JSON file, to keep a copy or
 * to set up another site the same way (Services\SettingsTransferService).
 *
 * A tool tab (`tool: true` in ./sections): it has no settings of its own, so
 * the screen gives it `{ keys, labels, onImported, blocked }` instead of a
 * section's values.
 *
 * Export picks the tabs to put in the file. Secrets (API keys, PIN hashes)
 * are never in it.
 *
 * Import reads the file, shows what each tab would change (and any value
 * that does not fit this site, such as a logo that is not in its media
 * library, which is skipped), then imports the ticked tabs after the person
 * types IMPORT, as Reset asks for RESET.
 */
import { useId, useRef, useState } from 'react';
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	AlertTriangle,
	CheckCircle2,
	Download,
	FileJson,
	ShieldCheck,
	Upload,
} from 'lucide-react';

import { get, post } from '@/api/client';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { toast, toastError } from '@/lib/toast';
import { cn } from '@/lib/utils';

const CONFIRM_WORD = 'IMPORT';

/**
 * A tab's name, or its key made readable when no tab shows it.
 *
 * @param {Object} labels Key => label.
 * @param {string} key    Section key.
 * @return {string} Label.
 */
const labelOf = ( labels, key ) =>
	labels[ key ] ||
	key.replace( /_/g, ' ' ).replace( /^\w/, ( c ) => c.toUpperCase() );

/**
 * Save a JSON value as a file.
 *
 * @param {Object} value    Value.
 * @param {string} filename File name.
 */
function download( value, filename ) {
	const url = URL.createObjectURL(
		new window.Blob( [ JSON.stringify( value, null, 2 ) ], {
			type: 'application/json',
		} )
	);
	const link = document.createElement( 'a' );
	link.href = url;
	link.download = filename;
	document.body.appendChild( link );
	link.click();
	link.remove();
	window.setTimeout( () => URL.revokeObjectURL( url ), 1000 );
}

/**
 * A list of tabs with a checkbox each and *All settings*.
 *
 * @param {Object}   props          Props.
 * @param {Object[]} props.items    `{ key, label, hint?, tone?, disabled? }`.
 * @param {string[]} props.checked  Ticked keys.
 * @param {Function} props.onChange Called with the new ticked keys.
 * @param {boolean}  props.columns  Two columns on wide screens (short rows).
 * @return {JSX.Element} List.
 */
function SectionList( { items, checked, onChange, columns = false } ) {
	const id = useId();
	const enabled = items.filter( ( item ) => ! item.disabled );
	const all = enabled.length > 0 && checked.length === enabled.length;
	let allState = false;
	if ( all ) {
		allState = true;
	} else if ( checked.length ) {
		allState = 'indeterminate';
	}
	return (
		<div className="overflow-hidden rounded-lg border border-border">
			<label
				htmlFor={ `${ id }-all` }
				className="flex cursor-pointer items-center justify-between gap-3 border-0 border-b border-solid border-border bg-muted/50 px-4 py-2.5"
			>
				<span className="flex items-center gap-3 text-sm font-semibold text-heading">
					<Checkbox
						id={ `${ id }-all` }
						checked={ allState }
						onCheckedChange={ () =>
							onChange(
								all ? [] : enabled.map( ( item ) => item.key )
							)
						}
					/>
					{ __( 'All settings', 'radius-hotel-booking' ) }
				</span>
				<span className="text-xs text-muted-foreground">
					{ sprintf(
						/* translators: 1: tabs ticked, 2: tabs in the list. */
						__( '%1$d of %2$d selected', 'radius-hotel-booking' ),
						checked.length,
						enabled.length
					) }
				</span>
			</label>
			<ul
				className={ cn(
					'm-0 grid list-none p-1',
					columns && 'sm:grid-cols-2'
				) }
			>
				{ items.map( ( item ) => (
					<li key={ item.key } className="m-0">
						<label
							htmlFor={ `${ id }-${ item.key }` }
							className={ cn(
								'flex items-start gap-3 rounded-md px-3 py-2 text-sm',
								item.disabled
									? 'cursor-not-allowed opacity-60'
									: 'cursor-pointer hover:bg-muted/50'
							) }
						>
							<Checkbox
								id={ `${ id }-${ item.key }` }
								className="mt-0.5"
								disabled={ item.disabled }
								checked={ checked.includes( item.key ) }
								onCheckedChange={ ( value ) =>
									onChange(
										value
											? [ ...checked, item.key ]
											: checked.filter(
													( key ) => key !== item.key
											  )
									)
								}
							/>
							<span className="min-w-0 flex-1">
								<span className="block font-medium text-heading">
									{ item.label }
								</span>
								{ item.hint ? (
									<span
										className={ cn(
											'mt-0.5 block text-xs',
											'warning' === item.tone
												? 'text-warning'
												: 'text-muted-foreground'
										) }
									>
										{ item.hint }
									</span>
								) : null }
							</span>
						</label>
					</li>
				) ) }
			</ul>
		</div>
	);
}

/**
 * Export: choose tabs, download the file.
 *
 * @param {Object}   props        Props.
 * @param {string[]} props.keys   Sections the person can see.
 * @param {Object}   props.labels Key => tab name.
 * @return {JSX.Element} Card.
 */
function ExportCard( { keys, labels } ) {
	// The unticked tabs: every tab starts ticked, including ones that
	// arrive after the card is shown.
	const [ unticked, setUnticked ] = useState( [] );
	const checked = keys.filter( ( key ) => ! unticked.includes( key ) );
	const setChecked = ( next ) =>
		setUnticked( keys.filter( ( key ) => ! next.includes( key ) ) );
	const [ busy, setBusy ] = useState( false );

	const run = async () => {
		setBusy( true );
		try {
			const { data } = await get( 'settings/export', {
				sections: checked.join( ',' ),
			} );
			const day = new Date().toISOString().slice( 0, 10 );
			download( data, `radius-hotel-booking-settings-${ day }.json` );
			toast.success(
				__( 'Settings file downloaded.', 'radius-hotel-booking' )
			);
		} catch ( error ) {
			toastError( error );
		} finally {
			setBusy( false );
		}
	};

	return (
		<SettingsSection
			title={ __( 'Export settings', 'radius-hotel-booking' ) }
			description={ __(
				'Download your settings as a file, to keep a copy or to set up another site the same way.',
				'radius-hotel-booking'
			) }
		>
			<SectionList
				items={ keys.map( ( key ) => ( {
					key,
					label: labelOf( labels, key ),
				} ) ) }
				checked={ checked }
				onChange={ setChecked }
				columns
			/>
			<div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
				<p className="m-0 flex items-center gap-2 text-[13px] text-muted-foreground">
					<ShieldCheck
						className="h-4 w-4 shrink-0 text-success"
						aria-hidden="true"
					/>
					{ __(
						'Passwords, PINs and API keys are never included.',
						'radius-hotel-booking'
					) }
				</p>
				<Button onClick={ run } disabled={ busy || ! checked.length }>
					<Download aria-hidden="true" />
					{ busy
						? __( 'Preparing…', 'radius-hotel-booking' )
						: __( 'Download file', 'radius-hotel-booking' ) }
				</Button>
			</div>
		</SettingsSection>
	);
}

/**
 * The line under a tab in the import preview: what importing it changes.
 *
 * @param {Object} section Preview section.
 * @return {string} Hint.
 */
function previewHint( section ) {
	if ( ! section.known ) {
		return __(
			'Not on this site (an add-on that is not active here). Skipped.',
			'radius-hotel-booking'
		);
	}
	const changes = section.changes
		? sprintf(
				/* translators: %d: number of settings that change. */
				_n(
					'%d setting changes',
					'%d settings change',
					section.changes,
					'radius-hotel-booking'
				),
				section.changes
		  )
		: __( 'Same as now', 'radius-hotel-booking' );
	if ( ! section.skipped.length ) {
		return changes;
	}
	return sprintf(
		/* translators: 1: "3 settings change", 2: list of skipped settings with the reason. */
		__( '%1$s. Skipped: %2$s', 'radius-hotel-booking' ),
		changes,
		section.skipped
			.map( ( item ) => `${ item.key } (${ item.message })` )
			.join( '; ' )
	);
}

/**
 * Import: choose the file, check the preview, confirm.
 *
 * @param {Object}   props            Props.
 * @param {Object}   props.labels     Key => tab name.
 * @param {Function} props.onImported Called with the server's result.
 * @param {boolean}  props.blocked    Another tab has unsaved changes.
 * @return {JSX.Element} Card.
 */
function ImportCard( { labels, onImported, blocked } ) {
	const fileId = useId();
	const typedId = useId();
	const inputRef = useRef( null );
	const [ file, setFile ] = useState( null );
	const [ fileName, setFileName ] = useState( '' );
	const [ preview, setPreview ] = useState( null );
	const [ checked, setChecked ] = useState( [] );
	const [ typed, setTyped ] = useState( '' );
	const [ busy, setBusy ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ dragging, setDragging ] = useState( false );

	const clear = () => {
		setFile( null );
		setFileName( '' );
		setPreview( null );
		setChecked( [] );
		setTyped( '' );
		setError( '' );
	};

	const read = async ( picked ) => {
		if ( ! picked || busy ) {
			return;
		}
		clear();
		setFileName( picked.name );
		let parsed;
		try {
			parsed = JSON.parse( await picked.text() );
		} catch ( e ) {
			setError(
				__(
					'This file cannot be read. Choose a settings file exported from Radius Hotel Booking (.json).',
					'radius-hotel-booking'
				)
			);
			return;
		}
		setBusy( true );
		try {
			const { data } = await post( 'settings/import/preview', {
				file: parsed,
			} );
			setFile( parsed );
			setPreview( data );
			// Tick every tab that would change something.
			setChecked(
				data.sections
					.filter( ( section ) => section.known && section.changes )
					.map( ( section ) => section.key )
			);
		} catch ( e ) {
			setError(
				e?.message ||
					__(
						'Something went wrong. Please try again.',
						'radius-hotel-booking'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	const ready =
		! blocked &&
		!! preview &&
		checked.length > 0 &&
		typed.trim() === CONFIRM_WORD;

	const run = async () => {
		if ( ! ready || busy ) {
			return;
		}
		setBusy( true );
		setError( '' );
		try {
			const { data } = await post( 'settings/import', {
				file,
				sections: checked,
			} );
			const count = Object.keys( data.imported || {} ).length;
			toast.success(
				sprintf(
					/* translators: %d: number of settings tabs imported. */
					_n(
						'Settings imported into %d tab.',
						'Settings imported into %d tabs.',
						count,
						'radius-hotel-booking'
					),
					count
				)
			);
			onImported?.( data );
			clear();
		} catch ( e ) {
			setError(
				e?.message ||
					__(
						'Something went wrong. Please try again.',
						'radius-hotel-booking'
					)
			);
		} finally {
			setBusy( false );
		}
	};

	const exportedOn = preview?.exported_at
		? new Date( preview.exported_at ).toLocaleString()
		: '';

	return (
		<SettingsSection
			title={ __( 'Import settings', 'radius-hotel-booking' ) }
			description={ __(
				'Replace settings with the ones in a file exported from Radius Hotel Booking. You see what changes before anything is saved.',
				'radius-hotel-booking'
			) }
		>
			{ blocked ? (
				<p className="m-0 flex items-start gap-2 rounded-lg bg-warning-soft px-3 py-2 text-[13px] text-warning">
					<AlertTriangle
						className="mt-0.5 h-4 w-4 shrink-0"
						aria-hidden="true"
					/>
					{ __(
						'Another tab has unsaved changes. Save or discard them before importing.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }

			{ /* The drop zone; once a file is read, a line with its name. */ }
			{ preview ? (
				<div className="flex flex-wrap items-center gap-3 rounded-lg border border-border bg-muted/30 px-4 py-3">
					<span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-success-soft text-success">
						<CheckCircle2 className="h-5 w-5" aria-hidden="true" />
					</span>
					<span className="min-w-0 flex-1">
						<span className="block truncate text-sm font-semibold text-heading">
							{ fileName }
						</span>
						<span className="block text-xs text-muted-foreground">
							{ sprintf(
								/* translators: 1: site address, 2: date and time. */
								__(
									'Exported from %1$s on %2$s',
									'radius-hotel-booking'
								),
								preview.site || '—',
								exportedOn || '—'
							) }
						</span>
					</span>
					<Button
						type="button"
						variant="outline"
						size="sm"
						disabled={ busy }
						onClick={ () => inputRef.current?.click() }
					>
						{ __( 'Choose another file', 'radius-hotel-booking' ) }
					</Button>
				</div>
			) : (
				<label
					htmlFor={ fileId }
					onDragOver={ ( event ) => {
						event.preventDefault();
						setDragging( true );
					} }
					onDragLeave={ () => setDragging( false ) }
					onDrop={ ( event ) => {
						event.preventDefault();
						setDragging( false );
						read( event.dataTransfer.files?.[ 0 ] );
					} }
					className={ cn(
						'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed px-6 py-8 text-center transition-colors',
						dragging
							? 'border-primary bg-primary-softer'
							: 'border-border hover:border-primary/50 hover:bg-muted/30'
					) }
				>
					<span className="flex h-11 w-11 items-center justify-center rounded-full bg-primary-soft text-primary">
						<FileJson className="h-5 w-5" aria-hidden="true" />
					</span>
					<span className="text-sm font-semibold text-heading">
						{ busy
							? __( 'Reading the file…', 'radius-hotel-booking' )
							: __(
									'Drop a settings file here, or click to choose one',
									'radius-hotel-booking'
							  ) }
					</span>
					<span className="text-xs text-muted-foreground">
						{ fileName ||
							__(
								'A .json file exported from Radius Hotel Booking',
								'radius-hotel-booking'
							) }
					</span>
				</label>
			) }
			<input
				id={ fileId }
				ref={ inputRef }
				type="file"
				accept=".json,application/json"
				className="sr-only"
				onChange={ ( event ) => {
					const picked = event.target.files?.[ 0 ];
					event.target.value = '';
					read( picked );
				} }
			/>

			{ error ? (
				<p
					role="alert"
					className="m-0 flex items-start gap-2 rounded-lg bg-destructive-soft px-3 py-2 text-[13px] text-destructive"
				>
					<AlertTriangle
						className="mt-0.5 h-4 w-4 shrink-0"
						aria-hidden="true"
					/>
					{ error }
				</p>
			) : null }

			{ preview ? (
				<>
					<div className="space-y-2">
						<p className="m-0 text-sm font-semibold text-heading">
							{ __(
								'Choose the settings to import',
								'radius-hotel-booking'
							) }
						</p>
						<SectionList
							items={ preview.sections.map( ( section ) => ( {
								key: section.key,
								label: labelOf( labels, section.key ),
								hint: previewHint( section ),
								tone:
									! section.known || section.skipped.length
										? 'warning'
										: '',
								disabled: ! section.known,
							} ) ) }
							checked={ checked }
							onChange={ setChecked }
						/>
					</div>
					<div className="flex flex-col gap-3 border-0 border-t border-solid border-border pt-4 sm:flex-row sm:items-end">
						<div className="min-w-0 flex-1 space-y-1.5">
							<Label
								htmlFor={ typedId }
								className="text-sm font-semibold text-heading"
							>
								{ sprintf(
									/* translators: %s: the word to type, IMPORT. */
									__(
										'Type %s to confirm',
										'radius-hotel-booking'
									),
									CONFIRM_WORD
								) }
							</Label>
							<Input
								id={ typedId }
								value={ typed }
								autoComplete="off"
								spellCheck={ false }
								placeholder={ CONFIRM_WORD }
								onChange={ ( event ) =>
									setTyped( event.target.value )
								}
								onKeyDown={ ( event ) => {
									if ( 'Enter' === event.key ) {
										event.preventDefault();
										run();
									}
								} }
								disabled={ busy || blocked }
							/>
						</div>
						<div className="flex gap-2">
							<Button
								variant="outline"
								onClick={ clear }
								disabled={ busy }
							>
								{ __( 'Cancel', 'radius-hotel-booking' ) }
							</Button>
							<Button
								variant="destructive"
								onClick={ run }
								disabled={ ! ready || busy }
								className={ cn( busy && 'cursor-wait' ) }
							>
								<Upload aria-hidden="true" />
								{ busy
									? __( 'Importing…', 'radius-hotel-booking' )
									: __(
											'Import settings',
											'radius-hotel-booking'
									  ) }
							</Button>
						</div>
					</div>
				</>
			) : null }
		</SettingsSection>
	);
}

/**
 * The tab.
 *
 * @param {Object}   props            Props (see the file comment).
 * @param {string[]} props.keys       Sections the person can export.
 * @param {Object}   props.labels     Key => tab name.
 * @param {Function} props.onImported Called after an import.
 * @param {boolean}  props.blocked    Another tab has unsaved changes.
 * @return {JSX.Element} Tab.
 */
export default function SettingsTransfer( {
	keys = [],
	labels = {},
	onImported,
	blocked = false,
} ) {
	return (
		<>
			<ExportCard keys={ keys } labels={ labels } />
			<ImportCard
				labels={ labels }
				onImported={ onImported }
				blocked={ blocked }
			/>
		</>
	);
}
