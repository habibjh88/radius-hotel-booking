<?php
/**
 * Payment API shape (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Resources
 */

namespace RadiusTheme\RadiusHotelBooking\Resources;

use RadiusTheme\RadiusHotelBooking\Documents\DocumentService;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\Payment;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;

defined( 'ABSPATH' ) || exit;

/**
 * A booking's ledger as its payment history panel shows it (5.13): each row
 * with its method's name, who recorded it, and whether it was voided (and by
 * which row, with the reason) — so the screen strikes it through.
 */
class PaymentResource {

	/**
	 * The ledger and the booking's money.
	 *
	 * @param Booking   $booking Booking.
	 * @param Payment[] $rows    Its ledger, oldest first.
	 * @return array `{ payments, summary, methods }`.
	 */
	public static function ledger( Booking $booking, array $rows ): array {
		$voided_by = array();
		$users     = array();
		foreach ( $rows as $row ) {
			if ( 'void' === $row->type ) {
				$voided_by[ (int) $row->voids_payment_id ] = $row;
			}
			if ( $row->recorded_by ) {
				$users[ (int) $row->recorded_by ] = '';
			}
		}
		if ( $users ) {
			foreach ( get_users(
				array(
					'include' => array_keys( $users ),
					'fields'  => array( 'ID', 'display_name' ),
				)
			) as $user ) {
				$users[ (int) $user->ID ] = (string) $user->display_name;
			}
		}

		$out = array();
		foreach ( $rows as $row ) {
			$void  = $voided_by[ (int) $row->id ] ?? null;
			$out[] = array(
				'id'               => (int) $row->id,
				'type'             => (string) $row->type,
				'amount'           => (float) $row->amount,
				'method'           => (string) $row->method,
				'method_label'     => PaymentSettings::method_label( (string) $row->method ),
				'reference'        => (string) $row->reference,
				'note'             => (string) $row->note,
				'received_at'      => Dates::to_iso( Dates::from_gmt( (string) $row->received_at_gmt ) ),
				'recorded_by'      => $row->recorded_by ? ( $users[ (int) $row->recorded_by ] ?? '' ) : '',
				'receipt_no'       => (string) $row->receipt_no,
				'receipt_urls'     => '' !== (string) $row->receipt_no ? DocumentService::urls( 'receipt', (int) $row->id ) : new \stdClass(),
				'voids_payment_id' => $row->voids_payment_id ? (int) $row->voids_payment_id : null,
				'voided'           => null !== $void,
				'void_reason'      => $void ? (string) $void->note : '',
			);
		}

		return array(
			'payments' => $out,
			'summary'  => array(
				'total'          => (float) $booking->total,
				'paid_total'     => (float) $booking->paid_total,
				'balance_due'    => (float) $booking->balance_due,
				'payment_status' => (string) $booking->payment_status,
				'on_hold'        => (bool) (int) $booking->on_hold,
			),
			// What the record dialog offers: the enabled methods, in order.
			'methods'  => array_map(
				static fn( $method ) => array(
					'key'   => (string) $method['key'],
					'label' => (string) $method['label'],
				),
				PaymentSettings::enabled_methods()
			),
		);
	}
}
