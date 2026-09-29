<?php
/**
 * Access key enforcement.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Middleware;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Access\AccessRegistry;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Enforces the M13 access map on a controller's actions (feature 13.16).
 *
 *   $this->middleware[] = new AccessMiddleware( array(
 *       'index'   => 'page.bookings',
 *       'store'   => 'bookings.create',
 *       'approve' => 'bookings.approve',
 *       'update'  => fn( WP_REST_Request $r ) => 'settings.' . $r['section'],
 *   ) );
 *
 * The action is the controller method the router dispatched to (the
 * `rtbp_action` request attribute). A key may be a string, a list (all must
 * pass) or a callable( $request ) returning either. `*` is the fallback; a
 * single string applies to every action. An action with no key is refused.
 *
 * Levels: `open` continues; `locked` answers 403 `access_locked`; `passcode`
 * is handed to the `rtbp_access_passcode_check` filter (Pro), and is treated
 * as `locked` when nothing answers. When several keys need a passcode, the
 * refusal lists them all (`data.keys`) so one prompt can unlock them.
 *
 * Run it after PermissionMiddleware, which checks the nonce and the referer.
 */
class AccessMiddleware implements MiddlewareInterface {

	/**
	 * Action => key(s).
	 *
	 * @var array<string, string|string[]|callable>
	 */
	private array $keys;

	/**
	 * Constructor.
	 *
	 * @param string|array $keys One key for every action, or action => key(s).
	 */
	public function __construct( $keys ) {
		$this->keys = is_array( $keys ) ? $keys : array( '*' => $keys );
	}

	/**
	 * Check every key of the action, in order; the first refusal answers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $next    Next handler.
	 * @return mixed
	 */
	public function handle( WP_REST_Request $request, callable $next ) {
		$keys = $this->keysFor( $request );

		if ( ! $keys ) {
			return $this->deny( $request, '' );
		}

		$pending = array();
		foreach ( $keys as $key ) {
			$level = Access::level( $key );

			if ( Access::OPEN === $level ) {
				continue;
			}

			if ( Access::PASSCODE === $level ) {
				/**
				 * Checks the passcode level for a request (Pro). Return true to let
				 * the request through, or a WP_Error to refuse it (its code, e.g.
				 * `passcode_required`, goes to the client). Leave null to treat the
				 * key as locked.
				 *
				 * @param true|WP_Error|null   $result  Null when unanswered.
				 * @param string               $key     Access key.
				 * @param WP_REST_Request|null $request Request; null from Access::can().
				 */
				$result = apply_filters( 'rtbp_access_passcode_check', null, $key, $request );

				if ( true === $result ) {
					continue;
				}
				if ( $result instanceof WP_Error ) {
					// Keep checking: a locked key still refuses outright, and every
					// passcode key is reported, so one prompt can unlock them all.
					$pending[ $key ] = $result;
					continue;
				}
			}

			return $this->deny( $request, $key );
		}

		if ( $pending ) {
			$first = reset( $pending );
			return ApiResponse::error( $first->get_error_message(), 403 )
				->withData(
					array(
						'key'  => (string) key( $pending ),
						'keys' => array_keys( $pending ),
					)
				)
				->withCode( (string) $first->get_error_code() )
				->send();
		}

		foreach ( $keys as $key ) {
			if ( 'GET' === $request->get_method() && 'page' === ( AccessRegistry::get( $key )['kind'] ?? '' ) ) {
				/**
				 * Fires when a page's data is read (M14 records page views).
				 *
				 * @param string          $key     Page key.
				 * @param int             $user_id User id.
				 * @param WP_REST_Request $request Request.
				 */
				do_action( 'rtbp_page_viewed', $key, get_current_user_id(), $request );
			}
		}

		return $next( $request );
	}

	/**
	 * Nothing to bypass: access is always checked.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param callable        $next    Next handler.
	 * @return mixed
	 */
	public function byPassRequest( WP_REST_Request $request, callable $next ) {
		return $this->handle( $request, $next );
	}

	/**
	 * The keys the request's action needs.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string[]
	 */
	private function keysFor( WP_REST_Request $request ): array {
		$action = (string) ( $request->get_attributes()['rtbp_action'] ?? '' );
		$keys   = $this->keys[ $action ] ?? ( $this->keys['*'] ?? null );

		if ( is_callable( $keys ) && ! is_string( $keys ) ) {
			$keys = $keys( $request );
		}

		return array_values( array_filter( array_map( 'strval', (array) $keys ) ) );
	}

	/**
	 * Refuse a request: 403 `access_locked`, plus the denial hook and event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @param string          $key     The refused key ('' when the action has none).
	 * @return mixed
	 */
	private function deny( WP_REST_Request $request, string $key ) {
		$user_id = get_current_user_id();

		/**
		 * Fires when a request is refused by the access map.
		 *
		 * @param string          $key     Access key ('' = the action declares none).
		 * @param int             $user_id User id.
		 * @param WP_REST_Request $request Request.
		 */
		do_action( 'rtbp_access_denied', $key, $user_id, $request );

		// The activity emitter arrives with M14.
		if ( function_exists( 'rtbp_activity' ) ) {
			\rtbp_activity(
				'security.denied',
				null,
				array(
					'key'    => $key,
					'method' => $request->get_method(),
					'route'  => $request->get_route(),
				)
			);
		}

		/**
		 * Filters the message shown when something is locked (Pro: Settings → Access).
		 *
		 * @param string $message Message.
		 * @param string $key     Access key.
		 */
		$message = (string) apply_filters(
			'rtbp_access_locked_message',
			__( 'You do not have access to this. Ask a manager if you need it.', 'radius-hotel-booking' ),
			$key
		);

		return ApiResponse::forbidden( $message )
			->withData( array( 'key' => $key ) )
			->withCode( 'access_locked' )
			->send();
	}
}
