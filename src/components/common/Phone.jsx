/**
 * The phone field every form uses: PhoneInput, loaded on first use (the
 * phone library stays out of the main bundles). A plain tel input shows
 * while it loads.
 *
 *   <PhoneField value={ phone } onChange={ setPhone } id="guest-phone" />
 */
import { Suspense, lazy } from 'react';

import { Input } from '@/components/ui/input';

const PhoneInput = lazy( () => import( './PhoneInput' ) );

/**
 * @param {Object}   props          Props (see PhoneInput).
 * @param {string}   props.value    E.164.
 * @param {Function} props.onChange Called with the new value.
 * @return {JSX.Element} Field.
 */
export default function PhoneField( { value, onChange, ...props } ) {
	return (
		<Suspense
			fallback={
				<Input
					type="tel"
					inputMode="tel"
					value={ value || '' }
					onChange={ ( event ) => onChange?.( event.target.value ) }
					id={ props.id }
					placeholder={ props.placeholder }
					disabled={ props.disabled }
					onBlur={ props.onBlur }
				/>
			}
		>
			<PhoneInput value={ value } onChange={ onChange } { ...props } />
		</Suspense>
	);
}
