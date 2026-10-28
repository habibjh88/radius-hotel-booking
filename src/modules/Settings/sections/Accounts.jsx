/**
 * Settings → Guest accounts: WordPress accounts on the website booking form.
 * Existing accounts (a shop's customers) are not copied anywhere: an account
 * is linked to its guest record the first time it books signed in.
 */
import { __ } from '@wordpress/i18n';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { ToggleRow } from './fields';

/**
 * @param {Object}   props          Props from the Settings screen.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Accounts( { value, setField, errors } ) {
	const roles = window.radius_hotel_booking_param?.account_roles || {};

	return (
		<>
			<SettingsSection
				title={ __( 'Signing in', 'radius-hotel-booking' ) }
				description={ __(
					'Guests with an account on this website can sign in on the booking form. Their details are filled in for them, and their account is linked to their guest record the first time they book.',
					'radius-hotel-booking'
				) }
			>
				<div className="space-y-5">
					<ToggleRow
						label={ __(
							'Show a "Sign in" link on the booking form',
							'radius-hotel-booking'
						) }
						description={ __(
							'Invites returning guests to sign in so they do not have to type their details again. Guests can still book without an account.',
							'radius-hotel-booking'
						) }
						checked={ value.showSignIn }
						onChange={ setField( 'showSignIn' ) }
						error={ errors.showSignIn }
					/>
					<ToggleRow
						label={ __(
							'Require sign-in to book',
							'radius-hotel-booking'
						) }
						description={ __(
							'Only signed-in guests can book on the website; others are asked to sign in first. Turn this on only when every guest can sign in: accounts without a real e-mail address cannot reset a forgotten password.',
							'radius-hotel-booking'
						) }
						checked={ value.requireSignIn }
						onChange={ setField( 'requireSignIn' ) }
						error={ errors.requireSignIn }
					/>
				</div>
			</SettingsSection>

			<SettingsSection
				title={ __( 'Express checkout', 'radius-hotel-booking' ) }
				description={ __(
					'Lets returning guests fill in their details without signing in.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __(
						'Offer express checkout on the booking form',
						'radius-hotel-booking'
					) }
					description={ __(
						'The guest enters their phone number. If the hotel knows it, their details are used for the booking. A new number only fills in the phone.',
						'radius-hotel-booking'
					) }
					checked={ value.expressCheckout }
					onChange={ setField( 'expressCheckout' ) }
					error={ errors.expressCheckout }
				>
					<ToggleRow
						label={ __(
							'Verify the phone with a code',
							'radius-hotel-booking'
						) }
						description={
							value.expressVerify
								? __(
										"A 6-digit code is sent to the guest's e-mail on file, and entering it fills in their details. Guests without an e-mail address need an SMS add-on to receive a code.",
										'radius-hotel-booking'
								  )
								: __(
										'No code: a known number shows only a masked name, e-mail and document, and the booking uses the details on file, identity document included. The booking is not linked to their account.',
										'radius-hotel-booking'
								  )
						}
						checked={ value.expressVerify }
						onChange={ setField( 'expressVerify' ) }
						error={ errors.expressVerify }
					/>
				</ToggleRow>
			</SettingsSection>

			<SettingsSection
				title={ __( 'New accounts', 'radius-hotel-booking' ) }
				description={ __(
					'What happens when someone without an account books on the website.',
					'radius-hotel-booking'
				) }
			>
				<ToggleRow
					label={ __(
						'Create an account for new guests',
						'radius-hotel-booking'
					) }
					description={ __(
						'When a booking comes from a phone number and e-mail address the website does not know, an account is created and the guest receives an e-mail to set their password. A guest without an e-mail address gets no account. Nobody is ever signed in automatically: if an account already exists for that phone or e-mail, the booking is taken without it.',
						'radius-hotel-booking'
					) }
					checked={ value.createAccounts }
					onChange={ setField( 'createAccounts' ) }
					error={ errors.createAccounts }
				>
					<div className="grid gap-4 sm:grid-cols-2">
						<Field
							label={ __( 'Username', 'radius-hotel-booking' ) }
							description={ __(
								'What guests type to sign in.',
								'radius-hotel-booking'
							) }
							error={ errors.usernameFrom }
						>
							<Select
								value={ value.usernameFrom || 'phone' }
								onValueChange={ setField( 'usernameFrom' ) }
							>
								<SelectTrigger>
									<SelectValue />
								</SelectTrigger>
								<SelectContent className="rtbp-root">
									<SelectItem value="phone">
										{ __(
											'Phone number',
											'radius-hotel-booking'
										) }
									</SelectItem>
									<SelectItem value="email">
										{ __(
											'E-mail address',
											'radius-hotel-booking'
										) }
									</SelectItem>
								</SelectContent>
							</Select>
						</Field>
						<Field
							label={ __(
								'Role for new accounts',
								'radius-hotel-booking'
							) }
							description={ __(
								'Only roles without access to manage the website are offered.',
								'radius-hotel-booking'
							) }
							error={ errors.accountRole }
						>
							<Select
								value={
									// A role no longer offered: the default (the first), as the server uses it.
									roles[ value.accountRole ]
										? value.accountRole
										: Object.keys( roles )[ 0 ] || ''
								}
								onValueChange={ setField( 'accountRole' ) }
							>
								<SelectTrigger>
									<SelectValue />
								</SelectTrigger>
								<SelectContent className="rtbp-root">
									{ Object.entries( roles ).map(
										( [ slug, name ] ) => (
											<SelectItem
												key={ slug }
												value={ slug }
											>
												{ name }
											</SelectItem>
										)
									) }
								</SelectContent>
							</Select>
						</Field>
					</div>
				</ToggleRow>
			</SettingsSection>
		</>
	);
}
