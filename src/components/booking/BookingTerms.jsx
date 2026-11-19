/**
 * What the guest reads and agrees to just before confirming a website
 * booking: the hotel's house rules (Settings → Public booking → House rules)
 * and the two checkboxes, privacy and house rules. Shared by the step-by-step
 * and the one-page booking forms, so both ask the same thing; the server
 * checks the same settings (`PublicBookingService::book()`).
 */
import { __ } from '@wordpress/i18n';
import { ClipboardList } from 'lucide-react';

import LucideIcon from '@/components/icons/LucideIcon';

/**
 * Whether the guest has ticked every box the settings ask for.
 *
 * @param {Object}  rules         The website's booking rules.
 * @param {boolean} consent       Privacy box ticked.
 * @param {boolean} rulesAccepted House rules box ticked.
 * @return {boolean} Ready to book.
 */
export function termsReady( rules, consent, rulesAccepted ) {
	if ( rules.privacyConsent && ! consent ) {
		return false;
	}
	return ! ( rules.houseRules?.consent && ! rulesAccepted );
}

/**
 * The house rules, each with its icon.
 *
 * @param {Object} props       Props.
 * @param {Object} props.house `{ title, rules: [ { icon, text } ] }`.
 * @return {JSX.Element|null} Box.
 */
export function HouseRulesBox( { house } ) {
	if ( ! house?.rules?.length ) {
		return null;
	}
	return (
		<section
			className="rounded-xl border border-warning bg-warning-soft p-4"
			aria-label={ house.title }
		>
			<h3 className="m-0 flex items-center gap-2 text-sm font-bold text-heading">
				<ClipboardList
					className="h-4 w-4 shrink-0 text-warning"
					aria-hidden="true"
				/>
				{ house.title }
			</h3>
			{ /* Divs, not ul/li: themes style lists inside post content. */ }
			<div role="list" className="mt-3 grid gap-2 sm:grid-cols-2">
				{ house.rules.map( ( rule, index ) => (
					<div
						role="listitem"
						// Rules have no id and never reorder here.
						key={ index }
						className="flex items-start gap-2.5 text-sm text-foreground"
					>
						<span
							className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border border-solid border-[color-mix(in_srgb,var(--warning)_35%,#ffffff)] bg-white text-warning"
							aria-hidden="true"
						>
							{ rule.icon ? (
								<LucideIcon
									name={ rule.icon }
									className="h-4 w-4"
								/>
							) : (
								<span className="h-1.5 w-1.5 rounded-full bg-current" />
							) }
						</span>
						<span className="pt-1 font-medium leading-snug">
							{ rule.text }
						</span>
					</div>
				) ) }
			</div>
		</section>
	);
}

/**
 * One checkbox row.
 *
 * @param {Object}      props          Props.
 * @param {boolean}     props.checked  Ticked.
 * @param {Function}    props.onChange Called with the new state.
 * @param {JSX.Element} props.children The label.
 * @return {JSX.Element} Row.
 */
function Check( { checked, onChange, children } ) {
	return (
		<label className="flex min-h-[32px] items-start gap-3 py-1 text-sm">
			<input
				type="checkbox"
				className="mt-0.5 h-5 w-5 shrink-0 accent-primary"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
			<span>{ children }</span>
		</label>
	);
}

/**
 * @param {Object}   props                 Props.
 * @param {Object}   props.rules           The website's booking rules.
 * @param {boolean}  props.consent         Privacy box ticked.
 * @param {Function} props.onConsent       Called with the privacy box's state.
 * @param {boolean}  props.rulesAccepted   House rules box ticked.
 * @param {Function} props.onRulesAccepted Called with the house rules box's state.
 * @param {Object}   props.errors          The server's `{ consent?, house_rules? }`.
 * @return {JSX.Element} Terms.
 */
export default function BookingTerms( {
	rules,
	consent,
	onConsent,
	rulesAccepted,
	onRulesAccepted,
	errors = {},
} ) {
	const house = rules.houseRules;
	return (
		<>
			<HouseRulesBox house={ house } />
			{ /* One tight group: the form's own spacing would split the boxes apart. */ }
			<div className="space-y-1 pt-1">
				{ rules.privacyConsent ? (
					<Check checked={ consent } onChange={ onConsent }>
						{ __(
							'I agree that the hotel keeps my details for this booking',
							'radius-hotel-booking'
						) }
						{ rules.privacyUrl ? (
							<>
								{ ' (' }
								<a
									href={ rules.privacyUrl }
									target="_blank"
									rel="noopener noreferrer"
									className="text-primary underline"
								>
									{ __(
										'privacy policy',
										'radius-hotel-booking'
									) }
								</a>
								{ ')' }
							</>
						) : null }
						.
					</Check>
				) : null }
				{ errors.consent ? (
					<p className="m-0 text-sm text-destructive" role="alert">
						{ errors.consent }
					</p>
				) : null }
				{ house?.consent ? (
					<Check
						checked={ rulesAccepted }
						onChange={ onRulesAccepted }
					>
						{ __(
							'I have read and accept the house rules.',
							'radius-hotel-booking'
						) }
					</Check>
				) : null }
				{ errors.house_rules ? (
					<p className="m-0 text-sm text-destructive" role="alert">
						{ errors.house_rules }
					</p>
				) : null }
			</div>
		</>
	);
}
