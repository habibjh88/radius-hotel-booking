/**
 * Form layout: `FormSection` groups related fields under a heading;
 * `Field` is one labelled control with help text and its error.
 *
 *   const form = useZodForm( schema );
 *   <FormSection title={ __( 'Guest', … ) }>
 *     <Field label={ __( 'Phone', … ) } name="phone" form={ form } required>
 *       <Input { ...form.register( 'phone' ) } />
 *     </Field>
 *   </FormSection>
 *
 * With `form` + `name`, the field shows react-hook-form's error for that name,
 * including server errors placed by applyServerErrors(). Without them, pass
 * `error` yourself. The control gets `id`, `aria-invalid` and
 * `aria-describedby` wired automatically.
 */
import { Children, cloneElement, isValidElement, useId } from 'react';
import { __ } from '@wordpress/i18n';

import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * A titled group of fields: heading + description on the left on wide
 * screens, fields on the right; stacked on phones.
 *
 * @param {Object}      props             Props.
 * @param {string}      props.title       Heading.
 * @param {string}      props.description One or two lines of context.
 * @param {string}      props.className   Extra classes.
 * @param {JSX.Element} props.children    Fields.
 * @return {JSX.Element} Section.
 */
export function FormSection( { title, description, className, children } ) {
	return (
		<section
			className={ cn(
				'grid gap-4 py-5 first:pt-0 last:pb-0 lg:grid-cols-3 lg:gap-8',
				className
			) }
		>
			{ title || description ? (
				<div className="min-w-0">
					{ title ? (
						<h3 className="m-0 p-0 text-sm font-semibold text-heading">
							{ title }
						</h3>
					) : null }
					{ description ? (
						<p className="m-0 mt-1 text-[13px] leading-5 text-muted-foreground">
							{ description }
						</p>
					) : null }
				</div>
			) : null }
			<div
				className={ cn(
					'min-w-0 space-y-4',
					title || description ? 'lg:col-span-2' : 'lg:col-span-3'
				) }
			>
				{ children }
			</div>
		</section>
	);
}

/**
 * Nested error lookup: `errors.guest.phone` for name "guest.phone".
 *
 * @param {Object} errors react-hook-form errors.
 * @param {string} name   Field name (dot path).
 * @return {Object|undefined} Error.
 */
const errorAt = ( errors, name ) =>
	String( name )
		.split( '.' )
		.reduce(
			( current, key ) => ( current ? current[ key ] : undefined ),
			errors
		);

/**
 * One field.
 *
 * @param {Object}      props             Props.
 * @param {string}      props.label       Label.
 * @param {string}      props.name        react-hook-form field name.
 * @param {Object}      props.form        The form (useZodForm / useForm return).
 * @param {string}      props.error       Error message (when not using `form`).
 * @param {string}      props.description Help text under the control.
 * @param {boolean}     props.required    Show the required marker.
 * @param {string}      props.className   Extra classes.
 * @param {JSX.Element} props.children    The control (one element).
 * @return {JSX.Element} Field.
 */
export function Field( {
	label,
	name,
	form,
	error,
	description,
	required = false,
	className,
	children,
} ) {
	const autoId = useId();
	const message =
		error ||
		( form && name
			? errorAt( form.formState.errors, name )?.message
			: '' ) ||
		'';
	const onlyChild = Children.only( children );
	const id = ( isValidElement( onlyChild ) && onlyChild.props.id ) || autoId;
	const helpId = `${ id }-help`;
	const errorId = `${ id }-error`;
	const describedBy = [
		description ? helpId : null,
		message ? errorId : null,
	]
		.filter( Boolean )
		.join( ' ' );

	const control = isValidElement( onlyChild )
		? cloneElement( onlyChild, {
				id,
				'aria-invalid': message ? true : undefined,
				'aria-describedby': describedBy || undefined,
				className: cn(
					onlyChild.props.className,
					message &&
						'!border-destructive focus-visible:!ring-destructive'
				),
		  } )
		: onlyChild;

	return (
		<div className={ cn( 'min-w-0 space-y-1.5', className ) }>
			{ label ? (
				<Label
					htmlFor={ id }
					className="text-sm font-semibold text-heading"
				>
					{ label }
					{ required ? (
						<span
							className="ml-0.5 text-destructive"
							aria-hidden="true"
						>
							*
						</span>
					) : null }
					{ required ? (
						<span className="sr-only">
							{ __( '(required)', 'radius-hotel-booking' ) }
						</span>
					) : null }
				</Label>
			) : null }
			{ control }
			{ description && ! message ? (
				<p id={ helpId } className="m-0 text-xs text-muted-foreground">
					{ description }
				</p>
			) : null }
			{ message ? (
				<p
					id={ errorId }
					role="alert"
					className="m-0 text-xs font-medium text-destructive"
				>
					{ message }
				</p>
			) : null }
		</div>
	);
}
