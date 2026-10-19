<?php
/**
 * The room type cards — rendered the same way by `[rtbp_rooms]`, the
 * *Hotel room types* block and the Elementor widget.
 *
 * Override from a theme at `radius-hotel-booking/embeds/rooms.php`.
 *
 * @var array  $types      Room types: RoomTypePage::find() data plus `rooms` (count) and `url`.
 * @var string $layout     grid|list.
 * @var int    $columns    2–4 (grid).
 * @var bool   $show_count Show the number of rooms of each type.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Support\Icons;

defined( 'ABSPATH' ) || exit;

// Literal classes, so Tailwind compiles them.
$rtbp_grid = 'list' === $layout
	? 'grid-cols-1'
	: array(
		2 => 'sm:grid-cols-2',
		3 => 'sm:grid-cols-2 lg:grid-cols-3',
		4 => 'sm:grid-cols-2 lg:grid-cols-4',
	)[ $columns ];
$rtbp_list = 'list' === $layout;
?>
<div class="rtbp-root rtbp-rooms text-foreground">
	<?php if ( ! $types ) : ?>
		<p class="rounded-xl border border-dashed border-border bg-muted/40 p-6 text-center text-sm text-muted-foreground">
			<?php esc_html_e( 'No rooms to show yet.', 'radius-hotel-booking' ); ?>
		</p>
	<?php else : ?>
		<div class="grid gap-6 <?php echo esc_attr( $rtbp_grid ); ?>">
			<?php
			foreach ( $types as $rtbp_type ) :
				$rtbp_from   = $rtbp_type['rates'][0] ?? null;
				$rtbp_people = sprintf(
					/* translators: %d: number of adults. */
					_n( '%d adult', '%d adults', $rtbp_type['max_adults'], 'radius-hotel-booking' ),
					$rtbp_type['max_adults']
				);
				if ( $rtbp_type['max_children'] > 0 ) {
					$rtbp_people = sprintf(
						/* translators: 1: adults, 2: number of children. */
						_n( '%1$s · %2$d child', '%1$s · %2$d children', $rtbp_type['max_children'], 'radius-hotel-booking' ),
						$rtbp_people,
						$rtbp_type['max_children']
					);
				}
				?>
				<article class="group flex flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-sm transition-shadow duration-200 hover:shadow-lg <?php echo $rtbp_list ? 'sm:flex-row' : ''; ?>">
					<a href="<?php echo esc_url( $rtbp_type['url'] ); ?>" class="relative block shrink-0 overflow-hidden bg-muted no-underline <?php echo $rtbp_list ? 'aspect-[4/3] sm:aspect-auto sm:w-80' : 'aspect-[4/3]'; ?>" tabindex="-1" aria-hidden="true">
						<?php
						if ( $rtbp_type['images'] ) :
							echo wp_get_attachment_image(
								$rtbp_type['images'][0],
								'large',
								false,
								array(
									'class'   => 'absolute inset-0 h-full w-full object-cover transition-transform duration-500 group-hover:scale-105',
									'loading' => 'lazy',
									'sizes'   => '(min-width: 1024px) 33vw, (min-width: 640px) 50vw, 100vw',
								)
							);
						else :
							?>
							<span class="absolute inset-0 flex items-center justify-center text-muted-foreground">
								<?php echo Icons::svg( 'bed-double', 'h-10 w-10' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
							</span>
						<?php endif; ?>
						<?php if ( $show_count && $rtbp_type['rooms'] > 0 ) : ?>
							<span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-white/95 px-2.5 py-1 text-xs font-semibold text-heading shadow-sm">
								<span class="h-1.5 w-1.5 rounded-full bg-success"></span>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %d: number of rooms of this type. */
										_n( '%d room', '%d rooms', $rtbp_type['rooms'], 'radius-hotel-booking' ),
										$rtbp_type['rooms']
									)
								);
								?>
							</span>
						<?php endif; ?>
					</a>

					<div class="flex min-w-0 flex-1 flex-col gap-3 p-5">
						<div class="space-y-1.5">
							<h3 class="m-0 text-lg font-bold leading-snug text-heading">
								<a href="<?php echo esc_url( $rtbp_type['url'] ); ?>" class="text-heading no-underline hover:text-primary">
									<?php echo esc_html( $rtbp_type['name'] ); ?>
								</a>
							</h3>
							<?php if ( '' !== $rtbp_type['short_description'] ) : ?>
								<p class="m-0 line-clamp-2 text-sm text-muted-foreground"><?php echo esc_html( $rtbp_type['short_description'] ); ?></p>
							<?php endif; ?>
						</div>

						<ul class="m-0 flex list-none flex-wrap gap-x-4 gap-y-1.5 p-0 text-sm text-muted-foreground">
							<li class="inline-flex items-center gap-1.5">
								<?php echo Icons::svg( 'users', 'h-4 w-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
								<?php echo esc_html( $rtbp_people ); ?>
							</li>
							<?php if ( '' !== $rtbp_type['bed_info'] ) : ?>
								<li class="inline-flex items-center gap-1.5">
									<?php echo Icons::svg( 'bed-double', 'h-4 w-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
									<?php echo esc_html( $rtbp_type['bed_info'] ); ?>
								</li>
							<?php endif; ?>
							<?php if ( null !== $rtbp_type['size_m2'] && $rtbp_type['size_m2'] > 0 ) : ?>
								<li class="inline-flex items-center gap-1.5">
									<?php echo Icons::svg( 'maximize', 'h-4 w-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
									<?php
									echo esc_html(
										sprintf(
											/* translators: %s: room size in square metres. */
											__( '%s m²', 'radius-hotel-booking' ),
											number_format_i18n( $rtbp_type['size_m2'] )
										)
									);
									?>
								</li>
							<?php endif; ?>
						</ul>

						<div class="mt-auto flex flex-wrap items-end justify-between gap-3 border-t border-border pt-4">
							<div class="min-w-0">
								<?php if ( $rtbp_from ) : ?>
									<span class="block text-xs text-muted-foreground"><?php esc_html_e( 'From', 'radius-hotel-booking' ); ?></span>
									<span class="block text-xl font-bold leading-tight text-heading"><?php echo esc_html( $rtbp_from['from'] ); ?></span>
									<span class="block truncate text-xs text-muted-foreground"><?php echo esc_html( $rtbp_from['name'] ); ?></span>
								<?php endif; ?>
							</div>
							<div class="flex flex-wrap items-center gap-2">
								<a href="<?php echo esc_url( $rtbp_type['url'] ); ?>" class="inline-flex h-10 items-center rounded-md border border-border bg-white px-4 text-sm font-semibold text-heading no-underline transition-colors hover:border-primary hover:bg-white hover:text-primary">
									<?php esc_html_e( 'Details', 'radius-hotel-booking' ); ?>
								</a>
								<?php if ( $rtbp_from && '' !== $rtbp_type['book_url'] ) : ?>
									<a href="<?php echo esc_url( $rtbp_type['book_url'] ); ?>" class="inline-flex h-10 items-center gap-1.5 rounded-md bg-primary px-4 text-sm font-semibold text-primary-foreground no-underline transition-colors hover:bg-primary-hover hover:text-primary-foreground">
										<?php esc_html_e( 'Book now', 'radius-hotel-booking' ); ?>
										<?php echo Icons::svg( 'arrow-right', 'h-4 w-4' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- sanitised SVG (Icons::svg). ?>
									</a>
								<?php endif; ?>
							</div>
						</div>
					</div>
				</article>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
