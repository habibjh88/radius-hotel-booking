/**
 * The room type a new room goes to, for the add dialogs opened outside a
 * room type (the All rooms screen).
 */
import { __ } from '@wordpress/i18n';

import { Label } from '@/components/ui/label';
import {
	Select,
	SelectContent,
	SelectItem,
	SelectTrigger,
	SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

/**
 * @param {Object}   props          Props.
 * @param {string}   props.id       Input id.
 * @param {Object[]} props.types    Room types (`{ id, name }`).
 * @param {string}   props.value    Selected id ('' = none).
 * @param {Function} props.onChange Called with the new id.
 * @param {string}   props.error    Field error.
 * @return {JSX.Element} Field.
 */
export default function RoomTypeField( { id, types, value, onChange, error } ) {
	return (
		<div className="space-y-1.5">
			<Label
				htmlFor={ id }
				className="text-sm font-semibold text-heading"
			>
				{ __( 'Room type', 'radius-hotel-booking' ) }
			</Label>
			<Select value={ value } onValueChange={ onChange }>
				<SelectTrigger
					id={ id }
					aria-invalid={ error ? true : undefined }
					className={ cn( error && '!border-destructive' ) }
				>
					<SelectValue
						placeholder={ __(
							'Choose a room type',
							'radius-hotel-booking'
						) }
					/>
				</SelectTrigger>
				<SelectContent>
					{ types.map( ( option ) => (
						<SelectItem
							key={ option.id }
							value={ String( option.id ) }
						>
							{ option.name }
						</SelectItem>
					) ) }
				</SelectContent>
			</Select>
			{ error ? (
				<p role="alert" className="m-0 text-xs text-destructive">
					{ error }
				</p>
			) : null }
		</div>
	);
}
