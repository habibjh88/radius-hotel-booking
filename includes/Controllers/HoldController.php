<?php
/**
 * Hold API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use RadiusTheme\RadiusHotelBooking\Services\Availability\HoldService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Staff holds (M08, feature 2.14): `POST holds`, `PUT holds/{token}`
 * (extend), `DELETE holds/{token}` (release; `?hold_id=` for one room).
 * Access `bookings.create`. The guest flow's holds come with M04, through
 * the same `HoldService` with the public audience.
 */
class HoldController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		// BaseController wants a model repository; holds do not use it.
		parent::__construct( Container::resolve( RoomRepository::class ) );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'store'   => 'bookings.create',
				'extend'  => 'bookings.create',
				'destroy' => 'bookings.create',
			)
		);
	}

	/**
	 * POST holds `{ room_id, rate_plan_id, arrival, units?, checkin_time?, room_type_id?, token? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$input = array();
					foreach ( array( 'room_id', 'rate_plan_id', 'arrival', 'units', 'checkin_time', 'room_type_id', 'token' ) as $key ) {
						$value = $request->get_param( $key );
						if ( null !== $value && is_scalar( $value ) ) {
							$input[ $key ] = sanitize_text_field( (string) $value );
						}
					}
					$hold = Container::resolve( HoldService::class )->create( $input, self::actor() );
					return ApiResponse::created( $hold, __( 'Room held.', 'radius-hotel-booking' ) )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * PUT holds/{token}: keep the holds for another hold period.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function extend( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$result = Container::resolve( HoldService::class )->extend( (string) $request->get_param( 'token' ), self::actor() );
					return ApiResponse::success( $result )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * DELETE holds/{token}[?hold_id=]: release.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$count = Container::resolve( HoldService::class )->release( (string) $request->get_param( 'token' ), self::actor(), absint( $request->get_param( 'hold_id' ) ) );
					return ApiResponse::success( array( 'released' => $count ) )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * The staff actor.
	 *
	 * @return array
	 */
	private static function actor(): array {
		return array(
			'audience' => AvailabilityService::STAFF,
			'user_id'  => get_current_user_id(),
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
		return 'Hold';
	}
}
