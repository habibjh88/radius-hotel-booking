<?php
/**
 * A room type's page (M04, 4.5): `/rooms/{slug}`, inside the theme.
 *
 * Override from a theme at `radius-hotel-booking/booking/room-type.php`. The
 * data comes from `Frontend\RoomTypePage::current()`:
 * `{ name, short_description, description, images[] (attachment ids),
 *   amenities[], bed_info, size_m2, max_adults, max_children,
 *   rates[ { name, when, from } ], book_url }`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Frontend\RoomTypePage;

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

		<?php if ( $rtbp_type['images'] ) : ?>
			<div class="grid gap-3 sm:grid-cols-3">
				<?php foreach ( $rtbp_type['images'] as $rtbp_index => $rtbp_image ) : ?>
					<div class="<?php echo 0 === $rtbp_index ? 'sm:col-span-3' : ''; ?> overflow-hidden rounded-xl bg-muted">
						<?php
						echo wp_get_attachment_image(
							$rtbp_image,
							0 === $rtbp_index ? 'large' : 'medium_large',
							false,
							array(
								'class'   => 'h-full w-full object-cover ' . ( 0 === $rtbp_index ? 'max-h-[28rem]' : 'aspect-[4/3]' ),
								'loading' => 0 === $rtbp_index ? 'eager' : 'lazy',
							)
						);
						?>
					</div>
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

				<?php if ( $rtbp_type['amenities'] ) : ?>
					<section class="space-y-3">
						<h2 class="text-xl font-bold text-heading"><?php esc_html_e( 'Amenities', 'radius-hotel-booking' ); ?></h2>
						<ul class="grid gap-2 sm:grid-cols-2">
							<?php foreach ( $rtbp_type['amenities'] as $rtbp_amenity ) : ?>
								<li class="flex items-center gap-2 text-sm">
									<span class="h-1.5 w-1.5 shrink-0 rounded-full bg-primary" aria-hidden="true"></span>
									<?php echo esc_html( $rtbp_amenity ); ?>
								</li>
							<?php endforeach; ?>
						</ul>
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
