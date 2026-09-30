<?php
/**
 * Guests API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Resources\GuestResource;
use RadiusTheme\RadiusHotelBooking\Services\Guests\GuestService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Guests (M09):
 * - `GET guests?q=&standing=&page=&per_page=` and `GET guests/{id}` (`page.guests`);
 * - `GET guests/lookup?q=` (`bookings.create`: the booking forms' quick search);
 * - `POST guests` (`guests.create`; 409 `guest_exists` with the record on file);
 * - `PUT guests/{id}` (`guests.edit`);
 * - `POST guests/{id}/ban` · `/unban` `{ reason }` (`guests.ban`);
 * - `GET guests/{id}/stays` (`page.guests`);
 * - `GET guests/{id}/id-number` (`guests.view_id`, logged): the only way to the full number.
 */
class GuestController extends BaseController {

	/**
	 * Guest service.
	 *
	 * @var GuestService
	 */
	private GuestService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( GuestRepository::class ) );
		$this->service      = Container::resolve( GuestService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'    => 'page.guests',
				'show'     => 'page.guests',
				'stays'    => 'page.guests',
				'lookup'   => 'bookings.create',
				'store'    => 'guests.create',
				'update'   => 'guests.edit',
				'ban'      => 'guests.ban',
				'unban'    => 'guests.ban',
				'idNumber' => 'guests.view_id',
			)
		);
	}

	/**
	 * GET /guests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$page = max( 1, absint( $request->get_param( 'page' ) ) );
				$per  = absint( $request->get_param( 'per_page' ) );
				$data = $this->service->page(
					sanitize_text_field( (string) $request->get_param( 'q' ) ),
					sanitize_key( (string) $request->get_param( 'standing' ) ),
					$page,
					$per ? $per : 20
				);
				return ApiResponse::success(
					array(
						'guests'   => array_map( array( GuestResource::class, 'summary' ), $data['items'] ),
						'id_types' => GuestService::idTypes(),
					),
					null,
					array(
						'total' => $data['total'],
						'page'  => $page,
					)
				);
			}
		);
	}

	/**
	 * GET /guests/lookup.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function lookup( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array(
					'guests'   => array_map( array( GuestResource::class, 'summary' ), $this->service->lookup( sanitize_text_field( (string) $request->get_param( 'q' ) ) ) ),
					// The booking flow's new-guest form needs them; its staff may not see the guest list.
					'id_types' => GuestService::idTypes(),
				)
			)
		);
	}

	/**
	 * GET /guests/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array(
					'guest'    => GuestResource::detail( $this->service->get( (int) $request->get_param( 'id' ) ) ),
					'id_types' => GuestService::idTypes(),
				)
			)
		);
	}

	/**
	 * POST /guests.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created(
				array( 'guest' => GuestResource::detail( $this->service->create( self::fields( $request ) ) ) ),
				__( 'Guest added.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * PUT /guests/{id}: only the fields sent change.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'guest' => GuestResource::detail( $this->service->update( (int) $request->get_param( 'id' ), self::fields( $request ) ) ) ),
				__( 'Guest saved.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * POST /guests/{id}/ban.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function ban( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'guest' => GuestResource::detail( $this->service->ban( (int) $request->get_param( 'id' ), (string) ( $request->get_json_params()['reason'] ?? '' ) ) ) ),
				__( 'Guest banned.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * POST /guests/{id}/unban.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function unban( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success(
				array( 'guest' => GuestResource::detail( $this->service->unban( (int) $request->get_param( 'id' ), (string) ( $request->get_json_params()['reason'] ?? '' ) ) ) ),
				__( 'Ban lifted.', 'radius-hotel-booking' )
			)
		);
	}

	/**
	 * GET /guests/{id}/stays.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function stays( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'stays' => $this->service->stays( (int) $request->get_param( 'id' ) ) ) )
		);
	}

	/**
	 * GET /guests/{id}/id-number: the full number, logged.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function idNumber( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( $this->service->revealId( (int) $request->get_param( 'id' ) ) )
		);
	}

	/**
	 * The guest fields of a request body (only those present).
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array
	 */
	private static function fields( WP_REST_Request $request ): array {
		$body = (array) $request->get_json_params();
		return array_intersect_key( $body, array_flip( array( 'first_name', 'last_name', 'phone', 'email', 'id_type', 'id_number' ) ) );
	}

	/**
	 * Unused: the resource shapes guests.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused: the resource shapes guests.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: GuestService validates.
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
		return 'Guest';
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
