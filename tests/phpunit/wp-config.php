<?php
/**
 * WordPress test-suite configuration.
 *
 * Referenced by phpunit.xml.dist through the WP_PHPUNIT__TESTS_CONFIG env var.
 * Every value falls back to an environment variable so the same file works on a
 * developer machine and in CI without being edited.
 *
 * Required before `npm run test:php` can run:
 *
 *   1. A WordPress checkout. Point WP_CORE_DIR at it, e.g.
 *      export WP_CORE_DIR="$HOME/wordpress"
 *   2. A throwaway MySQL database — the suite DROPS and recreates its tables,
 *      so never aim this at a database you care about.
 *      export WP_TESTS_DB_NAME=wordpress_test
 *      export WP_TESTS_DB_USER=root
 *      export WP_TESTS_DB_PASSWORD=
 *      export WP_TESTS_DB_HOST=127.0.0.1
 *
 * @package RadiusTheme\RadiusHotelBooking
 */

/**
 * Read an environment variable, falling back to a default.
 *
 * @param string $name    Variable name.
 * @param string $default Value to use when the variable is unset or empty.
 * @return string
 */
function rtbp_test_env( $name, $default = '' ) {
	$value = getenv( $name );

	return ( false === $value || '' === $value ) ? $default : $value;
}

// Path to the WordPress codebase being tested against. Must end in a slash.
$rtbp_core_dir = rtbp_test_env( 'WP_CORE_DIR', dirname( __DIR__, 2 ) . '/wordpress' );

define( 'ABSPATH', rtrim( $rtbp_core_dir, '/\\' ) . '/' );

if ( ! file_exists( ABSPATH . 'wp-settings.php' ) ) {
	echo 'Error: no WordPress checkout at ' . ABSPATH . PHP_EOL
		. 'Set WP_CORE_DIR to a WordPress directory, e.g. WP_CORE_DIR=$HOME/wordpress' . PHP_EOL;
	exit( 1 );
}

// Test database. This database is wiped on every run — use a dedicated one.
define( 'DB_NAME', rtbp_test_env( 'WP_TESTS_DB_NAME', 'wordpress_test' ) );
define( 'DB_USER', rtbp_test_env( 'WP_TESTS_DB_USER', 'root' ) );
define( 'DB_PASSWORD', rtbp_test_env( 'WP_TESTS_DB_PASSWORD' ) );
define( 'DB_HOST', rtbp_test_env( 'WP_TESTS_DB_HOST', '127.0.0.1' ) );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = rtbp_test_env( 'WP_TESTS_TABLE_PREFIX', 'wptests_' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

define( 'WP_TESTS_DOMAIN', rtbp_test_env( 'WP_TESTS_DOMAIN', 'example.org' ) );
define( 'WP_TESTS_EMAIL', rtbp_test_env( 'WP_TESTS_EMAIL', 'admin@example.org' ) );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );

define( 'WP_DEBUG', true );

// Salts are irrelevant for a throwaway test database, but WordPress expects them.
foreach ( array( 'AUTH', 'SECURE_AUTH', 'LOGGED_IN', 'NONCE' ) as $rtbp_salt ) {
	foreach ( array( 'KEY', 'SALT' ) as $rtbp_suffix ) {
		if ( ! defined( $rtbp_salt . '_' . $rtbp_suffix ) ) {
			define( $rtbp_salt . '_' . $rtbp_suffix, 'put your unique phrase here' );
		}
	}
}
