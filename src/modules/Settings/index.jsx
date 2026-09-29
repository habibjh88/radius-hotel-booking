/**
 * Settings screen (M17): vertical tabs on wide screens, a Select on phones;
 * each tab is a stack of SettingsSection cards with one Save; a sticky bar
 * appears while the tab has unsaved changes.
 *
 * Tabs come from ./sections (the free plugin's) and the
 * `rtbp.settings.sections` filter (add-ons, ADR-015). An add-on tab is
 * `{ key, label, description?, icon?, Component }` or the older
 * `{ key, label, render( props ) }`, with an optional `accessKey` (default
 * `settings.<key>`: the tab is hidden while it is locked). Both receive:
 *
 *   value      the section's current (draft) values
 *   setField   setField( key )( value )
 *   errors     key => the server's message for that field
 *   schema     the section's schema (types, options, limits)
 *   save       saves the tab (the sticky bar does this too); resolves with
 *              the saved values, or null when the server refused them
 *   saving     true while this tab saves
 *
 * The active tab is in the URL: `#/settings?section=display`.
 */
import { Suspense, useEffect, useMemo, useRef, useState } from 'react';
import { useSearchParams } from 'react-router-dom';
import { applyFilters } from '@wordpress/hooks';
import { __, sprintf } from '@wordpress/i18n';
import { RotateCcw, Settings as SettingsIcon } from 'lucide-react';

import ConfirmDialog from '@/components/common/ConfirmDialog';
import { Button } from '@/components/ui/button';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { canAccess, useAccessMap } from '@/lib/access';
import { applyPrimaryColor, isHexColor } from '@/lib/theme';
import { cn } from '@/lib/utils';
import coreSections from './sections';
import UpgradeNotice from './UpgradeNotice';
import useSettingsDrafts from './useSettingsDrafts';

/**
 * Core tabs plus add-on tabs, de-duplicated by key.
 *
 * @return {Array<Object>} Tabs.
 */
function useSections() {
	return useMemo( () => {
		const core = coreSections();
		const extra = (
			applyFilters( 'rtbp.settings.sections', [] ) || []
		).filter(
			( section ) =>
				section?.key &&
				( section.Component || typeof section.render === 'function' ) &&
				! core.some( ( { key } ) => key === section.key )
		);
		return [ ...core, ...extra ];
	}, [] );
}

/**
 * The tab list: vertical on wide screens, a Select on phones.
 *
 * @param {Object}        props          Props.
 * @param {Array<Object>} props.sections Tabs.
 * @param {string}        props.active   Active key.
 * @param {Function}      props.onSelect Called with a key.
 * @param {Function}      props.isDirty  Whether a tab has unsaved changes.
 * @return {JSX.Element} Nav.
 */
function SectionNav( { sections, active, onSelect, isDirty } ) {
	return (
		<>
			<div className="md:hidden">
				<Select value={ active } onValueChange={ onSelect }>
					<SelectTrigger
						aria-label={ __(
							'Settings section',
							'radius-hotel-booking'
						) }
					>
						<SelectValue />
					</SelectTrigger>
					<SelectContent className="rtbp-root">
						{ sections.map( ( section ) => (
							<SelectItem
								key={ section.key }
								value={ section.key }
							>
								{ section.label }
								{ isDirty( section.key ) ? ' •' : '' }
							</SelectItem>
						) ) }
					</SelectContent>
				</Select>
			</div>
			<nav
				className="hidden md:block"
				aria-label={ __( 'Settings sections', 'radius-hotel-booking' ) }
			>
				<ul className="m-0 list-none space-y-1 p-0">
					{ sections.map( ( section ) => {
						const Icon = section.icon || SettingsIcon;
						const selected = section.key === active;
						return (
							<li key={ section.key } className="m-0">
								<button
									type="button"
									onClick={ () => onSelect( section.key ) }
									aria-current={
										selected ? 'page' : undefined
									}
									className={ cn(
										'flex w-full items-center gap-2.5 rounded-lg border-0 px-3 py-2 text-left text-sm font-medium transition-colors',
										selected
											? 'bg-primary-soft text-primary'
											: 'bg-transparent text-heading hover:bg-muted'
									) }
								>
									<Icon
										className="h-4 w-4 shrink-0"
										aria-hidden="true"
									/>
									<span className="min-w-0 flex-1 truncate">
										{ section.label }
									</span>
									{ isDirty( section.key ) ? (
										<span
											className="h-2 w-2 shrink-0 rounded-full bg-warning"
											title={ __(
												'Unsaved changes',
												'radius-hotel-booking'
											) }
										/>
									) : null }
								</button>
							</li>
						);
					} ) }
				</ul>
			</nav>
		</>
	);
}

/**
 * The bar shown while the tab has unsaved changes.
 *
 * @param {Object}   props           Props.
 * @param {boolean}  props.saving    Saving.
 * @param {Function} props.onSave    Save.
 * @param {Function} props.onDiscard Discard.
 * @return {JSX.Element} Bar.
 */
function UnsavedBar( { saving, onSave, onDiscard } ) {
	return (
		<div
			role="region"
			aria-label={ __( 'Unsaved changes', 'radius-hotel-booking' ) }
			className="sticky bottom-[4.5rem] z-10 mt-4 flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-card px-4 py-3 shadow-lg md:bottom-4"
		>
			<p className="m-0 text-sm font-medium text-heading">
				{ __( 'You have unsaved changes.', 'radius-hotel-booking' ) }
			</p>
			<div className="flex gap-2">
				<Button
					variant="outline"
					onClick={ onDiscard }
					disabled={ saving }
				>
					{ __( 'Discard', 'radius-hotel-booking' ) }
				</Button>
				<Button onClick={ onSave } disabled={ saving }>
					{ saving
						? __( 'Saving…', 'radius-hotel-booking' )
						: __( 'Save changes', 'radius-hotel-booking' ) }
				</Button>
			</div>
		</div>
	);
}

