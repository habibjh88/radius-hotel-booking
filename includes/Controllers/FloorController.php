<?php
/**
 * Floors API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\FloorRepository;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\FloorService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `floors` CRUD and `PUT floors/order` (M06). Reading needs `page.rooms`,
 * changing needs `rooms.manage`. Every response carries the whole ordered
 * list, which is short and what the Floors screen shows.
 */
class FloorController extends BaseController {

	/**
	 * Floor service.
	 *
	 * @var FloorService
	 */
	private FloorService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( FloorRepository::class ) );
		$this->service      = Container::resolve( FloorService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => 'page.rooms',
				'show'    => 'page.rooms',
			)
		);
	}

	/**
	 * GET /floors.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond( $request, fn() => ApiResponse::success( array( 'floors' => $this->service->all() ) ) );
	}

	/**
	 * GET /floors/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'floor' => $this->transformItem( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) )
		);
	}

	/**
	 * One floor for the API.
	 *
	 * @param mixed $item Floor.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return array(
			'id'         => (int) $item->id,
			'name'       => (string) $item->name,
			'sort_order' => (int) $item->sort_order,
		);
	}

	/**
	 * Floors for the API.
	 *
	 * @param array $items Floors.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return array_map( array( $this, 'transformItem' ), $items );
	}

	/**
	 * No request rules: FloorService validates.
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
		return 'Floor';
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
