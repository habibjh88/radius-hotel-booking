<?php
/**
 * Option and transient key registry.
 *
 * Every wp_options / transient key the plugin owns is declared here so nothing
 * hard-codes a string twice.
 *
 * @package RadiusTheme\RadiusHotelBooking\Common
 */

namespace RadiusTheme\RadiusHotelBooking\Common;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Keys
 *
 * @since 1.0.0
 */
class Keys {

	/**
	 * Timestamp of the first install.
	 *
	 * @var string
	 */
	const INSTALLED = 'rtbp_installed';

	/**
	 * Last-installed plugin version.
	 *
	 * @var string
	 */
	const VERSION = 'rtbp_version';

	/**
	 * Last-installed database schema version.
	 *
	 * @var string
	 */
	const DB_VERSION = 'rtbp_db_version';

	/**
	 * Transient set on activation to trigger the one-time redirect.
	 *
	 * @var string
	 */
	const ACTIVATION_REDIRECT = 'rtbp_activation_redirect';

	/**
	 * Short-lived lock held while a schema upgrade runs, so two concurrent
	 * requests right after a release don't both run dbDelta.
	 *
	 * @var string
	 */
	const UPGRADING = 'rtbp_upgrading';

	/**
	 * Option holding the IDs of the pages created on install.
	 *
	 * @var string
	 */
	const PAGES = 'rtbp_pages_settings';
}
