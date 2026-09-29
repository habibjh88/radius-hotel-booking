/**
 * Several images from the WordPress media library, stored as attachment ids
 * in display order, with one of them marked as the cover.
 *
 *   <GalleryField
 *       value={ ids } onChange={ setIds }
 *       cover={ coverId } onCoverChange={ setCoverId }
 *       images={ roomType.gallery }   // known previews: [ { id, thumb } ]
 *   />
 *
 * Opens the media modal (images only, several at once) to add photos; each
 * tile can be moved left or right, made the cover, or removed. The first
 * photo is the cover when none is chosen. Needs `wp_enqueue_media()` like
 * MediaField. Also on `window.rtbp.ui`.
 */
import { useEffect, useRef, useState } from 'react';
import { __, sprintf } from '@wordpress/i18n';
import { ChevronLeft, ChevronRight, ImagePlus, Star, X } from 'lucide-react';

import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

const wpMedia = () =>
	( typeof window !== 'undefined' && window.wp?.media ) || null;

/**
 * Thumbnail URL of an attachment's JSON.
 *
 * @param {Object} data `attachment.toJSON()`.
 * @return {string} URL.
 */
const thumbOf = ( data ) =>
	data.sizes?.medium?.url || data.sizes?.thumbnail?.url || data.url || '';

/**
 * @param {Object}   props               Props.
 * @param {number[]} props.value         Attachment ids, in order.
 * @param {Function} props.onChange      Called with the new ids.
 * @param {number}   props.cover         Cover id (0 = the first photo).
 * @param {Function} props.onCoverChange Called with the new cover id.
 * @param {Object[]} props.images        Known previews `{ id, thumb|url }`.
 * @param {number}   props.max           Most photos allowed.
 * @param {boolean}  props.disabled      Read-only.
 * @param {string}   props.id            Id of the add button (set by Field).
 * @param {string}   props.className     Extra classes (Field adds the error border).
 * @return {JSX.Element} Field.
 */
