/**
 * Settings → Payments (17.12, 5.3): the methods the hotel accepts, in the
 * order guests see them, each with the instructions and account details
 * shown on the confirmation page and e-mail. The instructions may use merge
 * tags, replaced per booking. A method's key is made from its name on the
 * server the first time it is saved, and never changes (payments store it).
 */
import { __, sprintf } from '@wordpress/i18n';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';

import { Field } from '@/components/common/Form';
import SettingsSection from '@/components/common/SettingsSection';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';

const TAGS = [ '{amount}', '{reference}', '{deadline}', '{hotel_name}' ];

/**
 * @param {Object}   props          Props.
 * @param {Object}   props.value    Section values.
 * @param {Function} props.setField `setField( key )( value )`.
 * @param {Object}   props.errors   Key => server message.
 * @return {JSX.Element} Tab.
 */
export default function Payments( { value, setField, errors } ) {
	const methods = Array.isArray( value.methods ) ? value.methods : [];
	const save = setField( 'methods' );

	const change = ( index, fields ) =>
		save(
			methods.map( ( method, i ) =>
				i === index ? { ...method, ...fields } : method
			)
		);
	const move = ( index, by ) => {
		const next = [ ...methods ];
		const [ item ] = next.splice( index, 1 );
		next.splice( index + by, 0, item );
		save( next );
	};

	return (
		<SettingsSection
			title={ __( 'Payment methods', 'radius-hotel-booking' ) }
			description={ __(
				'How guests can pay you, in the order they see them. Guests get these instructions on the confirmation page and e-mail; staff pick the method when recording a payment.',
				'radius-hotel-booking'
			) }
		>
			<p className="m-0 text-xs text-muted-foreground">
				{ sprintf(
					/* translators: %s: the merge tags, e.g. "{amount}, {reference}". */
					__(
						'In the instructions, these tags are replaced for each booking: %s.',
						'radius-hotel-booking'
					),
					TAGS.join( ', ' )
				) }
			</p>
			{ errors.methods ? (
				<p role="alert" className="m-0 text-sm text-destructive">
					{ errors.methods }
				</p>
			) : null }

			<ol className="m-0 list-none space-y-3 p-0">
				{ methods.map( ( method, index ) => (
					<li
						// A new method has no key until it is saved.
						key={ method.key || `new-${ index }` }
						className="space-y-3 rounded-lg border border-border p-4"
					>
						<div className="flex flex-wrap items-center justify-between gap-2">
							<label className="flex items-center gap-2 text-sm font-semibold text-heading">
								<Switch
									checked={ !! method.enabled }
									onCheckedChange={ ( enabled ) =>
										change( index, { enabled } )
									}
									aria-label={ sprintf(
										/* translators: %s: payment method name. */
										__(
											'Offer %s to guests',
											'radius-hotel-booking'
										),
										method.label ||
											__(
												'this method',
												'radius-hotel-booking'
											)
									) }
								/>
								{ method.label ||
									__( 'New method', 'radius-hotel-booking' ) }
								{ ! method.enabled ? (
									<span className="text-xs font-normal text-muted-foreground">
										{ __( 'off', 'radius-hotel-booking' ) }
									</span>
								) : null }
							</label>
							<div className="flex items-center gap-1">
								<Button
									type="button"
									variant="ghost"
									size="icon"
									disabled={ 0 === index }
									onClick={ () => move( index, -1 ) }
									aria-label={ __(
										'Move up',
										'radius-hotel-booking'
									) }
								>
									<ArrowUp
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</Button>
								<Button
									type="button"
									variant="ghost"
									size="icon"
									disabled={ index === methods.length - 1 }
									onClick={ () => move( index, 1 ) }
									aria-label={ __(
										'Move down',
										'radius-hotel-booking'
									) }
								>
									<ArrowDown
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</Button>
								<Button
									type="button"
									variant="ghost"
									size="icon"
									className="text-destructive"
									onClick={ () =>
										save(
											methods.filter(
												( _, i ) => i !== index
											)
										)
									}
									aria-label={ __(
										'Remove this method',
										'radius-hotel-booking'
									) }
								>
									<Trash2
										className="h-4 w-4"
										aria-hidden="true"
									/>
								</Button>
							</div>
						</div>
						<Field
							label={ __( 'Name', 'radius-hotel-booking' ) }
							required
						>
							<Input
								value={ method.label ?? '' }
								maxLength={ 60 }
								onChange={ ( e ) =>
									change( index, { label: e.target.value } )
								}
							/>
						</Field>
						<Field
							label={ __(
								'Instructions for the guest',
								'radius-hotel-booking'
							) }
						>
							<Textarea
								rows={ 3 }
								className="!min-h-[96px]"
								maxLength={ 2000 }
								value={ method.instructions ?? '' }
								onChange={ ( e ) =>
									change( index, {
										instructions: e.target.value,
									} )
								}
							/>
						</Field>
						<Field
							label={ __(
								'Account details',
								'radius-hotel-booking'
							) }
							description={ __(
								'The number or account to pay into, shown under the instructions. Optional.',
								'radius-hotel-booking'
							) }
						>
							<Textarea
								rows={ 2 }
								className="!min-h-[64px]"
								maxLength={ 500 }
								value={ method.account ?? '' }
								onChange={ ( e ) =>
									change( index, { account: e.target.value } )
								}
							/>
						</Field>
					</li>
				) ) }
			</ol>

			<Button
				type="button"
				variant="outline"
				onClick={ () =>
					save( [
						...methods,
						{
							key: '',
							label: '',
							instructions: '',
							account: '',
							enabled: true,
						},
					] )
				}
			>
				<Plus className="h-4 w-4" aria-hidden="true" />
				{ __( 'Add a method', 'radius-hotel-booking' ) }
			</Button>
		</SettingsSection>
	);
}
