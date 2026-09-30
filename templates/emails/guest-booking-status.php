<?php
/**
 * Guest e-mail: a booking's status changed (approved, declined, cancelled).
 *
 * @var \RadiusTheme\RadiusHotelBooking\Abstracts\BaseEmail $email      The e-mail.
 * @var array                                               $data       `{ intro, rooms, total, reason }`.
 * @var array                                               $settings   The e-mail's settings.
 * @var array                                               $merge_tags Merge tags.
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

defined( 'ABSPATH' ) || exit;

do_action( 'rtbp_email_before_content', $email, $data );
?>
<p style="margin: 0 0 16px; font-size: 15px; line-height: 1.6; color: #111827;"><?php echo esc_html( $data['intro'] ); ?></p>

<?php if ( ! empty( $data['reason'] ) ) : ?>
<p style="margin: 0 0 16px; font-size: 14px; line-height: 1.6; color: #374151;">
	<strong><?php esc_html_e( 'Reason:', 'radius-hotel-booking' ); ?></strong>
	<?php echo esc_html( $data['reason'] ); ?>
</p>
<?php endif; ?>

<?php if ( ! empty( $data['rooms'] ) ) : ?>
<table width="100%" border="0" cellspacing="0" cellpadding="0" style="border-collapse: collapse; margin: 0 0 16px;">
	<?php foreach ( $data['rooms'] as $room ) : ?>
	<tr>
		<td style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #111827;">
			<?php
			/* translators: 1: room number, 2: rate name. */
			echo esc_html( sprintf( __( 'Room %1$s · %2$s', 'radius-hotel-booking' ), $room['room'], $room['rate'] ) );
			?>
			<br /><span style="font-size: 13px; color: #6b7280;"><?php echo esc_html( $room['start'] . ' → ' . $room['end'] ); ?></span>
		</td>
		<td align="right" style="padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 14px; color: #111827;"><?php echo esc_html( $room['total'] ); ?></td>
	</tr>
	<?php endforeach; ?>
	<tr>
		<td style="padding: 10px 0; font-size: 14px; font-weight: bold; color: #111827;"><?php esc_html_e( 'Total', 'radius-hotel-booking' ); ?></td>
		<td align="right" style="padding: 10px 0; font-size: 14px; font-weight: bold; color: #111827;"><?php echo esc_html( $data['total'] ); ?></td>
	</tr>
</table>
<?php endif; ?>
