<?php
/**
 * Records permission changes.
 *
 * @package RadiusTheme\RadiusHotelBooking\Access
 */

namespace RadiusTheme\RadiusHotelBooking\Access;

defined( 'ABSPATH' ) || exit;

/**
 * Turns a save of the `access` settings section into a per-key change list:
 * fires `rtbp_access_changed` and emits a `permission.changed` activity event
 * (M14), with before/after per `role:key`. `default` means "not set: the
 * role's code default, then the registry default applies".
 */
final class AccessAudit {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'rtbp_settings_updated', array( self::class, 'settings_updated' ), 10, 3 );
	}

	/**
	 * `rtbp_settings_updated` listener.
	 *
	 * @param string $section Section key.
	 * @param mixed  $before  Section values before.
	 * @param mixed  $after   Section values after.
	 * @return void
	 */
	public static function settings_updated( $section, $before, $after ): void {
		if ( 'access' !== $section ) {
			return;
		}

		Access::flush();

		$changes = self::diff(
			(array) ( ( (array) $before )['roleLevels'] ?? array() ),
			(array) ( ( (array) $after )['roleLevels'] ?? array() )
		);
		if ( ! $changes['before'] ) {
			return;
		}

		/**
		 * Fires when the permission map changes.
		 *
		 * @param string $scope   What changed: `defaults` (built-in roles). Pro adds `role`, `user`.
		 * @param array  $changes `before` and `after`: `role:key` => level or `default`.
		 * @param int    $user_id Who changed it.
		 */
		do_action( 'rtbp_access_changed', 'defaults', $changes, get_current_user_id() );

		// The activity emitter arrives with M14.
		if ( function_exists( 'rtbp_activity' ) ) {
			\rtbp_activity(
				'permission.changed',
				null,
				array(
					'before'      => $changes['before'],
					'after'       => $changes['after'],
					'description' => sprintf(
						/* translators: %d: number of permissions changed. */
						_n( 'Changed %d permission', 'Changed %d permissions', count( $changes['before'] ), 'radius-hotel-booking' ),
						count( $changes['before'] )
					),
				)
			);
		}
	}

	/**
	 * The keys whose stored level differs.
	 *
	 * @param array $before Role => key => level.
	 * @param array $after  Role => key => level.
	 * @return array{before: array<string, string>, after: array<string, string>}
	 */
	public static function diff( array $before, array $after ): array {
		$out = array(
			'before' => array(),
			'after'  => array(),
		);

		foreach ( array_unique( array_merge( array_keys( $before ), array_keys( $after ) ) ) as $role ) {
			$from = (array) ( $before[ $role ] ?? array() );
			$to   = (array) ( $after[ $role ] ?? array() );
			foreach ( array_unique( array_merge( array_keys( $from ), array_keys( $to ) ) ) as $key ) {
				$old = $from[ $key ] ?? 'default';
				$new = $to[ $key ] ?? 'default';
				if ( $old !== $new ) {
					$out['before'][ $role . ':' . $key ] = $old;
					$out['after'][ $role . ':' . $key ]  = $new;
				}
			}
		}

		return $out;
	}
}
