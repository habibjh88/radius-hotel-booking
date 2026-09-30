<?php
/**
 * Invoice (M05, 5.5, 5.6): one version of a booking's invoice, exactly as it
 * was issued (its snapshot). Printed from the browser; Pro renders the same
 * HTML to PDF.
 *
 * Override from a theme at `radius-hotel-booking/documents/invoice.php`.
 *
 * @var array $doc Invoice data: `number, version, latest, status, superseded, issued_at, booking,
 *                 hotel, guest, lines, totals, footer, logo_url, pdf?`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

$rtbp_date  = static fn( $iso ) => $iso ? Dates::format( Dates::from_iso( (string) $iso ), 'datetime' ) : '';
$rtbp_day   = static fn( $iso ) => $iso ? Dates::format( Dates::from_iso( (string) $iso ), 'date' ) : '';
$rtbp_hotel = (array) ( $doc['hotel'] ?? array() );
$rtbp_guest = (array) ( $doc['guest'] ?? array() );
$rtbp_tot   = (array) ( $doc['totals'] ?? array() );
$rtbp_rate  = static fn( $rate ) => rtrim( rtrim( number_format( (float) $rate, 2, '.', '' ), '0' ), '.' );
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title>
	<?php
	/* translators: %s: invoice number. */
	echo esc_html( sprintf( __( 'Invoice %s', 'radius-hotel-booking' ), $doc['number'] ) );
	?>
	</title>
	<?php rtbp_get_template( 'documents/document-style.php' ); ?>
