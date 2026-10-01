<?php
/**
 * Invoices: numbering, snapshots and versions (M05, 5.5, 5.8).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Payments
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Payments;

use DateTimeImmutable;
use RadiusTheme\RadiusHotelBooking\Core\Database\Transaction;
use RadiusTheme\RadiusHotelBooking\Models\Booking;
use RadiusTheme\RadiusHotelBooking\Models\Invoice;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\InvoiceVersionRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingTotals;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;
use RadiusTheme\RadiusHotelBooking\Support\Sequence;

defined( 'ABSPATH' ) || exit;

/**
 * Every booking gets one invoice, created in the booking's own transaction
 * so its number comes from an **unbroken** yearly sequence
 * (`{prefix}{year}-{000123}`, Settings → Invoices; a rolled-back booking
 * gives its number back). What the invoice shows is frozen in a **snapshot**
 * (hotel details, guest, the charged rooms, totals and the tax included), so
 * it reads the same forever.
 *
 * When the rooms or the total change, the invoice is **re-issued** as a new
 * version under the same number (status *revised*; every version is kept in
 * `invoice_versions`). A payment does not revise it — receipts do that job.
 * A cancelled or declined booking's invoice is marked *cancelled*, never
 * deleted. Bookings made before invoices existed get theirs the first time
 * one is needed (the next number).
 *
 * Revisions run after the change's commit (`rtbp_booking_changed`,
 * `rtbp_booking_status_changed`), each in its own transaction with the
 * booking row locked first, like every booking write.
 */
class InvoiceService {

	/**
	 * Invoices.
	 *
	 * @var InvoiceRepository
	 */
	private InvoiceRepository $invoices;

	/**
	 * Invoice versions.
	 *
	 * @var InvoiceVersionRepository
	 */
	private InvoiceVersionRepository $versions;

	/**
	 * Constructor.
	 *
	 * @param InvoiceRepository|null        $invoices Invoices.
	 * @param InvoiceVersionRepository|null $versions Versions.
	 */
	public function __construct( ?InvoiceRepository $invoices = null, ?InvoiceVersionRepository $versions = null ) {
		$this->invoices = $invoices ?? new InvoiceRepository();
		$this->versions = $versions ?? new InvoiceVersionRepository();
	}

	/**
	 * Hook the revisions onto the booking changes (once, from the plugin's
	 * boot). Priority 5: before the guest e-mails (10), which attach the invoice.
	 *
	 * @return void
	 */
	public static function init(): void {
		$revise = static function ( $booking ) {
			if ( ! is_object( $booking ) || empty( $booking->id ) ) {
				return;
			}
			try {
				( new self() )->revise( (int) $booking->id );
			} catch ( \Throwable $e ) {
				// The change is already committed: a failed revision must not turn it into an
				// error for the caller (critical review). The next change revises again.
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- the invoice is behind the booking; say so.
				error_log( '[radius-hotel-booking] Invoice revision failed for booking ' . (int) $booking->id . ': ' . $e->getMessage() );
			}
		};
		add_action( 'rtbp_booking_changed', $revise, 5 );
		add_action( 'rtbp_booking_status_changed', $revise, 5 );
	}

	/**
	 * The next invoice number (inside the transaction writing the invoice).
	 *
	 * @param DateTimeImmutable $at Issue time.
	 * @return string
	 */
	public static function nextNumber( DateTimeImmutable $at ): string {
		$year  = $at->setTimezone( Dates::timezone() )->format( 'Y' );
		$start = max( 1, (int) rtbp_setting( 'invoices', 'startNumber', 1 ) );
		return Sequence::format( (string) rtbp_setting( 'invoices', 'prefix', 'FAC-' ) . $year . '-', Sequence::next( 'invoice_' . $year, $start ) );
	}

	/**
	 * Issue a booking's invoice. **Inside the booking's transaction**, the
	 * booking locked (or just created): the number and the invoice commit or
	 * roll back together.
	 *
	 * @param Booking                $booking Booking (its totals current).
	 * @param DateTimeImmutable|null $now     Issue time.
	 * @return Invoice
	 * @throws \RuntimeException When the invoice cannot be written.
	 */
	public function issue( Booking $booking, ?DateTimeImmutable $now = null ): Invoice {
		$now      = $now ?? Dates::now();
		$number   = self::nextNumber( $now );
		$snapshot = $this->snapshot( $booking, $number, 1, $now );
		$invoice  = $this->invoices->create(
			array(
				'booking_id'    => (int) $booking->id,
				'number'        => $number,
				'version'       => 1,
				'status'        => 'issued',
				'totals'        => wp_json_encode( $snapshot['totals'] ),
				'issued_at'     => Dates::to_db( $now ),
				'issued_at_gmt' => Dates::to_gmt_db( $now ),
			)
		);
		if ( ! $invoice->id ) {
			throw new \RuntimeException( 'The invoice could not be saved.' );
		}
		$this->versions->create(
			array(
				'invoice_id' => (int) $invoice->id,
				'version'    => 1,
				'snapshot'   => wp_json_encode( $snapshot ),
			)
		);
		rtbp_activity(
			'invoices.issue',
			self::subject( $booking ),
			array(
				'after'       => array(
					'number' => $number,
					'total'  => Money::round( (float) $booking->total ),
				),
				'description' => sprintf(
					/* translators: 1: invoice number, 2: booking reference. */
					__( 'Issued invoice %1$s for %2$s', 'radius-hotel-booking' ),
					$number,
					$booking->reference
				),
			)
		);
		return $invoice;
	}

