<?php
/**
 * Activity action catalogue.
 *
 * @package RadiusTheme\RadiusHotelBooking\ActivityLog
 */

namespace RadiusTheme\RadiusHotelBooking\ActivityLog;

defined( 'ABSPATH' ) || exit;

/**
 * Every action the activity log knows (M14): key => kind and translatable
 * label. The action key is the access key where one exists
 * (`bookings.approve`). Kinds drive the log filter (feature 14.7).
 *
 * Modules add their actions in the task that emits them, through the
 * `rtbp_activity_actions` filter; the booking and payment keys are reserved
 * here (features 14.2, 14.15) and emitted by M02–M05.
 */
final class ActionCatalog {

	const DATA       = 'data';
	const VIEW       = 'view';
	const AUTH       = 'auth';
	const PAYMENT    = 'payment';
	const PERMISSION = 'permission';
	const SECURITY   = 'security';
	const SYSTEM     = 'system';

	/**
	 * The resolved catalogue, per request.
	 *
	 * @var array<string, array{kind: string, label: string}>|null
	 */
	private static ?array $resolved = null;

	/**
	 * Every action.
	 *
	 * @return array<string, array{kind: string, label: string}>
	 */
	public static function actions(): array {
		if ( null !== self::$resolved ) {
			return self::$resolved;
		}

		$rows = array(
			// Sign-in (14.3) and page views (14.4).
			'auth.login'                 => array( self::AUTH, __( 'Signed in', 'radius-hotel-booking' ) ),
			'auth.logout'                => array( self::AUTH, __( 'Signed out', 'radius-hotel-booking' ) ),
			'auth.failed'                => array( self::AUTH, __( 'Failed sign-in', 'radius-hotel-booking' ) ),
			'page.view'                  => array( self::VIEW, __( 'Opened a page', 'radius-hotel-booking' ) ),

			// Security (14.17).
			'security.denied'            => array( self::SECURITY, __( 'Tried something locked', 'radius-hotel-booking' ) ),
			'security.passcode_ok'       => array( self::SECURITY, __( 'Confirmed with a PIN', 'radius-hotel-booking' ) ),
			'security.passcode_failed'   => array( self::SECURITY, __( 'Entered a wrong PIN', 'radius-hotel-booking' ) ),
			'security.pin_locked'        => array( self::SECURITY, __( 'Locked out after wrong PINs', 'radius-hotel-booking' ) ),
			'security.pin_changed'       => array( self::SECURITY, __( 'Changed a PIN', 'radius-hotel-booking' ) ),
			// M04: a banned guest tried to book on the website (refused with a generic message).
			'security.booking_refused'   => array( self::SECURITY, __( 'Refused a website booking from a banned guest', 'radius-hotel-booking' ) ),

			// Permissions (14.16).
			'permission.changed'         => array( self::PERMISSION, __( 'Changed permissions', 'radius-hotel-booking' ) ),
			'permission.role_created'    => array( self::PERMISSION, __( 'Created an access role', 'radius-hotel-booking' ) ),
			'permission.role_updated'    => array( self::PERMISSION, __( 'Changed an access role', 'radius-hotel-booking' ) ),
			'permission.role_deleted'    => array( self::PERMISSION, __( 'Deleted an access role', 'radius-hotel-booking' ) ),
			'permission.overrides_reset' => array( self::PERMISSION, __( 'Reset personal overrides', 'radius-hotel-booking' ) ),

			// Settings and the log itself.
			'settings.updated'           => array( self::SYSTEM, __( 'Changed settings', 'radius-hotel-booking' ) ),
			'settings.pro_features'      => array( self::SYSTEM, __( 'Switched Pro features', 'radius-hotel-booking' ) ),
			'activity.archived'          => array( self::SYSTEM, __( 'Archived the activity log', 'radius-hotel-booking' ) ),
			'activity.purged'            => array( self::SYSTEM, __( 'Removed archived log entries', 'radius-hotel-booking' ) ),

			// Inventory (M06).
			'floors.create'              => array( self::DATA, __( 'Added a floor', 'radius-hotel-booking' ) ),
			'floors.update'              => array( self::DATA, __( 'Renamed a floor', 'radius-hotel-booking' ) ),
			'floors.delete'              => array( self::DATA, __( 'Deleted a floor', 'radius-hotel-booking' ) ),
			'floors.reorder'             => array( self::DATA, __( 'Reordered the floors', 'radius-hotel-booking' ) ),
			'room_types.create'          => array( self::DATA, __( 'Added a room type', 'radius-hotel-booking' ) ),
			'room_types.update'          => array( self::DATA, __( 'Changed a room type', 'radius-hotel-booking' ) ),
			'room_types.delete'          => array( self::DATA, __( 'Deleted a room type', 'radius-hotel-booking' ) ),
			'amenities.create'           => array( self::DATA, __( 'Added an amenity', 'radius-hotel-booking' ) ),
			'amenities.update'           => array( self::DATA, __( 'Renamed an amenity', 'radius-hotel-booking' ) ),
			'amenities.import'           => array( self::DATA, __( 'Added common amenities', 'radius-hotel-booking' ) ),
			'amenities.merge'            => array( self::DATA, __( 'Merged two amenities', 'radius-hotel-booking' ) ),
			'amenities.delete'           => array( self::DATA, __( 'Deleted an amenity', 'radius-hotel-booking' ) ),
			'amenities.reorder'          => array( self::DATA, __( 'Reordered the amenities', 'radius-hotel-booking' ) ),
			'rooms.create'               => array( self::DATA, __( 'Added a room', 'radius-hotel-booking' ) ),
			'rooms.update'               => array( self::DATA, __( 'Changed a room', 'radius-hotel-booking' ) ),
			'rooms.delete'               => array( self::DATA, __( 'Removed a room', 'radius-hotel-booking' ) ),
			'rooms.state'                => array( self::DATA, __( 'Changed a room state', 'radius-hotel-booking' ) ),
			'rooms.move'                 => array( self::DATA, __( 'Moved a room to another type', 'radius-hotel-booking' ) ),
			'rooms.bulk_create'          => array( self::DATA, __( 'Added rooms in bulk', 'radius-hotel-booking' ) ),

			// Rate plans and pricing (M07).
			'rate_plans.create'          => array( self::DATA, __( 'Added a rate plan', 'radius-hotel-booking' ) ),
			'rate_plans.update'          => array( self::DATA, __( 'Changed a rate plan', 'radius-hotel-booking' ) ),
			'rate_plans.delete'          => array( self::DATA, __( 'Deleted a rate plan', 'radius-hotel-booking' ) ),
			'rates.update'               => array( self::DATA, __( 'Changed a price', 'radius-hotel-booking' ) ),
			// Availability (M08). Web holds are counted, not logged.
			'availability.override'      => array( self::DATA, __( 'Changed a price for a date', 'radius-hotel-booking' ) ),
			'availability.close'         => array( self::DATA, __( 'Closed dates', 'radius-hotel-booking' ) ),
			'availability.open'          => array( self::DATA, __( 'Opened dates', 'radius-hotel-booking' ) ),
			'availability.bulk'          => array( self::DATA, __( 'Updated the calendar in bulk', 'radius-hotel-booking' ) ),
			'blocks.create'              => array( self::DATA, __( 'Blocked dates', 'radius-hotel-booking' ) ),
			'blocks.update'              => array( self::DATA, __( 'Changed a block', 'radius-hotel-booking' ) ),
			'blocks.delete'              => array( self::DATA, __( 'Removed a block', 'radius-hotel-booking' ) ),
			'holds.create'               => array( self::DATA, __( 'Held a room', 'radius-hotel-booking' ) ),
			'holds.release'              => array( self::DATA, __( 'Released a held room', 'radius-hotel-booking' ) ),

			// Guests (M09). Revealing an ID number is a sensitive read.
			'guests.create'              => array( self::DATA, __( 'Added a guest', 'radius-hotel-booking' ) ),
			'guests.edit'                => array( self::DATA, __( 'Changed a guest', 'radius-hotel-booking' ) ),
			'guests.ban'                 => array( self::DATA, __( 'Banned a guest', 'radius-hotel-booking' ) ),
			'guests.unban'               => array( self::DATA, __( 'Lifted a guest ban', 'radius-hotel-booking' ) ),
			'guests.view_id'             => array( self::VIEW, __( 'Viewed a guest identity document', 'radius-hotel-booking' ) ),
			'reports.export'             => array( self::VIEW, __( 'Exported a report', 'radius-hotel-booking' ) ),
			'exports.generate'           => array( self::DATA, __( 'Generated an export file', 'radius-hotel-booking' ) ),
			'exports.download'           => array( self::VIEW, __( 'Downloaded an export file', 'radius-hotel-booking' ) ),
			'exports.delete'             => array( self::DATA, __( 'Deleted an export file', 'radius-hotel-booking' ) ),
			'guests.note_add'            => array( self::DATA, __( 'Added a guest note', 'radius-hotel-booking' ) ),
			'guests.note_edit'           => array( self::DATA, __( 'Edited a guest note', 'radius-hotel-booking' ) ),
			'guests.note_remove'         => array( self::DATA, __( 'Removed a guest note', 'radius-hotel-booking' ) ),

			// Bookings (14.2), reserved for M02/M03.
			'bookings.create'            => array( self::DATA, __( 'Created a booking', 'radius-hotel-booking' ) ),
			'bookings.approve'           => array( self::DATA, __( 'Approved a booking', 'radius-hotel-booking' ) ),
			'bookings.decline'           => array( self::DATA, __( 'Declined a booking', 'radius-hotel-booking' ) ),
			'bookings.cancel'            => array( self::DATA, __( 'Cancelled a booking', 'radius-hotel-booking' ) ),
			'bookings.check_in'          => array( self::DATA, __( 'Checked a guest in', 'radius-hotel-booking' ) ),
			'bookings.check_out'         => array( self::DATA, __( 'Checked a guest out', 'radius-hotel-booking' ) ),
			'bookings.no_show'           => array( self::DATA, __( 'Marked a no-show', 'radius-hotel-booking' ) ),
			'bookings.edit'              => array( self::DATA, __( 'Edited a booking', 'radius-hotel-booking' ) ),
			'bookings.line_add'          => array( self::DATA, __( 'Added a room to a booking', 'radius-hotel-booking' ) ),
			'bookings.line_edit'         => array( self::DATA, __( 'Changed a room on a booking', 'radius-hotel-booking' ) ),
			'bookings.line_remove'       => array( self::DATA, __( 'Removed a room from a booking', 'radius-hotel-booking' ) ),
			'bookings.note_add'          => array( self::DATA, __( 'Added a booking note', 'radius-hotel-booking' ) ),
			'bookings.note_edit'         => array( self::DATA, __( 'Edited a booking note', 'radius-hotel-booking' ) ),
			'bookings.note_remove'       => array( self::DATA, __( 'Removed a booking note', 'radius-hotel-booking' ) ),

			// Payments (14.15), reserved for M05.
			'payments.record'            => array( self::PAYMENT, __( 'Recorded a payment', 'radius-hotel-booking' ) ),
			'payments.change_status'     => array( self::PAYMENT, __( 'Changed a payment status', 'radius-hotel-booking' ) ),
			'payments.void'              => array( self::PAYMENT, __( 'Voided a payment', 'radius-hotel-booking' ) ),
			'invoices.send'              => array( self::PAYMENT, __( 'Sent an invoice', 'radius-hotel-booking' ) ),
			'invoices.regenerate'        => array( self::PAYMENT, __( 'Regenerated an invoice', 'radius-hotel-booking' ) ),
			'payments.on_hold'           => array( self::PAYMENT, __( 'Put a payment on hold', 'radius-hotel-booking' ) ),
			'invoices.issue'             => array( self::PAYMENT, __( 'Issued an invoice', 'radius-hotel-booking' ) ),
			'invoices.revise'            => array( self::PAYMENT, __( 'Revised an invoice', 'radius-hotel-booking' ) ),
			'receipts.send'              => array( self::PAYMENT, __( 'Sent a receipt', 'radius-hotel-booking' ) ),
			'payments.remind'            => array( self::PAYMENT, __( 'Sent a payment reminder', 'radius-hotel-booking' ) ),
			'bookings.release_overdue'   => array( self::DATA, __( 'Released an unpaid booking', 'radius-hotel-booking' ) ),
		);

		$actions = array();
		foreach ( $rows as $key => $row ) {
			$actions[ $key ] = array(
				'kind'  => $row[0],
				'label' => $row[1],
			);
		}

		/**
		 * Filters the activity action catalogue. Modules add their actions here.
		 *
		 * @param array $actions Key => [ 'kind' => …, 'label' => … ].
		 */
		$filtered = (array) apply_filters( 'rtbp_activity_actions', $actions );

		self::$resolved = array();
		foreach ( $filtered as $key => $action ) {
			if ( is_array( $action ) && preg_match( '/^[a-z0-9_]+\.[a-z0-9_]+$/', (string) $key ) ) {
				self::$resolved[ (string) $key ] = array(
					'kind'  => isset( self::kinds()[ $action['kind'] ?? '' ] ) ? $action['kind'] : self::DATA,
					'label' => (string) ( $action['label'] ?? $key ),
				);
			}
		}
		return self::$resolved;
	}

