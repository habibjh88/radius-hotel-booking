<?php
/**
 * Blocked dates API controller.
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
use RadiusTheme\RadiusHotelBooking\Repositories\BlockRepository;
use RadiusTheme\RadiusHotelBooking\Services\Availability\BlockService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * `blocks` CRUD (feature 8.12). Reading needs `page.availability`, changing
 * needs `availability.manage`. `GET blocks?when=upcoming|past|all&scope=&page=&per_page=`.
 */
class BlockController extends BaseController {

	/**
	 * Block service.
	 *
	 * @var BlockService
	 */
	private BlockService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( BlockRepository::class ) );
		$this->service      = Container::resolve( BlockService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => 'page.availability',
				'show'    => 'page.availability',
				'store'   => 'availability.manage',
				'update'  => 'availability.manage',
				'destroy' => 'availability.manage',
			)
		);
	}

	/**
	 * GET /blocks.
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
					array(
						'when'  => sanitize_key( (string) $request->get_param( 'when' ) ),
						'scope' => sanitize_key( (string) $request->get_param( 'scope' ) ),
					),
					$page,
					$per ? $per : 20
				);
				return ApiResponse::success(
					array( 'blocks' => $data['items'] ),
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
	 * GET /blocks/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond( $request, fn( $request ) => ApiResponse::success( array( 'block' => $this->service->get( (int) $request->get_param( 'id' ) ) ) ) );
	}

	/**
	 * POST /blocks: `{ scope, scope_id, start_at, end_at, reason }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created( $this->service->create( (array) $request->get_json_params() ), __( 'Dates blocked.', 'radius-hotel-booking' ) )
		);
	}

	/**
	 * PUT /blocks/{id}: as store.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( $this->service->update( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ), __( 'Block saved.', 'radius-hotel-booking' ) )
		);
	}

	/**
	 * DELETE /blocks/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->delete( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array(), __( 'Block removed.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * Unused: the service shapes blocks.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused: the service shapes blocks.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: BlockService validates.
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
		return 'Block';
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
