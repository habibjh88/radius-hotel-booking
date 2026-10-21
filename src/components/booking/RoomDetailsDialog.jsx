/**
 * A room type's details in a popup (`[rtbp_booking room_details="popup"]`):
 * a photo slider on the left (previous / next, swipe, arrow keys; a click
 * opens the full lightbox of src/gallery), and on the right the name, beds,
 * guests, size and the amenities by section with their icons — what the room
 * type page shows, without leaving the booking form.
 *
 * Light on purpose (no Radix dialog in the website's bundle), in a portal on
 * `<body>` above the booking popup and below the lightbox.
 */
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { __, sprintf } from '@wordpress/i18n';
import {
	BedDouble,
	ChevronLeft,
	ChevronRight,
	Maximize2,
	Users,
	X,
} from 'lucide-react';

import LucideIcon from '@/components/icons/LucideIcon';
import { cn } from '@/lib/utils';

/**
 * The photos, one at a time.
 *
 * @param {Object}   props        Props.
 * @param {Object[]} props.photos Slides (`{ src, srcSet[], width, height, alt }`).
 * @param {string}   props.image  The type's photo when there are no slides.
 * @param {string}   props.title  Room type name.
 * @return {JSX.Element} Slider.
 */
function Slider( { photos, image, title } ) {
	const [ index, setIndex ] = useState( 0 );
	const touch = useRef( null );
	const count = photos.length;
	const go = ( step ) =>
		setIndex( ( now ) => ( now + step + count ) % count );

	// Arrow keys move the slider (not while the lightbox is open).
	useEffect( () => {
		if ( count < 2 ) {
			return undefined;
		}
		const onKey = ( event ) => {
			if ( document.querySelector( '.rtbp-lightbox' ) ) {
				return;
			}
			if ( 'ArrowLeft' === event.key ) {
				go( -1 );
			} else if ( 'ArrowRight' === event.key ) {
				go( 1 );
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => document.removeEventListener( 'keydown', onKey );
	}, [ count ] );

	if ( ! count ) {
		return (
			<div className="relative h-full min-h-[14rem] bg-muted">
				{ image ? (
					<img
						src={ image }
						alt=""
						className="absolute inset-0 m-0 h-full w-full object-cover"
					/>
				) : (
					<BedDouble
						className="absolute inset-0 m-auto h-12 w-12 text-muted-foreground"
						aria-hidden="true"
					/>
				) }
			</div>
		);
	}

	const slide = photos[ index ];
	const srcSet = [ ...( slide.srcSet || [] ), slide ]
		.map( ( size ) => `${ size.src } ${ size.width }w` )
		.join( ', ' );
	const arrow =
		'absolute top-1/2 z-[1] flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full border-0 bg-white/90 p-0 text-heading shadow-md transition hover:bg-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

	return (
		<div
			className="relative h-full min-h-[16rem] bg-muted"
			// A click opens the full lightbox (src/gallery) at this photo.
			data-rtbp-gallery={ JSON.stringify( photos ) }
			data-rtbp-gallery-title={ title }
			onTouchStart={ ( event ) => {
				touch.current = event.touches[ 0 ]?.clientX ?? null;
			} }
			onTouchEnd={ ( event ) => {
				const start = touch.current;
				const end = event.changedTouches[ 0 ]?.clientX;
				touch.current = null;
				if (
					count > 1 &&
					null !== start &&
					Math.abs( end - start ) > 40
				) {
					go( end < start ? 1 : -1 );
				}
			} }
		>
			<button
				type="button"
				className="absolute inset-0 m-0 block h-full w-full cursor-zoom-in border-0 bg-transparent p-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-ring"
				data-rtbp-gallery-index={ index }
				aria-label={ sprintf(
					/* translators: 1: photo number, 2: number of photos. */
					__( 'Open photo %1$d of %2$d', 'radius-hotel-booking' ),
					index + 1,
					count
				) }
			>
				<img
					key={ slide.src }
					src={ slide.src }
					srcSet={ srcSet }
					sizes="(min-width: 768px) 420px, 100vw"
					alt={ slide.alt || title }
					className="m-0 h-full w-full object-cover"
				/>
			</button>
			{ count > 1 ? (
				<>
					<button
						type="button"
						className={ cn( arrow, 'left-3' ) }
						onClick={ () => go( -1 ) }
						aria-label={ __(
							'Previous photo',
							'radius-hotel-booking'
						) }
					>
						<ChevronLeft className="h-5 w-5" aria-hidden="true" />
					</button>
					<button
						type="button"
						className={ cn( arrow, 'right-3' ) }
						onClick={ () => go( 1 ) }
						aria-label={ __(
							'Next photo',
							'radius-hotel-booking'
						) }
					>
						<ChevronRight className="h-5 w-5" aria-hidden="true" />
					</button>
					<span
						className="pointer-events-none absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/60 px-2.5 py-0.5 text-xs font-semibold text-white"
						aria-live="polite"
					>
						{ sprintf(
							/* translators: 1: photo number, 2: number of photos. */
							__( '%1$d / %2$d', 'radius-hotel-booking' ),
							index + 1,
							count
						) }
					</span>
				</>
			) : null }
		</div>
	);
}

/**
 * @param {Object}   props         Props.
 * @param {Object}   props.type    Room type from the search (`name`, `details`).
 * @param {string}   props.guests  "Up to 2 adults · 1 child".
 * @param {Function} props.onClose Close.
 * @return {JSX.Element} Popup.
 */
export default function RoomDetailsDialog( { type, guests, onClose } ) {
	const details = type.details || {};
	const photos = Array.isArray( details.gallery ) ? details.gallery : [];
	const groups = Array.isArray( details.amenity_groups )
		? details.amenity_groups
		: [];
	const closeRef = useRef( null );

	// Escape closes (the lightbox answers it first), the page stops
	// scrolling, focus moves in and goes back on close.
	useEffect( () => {
		const back = document.activeElement;
		const root = document.documentElement;
		const overflow = root.style.overflow;
		root.style.overflow = 'hidden';
		closeRef.current?.focus();
		const onKey = ( event ) => {
			if (
				'Escape' === event.key &&
				! document.querySelector( '.rtbp-lightbox' )
			) {
				onClose();
			}
		};
		document.addEventListener( 'keydown', onKey );
		return () => {
			root.style.overflow = overflow;
			document.removeEventListener( 'keydown', onKey );
			back?.focus?.();
		};
	}, [ onClose ] );

	const facts = [
		details.bed_info ? { icon: BedDouble, text: details.bed_info } : null,
		guests ? { icon: Users, text: guests } : null,
		details.size_m2
			? {
					icon: Maximize2,
					text: sprintf(
						/* translators: %s: room size in square metres. */
						__( '%s m²', 'radius-hotel-booking' ),
						details.size_m2
					),
			  }
			: null,
	].filter( Boolean );

	return createPortal(
		// Utilities are scoped *under* .rtbp-root, so the overlay is its child.
		<div className="rtbp-root" data-rtbp-nested-dialog>
			<div
				className="fixed inset-0 z-[100020] flex items-stretch justify-center bg-black/60 sm:items-center sm:p-6"
				onMouseDown={ ( event ) => {
					if ( event.target === event.currentTarget ) {
						onClose();
					}
				} }
			>
				<div
					role="dialog"
					aria-modal="true"
					aria-labelledby={ `rtbp-room-${ type.id }` }
					className="flex h-full w-full max-w-[1000px] flex-col overflow-clip bg-card shadow-2xl sm:h-auto sm:max-h-[min(40rem,calc(100vh-3rem))] sm:rounded-2xl md:flex-row"
				>
					<div className="aspect-[4/3] shrink-0 md:aspect-auto md:w-[42%]">
						<Slider
							photos={ photos }
							image={ details.image }
							title={ type.name }
						/>
					</div>
					<div className="flex min-h-0 min-w-0 flex-1 flex-col">
						<div className="flex shrink-0 items-start justify-between gap-3 border-b border-border px-5 py-4">
							<h2
								id={ `rtbp-room-${ type.id }` }
								className="m-0 text-xl font-bold text-heading sm:text-2xl"
							>
								{ type.name }
							</h2>
							<button
								ref={ closeRef }
								type="button"
								className="inline-flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-lg border-0 bg-muted p-0 text-heading transition hover:bg-heading hover:text-white focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
								aria-label={ __(
									'Close',
									'radius-hotel-booking'
								) }
								onClick={ onClose }
							>
								<X className="h-5 w-5" aria-hidden="true" />
							</button>
						</div>
						<div className="min-h-0 flex-1 space-y-5 overflow-y-auto px-5 py-4">
							{ details.short_description ? (
								<p className="m-0 text-sm text-muted-foreground">
									{ details.short_description }
								</p>
							) : null }
							{ facts.length ? (
								<ul className="m-0 flex list-none flex-wrap gap-x-6 gap-y-2 p-0 text-sm text-foreground">
									{ facts.map( ( { icon: Icon, text } ) => (
										<li
											key={ text }
											className="flex items-center gap-2"
										>
											<Icon
												className="h-5 w-5 text-muted-foreground"
												aria-hidden="true"
											/>
											{ text }
										</li>
									) ) }
								</ul>
							) : null }
							{ groups.map( ( group ) => (
								<section
									key={ group.name }
									className="space-y-2.5"
								>
									<h3 className="m-0 text-base font-bold text-heading">
										{ groups.length > 1
											? group.name
											: __(
													'Amenities',
													'radius-hotel-booking'
											  ) }
									</h3>
									<ul className="m-0 grid list-none gap-x-6 gap-y-2.5 p-0 sm:grid-cols-2">
										{ group.items.map( ( item ) => (
											<li
												key={ item.name }
												className="flex items-center gap-3 text-sm text-foreground"
											>
												<LucideIcon
													name={ item.icon }
													className="h-5 w-5 text-muted-foreground"
												/>
												{ item.name }
											</li>
										) ) }
									</ul>
								</section>
							) ) }
							{ details.url ? (
								<a
									href={ details.url }
									target="_blank"
									rel="noopener noreferrer"
									className="inline-block text-sm font-semibold text-primary no-underline hover:underline"
								>
									{ __(
										'See the full room page',
										'radius-hotel-booking'
									) }
									<span className="sr-only">
										{ __(
											'(opens in a new tab)',
											'radius-hotel-booking'
										) }
									</span>
								</a>
							) : null }
						</div>
					</div>
				</div>
			</div>
		</div>,
		document.body
	);
}
