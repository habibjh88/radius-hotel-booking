<?php
/**
 * A room type's page (M04, 4.5): `/rooms/{slug}`, inside the theme.
 *
 * Override from a theme at `radius-hotel-booking/booking/room-type.php`. The
 * data comes from `Frontend\RoomTypePage::current()`:
 * `{ name, short_description, description, images[] (attachment ids),
 *   amenities[], amenity_library{ sections[], amenities{ lower-case name: { icon, section } } }, bed_info, size_m2, max_adults, max_children,
 *   rates[ { name, when, from } ], book_url }`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Frontend\RoomTypePage;
use RadiusTheme\RadiusHotelBooking\Support\Icons;

defined( 'ABSPATH' ) || exit;

$rtbp_type = RoomTypePage::current();

get_header();
?>
<main id="primary" class="site-main">
	<div class="rtbp-root rtbp-room-type mx-auto max-w-5xl space-y-8 px-4 py-8 text-foreground">
		<header class="space-y-2">
			<h1 class="text-3xl font-bold text-heading"><?php echo esc_html( $rtbp_type['name'] ); ?></h1>
			<?php if ( '' !== $rtbp_type['short_description'] ) : ?>
				<p class="text-lg text-muted-foreground"><?php echo esc_html( $rtbp_type['short_description'] ); ?></p>
			<?php endif; ?>
		</header>

		<?php
		if ( $rtbp_type['images'] ) :
			// Every photo for the lightbox (opened by assets/gallery: full size, srcset, thumbnail).
			$rtbp_slides = array();
			foreach ( $rtbp_type['images'] as $rtbp_image ) {
				$rtbp_full = wp_get_attachment_image_src( $rtbp_image, 'full' );
				if ( ! $rtbp_full ) {
					continue;
				}
				$rtbp_thumb   = wp_get_attachment_image_src( $rtbp_image, 'medium' );
				$rtbp_srcset  = array();
				foreach ( array( 'medium_large', 'large', '1536x1536', '2048x2048' ) as $rtbp_size ) {
					$rtbp_src = wp_get_attachment_image_src( $rtbp_image, $rtbp_size );
					if ( $rtbp_src && $rtbp_src[1] < $rtbp_full[1] ) {
						$rtbp_srcset[ $rtbp_src[1] ] = array(
							'src'    => $rtbp_src[0],
							'width'  => (int) $rtbp_src[1],
							'height' => (int) $rtbp_src[2],
						);
					}
				}
				$rtbp_slides[] = array(
					'src'    => $rtbp_full[0],
					'width'  => (int) $rtbp_full[1],
					'height' => (int) $rtbp_full[2],
					'alt'    => (string) get_post_meta( $rtbp_image, '_wp_attachment_image_alt', true ),
					'thumb'  => $rtbp_thumb ? $rtbp_thumb[0] : $rtbp_full[0],
					'srcSet' => array_values( $rtbp_srcset ),
				);
			}
			$rtbp_count  = count( $rtbp_slides );
			$rtbp_shown  = array_slice( $rtbp_type['images'], 0, 4 );
			$rtbp_last   = count( $rtbp_shown ) - 1;
			$rtbp_more   = $rtbp_count - count( $rtbp_shown );
			// The row under the main photo: as many columns as photos in it (literal classes, for Tailwind).
			$rtbp_small  = array(
				1 => array( 'grid-cols-1', 'aspect-[32/9]' ),
				2 => array( 'grid-cols-2', 'aspect-[21/9]' ),
				3 => array( 'grid-cols-3', 'aspect-[16/9]' ),
			)[ max( 1, count( $rtbp_shown ) - 1 ) ];
			$rtbp_label  = sprintf(
				/* translators: %d: number of photos. */
				_n( 'View %d photo', 'View all %d photos', $rtbp_count, 'radius-hotel-booking' ),
				$rtbp_count
			);
			?>
			<div class="rtbp-gallery space-y-2 sm:space-y-3" data-rtbp-gallery="<?php echo esc_attr( (string) wp_json_encode( $rtbp_slides ) ); ?>" data-rtbp-gallery-title="<?php echo esc_attr( $rtbp_type['name'] ); ?>">
				<?php foreach ( $rtbp_shown as $rtbp_index => $rtbp_image ) : ?>
					<?php if ( 1 === $rtbp_index ) : ?>
						<div class="grid <?php echo esc_attr( $rtbp_small[0] ); ?> gap-2 sm:gap-3">
					<?php endif; ?>
					<?php $rtbp_full = wp_get_attachment_image_url( $rtbp_image, 'full' ); ?>
					<div class="group relative overflow-hidden rounded-2xl bg-muted <?php echo esc_attr( 0 === $rtbp_index ? 'aspect-[16/9] sm:aspect-[21/9]' : $rtbp_small[1] ); ?>">
						<a href="<?php echo esc_url( (string) $rtbp_full ); ?>" class="block h-full w-full" data-rtbp-gallery-index="<?php echo (int) $rtbp_index; ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: 1: photo number, 2: number of photos. */ __( 'Open photo %1$d of %2$d', 'radius-hotel-booking' ), $rtbp_index + 1, $rtbp_count ) ); ?>">
							<?php
							echo wp_get_attachment_image(
								$rtbp_image,
								0 === $rtbp_index ? 'large' : 'medium_large',
								false,
								array(
									'class'   => 'm-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.03]',
									'loading' => 0 === $rtbp_index ? 'eager' : 'lazy',
									'sizes'   => 0 === $rtbp_index ? '(min-width: 1024px) 1024px, 100vw' : '(min-width: 1024px) 340px, 33vw',
								)
							);
							?>
						</a>
						<?php if ( $rtbp_last === $rtbp_index ) : ?>
							<?php if ( $rtbp_more > 0 ) : ?>
								<span class="pointer-events-none absolute inset-0 flex items-center justify-center bg-black/45 text-2xl font-bold text-white sm:text-3xl" aria-hidden="true">
									<?php
									/* translators: %d: photos not shown. */
									echo esc_html( sprintf( __( '+%d', 'radius-hotel-booking' ), $rtbp_more ) );
									?>
								</span>
							<?php endif; ?>
							<button type="button" class="rtbp-gallery-button absolute bottom-2 right-2 inline-flex items-center gap-1.5 rounded-full border-0 bg-white/95 px-3 py-1.5 text-xs font-semibold normal-case tracking-normal text-heading shadow-md backdrop-blur transition hover:bg-white hover:text-heading focus:bg-white focus:text-heading focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring sm:bottom-3 sm:right-3 sm:text-sm" data-rtbp-gallery-index="0">
								<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="h-4 w-4 shrink-0"><path d="M18 22H4a2 2 0 0 1-2-2V6"/><path d="m22 13-1.296-1.296a2.41 2.41 0 0 0-3.408 0L11 18"/><circle cx="12" cy="8" r="2"/><rect width="16" height="16" x="6" y="2" rx="2"/></svg>
								<span class="hidden sm:inline"><?php echo esc_html( $rtbp_label ); ?></span>
								<span class="sm:hidden"><?php echo esc_html( (string) $rtbp_count ); ?></span>
							</button>
						<?php endif; ?>
					</div>
					<?php if ( $rtbp_index > 0 && $rtbp_last === $rtbp_index ) : ?>
						</div>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="grid gap-8 md:grid-cols-3">
			<div class="space-y-6 md:col-span-2">
				<ul class="flex flex-wrap gap-2 text-sm">
					<li class="rounded-full bg-muted px-3 py-1">
						<?php
						/* translators: %d: number of adults. */
						$rtbp_adults = sprintf( _n( 'Up to %d adult', 'Up to %d adults', (int) $rtbp_type['max_adults'], 'radius-hotel-booking' ), (int) $rtbp_type['max_adults'] );
						if ( $rtbp_type['max_children'] ) {
							/* translators: %d: number of children. */
							$rtbp_children = sprintf( _n( '%d child', '%d children', (int) $rtbp_type['max_children'], 'radius-hotel-booking' ), (int) $rtbp_type['max_children'] );
							/* translators: 1: "Up to 2 adults", 2: "1 child". */
							$rtbp_adults = sprintf( __( '%1$s and %2$s', 'radius-hotel-booking' ), $rtbp_adults, $rtbp_children );
						}
						echo esc_html( $rtbp_adults );
						?>
					</li>
					<?php if ( '' !== $rtbp_type['bed_info'] ) : ?>
						<li class="rounded-full bg-muted px-3 py-1"><?php echo esc_html( $rtbp_type['bed_info'] ); ?></li>
					<?php endif; ?>
					<?php if ( $rtbp_type['size_m2'] ) : ?>
						<li class="rounded-full bg-muted px-3 py-1">
							<?php
							/* translators: %s: room size in square metres. */
							printf( esc_html__( '%s m²', 'radius-hotel-booking' ), esc_html( number_format_i18n( $rtbp_type['size_m2'], floor( $rtbp_type['size_m2'] ) == $rtbp_type['size_m2'] ? 0 : 1 ) ) ); // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- a whole number shows no decimals.
							?>
						</li>
					<?php endif; ?>
				</ul>

				<?php if ( '' !== trim( $rtbp_type['description'] ) ) : ?>
					<div class="space-y-3 leading-relaxed">
						<?php echo wp_kses_post( wpautop( $rtbp_type['description'] ) ); ?>
					</div>
				<?php endif; ?>

				<?php
				if ( $rtbp_type['amenities'] ) :
					/**
					 * The icon of amenities that have none of their own (a Lucide icon name).
					 *
					 * @param string $icon Icon name.
					 */
					$rtbp_default_icon = Icons::clean( apply_filters( 'rtbp_amenity_default_icon', 'circle-check' ) );
					$rtbp_library      = (array) ( $rtbp_type['amenity_library'] ?? array() );
					// This room type's amenities by section, in the library's section order; unknown ones last.
					$rtbp_groups = array();
					foreach ( (array) ( $rtbp_library['sections'] ?? array() ) as $rtbp_section ) {
						$rtbp_groups[ (int) $rtbp_section['id'] ] = array(
							'name'  => (string) $rtbp_section['name'],
							'items' => array(),
						);
					}
					$rtbp_groups[0] = array(
						'name'  => __( 'Other', 'radius-hotel-booking' ),
						'items' => array(),
					);
					foreach ( $rtbp_type['amenities'] as $rtbp_amenity ) {
						$rtbp_meta    = $rtbp_library['amenities'][ mb_strtolower( $rtbp_amenity ) ] ?? array();
						$rtbp_section = (int) ( $rtbp_meta['section'] ?? 0 );
						$rtbp_section = isset( $rtbp_groups[ $rtbp_section ] ) ? $rtbp_section : 0;
						$rtbp_icon    = (string) ( $rtbp_meta['icon'] ?? '' );
						$rtbp_groups[ $rtbp_section ]['items'][] = array(
							'name' => $rtbp_amenity,
							'icon' => '' !== $rtbp_icon && Icons::valid( $rtbp_icon ) ? $rtbp_icon : $rtbp_default_icon,
						);
					}
					// "Other" last.
					$rtbp_other = $rtbp_groups[0];
					unset( $rtbp_groups[0] );
					$rtbp_groups[0] = $rtbp_other;
					$rtbp_groups    = array_filter( $rtbp_groups, static fn( $group ) => (bool) $group['items'] );
					?>
					<section class="space-y-5">
						<h2 class="text-xl font-bold text-heading"><?php esc_html_e( 'Amenities', 'radius-hotel-booking' ); ?></h2>
						<?php foreach ( $rtbp_groups as $rtbp_group ) : ?>
							<div class="space-y-2.5">
								<?php if ( count( $rtbp_groups ) > 1 ) : ?>
									<h3 class="text-xs font-bold uppercase tracking-wider text-muted-foreground"><?php echo esc_html( $rtbp_group['name'] ); ?></h3>
								<?php endif; ?>
								<ul class="grid gap-x-4 gap-y-2 sm:grid-cols-2">
									<?php foreach ( $rtbp_group['items'] as $rtbp_item ) : ?>
										<li class="flex items-center gap-2.5 text-sm">
											<span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-primary-soft text-primary">
												<?php echo Icons::svg( $rtbp_item['icon'], 'h-4 w-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
											</span>
											<?php echo esc_html( $rtbp_item['name'] ); ?>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endforeach; ?>
					</section>
				<?php endif; ?>
			</div>

			<aside class="space-y-4">
				<section class="space-y-3 rounded-xl border border-border bg-card p-4">
					<h2 class="text-lg font-bold text-heading"><?php esc_html_e( 'Stays and prices', 'radius-hotel-booking' ); ?></h2>
					<?php if ( $rtbp_type['rates'] ) : ?>
						<ul class="space-y-3">
							<?php foreach ( $rtbp_type['rates'] as $rtbp_rate ) : ?>
								<li class="flex items-start justify-between gap-3 border-b border-border pb-3 text-sm last:border-b-0 last:pb-0">
									<span>
										<span class="block font-semibold text-heading"><?php echo esc_html( $rtbp_rate['name'] ); ?></span>
										<span class="block text-muted-foreground"><?php echo esc_html( $rtbp_rate['when'] ); ?></span>
									</span>
									<span class="shrink-0 text-right">
										<span class="block text-xs text-muted-foreground"><?php esc_html_e( 'from', 'radius-hotel-booking' ); ?></span>
										<span class="block font-bold text-heading"><?php echo esc_html( $rtbp_rate['from'] ); ?></span>
									</span>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="text-sm text-muted-foreground"><?php esc_html_e( 'This room is not offered online at the moment.', 'radius-hotel-booking' ); ?></p>
					<?php endif; ?>
					<?php if ( $rtbp_type['rates'] && '' !== $rtbp_type['book_url'] ) : ?>
						<a class="flex h-12 w-full items-center justify-center rounded-md bg-primary px-5 font-semibold text-primary-foreground no-underline" href="<?php echo esc_url( $rtbp_type['book_url'] ); ?>">
							<?php esc_html_e( 'Book this room', 'radius-hotel-booking' ); ?>
						</a>
					<?php endif; ?>
				</section>
			</aside>
		</div>
	</div>
</main>
<?php
get_footer();
