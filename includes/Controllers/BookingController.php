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
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingLineService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingQuery;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingService;
use RadiusTheme\RadiusHotelBooking\Services\Booking\BookingStatusService;
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
				'show'     => 'page.bookings',
				'approve'  => 'bookings.approve',
				'decline'  => 'bookings.decline',
				'cancel'   => 'bookings.cancel',
				'checkIn'  => 'bookings.check_in',
				'checkOut' => 'bookings.check_out',
				'noShow'   => 'bookings.no_show',
				'freeRooms' => 'bookings.check_in',
				'addLine'    => 'bookings.line_add',
				'editLine'   => 'bookings.line_edit',
				'removeLine' => 'bookings.line_remove',
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
	 * GET bookings/{id}: the booking screen, in one response.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					return ApiResponse::success(
						array( 'booking' => BookingResource::record( ( new BookingQuery() )->load( (int) $request->get_param( 'id' ) ) ) )
					)->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * POST bookings/{id}/approve `{ line_id? }` (3.6).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function approve( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->approve( $id, (int) ( $body['line_id'] ?? 0 ) ), __( 'Booking approved.', 'radius-hotel-booking' ) );
	}

	/**
	 * POST bookings/{id}/decline `{ reason, line_id? }` (3.7).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function decline( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->decline( $id, (string) ( $body['reason'] ?? '' ), (int) ( $body['line_id'] ?? 0 ) ), __( 'Booking declined.', 'radius-hotel-booking' ) );
	}

	/**
	 * POST bookings/{id}/cancel `{ reason, line_id? }` (3.8).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function cancel( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->cancel( $id, (string) ( $body['reason'] ?? '' ), (int) ( $body['line_id'] ?? 0 ) ), __( 'Booking cancelled.', 'radius-hotel-booking' ) );
	}

	/**
	 * POST booking-lines/{id}/check-in `{ room_id? }` (3.9).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function checkIn( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->checkIn( $id, (int) ( $body['room_id'] ?? 0 ) ), __( 'Checked in.', 'radius-hotel-booking' ) );
	}

	/**
	 * POST booking-lines/{id}/check-out (3.10).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function checkOut( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->checkOut( $id ), __( 'Checked out.', 'radius-hotel-booking' ) );
	}

	/**
	 * POST booking-lines/{id}/no-show.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function noShow( WP_REST_Request $request ) {
		return $this->transition( $request, static fn( $service, $body, $id ) => $service->noShow( $id ), __( 'Marked as a no-show.', 'radius-hotel-booking' ) );
	}

	/**
	 * GET booking-lines/{id}/rooms: where the guest could move at check-in.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function freeRooms( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					return ApiResponse::success( array( 'rooms' => ( new BookingStatusService() )->freeRooms( (int) $request->get_param( 'id' ) ) ) )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * POST bookings/{id}/lines `{ room_id, rate_plan_id, arrival, units?, checkin_time?, adults, children?,
	 * room_type_id?, expected_total?, hold_token?, accept_new_price? }` (3.11).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function addLine( WP_REST_Request $request ) {
		return $this->lineChange(
			$request,
			static function ( BookingLineService $service, array $body, int $id ) {
				$service->add( $id, $body, self::staffActor() );
				return $id;
			},
			__( 'Room added.', 'radius-hotel-booking' )
		);
	}

	/**
	 * PUT booking-lines/{id} — any of the add fields (3.12).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function editLine( WP_REST_Request $request ) {
		return $this->lineChange( $request, static fn( BookingLineService $service, array $body, int $id ) => $service->edit( $id, $body, self::staffActor() ), __( 'Room updated.', 'radius-hotel-booking' ) );
	}

	/**
	 * DELETE booking-lines/{id} (3.12).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function removeLine( WP_REST_Request $request ) {
		return $this->lineChange( $request, static fn( BookingLineService $service, array $body, int $id ) => $service->remove( $id ), __( 'Room removed.', 'radius-hotel-booking' ) );
	}

	/**
	 * Run a line change and answer with the booking as it is now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $change  `( BookingLineService, body, id ) → booking id`.
	 * @param string          $message Success message.
	 * @return mixed
	 */
	private function lineChange( WP_REST_Request $request, callable $change, string $message ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) use ( $change, $message ) {
				try {
					$booking_id = $change( new BookingLineService(), (array) $request->get_json_params(), (int) $request->get_param( 'id' ) );
					return ApiResponse::success(
						array( 'booking' => BookingResource::record( ( new BookingQuery() )->load( $booking_id ) ) ),
						$message
					)->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * The desk user, as the owner of their holds.
	 *
	 * @return array
	 */
	private static function staffActor(): array {
		return array(
			'audience' => AvailabilityService::STAFF,
			'user_id'  => get_current_user_id(),
		);
	}

	/**
	 * Run a status change and answer with the booking as it is now.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $move    `( BookingStatusService, body, id ) → booking id`.
	 * @param string          $message Success message.
	 * @return mixed
	 */
	private function transition( WP_REST_Request $request, callable $move, string $message ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) use ( $move, $message ) {
				try {
					$booking_id = $move( new BookingStatusService(), (array) $request->get_json_params(), (int) $request->get_param( 'id' ) );
					return ApiResponse::success(
						array( 'booking' => BookingResource::record( ( new BookingQuery() )->load( $booking_id ) ) ),
						$message
					)->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
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
