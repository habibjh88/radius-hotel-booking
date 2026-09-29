<?php
/**
 * Activity events the free plugin emits itself.
 *
 * @package RadiusTheme\RadiusHotelBooking\ActivityLog
 */

namespace RadiusTheme\RadiusHotelBooking\ActivityLog;

use RadiusTheme\RadiusHotelBooking\Access\AccessRegistry;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Settings\SettingsSchema;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Sign-in, sign-out and failed sign-in of staff (14.3), page views (14.4,
 * from AccessMiddleware's `rtbp_page_viewed`) and settings changes (M17's
 * `rtbp_settings_updated`). Denials, passcode events and permission changes
 * are emitted where they happen (M13).
 */
final class CoreEvents {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'wp_login', array( self::class, 'login' ), 10, 2 );
		add_action( 'wp_logout', array( self::class, 'logout' ) );
		add_action( 'wp_login_failed', array( self::class, 'login_failed' ) );
		add_action( 'rtbp_page_viewed', array( self::class, 'page_viewed' ), 10, 2 );
		add_action( 'rtbp_settings_updated', array( self::class, 'settings_updated' ), 10, 3 );
	}

	/**
	 * `wp_login`, for dashboard users only.
	 *
	 * @param string  $login User login.
	 * @param WP_User $user  User.
	 * @return void
	 */
	public static function login( $login, $user ): void {
		if ( $user instanceof WP_User && user_can( $user, Capabilities::VIEW_DASHBOARD ) ) {
			rtbp_activity( 'auth.login', null, array( 'actor' => $user->ID ) );
		}
	}

	/**
	 * `wp_logout` (WordPress passes the user id).
	 *
	 * @param int $user_id User id.
	 * @return void
	 */
	public static function logout( $user_id = 0 ): void {
		$user_id = (int) $user_id;
		if ( $user_id && user_can( $user_id, Capabilities::VIEW_DASHBOARD ) ) {
			rtbp_activity( 'auth.logout', null, array( 'actor' => $user_id ) );
		}
	}

	/**
	 * `wp_login_failed`: only for an existing dashboard user, so random
	 * usernames from bots do not flood the log.
	 *
	 * @param string $login The username or e-mail tried.
	 * @return void
	 */
	public static function login_failed( $login ): void {
		$login = (string) $login;
		$user  = get_user_by( 'login', $login );
		if ( ! $user && is_email( $login ) ) {
			$user = get_user_by( 'email', $login );
		}
		if ( $user instanceof WP_User && user_can( $user, Capabilities::VIEW_DASHBOARD ) ) {
			rtbp_activity(
				'auth.failed',
				array(
					'type'  => 'user',
					'id'    => $user->ID,
					'label' => $user->display_name,
				),
				array( 'actor' => 0 )
			);
		}
	}

	/**
	 * `rtbp_page_viewed` (AccessMiddleware, GET on a page key). Pro groups
	 * repeated views (14.10).
	 *
	 * @param string $key     Page key.
	 * @param int    $user_id User id.
	 * @return void
	 */
	public static function page_viewed( $key, $user_id ): void {
		rtbp_activity(
			'page.view',
			array(
				'type'  => 'page',
				'id'    => (string) $key,
				'label' => (string) ( AccessRegistry::get( (string) $key )['label'] ?? $key ),
			),
			array( 'actor' => (int) $user_id )
		);
	}

	/**
	 * `rtbp_settings_updated`: the changed keys, sensitive keys removed.
	 * Sections guarded by another key than `settings.<section>` (the
	 * permission map, Pro's passcodes and feature switches) are audited as
	 * permission changes where they are saved, so they are skipped here.
	 *
	 * @param string $section Section key.
	 * @param mixed  $before  Values before.
	 * @param mixed  $after   Values after.
	 * @return void
	 */
	public static function settings_updated( $section, $before, $after ): void {
		$section = (string) $section;
		if ( AccessRegistry::settingsKey( $section ) !== 'settings.' . $section ) {
			return;
		}

		$before = SettingsSchema::strip_sensitive( $section, (array) $before );
		$after  = SettingsSchema::strip_sensitive( $section, (array) $after );
		if ( ! ChangeDiff::between( $before, $after )['before'] ) {
			return;
		}

		rtbp_activity(
			'settings.updated',
			array(
				'type'  => 'settings',
				'id'    => $section,
				'label' => $section,
			),
			array(
				'before' => $before,
				'after'  => $after,
			)
		);
	}
}
