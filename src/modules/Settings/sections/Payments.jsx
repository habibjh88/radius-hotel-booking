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
import IconField from '@/components/icons/IconField';
import SegmentedControl from '@/components/common/SegmentedControl';
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
			<div className="space-y-1.5">
				<p className="m-0 flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground">
					{ __(
						'In the instructions, these tags are replaced for each booking:',
						'radius-hotel-booking'
					) }
					{ TAGS.map( ( tag ) => (
						<code
							key={ tag }
							className="rounded-md bg-primary-soft px-1.5 py-0.5 font-mono text-xs font-semibold text-primary"
						>
							{ tag }
						</code>
					) ) }
				</p>
				<p className="m-0 text-xs text-muted-foreground">
					{ __(
						'The icon beside each name shows on the guest page; left empty, one is picked from the name.',
						'radius-hotel-booking'
					) }
				</p>
			</div>
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
						<div className="flex items-end gap-2">
							<IconField
								value={ method.icon ?? '' }
								onChange={ ( icon ) =>
									change( index, { icon } )
								}
								compact
								label={ method.label }
							/>
							<Field
								label={ __( 'Name', 'radius-hotel-booking' ) }
								className="flex-1"
								required
							>
								<Input
									value={ method.label ?? '' }
									maxLength={ 60 }
									onChange={ ( e ) =>
										change( index, {
											label: e.target.value,
										} )
									}
								/>
							</Field>
							<Field
								label={ __(
									'Short name',
									'radius-hotel-booking'
								) }
								className="w-32 shrink-0"
							>
								<Input
									value={ method.short ?? '' }
									maxLength={ 12 }
									placeholder={ __(
										'e.g. OM',
										'radius-hotel-booking'
									) }
									onChange={ ( e ) =>
										change( index, {
											short: e.target.value,
										} )
									}
								/>
							</Field>
						</div>
						<p className="m-0 -mt-2 text-xs text-muted-foreground">
							{ __(
								'The short name is used on the dashboard, where space is tight. Guests always see the full name.',
								'radius-hotel-booking'
							) }
						</p>
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
						{ /* The transaction number staff type when they record a payment (R3-T3). */ }
						<div className="grid gap-4 sm:grid-cols-2">
							<Field
								label={ __(
									'Payment reference',
									'radius-hotel-booking'
								) }
								description={ __(
									'Required: a payment by this method cannot be saved without its reference, so it can be checked later.',
									'radius-hotel-booking'
								) }
							>
								<SegmentedControl
									label={ __(
										'Payment reference',
										'radius-hotel-booking'
									) }
									value={ method.reference || 'optional' }
									onChange={ ( reference ) =>
										change( index, { reference } )
									}
									options={ [
										{
											value: 'none',
											label: __(
												'Not asked',
												'radius-hotel-booking'
											),
										},
										{
											value: 'optional',
											label: __(
												'Optional',
												'radius-hotel-booking'
											),
										},
										{
											value: 'required',
											label: __(
												'Required',
												'radius-hotel-booking'
											),
										},
									] }
								/>
							</Field>
							{ 'none' !== ( method.reference || 'optional' ) ? (
								<Field
									label={ __(
										'Name of the reference',
										'radius-hotel-booking'
									) }
									description={ __(
										'What staff are asked for, e.g. Transaction ID.',
										'radius-hotel-booking'
									) }
								>
									<Input
										maxLength={ 40 }
										placeholder={ __(
											'Reference',
											'radius-hotel-booking'
										) }
										value={ method.reference_label ?? '' }
										onChange={ ( e ) =>
											change( index, {
												reference_label: e.target.value,
											} )
										}
									/>
								</Field>
							) : null }
						</div>
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
							short: '',
							icon: '',
							instructions: '',
							account: '',
							reference: 'optional',
							reference_label: '',
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
