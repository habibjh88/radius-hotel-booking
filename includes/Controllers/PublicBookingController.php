<?php
/**
 * The public booking API: search, hold, book, and the guest's booking (M04, M05).
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\RateLimitMiddleware;
use RadiusTheme\RadiusHotelBooking\Frontend\PublicSession;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Services\Booking\PublicBookingService;
use RadiusTheme\RadiusHotelBooking\Services\Payments\GuestBookingView;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Every route here is **public** (no account, no nonce: the visitor is
 * anonymous) and listed through `rtbp_public_api_route_patterns`. Each has
 * its own per-IP limit (RateLimitMiddleware scopes), so staff routes are never
 * throttled and a busy search never uses up the booking limit:
 *
 * | Route | Limit |
 * |---|---|
 * | GET `public/availability` | 30 / min |
 * | POST `public/holds`, DELETE `public/holds/{token}` | 10 / min |
 * | POST `public/bookings` | 5 / min |
 * | GET `public/bookings/{token}` (M05, the 128-bit token is the key) | 30 / min |
 *
 * Holds belong to the visitor's `rtbp_guest` cookie (Frontend\PublicSession).
 * The rules live in `PublicBookingService`; this only reads input and answers.
 */
class PublicBookingController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( new BookingRepository() );
	}

	/**
	 * Let the routes through the capability check (they have none).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter(
			'rtbp_public_api_route_patterns',
			static function ( $patterns ) {
				$base                 = '#^/radius-hotel-booking/v1/public/';
				$patterns['GET'][]    = $base . 'bookings/[a-f0-9]{32}$#';
				$patterns['GET'][]    = $base . 'availability$#';
				$patterns['POST'][]   = $base . 'holds$#';
				$patterns['DELETE'][] = $base . 'holds/[A-Za-z0-9]{32}$#';
				$patterns['PUT'][]    = $base . 'holds/[A-Za-z0-9]{32}$#';
				$patterns['POST'][]   = $base . 'bookings$#';
				return $patterns;
			}
		);
	}

	/**
	 * GET public/availability?arrival=&departure=&adults=&children=&child_ages=&room_type_id=&rate_plan_id=&checkin_time=.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function availability( WP_REST_Request $request ) {
		return self::limited(
			$request,
			30,
			'public_availability',
			static function ( $request ) {
				$input = self::scalars( $request, array( 'arrival', 'departure', 'adults', 'children', 'room_type_id', 'rate_plan_id', 'checkin_time', 'hold_token' ) );
				$ages  = self::ages( $request->get_param( 'child_ages' ) );
				if ( $ages ) {
					$input['child_ages'] = $ages;
				}
				return ApiResponse::success( ( new PublicBookingService() )->availability( $input ) );
			}
		);
	}

	/**
	 * POST public/holds `{ room_type_id, rate_plan_id, arrival, departure, units?, checkin_time?, adults, children?, child_ages?, room_id?, token? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function holdStore( WP_REST_Request $request ) {
		return self::limited(
			$request,
			10,
			'public_holds',
			static function ( $request ) {
				$input = self::scalars( $request, array( 'room_type_id', 'rate_plan_id', 'arrival', 'departure', 'units', 'checkin_time', 'adults', 'children', 'room_id', 'token' ) );
				$ages  = self::ages( $request->get_param( 'child_ages' ) );
				if ( $ages ) {
					$input['child_ages'] = $ages;
				}
				$hold = ( new PublicBookingService() )->hold( $input, PublicSession::key( true ) );
				return ApiResponse::created( $hold, __( 'Room held for you.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * PUT public/holds/{token}: keep the visitor's held rooms another hold period.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function holdExtend( WP_REST_Request $request ) {
		return self::limited(
			$request,
			10,
			'public_holds',
			static function ( $request ) {
				return ApiResponse::success( ( new PublicBookingService() )->extend( (string) $request->get_param( 'token' ), PublicSession::key() ) );
			}
		);
	}

	/**
	 * DELETE public/holds/{token}[?hold_id=]: let the visitor's held rooms (or one) go.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function holdDestroy( WP_REST_Request $request ) {
		return self::limited(
			$request,
			10,
			'public_holds',
			static function ( $request ) {
				$released = ( new PublicBookingService() )->release( (string) $request->get_param( 'token' ), PublicSession::key(), absint( $request->get_param( 'hold_id' ) ) );
				return ApiResponse::success( array( 'released' => $released ) );
			}
		);
	}

	/**
	 * POST public/bookings `{ hold_token, lines[], guest{}, special_requests?, consent, accept_new_price? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return self::limited(
			$request,
			5,
			'public_bookings',
			static function ( $request ) {
				$result = ( new PublicBookingService() )->create( (array) $request->get_json_params(), PublicSession::key() );
				return ApiResponse::created( $result, __( 'Thank you — your booking has been received.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * GET public/bookings/{token}: the guest's booking (M05).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function showByToken( WP_REST_Request $request ) {
		return self::limited(
			$request,
			30,
			'public_booking_view',
			static function ( $request ) {
				$booking = GuestBookingView::byToken( sanitize_key( (string) $request->get_param( 'token' ) ) );
				if ( ! $booking ) {
					return ApiResponse::notFound( __( 'We could not find this booking.', 'radius-hotel-booking' ) );
				}
				return ApiResponse::success( array( 'booking' => GuestBookingView::data( $booking ) ) );
			}
		);
	}

	/**
	 * Run a handler under a per-IP limit; any error becomes the envelope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param int             $max     Requests per minute.
	 * @param string          $scope   Limit scope.
	 * @param callable        $handler Returns an ApiResponse.
	 * @return mixed
	 */
	private static function limited( WP_REST_Request $request, int $max, string $scope, callable $handler ) {
		return ( new RateLimitMiddleware( $max, MINUTE_IN_SECONDS, $scope ) )->handle(
			$request,
			static function ( $request ) use ( $handler ) {
				try {
					return $handler( $request )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * Scalar request parameters, sanitised.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string[]        $keys    Keys.
	 * @return array
	 */
	private static function scalars( WP_REST_Request $request, array $keys ): array {
		$out = array();
		foreach ( $keys as $key ) {
			$value = $request->get_param( $key );
			if ( null !== $value && '' !== $value && is_scalar( $value ) ) {
				$out[ $key ] = sanitize_text_field( (string) $value );
			}
		}
		return $out;
	}

	/**
	 * Child ages from `1,4` or an array.
	 *
	 * @param mixed $ages Raw.
	 * @return int[]
	 */
	private static function ages( $ages ): array {
		if ( is_string( $ages ) && '' !== $ages ) {
			$ages = explode( ',', $ages );
		}
		return is_array( $ages ) ? array_slice( array_map( 'absint', array_filter( $ages, 'is_scalar' ) ), 0, 10 ) : array();
	}

	/**
	 * Transform a single item.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Transform a collection.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * Validation rules (none: the service validates).
	 *
	 * @param mixed  $request Request.
	 * @param string $context Context.
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
