<?php
/**
 * The guest's booking page (M05, 5.2, 5.4): status, rooms, money, the
 * deadline counted down, how to pay, the invoice and receipts.
 *
 * Override from a theme at `radius-hotel-booking/booking/confirmation.php`.
 *
 * @var array $view `GuestBookingView::data()`.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

$rtbp_color = sanitize_hex_color( (string) rtbp_setting( 'display', 'primaryColor', '#1d4ed8' ) );
$rtbp_color = $rtbp_color ? $rtbp_color : '#1d4ed8';
$rtbp_fmt   = static fn( $iso, $type = 'datetime' ) => $iso ? Dates::format( Dates::from_iso( (string) $iso ), $type ) : '';
$rtbp_state = array(
	'pending'     => __( 'We have received your booking. The hotel will confirm it shortly.', 'radius-hotel-booking' ),
	'confirmed'   => __( 'Your booking is confirmed.', 'radius-hotel-booking' ),
	'checked_in'  => __( 'Welcome! Enjoy your stay.', 'radius-hotel-booking' ),
	'checked_out' => __( 'Thank you for staying with us.', 'radius-hotel-booking' ),
	'cancelled'   => __( 'This booking was cancelled.', 'radius-hotel-booking' ),
	'declined'    => __( 'The hotel could not accept this booking.', 'radius-hotel-booking' ),
	'no_show'     => __( 'This booking is closed.', 'radius-hotel-booking' ),
);
$rtbp_due   = (float) $view['balance_due'];
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="utf-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title>
	<?php
	/* translators: 1: booking reference, 2: hotel name. */
	echo esc_html( sprintf( __( 'Booking %1$s · %2$s', 'radius-hotel-booking' ), $view['reference'], $view['hotel']['name'] ) );
	?>
	</title>
	<style>
		* { box-sizing: border-box; }
		body { margin: 0; background: #f3f4f6; color: #111827; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; font-size: 15px; line-height: 1.55; }
		.wrap { max-width: 640px; margin: 0 auto; padding: 16px; }
		.card { background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: 20px; margin-bottom: 16px; }
		.hotel { text-align: center; padding: 8px 0 16px; }
		.hotel img { max-height: 56px; max-width: 200px; }
		.hotel h1 { margin: 8px 0 0; font-size: 18px; }
		h2 { margin: 0 0 12px; font-size: 16px; }
		.ref { font-size: 22px; font-weight: 700; letter-spacing: 0.02em; margin: 4px 0 0; }
		.muted { color: #6b7280; font-size: 13px; }
		.lead { margin: 12px 0 0; }
		.row { display: flex; justify-content: space-between; gap: 12px; padding: 6px 0; }
		.row + .row { border-top: 1px solid #f3f4f6; }
		.strong { font-weight: 700; }
		.due { color: #b45309; }
		.ok { color: #047857; }
		.bad { color: #b91c1c; }
		.deadline { margin-top: 12px; padding: 10px 12px; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; }
		.deadline.late { background: #fef2f2; border-color: #fecaca; }
		.method { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px 14px; margin-top: 10px; }
		.method h3 { margin: 0 0 4px; font-size: 15px; }
		.method p { margin: 0; white-space: pre-line; }
		.account { margin-top: 6px; padding: 8px 10px; background: #f9fafb; border-radius: 6px; font-family: ui-monospace, Menlo, monospace; white-space: pre-line; }
		.quote { margin-top: 12px; padding: 10px 12px; border-radius: 8px; background: #eff6ff; }
		.btn { display: inline-block; padding: 10px 16px; border-radius: 8px; background: <?php echo esc_attr( $rtbp_color ); ?>; color: #fff; text-decoration: none; font-weight: 600; }
		.links a { color: <?php echo esc_attr( $rtbp_color ); ?>; }
		.links a.btn { color: #fff; }
		.strike { text-decoration: line-through; color: #9ca3af; }
	</style>
</head>
<body>
<div class="wrap">
	<div class="hotel">
		<?php if ( ! empty( $view['hotel']['logo'] ) ) : ?>
			<img src="<?php echo esc_url( $view['hotel']['logo'] ); ?>" alt="" />
		<?php endif; ?>
		<h1><?php echo esc_html( $view['hotel']['name'] ); ?></h1>
	</div>

	<div class="card">
		<div class="muted"><?php esc_html_e( 'Booking', 'radius-hotel-booking' ); ?></div>
		<p class="ref"><?php echo esc_html( $view['reference'] ); ?></p>
		<p class="lead <?php echo in_array( $view['status'], array( 'cancelled', 'declined' ), true ) ? 'bad' : ''; ?>"><?php echo esc_html( $rtbp_state[ $view['status'] ] ?? '' ); ?></p>
		<?php if ( '' !== $view['guest_name'] ) : ?>
			<p class="muted">
			<?php
			/* translators: %s: guest name. */
			echo esc_html( sprintf( __( 'In the name of %s', 'radius-hotel-booking' ), $view['guest_name'] ) );
			?>
			</p>
		<?php endif; ?>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Your stay', 'radius-hotel-booking' ); ?></h2>
		<?php foreach ( $view['rooms'] as $rtbp_room ) : ?>
			<div class="row">
				<div class="<?php echo $rtbp_room['charged'] ? '' : 'strike'; ?>">
					<div class="strong">
					<?php
					/* translators: 1: room number, 2: rate name. */
					echo esc_html( sprintf( __( 'Room %1$s · %2$s', 'radius-hotel-booking' ), $rtbp_room['room'], $rtbp_room['rate_plan'] ) );
					?>
					</div>
					<div class="muted"><?php echo esc_html( $rtbp_fmt( $rtbp_room['start'] ) . ' → ' . $rtbp_fmt( $rtbp_room['end'] ) ); ?></div>
				</div>
				<div class="<?php echo $rtbp_room['charged'] ? '' : 'strike'; ?>"><?php echo esc_html( Money::format( $rtbp_room['total'] ) ); ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="card">
		<h2><?php esc_html_e( 'Payment', 'radius-hotel-booking' ); ?></h2>
		<div class="row"><span><?php esc_html_e( 'Total', 'radius-hotel-booking' ); ?></span><span class="strong"><?php echo esc_html( Money::format( $view['total'] ) ); ?></span></div>
		<?php if ( $view['paid_total'] > 0 ) : ?>
			<div class="row"><span><?php esc_html_e( 'Paid', 'radius-hotel-booking' ); ?></span><span><?php echo esc_html( Money::format( $view['paid_total'] ) ); ?></span></div>
		<?php endif; ?>
		<?php if ( $rtbp_due > 0 ) : ?>
			<div class="row"><span class="strong"><?php esc_html_e( 'To pay', 'radius-hotel-booking' ); ?></span><span class="strong due"><?php echo esc_html( Money::format( $rtbp_due ) ); ?></span></div>
		<?php elseif ( 'paid' === $view['payment_status'] ) : ?>
			<div class="row"><span class="strong ok"><?php esc_html_e( 'Paid in full. Thank you!', 'radius-hotel-booking' ); ?></span></div>
		<?php endif; ?>

		<?php if ( $view['instructions'] && $view['payment_due_at'] ) : ?>
			<div class="deadline <?php echo $view['overdue'] ? 'late' : ''; ?>" data-due="<?php echo esc_attr( $view['payment_due_at'] ); ?>">
				<?php if ( $view['overdue'] ) : ?>
					<?php
					/* translators: %s: date and time. */
					echo esc_html( sprintf( __( 'The payment was due %s. Please pay now or contact the hotel: your room may be released.', 'radius-hotel-booking' ), $rtbp_fmt( $view['payment_due_at'] ) ) );
					?>
				<?php else : ?>
					<?php
					/* translators: %s: date and time. */
					echo esc_html( sprintf( __( 'Please pay before %s.', 'radius-hotel-booking' ), $rtbp_fmt( $view['payment_due_at'] ) ) );
					?>
					<strong class="rtbp-left"></strong>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $view['instructions'] ) : ?>
			<div class="quote">
				<?php
				/* translators: %s: booking reference. */
				echo wp_kses( sprintf( __( 'Quote the reference %s with your payment.', 'radius-hotel-booking' ), '<strong>' . esc_html( $view['reference'] ) . '</strong>' ), array( 'strong' => array() ) );
				?>
			</div>
			<?php foreach ( $view['instructions'] as $rtbp_method ) : ?>
				<div class="method">
					<h3><?php echo esc_html( $rtbp_method['label'] ); ?></h3>
					<?php if ( '' !== $rtbp_method['instructions'] ) : ?>
						<p><?php echo esc_html( $rtbp_method['instructions'] ); ?></p>
					<?php endif; ?>
					<?php if ( '' !== $rtbp_method['account'] ) : ?>
						<div class="account"><?php echo esc_html( $rtbp_method['account'] ); ?></div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>

	<?php if ( $view['invoice'] || $view['receipts'] ) : ?>
	<div class="card links">
		<h2><?php esc_html_e( 'Documents', 'radius-hotel-booking' ); ?></h2>
		<?php if ( $view['invoice'] ) : ?>
			<p style="margin: 0 0 8px;"><a class="btn" href="<?php echo esc_url( $view['invoice']['url'] ); ?>" target="_blank" rel="noopener">
			<?php
			/* translators: %s: invoice number. */
			echo esc_html( sprintf( __( 'Invoice %s', 'radius-hotel-booking' ), $view['invoice']['number'] ) );
			?>
			</a></p>
		<?php endif; ?>
		<?php foreach ( $view['receipts'] as $rtbp_receipt ) : ?>
			<p style="margin: 4px 0;"><a href="<?php echo esc_url( $rtbp_receipt['url'] ); ?>" target="_blank" rel="noopener">
			<?php
			/* translators: 1: receipt number, 2: amount. */
			echo esc_html( sprintf( __( 'Receipt %1$s · %2$s', 'radius-hotel-booking' ), $rtbp_receipt['number'], Money::format( $rtbp_receipt['amount'] ) ) );
			?>
			</a></p>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="card muted">
		<?php echo esc_html( implode( ' · ', array_filter( array( $view['hotel']['phone'], $view['hotel']['email'] ) ) ) ); ?>
		<?php if ( '' !== $view['hotel']['address'] ) : ?>
			<div style="white-space: pre-line;"><?php echo esc_html( $view['hotel']['address'] ); ?></div>
		<?php endif; ?>
	</div>
</div>
<script>
( function () {
	var box = document.querySelector( '.deadline[data-due]' );
	var out = box && box.querySelector( '.rtbp-left' );
	if ( ! out ) { return; }
	var due = new Date( box.getAttribute( 'data-due' ) ).getTime();
	var tpl = 
	<?php
	echo wp_json_encode(
		array(
			/* translators: 1: days, 2: hours. */
			'd' => __( '(%1$d d %2$d h left)', 'radius-hotel-booking' ),
			/* translators: 1: hours, 2: minutes. */
			'h' => __( '(%1$d h %2$d min left)', 'radius-hotel-booking' ),
			/* translators: %d: minutes. */
			'm' => __( '(%d min left)', 'radius-hotel-booking' ),
		)
	);
	?>
	;
	function tick() {
		var min = Math.max( 0, Math.round( ( due - Date.now() ) / 60000 ) );
		var d = Math.floor( min / 1440 ), h = Math.floor( ( min % 1440 ) / 60 ), m = min % 60;
		out.textContent = ' ' + ( d ? tpl.d.replace( '%1$d', d ).replace( '%2$d', h ) : h ? tpl.h.replace( '%1$d', h ).replace( '%2$d', m ) : tpl.m.replace( '%d', m ) );
	}
	tick();
	setInterval( tick, 30000 );
}() );
</script>
</body>
</html>
