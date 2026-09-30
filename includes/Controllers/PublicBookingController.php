<?php
/**
 * The guest's booking, by its public token (M05, 5.2).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\RateLimitMiddleware;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET public/bookings/{token}` — **public** (no account; the 128-bit token
 * is the key), rate-limited per IP: the guest's view of their booking as
 * JSON (`GuestBookingView::data()`), for the booking flow's own confirmation
 * screen (M04). The same data renders the page `/?rtbp_booking=<token>`.
 */
class PublicBookingController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( new BookingRepository() );
		$this->middleware[] = new RateLimitMiddleware( 120, HOUR_IN_SECONDS );
	}

	/**
	 * Let the route through the capability check (it has none).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter(
			'rtbp_public_api_route_patterns',
			static function ( $patterns ) {
				$patterns['GET'][] = '#^/radius-hotel-booking/v1/public/bookings/[a-f0-9]{32}$#';
				return $patterns;
			}
		);
	}

	/**
	 * GET public/bookings/{token}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function showByToken( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				$booking = GuestBookingView::byToken( sanitize_key( (string) $request->get_param( 'token' ) ) );
				if ( ! $booking ) {
					return ApiResponse::notFound( __( 'We could not find this booking.', 'radius-hotel-booking' ) )->send();
				}
				return ApiResponse::success( array( 'booking' => GuestBookingView::data( $booking ) ) )->send();
			}
		);
	}

	/**
	 * Unused.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No rules.
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