export default function GalleryField( {
	value = [],
	onChange,
	cover = 0,
	onCoverChange,
	images = [],
	max = 30,
	disabled = false,
	id,
	className,
	'aria-invalid': ariaInvalid,
	'aria-describedby': ariaDescribedBy,
} ) {
	const ids = ( value || [] ).map( Number ).filter( Boolean );
	const [ thumbs, setThumbs ] = useState( () =>
		Object.fromEntries(
			images.map( ( image ) => [ image.id, image.thumb || image.url ] )
		)
	);
	const frame = useRef( null );
	const media = wpMedia();
	const activeCover = ids.includes( Number( cover ) )
		? Number( cover )
		: ids[ 0 ];

	// Fetch previews the page did not bring (e.g. after a discard).
	useEffect( () => {
		if ( ! media ) {
			return undefined;
		}
		let alive = true;
		ids.filter( ( one ) => ! thumbs[ one ] ).forEach( ( one ) => {
			const attachment = media.attachment( one );
			attachment
				.fetch()
				.then( () => {
					if ( alive ) {
						setThumbs( ( prev ) => ( {
							...prev,
							[ one ]: thumbOf( attachment.toJSON() ),
						} ) );
					}
				} )
				.catch( () => {} );
		} );
		return () => {
			alive = false;
		};
	}, [ ids.join( ',' ), media ] );

	useEffect(
		() => () => {
			frame.current?.off( 'select' );
			frame.current?.remove?.();
			frame.current = null;
		},
		[]
	);

	// The select handler reads the latest ids through this ref.
	const latest = useRef( ids );
	latest.current = ids;

	const add = () => {
		if ( ! media ) {
			return;
		}
		if ( ! frame.current ) {
			frame.current = media( {
				title: __( 'Add photos', 'radius-hotel-booking' ),
				button: { text: __( 'Add photos', 'radius-hotel-booking' ) },
				library: { type: 'image' },
				multiple: 'add',
			} );
			frame.current.on( 'select', () => {
				const picked = frame.current
					.state()
					.get( 'selection' )
					.toJSON()
					.filter( ( one ) =>
						String( one.mime ).startsWith( 'image/' )
					);
				setThumbs( ( prev ) => ( {
					...prev,
					...Object.fromEntries(
						picked.map( ( one ) => [ one.id, thumbOf( one ) ] )
					),
				} ) );
				const next = [ ...latest.current ];
				picked.forEach( ( one ) => {
					if ( ! next.includes( one.id ) ) {
						next.push( one.id );
					}
				} );
				onChange( next.slice( 0, max ) );
			} );
		}
		frame.current.open();
	};

	const move = ( index, step ) => {
		const next = [ ...ids ];
		const [ item ] = next.splice( index, 1 );
		next.splice( index + step, 0, item );
		onChange( next );
	};

	const remove = ( one ) => {
		onChange( ids.filter( ( other ) => other !== one ) );
		if ( one === Number( cover ) ) {
			onCoverChange?.( 0 );
		}
	};

	return (
		<div
			className={ cn(
				'min-w-0 space-y-3 rounded-lg border border-border bg-card p-3',
				className
			) }
		>
			{ ids.length ? (
				<ul className="m-0 grid list-none grid-cols-2 gap-3 p-0 sm:grid-cols-3 lg:grid-cols-4">
					{ ids.map( ( one, index ) => {
						const isCover = one === activeCover;
						return (
							<li
								key={ one }
								className={ cn(
									'group relative m-0 overflow-hidden rounded-md border bg-muted',
									isCover
										? 'border-primary ring-2 ring-primary'
										: 'border-border'
								) }
							>
								{ thumbs[ one ] ? (
									<img
										src={ thumbs[ one ] }
										alt=""
										className="block aspect-[4/3] w-full object-cover"
									/>
								) : (
									<span className="block aspect-[4/3] w-full" />
								) }
								{ isCover ? (
									<span className="absolute left-1.5 top-1.5 rounded bg-primary px-1.5 py-0.5 text-[11px] font-semibold text-primary-foreground">
										{ __(
											'Cover',
											'radius-hotel-booking'
										) }
									</span>
								) : null }
								{ ! disabled ? (
									<div className="absolute inset-x-0 bottom-0 flex items-center justify-between gap-0.5 bg-black/55 p-0.5 sm:gap-1 sm:p-1">
										<div className="flex gap-0.5 sm:gap-1">
											<TileButton
												label={ __(
													'Move left',
													'radius-hotel-booking'
												) }
												onClick={ () =>
													move( index, -1 )
												}
												disabled={ index === 0 }
											>
												<ChevronLeft className="h-4 w-4" />
											</TileButton>
											<TileButton
												label={ __(
													'Move right',
													'radius-hotel-booking'
												) }
												onClick={ () =>
													move( index, 1 )
												}
												disabled={
													index === ids.length - 1
												}
											>
												<ChevronRight className="h-4 w-4" />
											</TileButton>
										</div>
										<div className="flex gap-0.5 sm:gap-1">
											{ ! isCover && onCoverChange ? (
												<TileButton
													label={ __(
														'Use as cover',
														'radius-hotel-booking'
													) }
													onClick={ () =>
														onCoverChange( one )
													}
												>
													<Star className="h-4 w-4" />
												</TileButton>
											) : null }
											<TileButton
												label={ sprintf(
													/* translators: %d: photo position. */
													__(
														'Remove photo %d',
														'radius-hotel-booking'
													),
													index + 1
												) }
												onClick={ () => remove( one ) }
											>
												<X className="h-4 w-4" />
											</TileButton>
										</div>
									</div>
								) : null }
							</li>
						);
					} ) }
				</ul>
			) : (
				<p className="m-0 text-sm text-muted-foreground">
					{ __( 'No photos yet.', 'radius-hotel-booking' ) }
				</p>
			) }

			{ media ? (
				! disabled && ids.length < max ? (
					<Button
						type="button"
						variant="outline"
						size="sm"
						id={ id }
						aria-invalid={ ariaInvalid }
						aria-describedby={ ariaDescribedBy }
						onClick={ add }
					>
						<ImagePlus className="h-4 w-4" aria-hidden="true" />
						{ __( 'Add photos', 'radius-hotel-booking' ) }
					</Button>
				) : null
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

/**
 * A small icon button on a photo tile.
 *
 * @param {Object}      props          Props.
 * @param {string}      props.label    Accessible label (also the tooltip).
 * @param {Function}    props.onClick  Click handler.
 * @param {boolean}     props.disabled Disabled.
 * @param {JSX.Element} props.children Icon.
 * @return {JSX.Element} Button.
 */
function TileButton( { label, onClick, disabled = false, children } ) {
	return (
		<button
			type="button"
			onClick={ onClick }
			disabled={ disabled }
			aria-label={ label }
			title={ label }
			className="flex h-6 w-6 items-center justify-center rounded text-white hover:bg-white/20 disabled:opacity-30 sm:h-7 sm:w-7"
		>
			{ children }
		</button>
	);
}
