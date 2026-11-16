<?php
/**
 * Day and month names for dates printed by the plugin.
 *
 * @package RadiusTheme\RadiusHotelBooking\Support
 */

namespace RadiusTheme\RadiusHotelBooking\Support;

defined( 'ABSPATH' ) || exit;

/**
 * `wp_date()` takes day and month names from WordPress core's translation
 * of the language (`$wp_locale`). A staff member who picks French in the
 * account menu (UserLanguage) on a site whose WordPress core has no French
 * files gets French text but English dates ("Mon 9 Nov", "9 November 2026")
 * on documents, e-mails and anything else formatted in PHP.
 *
 * When core has no names for the current language and this plugin's
 * translation has them, `$wp_locale` is filled from this plugin's
 * catalogue; with core's own names (or in English) nothing changes.
 * Runs on `init` and again after every locale switch.
 */
final class LocaleNames {

	/**
	 * Hook in.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( self::class, 'fill' ), 1 );
		add_action( 'change_locale', array( self::class, 'fill' ), 20 );
	}

	/**
	 * Fill `$wp_locale` from this plugin's translation when core has none.
	 *
	 * @return void
	 */
	public static function fill(): void {
		global $wp_locale;
		if ( ! $wp_locale instanceof \WP_Locale ) {
			return;
		}
		// Core translated the names (or the language is English): keep them.
		if ( 'January' !== $wp_locale->get_month( 1 ) || 0 === strpos( determine_locale(), 'en' ) ) {
			return;
		}
		$months = self::months();
		// This plugin has no translation for the language either.
		if ( 'January' === $months[0] ) {
			return;
		}

		$abbrev = self::monthAbbrev();
		foreach ( $months as $index => $name ) {
			$key                               = zeroise( $index + 1, 2 );
			$wp_locale->month[ $key ]          = $name;
			$wp_locale->month_genitive[ $key ] = $name;
			$wp_locale->month_abbrev[ $name ]  = $abbrev[ $index ];
		}

		$days                       = self::weekdays();
		$short                      = self::weekdayAbbrev();
		$initial                    = self::weekdayInitial();
		$wp_locale->weekday         = array();
		$wp_locale->weekday_abbrev  = array();
		$wp_locale->weekday_initial = array();
		foreach ( $days as $index => $name ) {
			$wp_locale->weekday[ $index ]        = $name;
			$wp_locale->weekday_abbrev[ $name ]  = $short[ $index ];
			$wp_locale->weekday_initial[ $name ] = $initial[ $index ];
		}
	}

	/**
	 * Month names, January first.
	 *
	 * @return string[]
	 */
	private static function months(): array {
		return array(
			_x( 'January', 'month name', 'radius-hotel-booking' ),
			_x( 'February', 'month name', 'radius-hotel-booking' ),
			_x( 'March', 'month name', 'radius-hotel-booking' ),
			_x( 'April', 'month name', 'radius-hotel-booking' ),
			_x( 'May', 'month name', 'radius-hotel-booking' ),
			_x( 'June', 'month name', 'radius-hotel-booking' ),
			_x( 'July', 'month name', 'radius-hotel-booking' ),
			_x( 'August', 'month name', 'radius-hotel-booking' ),
			_x( 'September', 'month name', 'radius-hotel-booking' ),
			_x( 'October', 'month name', 'radius-hotel-booking' ),
			_x( 'November', 'month name', 'radius-hotel-booking' ),
			_x( 'December', 'month name', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Short month names, January first.
	 *
	 * @return string[]
	 */
	private static function monthAbbrev(): array {
		return array(
			_x( 'Jan', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Feb', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Mar', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Apr', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'May', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Jun', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Jul', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Aug', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Sep', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Oct', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Nov', 'month abbreviation', 'radius-hotel-booking' ),
			_x( 'Dec', 'month abbreviation', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Weekday names, Sunday first (as WP_Locale).
	 *
	 * @return string[]
	 */
	private static function weekdays(): array {
		return array(
			_x( 'Sunday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Monday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Tuesday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Wednesday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Thursday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Friday', 'weekday', 'radius-hotel-booking' ),
			_x( 'Saturday', 'weekday', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Short weekday names, Sunday first.
	 *
	 * @return string[]
	 */
	private static function weekdayAbbrev(): array {
		return array(
			_x( 'Sun', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Mon', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Tue', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Wed', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Thu', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Fri', 'weekday abbreviation', 'radius-hotel-booking' ),
			_x( 'Sat', 'weekday abbreviation', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Weekday initials, Sunday first.
	 *
	 * @return string[]
	 */
	private static function weekdayInitial(): array {
		return array(
			_x( 'S', 'Sunday initial', 'radius-hotel-booking' ),
			_x( 'M', 'Monday initial', 'radius-hotel-booking' ),
			_x( 'T', 'Tuesday initial', 'radius-hotel-booking' ),
			_x( 'W', 'Wednesday initial', 'radius-hotel-booking' ),
			_x( 'T', 'Thursday initial', 'radius-hotel-booking' ),
			_x( 'F', 'Friday initial', 'radius-hotel-booking' ),
			_x( 'S', 'Saturday initial', 'radius-hotel-booking' ),
		);
	}
}
