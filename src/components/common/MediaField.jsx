/**
 * A file from the WordPress media library, stored as its attachment id
 * (0 = none). Opens the WordPress media modal to choose or upload; shows an
 * image thumbnail, an audio player, or the file name.
 *
 *   <Field label={ __( 'Logo', … ) } error={ errors.logo }>
 *       <MediaField value={ value.logo } onChange={ setField( 'logo' ) } accept={ [ 'image/' ] } />
 *   </Field>
 *
 * `accept` takes the same mime prefixes as a `media` setting's `mime`
 * option (Settings\SettingsSchema), so the modal only lists those files and a
 * wrong pick is refused here as well as on the server. Needs
 * `wp_enqueue_media()`, which LoadAssets calls for the staff app; without it
 * (or without the `upload_files` capability) the field says so instead of
 * opening.
 *
 * Also on `window.rtbp.ui`.
 */
import { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { File as FileIcon, ImageOff } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

/**
 * The WordPress media API, when loaded.
 *
 * @return {Object|null} `wp.media`.
 */
const wpMedia = () =>
	( typeof window !== 'undefined' && window.wp?.media ) || null;

/**
 * Whether a mime type matches one of the accepted prefixes.
 *
 * @param {string}   mime   Mime type, e.g. `audio/mpeg`.
 * @param {string[]} accept Prefixes, e.g. `[ 'audio/' ]`; empty = any.
 * @return {boolean} Accepted.
 */
const accepts = ( mime, accept ) =>
	! accept?.length ||
	accept.some( ( prefix ) => String( mime ).startsWith( prefix ) );

/**
 * The attachment's details for the preview.
 *
 * @param {Object} data `attachment.toJSON()`.
 * @return {Object} Details.
 */
const detailsOf = ( data ) => ( {
	id: data.id,
	url: data.url,
	mime: data.mime || '',
	name: data.filename || data.title || '',
	thumb: data.sizes?.thumbnail?.url || data.sizes?.medium?.url || data.url,
} );

/**
 * @param {Object}   props                    Props.
 * @param {number}   props.value              Attachment id, 0 for none.
 * @param {Function} props.onChange           Called with the new id (0 when removed).
 * @param {string[]} props.accept             Mime prefixes, e.g. `[ 'image/' ]`.
 * @param {string}   props.title              Media modal title.
 * @param {boolean}  props.disabled           Disabled.
 * @param {string}   props.id                 Id of the choose button (set by Field).
 * @param {string}   props.className          Extra classes (Field adds the error border).
 * @param {boolean}  props.aria-invalid       Set by Field.
 * @param {string}   props.aria-describedby   Set by Field.
 * @return {JSX.Element} Field.
 */
export default function MediaField( {
	value,
	onChange,
	accept = [],
	title,
	disabled = false,
	id,
	className,
	'aria-invalid': ariaInvalid,
	'aria-describedby': ariaDescribedBy,
} ) {
	const attachmentId = Number( value ) || 0;
	const [ file, setFile ] = useState( null );
	const [ missing, setMissing ] = useState( false );
	const [ wrongType, setWrongType ] = useState( '' );
	const frame = useRef( null );
	const media = wpMedia();

	// Load the preview of the stored id (after a reload, a reset, a discard).
	// `file` is left out of the deps on purpose: a pick already set it.
	useEffect( () => {
		setMissing( false );
		if ( ! attachmentId ) {
			setFile( null );
			return undefined;
		}
		if ( file?.id === attachmentId || ! media ) {
			return undefined;
		}
		let alive = true;
		const attachment = media.attachment( attachmentId );
		attachment
			.fetch()
			.then( () => {
				if ( alive ) {
					setFile( detailsOf( attachment.toJSON() ) );
				}
			} )
			.catch( () => {
				if ( alive ) {
					setFile( null );
					setMissing( true );
				}
			} );
		return () => {
			alive = false;
		};
	}, [ attachmentId, media ] );

	const open = () => {
		if ( ! media ) {
			return;
		}
		if ( ! frame.current ) {
			const types = accept
				.map( ( prefix ) => prefix.replace( /\/$/, '' ) )
				.filter( Boolean );
			frame.current = media( {
				title: title || __( 'Choose a file', 'radius-hotel-booking' ),
				button: { text: __( 'Use this file', 'radius-hotel-booking' ) },
				library: types.length ? { type: types } : {},
				multiple: false,
			} );
			frame.current.on( 'select', () => {
				const picked = frame.current
					.state()
					.get( 'selection' )
					.first()
					?.toJSON();
				if ( ! picked ) {
					return;
				}
				if ( ! accepts( picked.mime, accept ) ) {
					setWrongType(
						sprintf(
							/* translators: %s: file name. */
							__(
								'%s is not an accepted file type.',
								'radius-hotel-booking'
							),
							picked.filename || picked.title
						)
					);
					return;
				}
				setWrongType( '' );
				setMissing( false );
				setFile( detailsOf( picked ) );
				onChange( picked.id );
			} );
		}
		frame.current.open();
	};

	// The modal belongs to this field; drop it with the field.
	useEffect(
		() => () => {
			frame.current?.off( 'select' );
			frame.current?.remove?.();
			frame.current = null;
		},
		[]
	);

	const remove = () => {
		setFile( null );
		setMissing( false );
		setWrongType( '' );
		onChange( 0 );
	};

	const isImage = file?.mime.startsWith( 'image/' );
	const isAudio = file?.mime.startsWith( 'audio/' );

	return (
		<div
			className={ cn(
				'min-w-0 space-y-2 rounded-lg border border-border bg-card p-3',
				className
			) }
		>
			{ file ? (
				<div className="flex min-w-0 items-center gap-3">
					{ isImage ? (
						<img
							src={ file.thumb }
							alt=""
							className="h-14 w-14 shrink-0 rounded-md border border-border object-cover"
						/>
					) : (
						<span className="flex h-14 w-14 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
							<FileIcon className="h-5 w-5" aria-hidden="true" />
						</span>
					) }
					<div className="min-w-0 flex-1 space-y-1.5">
						<p
							className="m-0 truncate text-sm font-medium text-heading"
							title={ file.name }
						>
							{ file.name }
						</p>
						{ isAudio ? (
							<audio
								controls
								preload="none"
								src={ file.url }
								className="h-8 w-full max-w-xs"
							/>
						) : null }
					</div>
				</div>
			) : null }

			{ missing ? (
				<p className="m-0 flex items-center gap-2 text-sm text-warning">
					<ImageOff className="h-4 w-4 shrink-0" aria-hidden="true" />
					{ __(
						'The file is no longer in the media library.',
						'radius-hotel-booking'
					) }
				</p>
			) : null }

			{ ! file && ! missing && attachmentId === 0 ? (
				<p className="m-0 text-sm text-muted-foreground">
					{ __( 'No file selected.', 'radius-hotel-booking' ) }
				</p>
			) : null }

			{ wrongType ? (
				<p role="alert" className="m-0 text-xs text-destructive">
					{ wrongType }
				</p>
			) : null }

			{ media ? (
				<div className="flex flex-wrap gap-2">
					<Button
						type="button"
						variant="outline"
						size="sm"
						id={ id }
						aria-invalid={ ariaInvalid }
						aria-describedby={ ariaDescribedBy }
						onClick={ open }
						disabled={ disabled }
					>
						{ attachmentId
							? __( 'Replace', 'radius-hotel-booking' )
							: __( 'Choose file', 'radius-hotel-booking' ) }
					</Button>
					{ attachmentId ? (
						<Button
							type="button"
							variant="ghost"
							size="sm"
							onClick={ remove }
							disabled={ disabled }
						>
							{ __( 'Remove', 'radius-hotel-booking' ) }
						</Button>
					) : null }
				</div>
			) : (
				<p className="m-0 text-xs text-muted-foreground">
					{ __(
						'The media library is not available for your account.',
						'radius-hotel-booking'
					) }
				</p>
			) }
		</div>
	);
}
