<?php
/**
 * Guest e-mail: payment received, with the receipt (M05, 5.12).
 *
 * @var \RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail $email      The e-mail.
 * @var array $data `{ intro, amount, method, reference, received_at, receipt_no, paid, due, receipt_url, page_url }`.
 * @var array $settings   The e-mail's settings.
 * @var array $merge_tags Merge tags.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;

$rtbp_color = sanitize_hex_color( (string) rtbp_setting( 'display', 'primaryColor', '#1d4ed8' ) );
$rtbp_color = $rtbp_color ? $rtbp_color : '#1d4ed8';
$rtbp_row   = static function ( $label, $value, $bold = false ) {
	printf(
		'<tr><td style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #6b7280;">%1$s</td><td align="right" style="padding: 6px 0; border-bottom: 1px solid #f3f4f6; font-size: 14px; color: #111827;%3$s">%2$s</td></tr>',
		esc_html( $label ),
		esc_html( $value ),
		$bold ? ' font-weight: bold;' : ''
	);
};

do_action( 'rtbp_email_before_content', $email, $data );
?>
<p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #111827;"><?php echo esc_html( $data['intro'] ); ?></p>

<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 0 0 16px;">
	<?php
	$rtbp_row( __( 'Amount', 'radius-hotel-booking' ), $data['amount'], true );
	$rtbp_row( __( 'Paid by', 'radius-hotel-booking' ), $data['method'] );
	if ( '' !== $data['reference'] ) {
		$rtbp_row( __( 'Reference', 'radius-hotel-booking' ), $data['reference'] );
	}
	$rtbp_row( __( 'Received', 'radius-hotel-booking' ), $data['received_at'] );
	$rtbp_row( __( 'Receipt', 'radius-hotel-booking' ), $data['receipt_no'] );
	$rtbp_row( __( 'Paid to date', 'radius-hotel-booking' ), $data['paid'] );
	if ( '' !== $data['due'] ) {
		$rtbp_row( __( 'Still to pay', 'radius-hotel-booking' ), $data['due'], true );
	}
	?>
</table>
<?php if ( '' === $data['due'] ) : ?>
<p style="margin: 0 0 16px; font-size: 14px; font-weight: bold; color: #047857;"><?php esc_html_e( 'Your booking is paid in full.', 'radius-hotel-booking' ); ?></p>
<?php endif; ?>

<p style="margin: 16px 0 8px;">
	<a href="<?php echo esc_url( $data['receipt_url'] ); ?>" style="display: inline-block; padding: 10px 18px; background: <?php echo esc_attr( $rtbp_color ); ?>; color: #ffffff; text-decoration: none; border-radius: 6px; font-size: 14px; font-weight: bold;"><?php esc_html_e( 'Your receipt', 'radius-hotel-booking' ); ?></a>
</p>
<p style="margin: 0 0 16px; font-size: 14px;"><a href="<?php echo esc_url( $data['page_url'] ); ?>" style="color: <?php echo esc_attr( $rtbp_color ); ?>;"><?php esc_html_e( 'See your booking', 'radius-hotel-booking' ); ?></a></p>
<?php
do_action( 'rtbp_email_after_content', $email, $data );
