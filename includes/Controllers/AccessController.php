<?php
/**
 * Access API controller.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

use RadiusTheme\RadiusHotelBooking\Access\Access;
use RadiusTheme\RadiusHotelBooking\Access\AccessRegistry;
use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * The current user's access map (M13). The screens read it through
 * `useAccess()`; the server enforces it with AccessMiddleware.
 */
class AccessController {

	/**
	 * GET /access/me: key => level for the current user. Any dashboard user
	 * may read their own map.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function me( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				fn() => ApiResponse::success( self::payload() )->send()
			)
		);
	}

	/**
	 * GET /access/registry: the keys, groups and built-in roles, for the
	 * permission matrix (Settings → Permissions). The stored levels come from
	 * the `access` settings section.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function registry( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$access     = new AccessMiddleware( 'access.manage' );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				fn( $request ) => $access->handle(
					$request,
					fn() => ApiResponse::success( self::registryPayload() )->send()
				)
			)
		);
	}

	/**
	 * Groups, keys (in registry order) and the built-in roles with their code
	 * defaults.
	 *
	 * @return array
	 */
	public static function registryPayload(): array {
		$groups = array();
		foreach ( AccessRegistry::groups() as $key => $label ) {
			$groups[] = array(
				'key'   => $key,
				'label' => $label,
			);
		}

		$keys = array();
		foreach ( AccessRegistry::keys() as $definition ) {
			$keys[] = array(
				'key'     => $definition['key'],
				'label'   => $definition['label'],
				'kind'    => $definition['kind'],
				'group'   => $definition['group'],
				'default' => $definition['default'],
			);
		}

		$names    = wp_roles()->get_names();
		$defaults = Access::role_defaults();
		$roles    = array();
		foreach ( Capabilities::manageableRoles() as $slug ) {
			$roles[] = array(
				'slug'     => $slug,
				'name'     => translate_user_role( $names[ $slug ] ?? $slug ),
				'defaults' => (object) ( $defaults[ $slug ] ?? array() ),
			);
		}

		return array(
			'groups' => $groups,
			'keys'   => $keys,
			'roles'  => $roles,
		);
	}

	/**
	 * The current user's map, as `access/me` and the localized admin data send it.
	 *
	 * @return array{levels: array<string, string>, lockedMessage: string}
	 */
	public static function payload(): array {
		return array(
			'levels'        => Access::map(),
			/** This filter is documented in includes/Core/Api/Middleware/AccessMiddleware.php */
			'lockedMessage' => (string) apply_filters(
				'rtbp_access_locked_message',
				__( 'You do not have access to this. Ask a manager if you need it.', 'radius-hotel-booking' ),
				''
			),
		);
	}
}
