<?php
/**
 * The rate plans table (M07).
 *
 * @package RadiusTheme\RadiusHotelBooking\Databases\Table
 */

namespace RadiusTheme\RadiusHotelBooking\Databases\Table;

use RadiusTheme\RadiusHotelBooking\Abstracts\Migration;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Blueprint;
use RadiusTheme\RadiusHotelBooking\Core\Database\Schema\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * `…radius_hotel_booking_rate_plans`: the library of stay windows (features
 * 7.1–7.5). A plan is `fixed` (start/end time, may cross midnight; equal
 * times = 24 h) or `flexible` (a duration and an allowed check-in range).
 * Times are `HH:MM` strings. See booking-engine §2.
 */
class RatePlansTable extends Migration {

	/**
	 * Set once the starter plans were created, so they are never recreated.
	 */
	const SEEDED_OPTION = 'rtbp_rate_plans_seeded';

	/**
	 * Create or upgrade the table (dbDelta: safe to run again), then seed.
	 *
	 * @return void
	 */
	public function up(): void {
		Schema::create(
			$this->tablePrefix . 'rate_plans',
			function ( Blueprint $table ) {
				$table->id();
				$table->string( 'name', 120 );
				$table->string( 'code', 60 );
				$table->string( 'type', 20 )->default( 'fixed' );
				$table->char( 'start_time', 5 )->nullable();
				$table->char( 'end_time', 5 )->nullable();
				$table->unsignedInteger( 'duration_minutes' )->default( 0 );
				$table->char( 'checkin_from', 5 )->nullable();
				$table->char( 'checkin_until', 5 )->nullable();
				$table->boolean( 'multi_unit' )->default( 0 );
				$table->text( 'features' )->nullable();
				$table->longText( 'policy' )->nullable();
				$table->boolean( 'is_active' )->default( 1 );
				$table->unsignedInteger( 'sort_order' )->default( 0 );
				$table->timestamps();
				$table->softDeletes();

				$table->unique( 'code', 'code' );
				$table->index( array( 'is_active', 'sort_order' ), 'active_order' );
			}
		);

		$this->seed();
	}

	/**
	 * The generic starter set (ADR-021), only on an empty table and only once.
	 * The client's own plans come from the M18 import.
	 *
	 * @return void
	 */
	private function seed(): void {
		global $wpdb;
		if ( get_option( self::SEEDED_OPTION ) ) {
			return;
		}
		// With the WordPress prefix ($this->tablePrefix is only the plugin's).
		$table = rtbp_table( 'rate_plans' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- one-time seed check.
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );
		if ( null === $count ) {
			return; // The table is not there: try again on the next migration run.
		}
		if ( (int) $count > 0 ) {
			update_option( self::SEEDED_OPTION, 1, false );
			return;
		}

		$now   = current_time( 'mysql' );
		$plans = array(
			array(
				'name'             => __( 'Half Day', 'radius-hotel-booking' ),
				'code'             => 'half-day',
				'type'             => 'fixed',
				'start_time'       => '08:30',
				'end_time'         => '17:00',
				'duration_minutes' => 510,
				'multi_unit'       => 0,
				'features'         => wp_json_encode( array( __( '8.5 hours stay', 'radius-hotel-booking' ) ) ),
				'sort_order'       => 0,
			),
			array(
				'name'             => __( 'Overnight', 'radius-hotel-booking' ),
				'code'             => 'overnight',
				'type'             => 'fixed',
				'start_time'       => '20:00',
				'end_time'         => '08:00',
				'duration_minutes' => 720,
				'multi_unit'       => 1,
				'features'         => wp_json_encode( array( __( '12 hours stay', 'radius-hotel-booking' ) ) ),
				'sort_order'       => 1,
			),
			array(
				'name'             => __( '24 Hours Flexible', 'radius-hotel-booking' ),
				'code'             => '24h-flexible',
				'type'             => 'flexible',
				'duration_minutes' => 1440,
				'checkin_from'     => '00:00',
				'checkin_until'    => '23:30',
				'multi_unit'       => 1,
				'features'         => wp_json_encode( array( __( 'Flexible check-in', 'radius-hotel-booking' ) ) ),
				'sort_order'       => 2,
			),
		);
		$inserted = 0;
		foreach ( $plans as $plan ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- seed insert.
			$inserted += (int) $wpdb->insert(
				$table,
				$plan + array(
					'is_active'  => 1,
					'created_at' => $now,
					'updated_at' => $now,
				)
			);
		}
		if ( count( $plans ) === $inserted ) {
			update_option( self::SEEDED_OPTION, 1, false );
		}
	}

	/**
	 * Drop the table.
	 *
	 * @return void
	 */
	public function down(): void {
		Schema::drop( $this->tablePrefix . 'rate_plans' );
		delete_option( self::SEEDED_OPTION );
	}
}
