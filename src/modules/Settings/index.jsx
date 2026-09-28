import { useEffect, useMemo, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';

import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { get, put } from '@/api/client';
import { applyPrimaryColor } from '@/lib/theme';
import { toast, toastError } from '@/lib/toast';
import BrandColorField from './BrandColorField';

const SECTIONS = [
	{ key: 'general', label: __( 'General', 'radius-hotel-booking' ) },
	{ key: 'display', label: __( 'Display', 'radius-hotel-booking' ) },
];

/**
 * Settings screen.
 *
 * Reads `GET /settings`, writes one section at a time with
 * `PUT /settings/<section>`. Sections and their defaults are declared in
 * Helpers\SettingsHelper — add one there and render it here.
 *
 * @return {JSX.Element} Screen.
 */
export default function Settings() {
	const [ settings, setSettings ] = useState( null );
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( '' );

	// Tabs contributed by add-ons (ADR-015). Each entry is
	// `{ key, label, render( { value, setField, save, saving } ) }`, where `key`
	// is a section registered in PHP through the `rtbp_settings` filter.
	const extraSections = useMemo(
		() =>
			( applyFilters( 'rtbp.settings.sections', [] ) || [] ).filter(
				( section ) =>
					section?.key &&
					typeof section.render === 'function' &&
					! SECTIONS.some( ( { key } ) => key === section.key )
			),
		[]
	);

	// The brand colour as last saved: previews change it live, and leaving the
	// screen without saving puts this one back.
	const savedPrimary = useRef( null );

	useEffect( () => {
		get( 'settings' )
			.then( ( { data } ) => {
				const loaded = data.settings ?? data;
				savedPrimary.current = loaded.display?.primaryColor ?? null;
				setSettings( loaded );
			} )
			.catch( ( error ) => setNotice( error.message ) );

		return () => {
			if ( savedPrimary.current ) {
				applyPrimaryColor( savedPrimary.current );
			}
		};
	}, [] );

	const setField = ( section, field ) => ( value ) =>
		setSettings( ( current ) => ( {
			...current,
			[ section ]: { ...current[ section ], [ field ]: value },
		} ) );

	const save = async ( section ) => {
		setSaving( true );

		try {
			await put( `settings/${ section }`, settings[ section ] );
			if ( section === 'display' ) {
				savedPrimary.current = settings.display?.primaryColor ?? null;
			}
			toast.success( __( 'Settings saved.', 'radius-hotel-booking' ) );
		} catch ( error ) {
			toastError( error );
		} finally {
			setSaving( false );
		}
	};

	if ( ! settings ) {
		return (
			<p className="text-sm text-muted-foreground">
				{ notice || __( 'Loading settings…', 'radius-hotel-booking' ) }
			</p>
		);
	}

	return (
		<div className="max-w-3xl space-y-4">
			<Tabs defaultValue="general">
				<TabsList>
					{ [ ...SECTIONS, ...extraSections ].map(
						( { key, label } ) => (
							<TabsTrigger key={ key } value={ key }>
								{ label }
							</TabsTrigger>
						)
					) }
				</TabsList>

				<TabsContent value="general">
					<Card>
						<CardHeader>
							<CardTitle>
								{ __( 'General', 'radius-hotel-booking' ) }
							</CardTitle>
						</CardHeader>
						<CardContent className="space-y-4">
							<div>
								<Label htmlFor="rtbp-company">
									{ __(
										'Company name',
										'radius-hotel-booking'
									) }
								</Label>
								<Input
									id="rtbp-company"
									value={
										settings.general?.companyName ?? ''
									}
									onChange={ ( event ) =>
										setField(
											'general',
											'companyName'
										)( event.target.value )
									}
								/>
							</div>

							<div>
								<Label htmlFor="rtbp-contact">
									{ __(
										'Contact email',
										'radius-hotel-booking'
									) }
								</Label>
								<Input
									id="rtbp-contact"
									type="email"
									value={
										settings.general?.contactEmail ?? ''
									}
									onChange={ ( event ) =>
										setField(
											'general',
											'contactEmail'
										)( event.target.value )
									}
								/>
							</div>

							<div className="flex items-center justify-between rounded-md border border-border p-3">
								<Label htmlFor="rtbp-debug" className="m-0">
									{ __(
										'Enable debug logging',
										'radius-hotel-booking'
									) }
								</Label>
								<Switch
									id="rtbp-debug"
									checked={ !! settings.general?.enableDebug }
									onCheckedChange={ setField(
										'general',
										'enableDebug'
									) }
								/>
							</div>

							<Button
								disabled={ saving }
								onClick={ () => save( 'general' ) }
							>
								{ saving
									? __( 'Saving…', 'radius-hotel-booking' )
									: __(
											'Save changes',
											'radius-hotel-booking'
									  ) }
							</Button>
						</CardContent>
					</Card>
				</TabsContent>

				<TabsContent value="display">
					<Card>
						<CardHeader>
							<CardTitle>
								{ __( 'Display', 'radius-hotel-booking' ) }
							</CardTitle>
						</CardHeader>
						<CardContent className="space-y-4">
							<BrandColorField
								value={ settings.display?.primaryColor }
								onChange={ setField(
									'display',
									'primaryColor'
								) }
							/>

							<div>
								<Label htmlFor="rtbp-columns">
									{ __( 'Columns', 'radius-hotel-booking' ) }
								</Label>
								<Input
									id="rtbp-columns"
									type="number"
									min="1"
									max="6"
									value={ settings.display?.columns ?? 3 }
									onChange={ ( event ) =>
										setField(
											'display',
											'columns'
										)( Number( event.target.value ) )
									}
								/>
							</div>

							<Button
								disabled={ saving }
								onClick={ () => save( 'display' ) }
							>
								{ saving
									? __( 'Saving…', 'radius-hotel-booking' )
									: __(
											'Save changes',
											'radius-hotel-booking'
									  ) }
							</Button>
						</CardContent>
					</Card>
				</TabsContent>

				{ extraSections.map( ( { key, render } ) => (
					<TabsContent key={ key } value={ key }>
						{ render( {
							value: settings[ key ] ?? {},
							setField: ( field ) => setField( key, field ),
							save: () => save( key ),
							saving,
						} ) }
					</TabsContent>
				) ) }
			</Tabs>
		</div>
	);
}
