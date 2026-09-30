<?php
/**
 * Bookings API (M02).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Resources\BookingResource;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `POST bookings` (`bookings.create`, plus `payments.record` for *Paid now* and
 * `guests.create` for a new guest): a desk booking — `{ hold_token?, lines: [ { room_id,
 * rate_plan_id, arrival, units?, checkin_time?, adults, children?, room_type_id?,
 * expected_total? } ], guest_id | guest: {…}, payment_state, note?, accept_new_price? }`.
 * Conflicts answer 409 with a code the flow understands: `room_unavailable`
 * (+ `index`, `room`, `booking_ref`), `price_changed` (+ `index`, `quote`),
 * `guest_banned`, `guest_exists` (a new guest's phone or e-mail is on file under
 * another name; `guests: [ { id, reference, name, match } ]`). The booking record (M03) adds the reading routes.
 */
class BookingController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( BookingRepository::class ) );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				// Every key the booking needs, so one PIN prompt can unlock them all:
				// "Paid now" records money taken, and a new guest is a guest created.
				'store' => static function ( $request ) {
					$body = (array) $request->get_json_params();
					$keys = array( 'bookings.create' );
					if ( 'paid' === ( $body['payment_state'] ?? '' ) ) {
						$keys[] = 'payments.record';
					}
					if ( empty( $body['guest_id'] ) ) {
						$keys[] = 'guests.create';
					}
					return $keys;
				},
			)
		);
	}

	/**
	 * POST bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$service = Container::resolve( BookingService::class );
					$booking = $service->create(
						(array) $request->get_json_params(),
						array(
							'audience' => AvailabilityService::STAFF,
							'user_id'  => get_current_user_id(),
						),
						'desk'
					);
					return ApiResponse::created(
						array(
							'booking' => BookingResource::detail(
								$booking,
								$service->lines( (int) $booking->id ),
								$booking->guest_id ? Container::resolve( GuestRepository::class )->find( (int) $booking->guest_id ) : null
							),
						),
						/* translators: %s: booking reference. */
						sprintf( __( 'Booking %s created.', 'radius-hotel-booking' ), $booking->reference )
					)->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * Unused (no collection yet).
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused (no collection yet).
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
		return 'Booking';
	}
}