</head>
<body>
<?php if ( empty( $doc['pdf'] ) ) : ?>
<div class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'radius-hotel-booking' ); ?></button></div>
<?php endif; ?>
<div class="sheet">
	<?php if ( 'cancelled' === ( $doc['status'] ?? '' ) ) : ?>
		<div class="stamp"><?php esc_html_e( 'Cancelled', 'radius-hotel-booking' ); ?></div>
	<?php endif; ?>

	<table>
		<tr>
			<td style="width: 55%;">
				<?php if ( ! empty( $doc['logo_url'] ) ) : ?>
					<img class="logo" src="<?php echo esc_url( $doc['logo_url'] ); ?>" alt="" /><br />
				<?php endif; ?>
				<p class="hotel-name"><?php echo esc_html( $rtbp_hotel['name'] ?? '' ); ?></p>
				<?php if ( ! empty( $rtbp_hotel['address'] ) ) : ?>
					<div class="muted" style="white-space: pre-line;"><?php echo esc_html( $rtbp_hotel['address'] ); ?></div>
				<?php endif; ?>
				<div class="muted">
					<?php echo esc_html( implode( ' · ', array_filter( array( $rtbp_hotel['phone'] ?? '', $rtbp_hotel['email'] ?? '' ) ) ) ); ?>
				</div>
				<?php if ( ! empty( $rtbp_hotel['tax_number'] ) ) : ?>
					<div class="muted">
					<?php
					/* translators: %s: tax number. */
					echo esc_html( sprintf( __( 'Tax no. %s', 'radius-hotel-booking' ), $rtbp_hotel['tax_number'] ) );
					?>
					</div>
				<?php endif; ?>
				<?php if ( ! empty( $rtbp_hotel['cnps_number'] ) ) : ?>
					<div class="muted">
					<?php
					/* translators: %s: CNPS number. */
					echo esc_html( sprintf( __( 'CNPS no. %s', 'radius-hotel-booking' ), $rtbp_hotel['cnps_number'] ) );
					?>
					</div>
				<?php endif; ?>
			</td>
			<td>
				<p class="doc-title"><?php esc_html_e( 'Invoice', 'radius-hotel-booking' ); ?></p>
				<table class="meta">
					<tr><td class="label"><?php esc_html_e( 'Number', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $doc['number'] ); ?></td></tr>
					<tr><td class="label"><?php esc_html_e( 'Date', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $rtbp_day( $doc['issued_at'] ?? '' ) ); ?></td></tr>
					<tr><td class="label"><?php esc_html_e( 'Booking', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $doc['booking']['reference'] ?? '' ); ?></td></tr>
					<?php if ( (int) ( $doc['version'] ?? 1 ) > 1 ) : ?>
						<tr><td class="label"><?php esc_html_e( 'Version', 'radius-hotel-booking' ); ?></td><td class="value">
						<?php
						/* translators: %d: version number. */
						echo esc_html( sprintf( __( 'Revised (version %d)', 'radius-hotel-booking' ), (int) $doc['version'] ) );
						?>
						</td></tr>
					<?php endif; ?>
				</table>
				<?php if ( 'cancelled' === ( $doc['status'] ?? '' ) ) : ?>
					<p style="text-align: right; margin: 8px 0 0;"><span class="flag"><?php esc_html_e( 'Cancelled', 'radius-hotel-booking' ); ?></span></p>
				<?php elseif ( ! empty( $doc['superseded'] ) ) : ?>
					<p style="text-align: right; margin: 8px 0 0;"><span class="flag">
					<?php
					/* translators: %d: the current version number. */
					echo esc_html( sprintf( __( 'Replaced by version %d', 'radius-hotel-booking' ), (int) $doc['latest'] ) );
					?>
					</span></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php if ( $rtbp_guest ) : ?>
	<div class="box">
		<p class="box-title"><?php esc_html_e( 'Billed to', 'radius-hotel-booking' ); ?></p>
		<strong><?php echo esc_html( $rtbp_guest['name'] ?? '' ); ?></strong>
		<?php if ( ! empty( $rtbp_guest['reference'] ) ) : ?>
			<span class="muted">(<?php echo esc_html( $rtbp_guest['reference'] ); ?>)</span>
		<?php endif; ?>
		<div class="muted"><?php echo esc_html( implode( ' · ', array_filter( array( $rtbp_guest['phone'] ?? '', $rtbp_guest['email'] ?? '' ) ) ) ); ?></div>
	</div>
	<?php endif; ?>

	<table class="lines">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Room', 'radius-hotel-booking' ); ?></th>
				<th><?php esc_html_e( 'Stay', 'radius-hotel-booking' ); ?></th>
				<th class="num"><?php esc_html_e( 'Guests', 'radius-hotel-booking' ); ?></th>
				<th class="num"><?php esc_html_e( 'Amount', 'radius-hotel-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
		<?php foreach ( (array) ( $doc['lines'] ?? array() ) as $rtbp_line ) : ?>
			<tr>
				<td>
					<?php
					/* translators: %s: room number. */
					echo '<strong>' . esc_html( sprintf( __( 'Room %s', 'radius-hotel-booking' ), $rtbp_line['room'] ) ) . '</strong>';
					?>
					<div class="muted"><?php echo esc_html( implode( ' · ', array_filter( array( $rtbp_line['room_type'] ?? '', $rtbp_line['rate_plan'] ?? '' ) ) ) ); ?></div>
				</td>
				<td><?php echo esc_html( $rtbp_date( $rtbp_line['start'] ) . ' → ' . $rtbp_date( $rtbp_line['end'] ) ); ?></td>
				<td class="num"><?php echo esc_html( (string) ( (int) $rtbp_line['adults'] + (int) $rtbp_line['children'] ) ); ?></td>
				<td class="num"><?php echo esc_html( Money::format( $rtbp_line['total'] ) ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>

	<table class="totals">
		<?php if ( ! empty( $rtbp_tot['discount_total'] ) ) : ?>
			<tr><td><?php esc_html_e( 'Rooms', 'radius-hotel-booking' ); ?></td><td class="num"><?php echo esc_html( Money::format( $rtbp_tot['subtotal'] ) ); ?></td></tr>
			<tr><td><?php esc_html_e( 'Discount', 'radius-hotel-booking' ); ?></td><td class="num">−<?php echo esc_html( Money::format( abs( (float) $rtbp_tot['discount_total'] ) ) ); ?></td></tr>
		<?php endif; ?>
		<tr class="grand"><td><?php esc_html_e( 'Total', 'radius-hotel-booking' ); ?></td><td class="num"><?php echo esc_html( Money::format( $rtbp_tot['total'] ?? 0 ) ); ?></td></tr>
		<?php if ( ! empty( $rtbp_tot['tax'] ) ) : ?>
			<tr><td class="muted">
			<?php
			/* translators: 1: tax name, e.g. VAT, 2: rate in percent. */
			echo esc_html( sprintf( __( 'Including %1$s %2$s %%', 'radius-hotel-booking' ), $rtbp_tot['tax']['label'], $rtbp_rate( $rtbp_tot['tax']['rate'] ) ) );
			?>
			</td><td class="num muted"><?php echo esc_html( Money::format( $rtbp_tot['tax']['included'] ) ); ?></td></tr>
		<?php endif; ?>
	</table>

	<?php if ( ! empty( $doc['footer'] ) ) : ?>
		<div class="footer"><?php echo esc_html( $doc['footer'] ); ?></div>
	<?php endif; ?>
</div>
</body>
</html>
