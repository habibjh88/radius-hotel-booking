<?php
/**
 * Dashboard summary endpoint.
 *
 * @package RadiusTheme\RadiusHotelBooking\Controllers
 */

namespace RadiusTheme\RadiusHotelBooking\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use RadiusTheme\RadiusHotelBooking\Core\Api\ApiResponse;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AccessMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\AuthMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Api\Middleware\PermissionMiddleware;
use RadiusTheme\RadiusHotelBooking\Core\Permissions\Capabilities;
use RadiusTheme\RadiusHotelBooking\Helpers\SettingsHelper;
use WP_REST_Request;

/**
 * GET /dashboard/summary — everything the dashboard screen shows, in one call.
 *
 * The payload shape is the contract with src/modules/Dashboard. Each module
 * fills in its part through the `rtbp_dashboard_summary` filter as it is built
 * (M06 rooms, M01 bookings, M05 payments …), so this controller never needs to
 * know about bookings, and a fresh install returns honest zeros plus the setup
 * checklist.
 */
class DashboardController {

	/**
	 * GET /dashboard/summary.
	 *
	 * @param WP_REST_Request $request Current request.
	 * @return mixed
	 */
	public function summary( WP_REST_Request $request ) {
		$auth       = new AuthMiddleware();
		$permission = new PermissionMiddleware( Capabilities::VIEW_DASHBOARD );
		$access     = new AccessMiddleware( 'page.dashboard' );

		return $auth->handle(
			$request,
			fn( $request ) => $permission->handle(
				$request,
				fn( $request ) => $access->handle(
					$request,
					fn() => ApiResponse::success( $this->build() )->send()
				)
			)
		);
	}

	/**
	 * Build the summary.
	 *
	 * @return array
	 */
	private function build(): array {
		$summary = array(
			'stats'           => array(
				'awaiting_approval' => 0,
				'arrivals_today'    => 0,
				'departures_today'  => 0,
				'rooms_free'        => 0,
				'rooms_total'       => 0,
			),
			'today'           => array(
				'arrivals'   => array(),
				'departures' => array(),
			),
			'recent_bookings' => array(),
			'rooms_by_state'  => array(
				'available'      => 0,
				'maintenance'    => 0,
				'out_of_service' => 0,
			),
			'setup'           => $this->setup_steps(),
		);

		/**
		 * Filters the dashboard summary. Modules add their figures here.
		 *
		 * @param array $summary The summary payload.
		 */
		$summary = (array) apply_filters( 'rtbp_dashboard_summary', $summary );

		$summary['setup'] = array_values( (array) ( $summary['setup'] ?? array() ) );

		return $summary;
	}

	/**
	 * The "get your hotel ready" checklist. A module marks its step done
	 * through the `rtbp_dashboard_summary` filter once it can tell.
	 *
	 * @return array<string,array{label:string,description:string,path:string,done:bool}>
	 */
	private function setup_steps(): array {
		$general = SettingsHelper::get_setting( 'general' );

		return array(
			'hotel_details' => array(
				'label'       => __( 'Add your hotel details', 'radius-hotel-booking' ),
				'description' => __( 'Name and contact e-mail, used on e-mails and invoices.', 'radius-hotel-booking' ),
				'path'        => '/settings',
				'done'        => ! empty( $general['companyName'] ) && ! empty( $general['contactEmail'] ),
			),
			'room_types'    => array(
				'label'       => __( 'Create your room types', 'radius-hotel-booking' ),
				'description' => __( 'For example Standard Room and Room VIP.', 'radius-hotel-booking' ),
				'path'        => '/rooms',
				'done'        => false,
			),
			'rooms'         => array(
				'label'       => __( 'Add floors and rooms', 'radius-hotel-booking' ),
				'description' => __( 'Every physical room with its number and floor.', 'radius-hotel-booking' ),
				'path'        => '/rooms',
				'done'        => false,
			),
			'rate_plans'    => array(
				'label'       => __( 'Set up rate plans and prices', 'radius-hotel-booking' ),
				'description' => __( 'Your stay windows, such as Half Day or Overnight, and their prices.', 'radius-hotel-booking' ),
				'path'        => '/rate-plans',
				'done'        => false,
			),
			'payments'      => array(
				'label'       => __( 'Write your payment instructions', 'radius-hotel-booking' ),
				'description' => __( 'How guests pay you: Wave, Orange Money, cash or bank transfer.', 'radius-hotel-booking' ),
				'path'        => '/payments',
				'done'        => false,
			),
		);
	}
}
