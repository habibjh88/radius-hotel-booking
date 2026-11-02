<?php
/**
 * Each staff member's dashboard language, and the account menu's role line.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Account
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Account;

use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;

defined( 'ABSPATH' ) || exit;

/**
 * The language a signed-in staff member picks in the account menu (R1-T6).
 * It is stored as their WordPress user language (`locale` user meta, which
 * wp-admin already honours) and applied to the hotel dashboard page and the
 * plugin's REST answers, where WordPress would use the site language. The
 * strings come from the site's translations (Loco, WPML …), so only languages
 * installed on the site are offered (ADR-019).
 */
class UserLanguage {

	/**
	 * The plugin's REST namespace, as it appears in a request path.
	 */
	private const REST_PATH = '/radius-hotel-booking/v1/';

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_filter( 'determine_locale', array( self::class, 'determine' ), 20 );
	}

	/**
	 * The languages offered: English plus every language installed on the site.
	 *
	 * @return array[] `{ locale, label, flag }`.
	 */
	public static function options(): array {
		$locales = array_values( array_unique( array_merge( array( 'en_US' ), get_available_languages() ) ) );
		$options = array_map(
			static fn( $locale ) => array(
				'locale' => $locale,
				'label'  => self::name( $locale ),
				'flag'   => '',
			),
			$locales
		);

		/**
		 * Filters the languages of the account menu. Each row is
		 * `{ locale, label, flag }`; `flag` is optional (an emoji).
		 *
		 * @param array[] $options Languages.
		 */
		$filtered = (array) apply_filters( 'rtbp_dashboard_languages', $options );
		return array_values(
			array_filter(
				array_map(
					static fn( $row ) => is_array( $row ) && ! empty( $row['locale'] ) ? array(
						'locale' => (string) $row['locale'],
						'label'  => (string) ( $row['label'] ?? $row['locale'] ),
						'flag'   => (string) ( $row['flag'] ?? '' ),
					) : null,
					$filtered
				)
			)
		);
	}

	/**
	 * The user's dashboard language: their choice when it is offered, else
	 * the site language.
	 *
	 * @param int $user_id User id.
	 * @return string Locale.
	 */
	public static function current( int $user_id ): string {
		$chosen = $user_id > 0 ? (string) get_user_meta( $user_id, 'locale', true ) : '';

		/**
		 * Filters the language a user picked (an add-on may read an older setting).
		 *
		 * @param string $chosen  Locale, or '' when they never picked one.
		 * @param int    $user_id User id.
		 */
		$chosen = (string) apply_filters( 'rtbp_user_language', $chosen, $user_id );
		if ( '' !== $chosen && in_array( $chosen, array_column( self::options(), 'locale' ), true ) ) {
			return $chosen;
		}
		return get_locale();
	}

	/**
	 * Save the user's choice.
	 *
	 * @param int    $user_id User id.
	 * @param string $locale  Locale.
	 * @return string The saved locale.
	 * @throws DomainException 422 when the language is not offered.
	 */
	public static function set( int $user_id, string $locale ): string {
		if ( ! in_array( $locale, array_column( self::options(), 'locale' ), true ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it.
			throw DomainException::invalid( array( 'locale' => __( 'This language is not available on this site.', 'radius-hotel-booking' ) ) );
		}
		$before = (string) get_user_meta( $user_id, 'locale', true );
		update_user_meta( $user_id, 'locale', $locale );
		if ( $before !== $locale ) {
			rtbp_activity(
				'user.language',
				null,
				array(
					'before'      => array( 'locale' => $before ),
					'after'       => array( 'locale' => $locale ),
					/* translators: %s: language name. */
					'description' => sprintf( __( 'Changed their dashboard language to %s', 'radius-hotel-booking' ), self::name( $locale ) ),
				)
			);
		}
		return $locale;
	}

	/**
	 * `determine_locale`: a signed-in staff member's language on the
	 * dashboard page and in the plugin's REST answers (wp-admin already uses
	 * the user language).
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	public static function determine( $locale ) {
		if ( is_admin() || ! did_action( 'plugins_loaded' ) || ! function_exists( 'wp_get_current_user' ) || ! is_user_logged_in() ) {
			return $locale;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing hint (?rest_route=…).
		$route = isset( $_GET['rest_route'] ) ? sanitize_text_field( wp_unslash( $_GET['rest_route'] ) ) : '';
		$rest  = false !== strpos( $uri, self::REST_PATH ) || false !== strpos( $route, self::REST_PATH );
		if ( ! $rest && ! self::onDashboardPage( $uri ) ) {
			return $locale;
		}
		if ( ! current_user_can( Capabilities::VIEW_DASHBOARD ) ) {
			return $locale;
		}
		$user_id = get_current_user_id();
		$chosen  = (string) get_user_meta( $user_id, 'locale', true );
		$chosen  = (string) apply_filters( 'rtbp_user_language', $chosen, $user_id );
		return '' !== $chosen ? $chosen : $locale;
	}

	/**
	 * The account menu's second line: Administrator, or the user's role title.
	 *
	 * @param int $user_id User id.
	 * @return string
	 */
	public static function roleLabel( int $user_id ): string {
		$user  = get_userdata( $user_id );
		$label = '';
		if ( $user ) {
			if ( user_can( $user, 'manage_options' ) ) {
				$label = __( 'Administrator', 'radius-hotel-booking' );
			} else {
				$labels = Capabilities::roleLabels();
				foreach ( (array) $user->roles as $role ) {
					if ( isset( $labels[ $role ] ) ) {
						$label = $labels[ $role ];
						break;
					}
				}
				if ( '' === $label && $user->roles ) {
					$names = wp_roles()->get_names();
					$first = reset( $user->roles );
					$label = isset( $names[ $first ] ) ? translate_user_role( $names[ $first ] ) : '';
				}
			}
		}

		/**
		 * Filters the role line under the name in the account menu (an add-on
		 * may show the user's access role instead).
		 *
		 * @param string $label   Role title.
		 * @param int    $user_id User id.
		 */
		return (string) apply_filters( 'rtbp_user_role_label', $label, $user_id );
	}

	/**
	 * Whether the request is for the hotel dashboard page (the page set in
	 * Settings, or its slug).
	 *
	 * @param string $uri Request URI.
	 * @return bool
	 */
	private static function onDashboardPage( string $uri ): bool {
		static $path = null;
		if ( null === $path ) {
			// Permalinks need $wp_rewrite, which exists only after plugins_loaded.
			if ( empty( $GLOBALS['wp_rewrite'] ) || ! function_exists( 'rtbp_page_url' ) ) {
				return false;
			}
			$path = (string) wp_parse_url( rtbp_page_url( 'dashboardPage' ), PHP_URL_PATH );
		}
		$current = (string) wp_parse_url( $uri, PHP_URL_PATH );
		return '' !== $path && '/' !== $path && 0 === strpos( trailingslashit( $current ), trailingslashit( $path ) );
	}

	/**
	 * A language's own name ("Français"), or its code without the intl extension.
	 *
	 * @param string $locale Locale.
	 * @return string
	 */
	private static function name( string $locale ): string {
		if ( 'en_US' === $locale ) {
			return 'English';
		}
		if ( class_exists( '\Locale' ) ) {
			$name = (string) \Locale::getDisplayName( $locale, $locale );
			if ( '' !== $name ) {
				return function_exists( 'mb_convert_case' ) ? mb_convert_case( mb_substr( $name, 0, 1 ), MB_CASE_UPPER ) . mb_substr( $name, 1 ) : ucfirst( $name );
			}
		}
		return $locale;
	}
}
