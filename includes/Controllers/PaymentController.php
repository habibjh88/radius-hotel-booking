<?php
/**
 * Payments API (M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\PaymentRepository;
use RadiusTheme\RadiusHotelBooking\Resources\BookingResource;
use RadiusTheme\RadiusHotelBooking\Resources\PaymentResource;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingQuery;
use RadiusTheme\RadiusHotelBooking\Services\Payments\PaymentService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * - `GET bookings/{id}/payments` (`page.bookings`): the ledger, the money and the methods offered.
 * - `POST bookings/{id}/payments` (`payments.record`): `{ type (payment|refund), amount, method,
 *   reference?, note?, received_at? }`.
 * - `POST payments/{id}/void` (`payments.void`): `{ reason }`.
 * - `POST bookings/{id}/payment-status` (`payments.change_status`): `{ on_hold }` — the only
 *   status staff set by hand; paid / partially paid come from the ledger.
 *
 * Every write answers with the ledger **and** the booking as its screen shows it, so the screen
 * needs no second request.
 */
class PaymentController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( new PaymentRepository() );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'ledger'    => 'page.bookings',
				'record'    => 'payments.record',
				'voidRow'   => 'payments.void',
				'setOnHold' => 'payments.change_status',
			)
		);
	}

	/**
	 * GET bookings/{id}/payments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function ledger( WP_REST_Request $request ) {
		return $this->answer( $request, static fn( PaymentService $service, array $body, int $id ) => $id, '', false );
	}

	/**
	 * POST bookings/{id}/payments.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function record( WP_REST_Request $request ) {
		$refund = 'refund' === ( ( (array) $request->get_json_params() )['type'] ?? '' );
		return $this->answer(
			$request,
			static function ( PaymentService $service, array $body, int $id ) {
				$service->record( $id, $body );
				return $id;
			},
			$refund ? __( 'Refund recorded.', 'radius-hotel-booking' ) : __( 'Payment recorded.', 'radius-hotel-booking' )
		);
	}

	/**
	 * POST payments/{id}/void.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function voidRow( WP_REST_Request $request ) {
		return $this->answer(
			$request,
			static fn( PaymentService $service, array $body, int $id ) => (int) $service->void( $id, (string) ( $body['reason'] ?? '' ) )->booking_id,
			__( 'Payment voided.', 'radius-hotel-booking' )
		);
	}

	/**
	 * POST bookings/{id}/payment-status `{ on_hold }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function setOnHold( WP_REST_Request $request ) {
		$on = ! empty( ( (array) $request->get_json_params() )['on_hold'] );
		return $this->answer(
			$request,
			static fn( PaymentService $service, array $body, int $id ) => (int) $service->setOnHold( $id, $on )->id,
			$on ? __( 'Payment put on hold.', 'radius-hotel-booking' ) : __( 'Payment taken off hold.', 'radius-hotel-booking' )
		);
	}

	/**
	 * Run a call and answer with the ledger and the booking.
	 *
	 * @param WP_REST_Request $request      Request.
	 * @param callable        $call         `( PaymentService, body, id ) → booking id`.
	 * @param string          $message      Success message.
	 * @param bool            $with_booking Include the booking record (writes).
	 * @return mixed
	 */
	private function answer( WP_REST_Request $request, callable $call, string $message, bool $with_booking = true ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) use ( $call, $message, $with_booking ) {
				try {
					$service    = new PaymentService();
					$booking_id = $call( $service, (array) $request->get_json_params(), (int) $request->get_param( 'id' ) );
					$booking    = ( new BookingRepository() )->find( $booking_id );
					$data       = PaymentResource::ledger( $booking, $service->forBooking( $booking_id ) );
					if ( $with_booking ) {
						$data['booking'] = BookingResource::record( ( new BookingQuery() )->load( $booking_id ) );
					}
					return ApiResponse::success( $data, $message )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * Unused (no collection).
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused (no collection).
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: the service validates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $context Context.
	 * @return array
	 */
	protected function getValidationRules( $request, string $context = 'create' ) {
		unset( $request, $context );
		return array();
	}

	/**
	 * Resource type.
	 *
	 * @return string
	 */
	protected function getResourceType(): string {
		return 'Payment';
	}
}