	/**
	 * The booking's invoice, issuing it now if the booking predates invoices.
	 * Inside a transaction with the booking locked (a receipt, a revision).
	 *
	 * @param Booking $booking Booking, read under its lock.
	 * @return Invoice
	 */
	public function forBookingOrIssue( Booking $booking ): Invoice {
		$invoice = $this->invoices->forBooking( (int) $booking->id );
		return $invoice ? $invoice : $this->issue( $booking );
	}

	/**
	 * The invoice of a booking, issued if missing (its own transaction).
	 *
	 * @param int $booking_id Booking id.
	 * @return Invoice|null Null when the booking does not exist.
	 */
	public function ensure( int $booking_id ): ?Invoice {
		$invoice = $this->invoices->forBooking( $booking_id );
		if ( $invoice ) {
			return $invoice;
		}
		return Transaction::run(
			function () use ( $booking_id ) {
				$booking = ( new BookingRepository() )->lockedFind( $booking_id );
				return $booking ? $this->forBookingOrIssue( $booking ) : null;
			}
		);
	}

	/**
	 * Bring the invoice in line with the booking after a change: a new
	 * version when the rooms or the total differ from what it shows, or
	 * *cancelled* when the booking is.
	 *
	 * @param int                    $booking_id Booking id.
	 * @param DateTimeImmutable|null $now        Now.
	 * @return Invoice|null The invoice as it is now (null: no booking).
	 */
	public function revise( int $booking_id, ?DateTimeImmutable $now = null ): ?Invoice {
		$now = $now ?? Dates::now();
		return Transaction::run(
			function () use ( $booking_id, $now ) {
				$booking = ( new BookingRepository() )->lockedFind( $booking_id );
				if ( ! $booking ) {
					return null;
				}
				$invoice = $this->forBookingOrIssue( $booking );
				if ( 'cancelled' === $invoice->status ) {
					return $invoice;
				}

				if ( in_array( (string) $booking->status, array( 'cancelled', 'declined' ), true ) ) {
					$this->invoices->update( (int) $invoice->id, array( 'status' => 'cancelled' ) );
					$this->log( $booking, $invoice, (int) $invoice->version, 'cancelled' );
					return $this->invoices->find( (int) $invoice->id );
				}

				$current = $this->versions->forInvoice( (int) $invoice->id );
				$last    = $current ? json_decode( (string) end( $current )->snapshot, true ) : array();
				$next    = $this->snapshot( $booking, (string) $invoice->number, (int) $invoice->version + 1, $now );
				if ( self::billed( $last ) === self::billed( $next ) ) {
					return $invoice;
				}
				$version = (int) $invoice->version + 1;
				$this->versions->create(
					array(
						'invoice_id' => (int) $invoice->id,
						'version'    => $version,
						'snapshot'   => wp_json_encode( $next ),
					)
				);
				$this->invoices->update(
					(int) $invoice->id,
					array(
						'version' => $version,
						'status'  => 'revised',
						'totals'  => wp_json_encode( $next['totals'] ),
					)
				);
				$this->log( $booking, $invoice, $version, 'revised', $last['totals']['total'] ?? null, $next['totals']['total'] );
				return $this->invoices->find( (int) $invoice->id );
			}
		);
	}

