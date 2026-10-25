<?php
/**
 * Rooms API controller.
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
use RadiusTheme\RadiusHotelBooking\Models\Room;
use RadiusTheme\RadiusHotelBooking\Repositories\RoomRepository;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\RoomService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `rooms` CRUD, `POST rooms/{id}/state` and `GET room-types/{id}/rooms`
 * (M06). Reading needs `page.rooms`; every change needs `rooms.manage`.
 */
class RoomController extends BaseController {

	/**
	 * Room service.
	 *
	 * @var RoomService
	 */
	private RoomService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( RoomRepository::class ) );
		$this->service      = Container::resolve( RoomService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'      => 'page.rooms',
				'show'       => 'page.rooms',
				'ofType'     => 'page.rooms',
				'store'      => 'rooms.manage',
				'update'     => 'rooms.manage',
				'destroy'    => 'rooms.manage',
				'state'      => 'rooms.manage',
				'bulk'       => 'rooms.manage',
				'bulkAction' => 'rooms.manage',
				'move'       => 'rooms.move',
				'bulkMove'   => 'rooms.move',
			)
		);
	}

	/**
	 * GET /rooms?room_type_id=: rooms of one type, in natural order. Without
	 * `room_type_id`, every room of the hotel with its type and floor names.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$type_id = absint( $request->get_param( 'room_type_id' ) );
				return ApiResponse::success(
					array(
						'rooms' => $type_id
							? $this->transformCollection( Container::resolve( RoomRepository::class )->ofType( $type_id ) )
							: $this->service->listAll(),
					)
				);
			}
		);
	}

	/**
	 * GET /rooms/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'room' => $this->transformItem( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) )
		);
	}

	/**
	 * GET /room-types/{id}/rooms: the type's rooms grouped by floor.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function ofType( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( $this->service->groupedByFloor( (int) $request->get_param( 'id' ) ) )
		);
	}

	/**
	 * POST /rooms.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created(
				array( 'room' => $this->transformItem( $this->service->create( (array) $request->get_json_params() ) ) ),
				__( 'Room added.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * PUT/PATCH /rooms/{id}: `{ number?, floor_id? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'room' => $this->transformItem( $this->service->update( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ) ) ),
				__( 'Room saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * POST /rooms/{id}/state: `{ state, note? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function state( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'room' => $this->transformItem( $this->service->setState( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ) ) ),
				__( 'Room state saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * POST /rooms/bulk: a range of rooms; `dry_run: true` returns the preview only.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function bulk( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$result = $this->service->bulk( (array) $request->get_json_params() );
				$dry    = rest_sanitize_boolean( $request->get_json_params()['dry_run'] ?? false );
				return ApiResponse::success(
					$result,
					$dry ? null : sprintf(
						/* translators: %d: number of rooms. */
						_n( '%d room added.', '%d rooms added.', count( $result['created'] ), 'radius-hotel-booking' ),
						count( $result['created'] )
					)
				);
			}
		);
	}

	/**
	 * POST /rooms/bulk-action: `{ action: remove|state, ids, state?, note? }`.
	 * Answers `{ done, failed }`; refused rooms do not stop the others.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function bulkAction( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$params = (array) $request->get_json_params();
				$result = $this->service->bulkAction( $params );
				$done   = count( $result['done'] );
				return ApiResponse::success(
					$result,
					'remove' === ( $params['action'] ?? '' )
						/* translators: %d: number of rooms. */
						? sprintf( _n( '%d room removed.', '%d rooms removed.', $done, 'radius-hotel-booking' ), $done )
						/* translators: %d: number of rooms. */
						: sprintf( _n( '%d room updated.', '%d rooms updated.', $done, 'radius-hotel-booking' ), $done )
				);
			}
		);
	}

	/**
	 * POST /rooms/bulk-move: `{ ids, room_type_id }`. Rooms with upcoming
	 * bookings are refused (move them one by one) and listed in `failed`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function bulkMove( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$result = $this->service->bulkMove( (array) $request->get_json_params() );
				$done   = count( $result['done'] );
				return ApiResponse::success(
					$result,
					sprintf(
						/* translators: 1: number of rooms, 2: room type name. */
						_n( '%1$d room moved to %2$s.', '%1$d rooms moved to %2$s.', $done, 'radius-hotel-booking' ),
						$done,
						$result['to']['name']
					)
				);
			}
		);
	}

	/**
	 * POST /rooms/{id}/move: `{ room_type_id, confirm?, bookings? }`. Without
	 * `confirm` it only answers the warning (upcoming bookings).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function move( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$result = $this->service->move( (int) $request->get_param( 'id' ), (array) $request->get_json_params() );
				return ApiResponse::success(
					$result,
					$result['moved']
						/* translators: 1: room number, 2: room type name. */
						? sprintf( __( 'Room %1$s moved to %2$s.', 'radius-hotel-booking' ), $result['room']['number'], $result['to']['name'] )
						: null
				);
			}
		);
	}

	/**
	 * DELETE /rooms/{id}: refused while it has future bookings.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array(), __( 'Room removed.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * One room for the API.
	 *
	 * @param mixed $item Room.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return RoomService::shape( $item );
	}

	/**
	 * Rooms for the API.
	 *
	 * @param array $items Rooms.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return array_map( static fn( Room $room ) => RoomService::shape( $room ), $items );
	}

	/**
	 * No request rules: RoomService validates.
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
		return 'Room';
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
