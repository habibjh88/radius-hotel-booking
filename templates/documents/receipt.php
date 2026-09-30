<?php
/**
 * Receipt (M05, 5.12): one payment, with the booking and invoice it belongs
 * to and what was still due right after it. Printed from the browser; Pro
 * renders the same HTML to PDF.
 *
 * Override from a theme at `radius-hotel-booking/documents/receipt.php`.
 *
 * @var array $doc Receipt data: `number, received_at, amount, method, reference, note, recorded_by, voided,
 *                 booking, invoice_number, paid_to_date, balance_after, guest, hotel, logo_url, footer, pdf?`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

$rtbp_hotel = (array) ( $doc['hotel'] ?? array() );
$rtbp_guest = (array) ( $doc['guest'] ?? array() );
$rtbp_when  = ! empty( $doc['received_at'] ) ? Dates::format( Dates::from_iso( (string) $doc['received_at'] ), 'datetime' ) : '';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title>
	<?php
	/* translators: %s: receipt number. */
	echo esc_html( sprintf( __( 'Receipt %s', 'radius-hotel-booking' ), $doc['number'] ) );
	?>
	</title>
	<?php rtbp_get_template( 'documents/document-style.php' ); ?>
</head>
<body>
<?php if ( empty( $doc['pdf'] ) ) : ?>
<div class="actions"><button type="button" onclick="window.print()"><?php esc_html_e( 'Print', 'radius-hotel-booking' ); ?></button></div>
<?php endif; ?>
<div class="sheet">
	<?php if ( ! empty( $doc['voided'] ) ) : ?>
		<div class="stamp"><?php esc_html_e( 'Voided', 'radius-hotel-booking' ); ?></div>
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
				<div class="muted"><?php echo esc_html( implode( ' · ', array_filter( array( $rtbp_hotel['phone'] ?? '', $rtbp_hotel['email'] ?? '' ) ) ) ); ?></div>
			</td>
			<td>
				<p class="doc-title"><?php esc_html_e( 'Receipt', 'radius-hotel-booking' ); ?></p>
				<table class="meta">
					<tr><td class="label"><?php esc_html_e( 'Number', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $doc['number'] ); ?></td></tr>
					<tr><td class="label"><?php esc_html_e( 'Received', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $rtbp_when ); ?></td></tr>
					<tr><td class="label"><?php esc_html_e( 'Booking', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $doc['booking']['reference'] ?? '' ); ?></td></tr>
					<?php if ( ! empty( $doc['invoice_number'] ) ) : ?>
						<tr><td class="label"><?php esc_html_e( 'Invoice', 'radius-hotel-booking' ); ?></td><td class="value"><?php echo esc_html( $doc['invoice_number'] ); ?></td></tr>
					<?php endif; ?>
				</table>
				<?php if ( ! empty( $doc['voided'] ) ) : ?>
					<p style="text-align: right; margin: 8px 0 0;"><span class="flag"><?php esc_html_e( 'Voided', 'radius-hotel-booking' ); ?></span></p>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<?php if ( $rtbp_guest ) : ?>
	<div class="box">
		<p class="box-title"><?php esc_html_e( 'Received from', 'radius-hotel-booking' ); ?></p>
		<strong><?php echo esc_html( $rtbp_guest['name'] ?? '' ); ?></strong>
		<div class="muted"><?php echo esc_html( $rtbp_guest['phone'] ?? '' ); ?></div>
	</div>
	<?php endif; ?>

	<table class="lines">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Method', 'radius-hotel-booking' ); ?></th>
				<th><?php esc_html_e( 'Reference', 'radius-hotel-booking' ); ?></th>
				<th class="num"><?php esc_html_e( 'Amount', 'radius-hotel-booking' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<tr>
				<td><?php echo esc_html( $doc['method'] ); ?></td>
				<td><?php echo esc_html( '' !== (string) $doc['reference'] ? $doc['reference'] : '—' ); ?></td>
				<td class="num"><strong><?php echo esc_html( Money::format( $doc['amount'] ) ); ?></strong></td>
			</tr>
		</tbody>
	</table>

	<table class="totals">
		<tr><td><?php esc_html_e( 'Booking total', 'radius-hotel-booking' ); ?></td><td class="num"><?php echo esc_html( Money::format( $doc['booking']['total'] ?? 0 ) ); ?></td></tr>
		<tr><td><?php esc_html_e( 'Paid to date', 'radius-hotel-booking' ); ?></td><td class="num"><?php echo esc_html( Money::format( $doc['paid_to_date'] ) ); ?></td></tr>
		<tr class="grand"><td><?php esc_html_e( 'Balance due', 'radius-hotel-booking' ); ?></td><td class="num"><?php echo esc_html( Money::format( max( 0, (float) $doc['balance_after'] ) ) ); ?></td></tr>
	</table>

	<?php if ( ! empty( $doc['voided'] ) ) : ?>
		<div class="note">
		<?php
		/* translators: %s: why the payment was voided. */
		echo esc_html( sprintf( __( 'This payment was voided: %s', 'radius-hotel-booking' ), $doc['voided'] ) );
		?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $doc['recorded_by'] ) ) : ?>
		<p class="muted" style="margin-top: 16px;">
		<?php
		/* translators: %s: staff name. */
		echo esc_html( sprintf( __( 'Received by %s', 'radius-hotel-booking' ), $doc['recorded_by'] ) );
		?>
		</p>
	<?php endif; ?>

	<?php if ( ! empty( $doc['footer'] ) ) : ?>
		<div class="footer"><?php echo esc_html( $doc['footer'] ); ?></div>
	<?php endif; ?>
</div>
</body>
</html>
