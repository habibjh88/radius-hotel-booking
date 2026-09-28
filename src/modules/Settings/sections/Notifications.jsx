/**
 * Settings → Notifications: the new-booking alert on staff screens and its
 * sound (features 17.1, 17.2).
 */
import { useEffect, useRef, useState } from 'react';
import { __ } from '@wordpress/i18n';
import { Play } from 'lucide-react';

import { Field } from '@/components/common/Form';
import MediaField from '@/components/common/MediaField';
import SegmentedControl from '@/components/common/SegmentedControl';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { playNotificationSound } from '@/lib/sound';
import { toast } from '@/lib/toast';
import { NumberInput, ToggleRow } from './fields';

/**
 * URL of a media library attachment ('' until known, or for 0).
 *
 * @param {number} id Attachment id.
 * @return {string} URL.
 */
function useAttachmentUrl( id ) {
	const [ url, setUrl ] = useState( '' );
	useEffect( () => {
		setUrl( '' );
		const media = window.wp?.media;
		if ( ! id || ! media ) {
			return undefined;
		}
		let alive = true;
		const attachment = media.attachment( id );
		attachment
			.fetch()
			.then( () => alive && setUrl( attachment.get( 'url' ) || '' ) )
			.catch( () => {} );
		return () => {
			alive = false;
		};
	}, [ id ] );
	return url;
}

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @param {Object}   props.schema   Key => schema (limits).
 * @return {JSX.Element} Tab.
 */
export default function Notifications( { value, setField, errors, schema } ) {
	const soundId = Number( value.soundId ) || 0;
	// "Your own sound" stays selected while no file is chosen yet.
	const [ mode, setMode ] = useState( soundId ? 'custom' : 'chime' );
	// Follow outside changes: a reset, a discard or Remove back to 0 means the chime.
	const previousId = useRef( soundId );
	useEffect( () => {
		if ( soundId ) {
			setMode( 'custom' );
		} else if ( previousId.current ) {
			setMode( 'chime' );
		}
		previousId.current = soundId;
	}, [ soundId ] );
	const url = useAttachmentUrl( soundId );

	const chooseMode = ( next ) => {
		setMode( next );
		if ( next === 'chime' ) {
			setField( 'soundId' )( 0 );
		}
	};

	const preview = async () => {
		const played = await playNotificationSound(
			mode === 'custom' ? url : ''
		);
		if ( ! played ) {
			toast.error(
				__(
					'The browser blocked the sound. Click on the page and try again.',
					'radius-hotel-booking'
				)
			);
		}
	};

	return (
		<SettingsSection
			title={ __( 'New bookings', 'radius-hotel-booking' ) }
			description={ __(
				'How staff are told about a new booking while a dashboard screen is open.',
				'radius-hotel-booking'
			) }
		>
			<ToggleRow
				label={ __( 'Alert on new bookings', 'radius-hotel-booking' ) }
				description={ __(
					'Show an alert on every staff screen when a booking arrives.',
					'radius-hotel-booking'
				) }
				checked={ value.newBookingAlert }
				onChange={ setField( 'newBookingAlert' ) }
				error={ errors.newBookingAlert }
			>
				<div className="space-y-5">
					<ToggleRow
						label={ __( 'Play a sound', 'radius-hotel-booking' ) }
						description={ __(
							'Browsers play sound only after someone has clicked on the page.',
							'radius-hotel-booking'
						) }
						checked={ value.playSound }
						onChange={ setField( 'playSound' ) }
						error={ errors.playSound }
					>
						<div className="space-y-3">
							<SegmentedControl
								label={ __( 'Sound', 'radius-hotel-booking' ) }
								value={ mode }
								onChange={ chooseMode }
								options={ [
									{
										value: 'chime',
										label: __(
											'Built-in chime',
											'radius-hotel-booking'
										),
									},
									{
										value: 'custom',
										label: __(
											'Your own sound',
											'radius-hotel-booking'
										),
									},
								] }
							/>
							{ mode === 'custom' ? (
								<Field
									label={ __(
										'Sound file',
										'radius-hotel-booking'
									) }
									description={ __(
										'An MP3, WAV, OGG or M4A file from the media library. Keep it short.',
										'radius-hotel-booking'
									) }
									error={ errors.soundId }
								>
									<MediaField
										value={ soundId }
										onChange={ setField( 'soundId' ) }
										accept={
											schema.soundId?.mime || [ 'audio/' ]
										}
										title={ __(
											'Choose a notification sound',
											'radius-hotel-booking'
										) }
									/>
								</Field>
							) : null }
							<Button
								type="button"
								variant="outline"
								size="sm"
								onClick={ preview }
								disabled={ mode === 'custom' && ! url }
							>
								<Play aria-hidden="true" />
								{ __( 'Play preview', 'radius-hotel-booking' ) }
							</Button>
						</div>
					</ToggleRow>

					<Field
						label={ __(
							'Check for new bookings every',
							'radius-hotel-booking'
						) }
						description={ __(
							'Shorter is quicker to notice, longer puts less load on the server.',
							'radius-hotel-booking'
						) }
						error={ errors.pollSeconds }
					>
						<NumberInput
							value={ value.pollSeconds }
							onChange={ setField( 'pollSeconds' ) }
							unit={ __( 'seconds', 'radius-hotel-booking' ) }
							limits={ schema.pollSeconds }
						/>
					</Field>
				</div>
			</ToggleRow>
		</SettingsSection>
	);
}
