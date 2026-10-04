<?php
/**
 * Amenities API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\AmenityRepository;
use RadiusTheme\RadiusHotelBooking\Services\Inventory\AmenityService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `amenities` CRUD and `PUT amenities/order`: the shared amenity library.
 * Reading needs `page.rooms`, changing needs `room_types.manage`. Every
 * response carries the whole ordered list, which the Amenities screen shows.
 */
class AmenityController extends BaseController {

	/**
	 * Amenity service.
	 *
	 * @var AmenityService
	 */
	private AmenityService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( AmenityRepository::class ) );
		$this->service      = Container::resolve( AmenityService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => 'page.rooms',
				'show'    => 'page.rooms',
				'store'   => 'room_types.manage',
				'update'  => 'room_types.manage',
				'destroy' => 'room_types.manage',
				'order'   => 'room_types.manage',
				'common'  => 'page.rooms',
				'import'  => 'room_types.manage',
			)
		);
	}

	/**
	 * GET /amenities.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond( $request, fn() => ApiResponse::success( array( 'amenities' => $this->service->all() ) ) );
	}

	/**
	 * GET /amenities/common: the common amenities, grouped, each marked
	 * `added` when the library already holds it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function common( WP_REST_Request $request ) {
		return $this->respond( $request, fn() => ApiResponse::success( array( 'groups' => $this->service->common() ) ) );
	}

	/**
	 * POST /amenities/import: `{ names: [ … ] }`, added last; names already in
	 * the list are skipped.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function import( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$names = $request->get_json_params()['names'] ?? array();
				$added = $this->service->createMany( is_array( $names ) ? $names : array() );
				return ApiResponse::success(
					array(
						'added'     => $added,
						'amenities' => $this->service->all(),
					),
					sprintf(
						/* translators: %d: number of amenities. */
						_n( '%d amenity added.', '%d amenities added.', count( $added ), 'radius-hotel-booking' ),
						count( $added )
					)
				);
			}
		);
	}

	/**
	 * GET /amenities/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'amenity' => $this->transformItem( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) )
		);
	}

	/**
	 * POST /amenities: `{ name }`, added last.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$amenity = $this->service->create( (array) $request->get_json_params() );
				return ApiResponse::created(
					array(
						'amenity'   => $this->transformItem( $amenity ),
						'amenities' => $this->service->all(),
					),
					__( 'Amenity added.', 'radius-hotel-booking' )
				);
			}
		);
	}

	/**
	 * PUT /amenities/{id}: `{ name, merge? }`; `merge` folds it into the
	 * amenity that already has that name.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$id      = (int) $request->get_param( 'id' );
				$amenity = $this->service->update( $id, (array) $request->get_json_params() );
				return ApiResponse::success(
					array(
						'amenity'   => $this->transformItem( $amenity ),
						'amenities' => $this->service->all(),
					),
					// Another id back: it was merged into that one.
					(int) $amenity->id === $id ? __( 'Amenity renamed.', 'radius-hotel-booking' ) : __( 'Amenities merged.', 'radius-hotel-booking' )
				);
			}
		);
	}

	/**
	 * PUT /amenities/order: `{ ids: [ … ] }`, every amenity once, first to last.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function order( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'amenities' => $this->service->reorder( (array) ( $request->get_json_params()['ids'] ?? array() ) ) ),
				__( 'Amenity order saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * DELETE /amenities/{id}: also taken off every room type.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array( 'amenities' => $this->service->all() ), __( 'Amenity deleted.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * One amenity for the API.
	 *
	 * @param mixed $item Amenity.
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
	 * Amenities for the API.
	 *
	 * @param array $items Amenities.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return array_map( array( $this, 'transformItem' ), $items );
	}

	/**
	 * No request rules: AmenityService validates.
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
		return 'Amenity';
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