/**
 * Loading placeholder.
 *
 * @return {JSX.Element} Skeleton.
 */
function SettingsSkeleton() {
	return (
		<div className="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)]">
			<div className="hidden space-y-2 md:block">
				{ [ 1, 2, 3 ].map( ( i ) => (
					<Skeleton key={ i } className="h-9 w-full" />
				) ) }
			</div>
			<Skeleton className="h-64 w-full" />
		</div>
	);
}

/**
 * @return {JSX.Element} Screen.
 */
export default function Settings() {
	const sections = useSections();
	const state = useSettingsDrafts();
	const [ params, setParams ] = useSearchParams();
	const [ confirmReset, setConfirmReset ] = useState( false );

	// Only tabs whose section the server knows (an add-on may be half-loaded)
	// and whose access key is not locked (M13; the server refuses it anyway).
	const { data: accessLevels } = useAccessMap();
	const available = useMemo(
		() =>
			state.drafts
				? sections.filter(
						( section ) =>
							section.key in state.drafts &&
							canAccess(
								section.accessKey ?? `settings.${ section.key }`
							)
				  )
				: [],
		// accessLevels: re-filter when the access map changes.
		[ sections, state.drafts, accessLevels ]
	);
	const active =
		available.find(
			( section ) => section.key === params.get( 'section' )
		) || available[ 0 ];

	const select = ( key ) => setParams( { section: key } );

	// Warn before leaving the page with unsaved changes.
	const anyDirty = state.dirtySections.length > 0;
	useEffect( () => {
		if ( ! anyDirty ) {
			return undefined;
		}
		const warn = ( event ) => {
			event.preventDefault();
			event.returnValue = '';
		};
		window.addEventListener( 'beforeunload', warn );
		return () => window.removeEventListener( 'beforeunload', warn );
	}, [ anyDirty ] );

	// The brand colour previews live while edited; leaving the screen puts
	// the saved one back.
	const savedColor = useRef( null );
	savedColor.current = state.saved?.display?.primaryColor ?? null;
	const draftColor = state.drafts?.display?.primaryColor;
	useEffect( () => {
		if ( isHexColor( draftColor ) ) {
			applyPrimaryColor( draftColor );
		}
	}, [ draftColor ] );
	useEffect(
		() => () => {
			if ( isHexColor( savedColor.current ) ) {
				applyPrimaryColor( savedColor.current );
			}
		},
		[]
	);

	if ( state.loadError ) {
		return (
			<div
				role="alert"
				className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border bg-destructive-soft p-4 text-sm text-destructive"
			>
				<span>{ state.loadError.message }</span>
				<Button variant="outline" size="sm" onClick={ state.reload }>
					{ __( 'Try again', 'radius-hotel-booking' ) }
				</Button>
			</div>
		);
	}

	if ( ! state.drafts || ! active ) {
		return <SettingsSkeleton />;
	}

	const key = active.key;
	// Save or reset, then let the tab react (e.g. General updates formats).
	const after = ( values ) => {
		if ( values && typeof active.onSaved === 'function' ) {
			active.onSaved( values );
		}
		return values;
	};
	const props = {
		value: state.drafts[ key ] ?? {},
		setField: state.setField( key ),
		errors: state.errors[ key ] ?? {},
		schema: state.schema[ key ] ?? {},
		save: () => state.save( key ).then( after ),
		saving: state.saving === key,
	};
	const { Component } = active;

	return (
		<>
			<UpgradeNotice />
			<div className="grid gap-6 md:grid-cols-[13rem_minmax(0,1fr)]">
				<SectionNav
					sections={ available }
					active={ key }
					onSelect={ select }
					isDirty={ state.isDirty }
				/>

				<div className="min-w-0">
					<div className="mb-4 flex flex-wrap items-start justify-between gap-3">
						<div className="min-w-0">
							<h2 className="m-0 p-0 text-lg font-semibold text-heading">
								{ active.label }
							</h2>
							{ active.description ? (
								<p className="m-0 mt-1 text-sm text-muted-foreground">
									{ active.description }
								</p>
							) : null }
						</div>
						<Button
							variant="ghost"
							size="sm"
							onClick={ () => setConfirmReset( true ) }
							disabled={ !! state.saving }
						>
							<RotateCcw aria-hidden="true" />
							{ __(
								'Reset to defaults',
								'radius-hotel-booking'
							) }
						</Button>
					</div>

					<div className="space-y-4">
						<Suspense
							fallback={ <Skeleton className="h-48 w-full" /> }
						>
							{ Component ? (
								<Component key={ key } { ...props } />
							) : (
								active.render( props )
							) }
						</Suspense>
					</div>

					{ state.isDirty( key ) ? (
						<UnsavedBar
							saving={ props.saving }
							onSave={ props.save }
							onDiscard={ () => state.discard( key ) }
						/>
					) : null }
				</div>

				<ConfirmDialog
					open={ confirmReset }
					onOpenChange={ setConfirmReset }
					title={ sprintf(
						/* translators: %s: settings section, e.g. "General". */
						__(
							'Reset %s to its defaults?',
							'radius-hotel-booking'
						),
						active.label
					) }
					description={ __(
						'Every setting on this tab goes back to its default value. Other tabs are not changed.',
						'radius-hotel-booking'
					) }
					confirmLabel={ __( 'Reset', 'radius-hotel-booking' ) }
					destructive
					onConfirm={ () => state.reset( key ).then( after ) }
				/>
			</div>
		</>
	);
}
