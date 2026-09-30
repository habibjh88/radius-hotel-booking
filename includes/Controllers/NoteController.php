<?php
/**
 * Notes API controller.
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
use RadiusTheme\RadiusHotelBooking\Models\Note;
use RadiusTheme\RadiusHotelBooking\Repositories\NoteRepository;
use RadiusTheme\RadiusHotelBooking\Services\Notes\NoteService;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Notes on any registered record type (`rtbp_note_types`):
 * `GET notes?type=guest&id=` · `POST notes { notable_type, notable_id, type, body }` ·
 * `GET` / `PUT` / `DELETE notes/{id}`. The access key comes from the record
 * type — for a guest `page.guests` to read, `guests.note_add|note_edit|
 * note_remove` to change; an unknown type has no key and is refused.
 */
class NoteController extends BaseController {

	/**
	 * Note service.
	 *
	 * @var NoteService
	 */
	private NoteService $service;

	/**
	 * Resolve dependencies.
	 */
	public function __construct() {
		parent::__construct( Container::resolve( NoteRepository::class ) );
		$this->service      = Container::resolve( NoteService::class );
		$this->middleware[] = new AuthMiddleware();
		$this->middleware[] = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$of                 = fn( $request ) => $this->service->notableTypeOf( (int) $request->get_param( 'id' ) );
		$this->middleware[] = new AccessMiddleware(
			array(
				'index'   => static fn( $request ) => NoteService::accessKey( sanitize_key( (string) $request->get_param( 'type' ) ), 'read' ),
				'store'   => static fn( $request ) => NoteService::accessKey( sanitize_key( (string) ( $request->get_json_params()['notable_type'] ?? '' ) ), 'add' ),
				'show'    => static fn( $request ) => NoteService::accessKey( $of( $request ), 'read' ),
				'update'  => static fn( $request ) => NoteService::accessKey( $of( $request ), 'edit' ),
				'destroy' => static fn( $request ) => NoteService::accessKey( $of( $request ), 'remove' ),
			)
		);
	}

	/**
	 * GET /notes?type=&id=.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function index( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$type  = sanitize_key( (string) $request->get_param( 'type' ) );
				$notes = $this->service->list( $type, absint( $request->get_param( 'id' ) ) );
				return ApiResponse::success(
					array(
						'notes'   => array_map( array( self::class, 'shape' ), $notes ),
						// What this viewer may do here (the server checks again).
						'can_add' => self::allowed( $type, 'add' ),
					)
				);
			}
		);
	}

	/**
	 * GET /notes/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function show( WP_REST_Request $request ) {
		return $this->respond( $request, fn( $request ) => ApiResponse::success( array( 'note' => self::shape( $this->service->get( (int) $request->get_param( 'id' ) ) ) ) ) );
	}

	/**
	 * POST /notes.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function store( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::created( array( 'note' => self::shape( $this->service->add( (array) $request->get_json_params() ) ) ), __( 'Note added.', 'radius-hotel-booking' ) )
		);
	}

	/**
	 * PUT /notes/{id}: `{ type?, body? }`.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function update( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			fn( $request ) => ApiResponse::success( array( 'note' => self::shape( $this->service->edit( (int) $request->get_param( 'id' ), (array) $request->get_json_params() ) ) ), __( 'Note saved.', 'radius-hotel-booking' ) )
		);
	}

	/**
	 * DELETE /notes/{id}.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function destroy( WP_REST_Request $request ) {
		return $this->respond(
			$request,
			function ( $request ) {
				$this->service->remove( (int) $request->get_param( 'id' ) );
				return ApiResponse::success( array(), __( 'Note removed.', 'radius-hotel-booking' ) );
			}
		);
	}

	/**
	 * A note for the API.
	 *
	 * @param Note $note Note.
	 * @return array
	 */
	public static function shape( Note $note ): array {
		$editor = $note->edited_by ? get_userdata( (int) $note->edited_by ) : null;
		return array(
			'id'         => (int) $note->id,
			'type'       => (string) $note->type,
			'body'       => (string) $note->body,
			'author'     => array(
				'id'   => $note->author_id ? (int) $note->author_id : null,
				'name' => (string) $note->author_name,
			),
			// BaseModel stamps these in local time.
			'created_at' => substr( (string) $note->created_at, 0, 16 ),
			'updated_at' => substr( (string) $note->updated_at, 0, 16 ),
			'edited'     => (bool) $note->edited_by,
			'edited_by'  => $editor ? (string) $editor->display_name : null,
			'can_edit'   => self::allowed( (string) $note->notable_type, 'edit' ),
			'can_remove' => self::allowed( (string) $note->notable_type, 'remove' ),
		);
	}

	/**
	 * Whether the viewer is not locked out of an action on a type.
	 *
	 * @param string $notable_type Type.
	 * @param string $action       Action.
	 * @return bool
	 */
	private static function allowed( string $notable_type, string $action ): bool {
		$key = NoteService::accessKey( $notable_type, $action );
		return '' !== $key && Access::LOCKED !== Access::level( $key );
	}

	/**
	 * Unused: shape() is used.
	 *
	 * @param mixed $item Item.
	 * @return array
	 */
	protected function transformItem( $item ): array {
		return (array) $item;
	}

	/**
	 * Unused: shape() is used.
	 *
	 * @param array $items Items.
	 * @return array
	 */
	protected function transformCollection( array $items ): array {
		return $items;
	}

	/**
	 * No request rules: NoteService validates.
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
		return 'Note';
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
