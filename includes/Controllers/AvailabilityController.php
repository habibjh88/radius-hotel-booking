<?php
/**
 * Availability API controller.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseController;
use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Container\Container;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Resources\AvailabilityResource;
use RadiusTheme\RadiusHotelBooking\Services\Availability\AvailabilityService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET availability` (M08): the staff search. Open to anyone who may see the
 * availability page **or** take a booking — a receptionist with the page
 * locked must still find a room for a guest. Reads only.
 *
 * The public search (the guest booking flow) is added by M04 with the public
 * shape, `AvailabilityResource::public()`, behind its own rate limit.
 */
class AvailabilityController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		// BaseController wants a model repository; the search does not use it.
		parent::__construct( Container::resolve( RoomRepository::class ) );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				// Either key opens it: the one that is not locked is checked.
				'search' => static fn() => Access::LOCKED !== Access::level( 'page.availability' ) ? 'page.availability' : 'bookings.create',
			)
		);
	}

	/**
	 * GET availability?arrival=&departure=&adults=&children=&child_ages[]=
	 * &room_type_id=&rate_plan_id=&checkin_time=&hold_token=&exclude_booking_line=
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function search( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$input = array();
					foreach ( array( 'arrival', 'departure', 'adults', 'children', 'room_type_id', 'rate_plan_id', 'checkin_time', 'hold_token', 'exclude_booking_line' ) as $key ) {
						$value = $request->get_param( $key );
						if ( null !== $value && '' !== $value && is_scalar( $value ) ) {
							$input[ $key ] = sanitize_text_field( (string) $value );
						}
					}
					$ages = $request->get_param( 'child_ages' );
					if ( is_string( $ages ) && '' !== $ages ) {
						$ages = explode( ',', $ages );
					}
					if ( is_array( $ages ) ) {
						$input['child_ages'] = array_map( 'absint', array_filter( $ages, 'is_scalar' ) );
					}

					$result = Container::resolve( AvailabilityService::class )->search( $input, AvailabilityService::STAFF );
					return ApiResponse::success( AvailabilityResource::staff( $result ) )->send();
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
		return 'Availability';
	}
}