	/**
	 * What an invoice shows, frozen: the hotel, the guest, the rooms charged
	 * and the totals with the tax they include.
	 *
	 * @param Booking           $booking Booking.
	 * @param string            $number  Invoice number.
	 * @param int               $version Version.
	 * @param DateTimeImmutable $at      Issue time of this version.
	 * @return array
	 */
	public function snapshot( Booking $booking, string $number, int $version, DateTimeImmutable $at ): array {
		$general = (array) \RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper::get_setting( 'general' );
		$guest   = $booking->guest_id ? ( new GuestRepository() )->find( (int) $booking->guest_id ) : null;
		$lines   = ( new BookingRoomRepository() )->forBooking( (int) $booking->id );
		$types   = array();
		foreach ( ( new RoomTypeRepository() )->findMany( array_values( array_unique( array_map( static fn( $line ) => (int) $line->room_type_id, $lines ) ) ) ) as $type ) {
			$types[ (int) $type->id ] = (string) $type->name;
		}

		$rows = array();
		foreach ( $lines as $line ) {
			if ( in_array( (string) $line->status, BookingTotals::NOT_CHARGED, true ) ) {
				continue;
			}
			$rows[] = array(
				'room'      => (string) $line->room_number,
				'room_type' => $types[ (int) $line->room_type_id ] ?? '',
				'rate_plan' => (string) $line->rate_plan_name,
				'start'     => Dates::to_iso( Dates::from_gmt( (string) $line->start_at_gmt ) ),
				'end'       => Dates::to_iso( Dates::from_gmt( (string) $line->end_at_gmt ) ),
				'units'     => (int) $line->units,
				'adults'    => (int) $line->adults,
				'children'  => (int) $line->children,
				'unit'      => Money::round( (float) $line->unit_price ),
				'total'     => Money::round( (float) $line->total ),
			);
		}

		$total = Money::round( (float) $booking->total );
		$rate  = max( 0, (float) rtbp_setting( 'invoices', 'taxRate', 0 ) );
		return array(
			'number'    => $number,
			'version'   => $version,
			'issued_at' => Dates::to_iso( $at ),
			'booking'   => array(
				'reference' => (string) $booking->reference,
				'made_at'   => $booking->created_at_gmt ? Dates::to_iso( Dates::from_gmt( (string) $booking->created_at_gmt ) ) : null,
			),
			'hotel'     => array(
				'name'        => (string) ( '' !== (string) ( $general['legalName'] ?? '' ) ? $general['legalName'] : ( $general['companyName'] ?? '' ) ),
				'trade_name'  => (string) ( $general['companyName'] ?? '' ),
				'address'     => (string) ( $general['address'] ?? '' ),
				'phone'       => (string) ( $general['phone'] ?? '' ),
				'email'       => (string) ( $general['contactEmail'] ?? '' ),
				'tax_number'  => (string) ( $general['taxNumber'] ?? '' ),
				'cnps_number' => (string) ( $general['cnpsNumber'] ?? '' ),
				'logo'        => (int) ( $general['logo'] ?? 0 ),
			),
			'guest'     => $guest ? array(
				'name'      => $guest->fullName(),
				'reference' => (string) $guest->reference,
				'phone'     => (string) $guest->phone,
				'email'     => $guest->email_is_placeholder ? '' : (string) $guest->email,
			) : null,
			'lines'     => $rows,
			'totals'    => array(
				'subtotal'       => Money::round( (float) $booking->subtotal ),
				'discount_total' => Money::round( (float) $booking->discount_total ),
				'total'          => $total,
				'currency'       => (string) $booking->currency,
				// Prices include the tax: the part of the total it is.
				'tax'            => $rate > 0 ? array(
					'label'    => (string) rtbp_setting( 'invoices', 'taxLabel', '' ),
					'rate'     => $rate,
					'included' => Money::round( $total - $total / ( 1 + $rate / 100 ) ),
				) : null,
			),
			'footer'    => (string) rtbp_setting( 'invoices', 'footerText', '' ),
		);
	}

	/**
	 * The part of a snapshot a revision is about: the rooms and the totals.
	 *
	 * @param array $snapshot Snapshot.
	 * @return string
	 */
	private static function billed( array $snapshot ): string {
		return (string) wp_json_encode(
			array(
				'lines'  => $snapshot['lines'] ?? array(),
				'totals' => array_intersect_key( (array) ( $snapshot['totals'] ?? array() ), array_flip( array( 'subtotal', 'discount_total', 'total' ) ) ),
			)
		);
	}

	/**
	 * Log a revision or a cancellation.
	 *
	 * @param Booking    $booking Booking.
	 * @param Invoice    $invoice Invoice (before).
	 * @param int        $version Version now.
	 * @param string     $status  revised|cancelled.
	 * @param float|null $before  Total before.
	 * @param float|null $after   Total after.
	 * @return void
	 */
	private function log( Booking $booking, Invoice $invoice, int $version, string $status, $before = null, $after = null ): void {
		rtbp_activity(
			'invoices.revise',
			self::subject( $booking ),
			array(
				'before'      => array_filter(
					array(
						'version' => (int) $invoice->version,
						'status'  => (string) $invoice->status,
						'total'   => $before,
					),
					static fn( $v ) => null !== $v
				),
				'after'       => array_filter(
					array(
						'version' => $version,
						'status'  => $status,
						'total'   => $after,
					),
					static fn( $v ) => null !== $v
				),
				'description' => sprintf(
					/* translators: 1: invoice number, 2: version number. */
					'cancelled' === $status ? __( 'Marked invoice %1$s cancelled', 'radius-hotel-booking' ) : __( 'Re-issued invoice %1$s as version %2$d', 'radius-hotel-booking' ),
					$invoice->number,
					$version
				),
			)
		);
	}

	/**
	 * The activity subject of a booking.
	 *
	 * @param Booking $booking Booking.
	 * @return array
	 */
	private static function subject( Booking $booking ): array {
		return array(
			'type'  => 'booking',
			'id'    => (int) $booking->id,
			'label' => (string) $booking->reference,
		);
	}
}
