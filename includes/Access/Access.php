<?php
/**
 * Access level resolver.
 *
 * @package RadiusTheme\RadiusHotelBooking\Access
 */

namespace RadiusTheme\RadiusHotelBooking\Access;

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves a user's level (`open`, `passcode`, `locked`) for an access key
 * (M13, ADR-004):
 *
 *   1. the level stored for the user's plugin role (Settings → Permissions),
 *      or that role's code default; with several roles the most permissive wins
 *   2. the key's registry default
 *   3. the `rtbp_access_level` filter (Pro: custom role, then per-person override)
 *   4. administrators are never locked out: `locked` becomes `passcode` when a
 *      passcode handler exists, `open` otherwise
 *
 * A user without the dashboard capability, and an unknown key, are `locked`.
 */
final class Access {

	const OPEN     = 'open';
	const PASSCODE = 'passcode';
	const LOCKED   = 'locked';

	/**
	 * Resolved maps, per user id, for this request.
	 *
	 * @var array<int, array<string, string>>
	 */
	private static array $cache = array();

	/**
	 * Resolved role_defaults(), for this request.
	 *
	 * @var array<string, array<string, string>>|null
	 */
	private static ?array $role_defaults = null;

	/**
	 * Every level, most permissive first.
	 *
	 * @return string[]
	 */
	public static function levels(): array {
		return array( self::OPEN, self::PASSCODE, self::LOCKED );
	}

	/**
	 * A user's level for one key.
	 *
	 * @param string           $key  Access key, e.g. `bookings.cancel`.
	 * @param int|WP_User|null $user User; null = the current user.
	 * @return string One of levels().
	 */
	public static function level( string $key, $user = null ): string {
		$user = self::user( $user );
		if ( ! $user ) {
			return self::LOCKED;
		}

		if ( ! isset( self::$cache[ $user->ID ][ $key ] ) ) {
			self::$cache[ $user->ID ][ $key ] = self::resolve( $key, $user );
		}

		return self::$cache[ $user->ID ][ $key ];
	}

	/**
	 * Whether the current user may do something now: `open`, or `passcode`
	 * with a valid unlock (checked by the `rtbp_access_passcode_check` filter,
	 * with no request). Services reachable from several endpoints re-check
	 * with this; controllers use AccessMiddleware.
	 *
	 * @param string $key Access key.
	 * @return bool
	 */
	public static function can( string $key ): bool {
		$level = self::level( $key );

		if ( self::PASSCODE === $level ) {
			/** This filter is documented in includes/Core/Api/Middleware/AccessMiddleware.php */
			return true === apply_filters( 'rtbp_access_passcode_check', null, $key, null );
		}

		return self::OPEN === $level;
	}

	/**
	 * A user's level for every registered key.
	 *
	 * @param int|WP_User|null $user User; null = the current user.
	 * @return array<string, string> Key => level.
	 */
	public static function map( $user = null ): array {
		$user = self::user( $user );
		$map  = array();
		foreach ( array_keys( AccessRegistry::keys() ) as $key ) {
			$map[ $key ] = $user ? self::level( $key, $user ) : self::LOCKED;
		}
		return $map;
	}

	/**
	 * Whether something handles the `passcode` level. The free plugin does not:
	 * without a handler, `passcode` is enforced as `locked`.
	 *
	 * @return bool
	 */
	public static function passcode_supported(): bool {
		return (bool) has_filter( 'rtbp_access_passcode_check' );
	}

	/**
	 * Built-in role => key => level defaults in code. Stored levels (Settings →
	 * Permissions) override them key by key.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function role_defaults(): array {
		if ( null !== self::$role_defaults ) {
			return self::$role_defaults;
		}

		// The manager runs the hotel: everything is open until narrowed.
		$defaults = array(
			'rtbp_manager' => array_fill_keys( array_keys( AccessRegistry::keys() ), self::OPEN ),
		);

		/**
		 * Filters the code defaults of the built-in roles.
		 *
		 * @param array $defaults Role slug => key => level.
		 */
		self::$role_defaults = (array) apply_filters( 'rtbp_access_role_defaults', $defaults );
		return self::$role_defaults;
	}

	/**
	 * A role's own level for a key (stored, then code default), or null when
	 * the role does not set it.
	 *
	 * @param string $role Role slug.
	 * @param string $key  Access key.
	 * @return string|null
	 */
	public static function role_level( string $role, string $key ): ?string {
		$stored = rtbp_setting( 'access', 'roleLevels', array() );
		$level  = $stored[ $role ][ $key ] ?? ( self::role_defaults()[ $role ][ $key ] ?? null );

		return in_array( $level, self::levels(), true ) ? $level : null;
	}

	/**
	 * Forget resolved levels (after a permission change, and in logic checks).
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$cache         = array();
		self::$role_defaults = null;
	}

	/**
	 * Resolve one key for one user.
	 *
	 * @param string  $key  Access key.
	 * @param WP_User $user User.
	 * @return string
	 */
	private static function resolve( string $key, WP_User $user ): string {
		$definition = AccessRegistry::get( $key );
		if ( null === $definition || ! user_can( $user, Capabilities::VIEW_DASHBOARD ) ) {
			return self::LOCKED;
		}

		$level = null;
		foreach ( array_intersect( (array) $user->roles, Capabilities::manageableRoles() ) as $role ) {
			$level = self::most_permissive( $level, self::role_level( $role, $key ) );
		}
		$level ??= $definition['default'];

		/**
		 * Filters a user's level for an access key. Pro applies custom access
		 * roles and per-person overrides here.
		 *
		 * @param string  $level      open | passcode | locked.
		 * @param string  $key        Access key.
		 * @param WP_User $user       User.
		 * @param array   $definition The key's registry definition.
		 */
		$filtered = apply_filters( 'rtbp_access_level', $level, $key, $user, $definition );
		$level    = in_array( $filtered, self::levels(), true ) ? $filtered : $level;

		// Administrators are never locked out; they still confirm with a PIN
		// when something handles the passcode level.
		if ( self::LOCKED === $level && user_can( $user, 'manage_options' ) ) {
			$level = self::passcode_supported() ? self::PASSCODE : self::OPEN;
		}

		return $level;
	}

	/**
	 * The more permissive of two levels (null = not set).
	 *
	 * @param string|null $a Level.
	 * @param string|null $b Level.
	 * @return string|null
	 */
	private static function most_permissive( ?string $a, ?string $b ): ?string {
		if ( null === $a || null === $b ) {
			return $a ?? $b;
		}
		return array_search( $a, self::levels(), true ) <= array_search( $b, self::levels(), true ) ? $a : $b;
	}

	/**
	 * Normalise the user argument.
	 *
	 * @param int|WP_User|null $user User.
	 * @return WP_User|null A logged-in user, or null.
	 */
	private static function user( $user ): ?WP_User {
		if ( null === $user ) {
			$user = wp_get_current_user();
		} elseif ( is_numeric( $user ) ) {
			$user = get_user_by( 'id', (int) $user );
		}

		return $user instanceof WP_User && $user->exists() ? $user : null;
	}
}
