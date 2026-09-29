<?php
/**
 * Room type rates (Price & Rates grid) API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRateRepository;
use RadiusTheme\RadiusHotelBooking\Services\Pricing\RoomTypeRateService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `GET/PUT room-types/{id}/rates`: the whole grid in one call (M07).
 * Reading needs `page.rates`, saving needs `rates.manage`.
 */
class RoomTypeRateController extends BaseController {

	/**
	 * Grid service.
	 *
	 * @var RoomTypeRateService
	 */
	private RoomTypeRateService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( RoomTypeRateRepository::class ) );
		$this->service      = Container::resolve( RoomTypeRateService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'grid' => 'page.rates',
				'save' => 'rates.manage',
			)
		);
	}

	/**
	 * GET room-types/{id}/rates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function grid( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'rates' => $this->service->grid( (int) $request->get_param( 'id' ) ) ) )
		);
	}

	/**
	 * PUT room-types/{id}/rates: `{ rates: [ … ] }` in display order.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function save( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'rates' => $this->service->save( (int) $request->get_param( 'id' ), (array) ( $request->get_json_params()['rates'] ?? array() ) ) ),
				__( 'Prices saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * Rows are shaped by the service.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Rows are shaped by the service.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: RoomTypeRateService validates.
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
		return 'RoomTypeRate';
	}

	/**
	 * Run a handler behind the middleware and turn exceptions into the
	 * ApiResponse envelope.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $handler Returns an ApiResponse.
	 * @return mixed
	 */
	private function respond( WP_REST_Request $request, callable $handler ) {
		return $this->applyMiddleware(
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
}