	/**
	 * The kind of an action: from the catalogue, else `data`.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function kind( string $action ): string {
		return self::actions()[ $action ]['kind'] ?? self::DATA;
	}

	/**
	 * The label of an action: from the catalogue, else the key.
	 *
	 * @param string $action Action key.
	 * @return string
	 */
	public static function label( string $action ): string {
		return self::actions()[ $action ]['label'] ?? $action;
	}

	/**
	 * The kinds, for the log filter (feature 14.7).
	 *
	 * @return array<string, string> Kind => translated label.
	 */
	public static function kinds(): array {
		return array(
			self::DATA       => __( 'Data changes', 'radius-hotel-booking' ),
			self::VIEW       => __( 'Page views', 'radius-hotel-booking' ),
			self::AUTH       => __( 'Sign-in', 'radius-hotel-booking' ),
			self::PAYMENT    => __( 'Payments', 'radius-hotel-booking' ),
			self::PERMISSION => __( 'Permissions', 'radius-hotel-booking' ),
			self::SECURITY   => __( 'Security', 'radius-hotel-booking' ),
			self::SYSTEM     => __( 'System', 'radius-hotel-booking' ),
		);
	}

	/**
	 * Forget the resolved catalogue.
	 *
	 * @return void
	 */
	public static function flush(): void {
		self::$resolved = null;
	}
}
