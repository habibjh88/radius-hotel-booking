/**
 * Settings → Public booking (M04): how guests book on the website. The rules
 * shared with the front desk (booking window, same-day cut-off, approval,
 * unavailable rooms) are in Booking rules.
 */
import { useState } from 'react';
import { __ } from '@wordpress/i18n';
import { FilePlus2 } from 'lucide-react';

import { post } from '@/api/client';
import { Button } from '@/components/ui/button';
import { toast, toastError } from '@/lib/toast';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { NumberInput, ToggleRow } from './fields';

/**
 * @param {Object}   props          Props from the Settings screen.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema.
 * @param {Function} props.accept   Take values the server already saved.
 * @return {JSX.Element} Tab.
 */
export default function Website( { value, setField, errors, schema, accept } ) {
	const params = window.radius_hotel_booking_param || {};
	const [ pages, setPages ] = useState( params.site_pages || [] );
	const [ creating, setCreating ] = useState( false );
	const chosen = pages.some(
		( page ) => page.id === Number( value.resultsPageId )
	);

	// Create *Book a room* with [rtbp_booking] and choose it (saved on the server at once).
	const createPage = () => {
		setCreating( true );
		post( 'settings/website/booking-page' )
			.then( ( { data, message } ) => {
				const page = { id: data.page.id, title: data.page.title };
				const next = [
					...pages.filter( ( p ) => p.id !== page.id ),
					page,
				];
				setPages( next );
				params.site_pages = next;
				// Saved on the server already: take it as saved, not as an unsaved change.
				if ( accept && data.data ) {
					accept( data.data );
				} else {
					setField( 'resultsPageId' )( page.id );
				}
				toast.success( message );
			} )
			.catch( toastError )
			.finally( () => setCreating( false ) );
	};
	const privacyUrl = params.privacy_url || '';

	return (
		<>
			<SettingsSection
				title={ __( 'Search and results', 'radius-hotel-booking' ) }
				description={ __(
					'The search bar sends guests to the booking page, which shows the rooms free for their dates.',
					'radius-hotel-booking'
				) }
			>
				<Field
					label={ __( 'Booking page', 'radius-hotel-booking' ) }
					description={ __(
						'The page with the [rtbp_booking] shortcode or the Booking block.',
						'radius-hotel-booking'
					) }
					error={ errors.resultsPageId }
				>
					<Select
						value={ String( value.resultsPageId || 0 ) }
						onValueChange={ ( next ) =>
							setField( 'resultsPageId' )( Number( next ) )
						}
					>
						<SelectTrigger
							className="w-full sm:w-80"
							aria-label={ __(
								'Booking page',
								'radius-hotel-booking'
							) }
						>
							<SelectValue />
						</SelectTrigger>
						<SelectContent className="rtbp-root">
							<SelectItem value="0">
								{ __(
									'Not chosen yet',
									'radius-hotel-booking'
								) }
							</SelectItem>
							{ pages.map( ( page ) => (
								<SelectItem
									key={ page.id }
									value={ String( page.id ) }
								>
									{ page.title }
								</SelectItem>
							) ) }
						</SelectContent>
					</Select>
				</Field>
				{ /* Outside the Field: it passes one child (the control) to its label slot. */ }
				{ ! chosen ? (
					<div>
						<Button
							type="button"
							variant="outline"
							disabled={ creating }
							onClick={ createPage }
						>
							<FilePlus2 aria-hidden="true" />
							{ __(
								'Create the booking page',
								'radius-hotel-booking'
							) }
						</Button>
					</div>
				) : null }

				<Field
					label={ __( 'Adults preselected', 'radius-hotel-booking' ) }
					error={ errors.defaultAdults }
				>
					<NumberInput
						value={ value.defaultAdults }
						onChange={ setField( 'defaultAdults' ) }
						unit={ __( 'adults', 'radius-hotel-booking' ) }
						limits={ schema.defaultAdults }
					/>
				</Field>

				<ToggleRow
					label={ __( 'Ask how many rooms', 'radius-hotel-booking' ) }
					description={ __(
						'Adds a rooms field to the search bar, for guests booking for a group.',
						'radius-hotel-booking'
					) }
					checked={ value.showRoomsField }
					onChange={ setField( 'showRoomsField' ) }
					error={ errors.showRoomsField }
				/>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Booking form', 'radius-hotel-booking' ) }
				description={ __(
					'What guests choose and agree to before they book.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __(
						'Guests choose their room',
						'radius-hotel-booking'
					) }
					description={ __(
						'Guests pick the room number. When off, the first free room is given to them.',
						'radius-hotel-booking'
					) }
					checked={ value.guestPicksRoom }
					onChange={ setField( 'guestPicksRoom' ) }
					error={ errors.guestPicksRoom }
				/>
				<ToggleRow
					label={ __(
						'Ask guests to accept the privacy policy',
						'radius-hotel-booking'
					) }
					description={
						privacyUrl
							? __(
									'A checkbox linking to your privacy policy page.',
									'radius-hotel-booking'
							  )
							: __(
									'A checkbox before booking. Set your privacy policy page in Settings → Privacy (WordPress) so the box can link to it.',
									'radius-hotel-booking'
							  )
					}
					checked={ value.privacyConsent }
					onChange={ setField( 'privacyConsent' ) }
					error={ errors.privacyConsent }
				/>
			</SettingsSection>
		</>
	);
}
