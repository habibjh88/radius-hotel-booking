<?php
/**
 * Room types API controller.
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
use RadiusTheme\RadiusHotelBooking\Models\RoomType;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomTypeRepository;
use RadiusTheme\RadiusHotelBooking\Resources\RoomTypeResource;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomTypeService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `room-types` CRUD (M06). Reading needs `page.rooms`, changing needs
 * `room_types.manage`. Validation lives in RoomTypeService.
 */
class RoomTypeController extends BaseController {

	/**
	 * Room type service.
	 *
	 * @var RoomTypeService
	 */
	private RoomTypeService $service;

	/**
	 * Rooms, for the counts.
	 *
	 * @var RoomRepository
	 */
	private RoomRepository $rooms;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( RoomTypeRepository::class ) );
		$this->service      = Container::resolve( RoomTypeService::class );
		$this->rooms        = Container::resolve( RoomRepository::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => 'page.rooms',
				'show'    => 'page.rooms',
				'store'   => 'room_types.manage',
				'update'  => 'room_types.manage',
				'destroy' => 'room_types.manage',
			)
		);
	}

	/**
	 * GET /room-types: every type with its room counts.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn() => ApiResponse::success( array( 'room_types' => $this->transformCollection( $this->service->all() ) ) )
		);
	}

	/**
	 * GET /room-types/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'room_type' => $this->transformItem( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) )
		);
	}

	/**
	 * POST /room-types.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created(
				array( 'room_type' => $this->transformItem( $this->service->create( (array) $request->get_json_params() ) ) ),
				__( 'Room type added.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * PUT/PATCH /room-types/{id}: only the fields given change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'room_type' => $this->transformItem( $this->service->update( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ) ) ),
				__( 'Room type saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * DELETE /room-types/{id}: refused while the type has rooms.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array(), __( 'Room type deleted.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * One room type for the API.
	 *
	 * @param mixed $item RoomType.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		$counts = $this->rooms->stateCounts( array( (int) $item->id ) );
		return RoomTypeResource::make( $item, $counts[ (int) $item->id ] ?? array() );
	}

	/**
	 * Room types for the API (one count query for all).
	 *
	 * @param array $items RoomType[].
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		$counts = $this->rooms->stateCounts();
		return array_map( static fn( RoomType $type ) => RoomTypeResource::make( $type, $counts[ (int) $type->id ] ?? array() ), $items );
	}

	/**
	 * No request rules: RoomTypeService validates.
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
		return 'RoomType';
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
