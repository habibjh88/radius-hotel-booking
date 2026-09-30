<?php
/**
 * Availability calendar API controller.
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
use RadiusTheme\RadiusHotelBooking\Services\Availability\CalendarService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET availability/calendar?room_type_id=&month=` (`page.availability`) and
 * `PUT availability/calendar` `{ room_type_id, cells: [ … ] }`
 * (`availability.manage`), features 8.1–8.4 and 8.6, and
 * `POST availability/calendar/bulk` (`availability.manage`), feature 8.5.
 */
class CalendarController extends BaseController {

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		// BaseController wants a model repository; the calendar does not use it.
		parent::__construct( Container::resolve( RoomRepository::class ) );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'grid' => 'page.availability',
				'save' => 'availability.manage',
				'bulk' => 'availability.manage',
			)
		);
	}

	/**
	 * GET: the month grid of one room type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function grid( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$grid = Container::resolve( CalendarService::class )->grid( absint( $request->get_param( 'room_type_id' ) ), sanitize_text_field( (string) $request->get_param( 'month' ) ) );
					return ApiResponse::success( $grid )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * PUT: change cells.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function save( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$body   = (array) $request->get_json_params();
					$cells  = isset( $body['cells'] ) && is_array( $body['cells'] ) ? $body['cells'] : array();
					$result = Container::resolve( CalendarService::class )->save( absint( $body['room_type_id'] ?? 0 ), $cells );
					return ApiResponse::success( $result, __( 'Calendar saved.', 'radius-hotel-booking' ) )->send();
				} catch ( \Throwable $e ) {
					return ApiResponse::fromThrowable( $e )->send();
				}
			}
		);
	}

	/**
	 * POST: bulk update, or its preview when `preview` is true.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function bulk( WP_REST_Request $request ) {
		return $this->applyMiddleware(
			$request,
			static function ( $request ) {
				try {
					$body    = (array) $request->get_json_params();
					$preview = rest_sanitize_boolean( $body['preview'] ?? false );
					$input   = array(
						'from'          => sanitize_text_field( (string) ( $body['from'] ?? '' ) ),
						'to'            => sanitize_text_field( (string) ( $body['to'] ?? '' ) ),
						'weekdays'      => is_array( $body['weekdays'] ?? null ) ? $body['weekdays'] : array(),
						'rate_plan_ids' => is_array( $body['rate_plan_ids'] ?? null ) ? $body['rate_plan_ids'] : array(),
						'action'        => sanitize_key( (string) ( $body['action'] ?? '' ) ),
						'price'         => isset( $body['price'] ) && is_scalar( $body['price'] ) ? $body['price'] : null,
					);
					$result  = Container::resolve( CalendarService::class )->bulk( absint( $body['room_type_id'] ?? 0 ), $input, ! $preview );
					return ApiResponse::success( $result, $preview ? null : __( 'Calendar saved.', 'radius-hotel-booking' ) )->send();
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
		return 'AvailabilityCalendar';
	}
}
