<?php
/**
 * Give existing bookings the reference format chosen in Settings → Booking rules.
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Booking
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Booking;

defined( 'ABSPATH' ) || exit;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Support\Sequence;

/**
 * Rewrites each booking's reference in the current format, keeping its
 * number and year: RT-2026-000016 becomes RT26-016. The year is the one in
 * the old reference (`-2026-`), else the year the booking was made.
 *
 * - The first reference a booking ever had is kept in `former_reference`,
 *   so search, scanning and a guest quoting it from an e-mail or a mobile
 *   money message still find the booking.
 * - A booking whose new reference another booking already holds (two years'
 *   number 16 without a year in the format) is left as it is and reported.
 * - Issued invoices keep the reference printed on them (a legal document).
 * - The counters are raised to the highest number used, so a new booking
 *   never takes a renumbered one's reference.
 */
class ReferenceRenumber {

	/**
	 * Examples shown in the preview.
	 */
	private const EXAMPLES = 5;

	/**
	 * What renumbering would do, without writing.
	 *
	 * @return array `{ total, changes, unchanged, conflicts, examples: [ { from, to } ], conflict_examples }`.
	 */
	public function preview(): array {
		return $this->summary( $this->plan() );
	}

	/**
	 * Renumber. Everything or nothing: one transaction.
	 *
	 * @return array The summary, as preview() gives it.
	 * @throws DomainException 500 when the database refused a write.
	 */
	public function apply(): array {
		global $wpdb;
		$table = rtbp_table( 'bookings' );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one transaction over the plugin's own table.
		$wpdb->query( 'START TRANSACTION' );
		try {
			$plan  = $this->plan( true );
			$moves = $plan['moves'];
			// Two passes, so a booking may take a reference another one is
			// giving up in the same run (the column is UNIQUE).
			foreach ( $moves as $move ) {
				$ok = $wpdb->query( $wpdb->prepare( 'UPDATE %i SET reference = %s WHERE id = %d', $table, '~' . $move['id'], $move['id'] ) );
				if ( false === $ok ) {
					throw new \RuntimeException( $wpdb->last_error );
				}
			}
			foreach ( $moves as $move ) {
				$ok = $wpdb->query(
					$wpdb->prepare(
						'UPDATE %i SET reference = %s, former_reference = COALESCE( NULLIF( former_reference, %s ), %s ) WHERE id = %d',
						$table,
						$move['to'],
						'',
						$move['from'],
						$move['id']
					)
				);
				if ( false === $ok ) {
					throw new \RuntimeException( $wpdb->last_error );
				}
			}
			foreach ( $plan['counters'] as $counter => $highest ) {
				Sequence::atLeast( $counter, $highest );
			}
			$wpdb->query( 'COMMIT' );
		} catch ( \Throwable $e ) {
			$wpdb->query( 'ROLLBACK' );
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw new DomainException( 'renumber_failed', __( 'The bookings could not be renumbered. Nothing was changed.', 'radius-hotel-booking' ), 500 );
		}
		// phpcs:enable

		$summary = $this->summary( $plan );
		if ( $moves && function_exists( 'rtbp_activity' ) ) {
			rtbp_activity(
				'settings.renumbered',
				null,
				array(
					/* translators: %d: number of bookings. */
					'description' => sprintf( _n( 'Renumbered %d booking to the new reference format', 'Renumbered %d bookings to the new reference format', count( $moves ), 'radius-hotel-booking' ), count( $moves ) ),
					'changed'     => count( $moves ),
					'conflicts'   => $summary['conflicts'],
				)
			);
		}
		return $summary;
	}

	/**
	 * Work out every booking's new reference.
	 *
	 * @param bool $lock Lock the rows (inside apply()'s transaction).
	 * @return array `{ total, moves: [ { id, from, to } ], conflicts: [ { from, to } ], counters: { name: highest } }`.
	 */
	private function plan( bool $lock = false ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- every booking, read once (soft-deleted ones too: the column is UNIQUE).
		$rows = $wpdb->get_results(
			$wpdb->prepare( 'SELECT id, reference, created_at FROM %i ORDER BY id' . ( $lock ? ' FOR UPDATE' : '' ), rtbp_table( 'bookings' ) ),
			ARRAY_A
		);

		$current  = array();
		$wanted   = array();
		$counters = array();
		foreach ( (array) $rows as $row ) {
			$from             = (string) $row['reference'];
			$current[ $from ] = (int) $row['id'];
			if ( ! preg_match( '/(\d+)$/', $from, $number ) ) {
				continue; // No number to keep.
			}
			$year   = preg_match( '/(?:^|\D)((?:19|20)\d{2})-\d+$/', $from, $match )
				? $match[1]
				: substr( (string) $row['created_at'], 0, 4 );
			$format = BookingService::referenceFormat( $year );
			$to     = Sequence::format( $format['start'], (int) $number[1], $format['digits'] );

			$counters[ $format['counter'] ] = max( $counters[ $format['counter'] ] ?? 0, (int) $number[1] );
			$wanted[]                       = array(
				'id'   => (int) $row['id'],
				'from' => $from,
				'to'   => $to,
			);
		}

		// A new reference may be taken by a booking that keeps its own, or
		// wanted by two bookings: those stay as they are.
		$leaving = array();
		$claims  = array();
		foreach ( $wanted as $item ) {
			if ( $item['from'] !== $item['to'] ) {
				$leaving[ $item['from'] ] = true;
			}
			$claims[ $item['to'] ] = ( $claims[ $item['to'] ] ?? 0 ) + 1;
		}
		$moves     = array();
		$conflicts = array();
		foreach ( $wanted as $item ) {
			if ( $item['from'] === $item['to'] ) {
				continue;
			}
			$held = isset( $current[ $item['to'] ] ) && ! isset( $leaving[ $item['to'] ] );
			if ( $held || $claims[ $item['to'] ] > 1 ) {
				$conflicts[] = array(
					'from' => $item['from'],
					'to'   => $item['to'],
				);
				continue;
			}
			$moves[] = $item;
		}

		return array(
			'total'     => count( (array) $rows ),
			'moves'     => $moves,
			'conflicts' => $conflicts,
			'counters'  => $counters,
		);
	}

	/**
	 * The plan in figures, with a few examples.
	 *
	 * @param array $plan plan().
	 * @return array
	 */
	private function summary( array $plan ): array {
		$pick = static fn( $item ) => array(
			'from' => $item['from'],
			'to'   => $item['to'],
		);
		return array(
			'total'             => $plan['total'],
			'changes'           => count( $plan['moves'] ),
			'unchanged'         => $plan['total'] - count( $plan['moves'] ) - count( $plan['conflicts'] ),
			'conflicts'         => count( $plan['conflicts'] ),
			'examples'          => array_map( $pick, array_slice( $plan['moves'], 0, self::EXAMPLES ) ),
			'conflict_examples' => array_map( $pick, array_slice( $plan['conflicts'], 0, self::EXAMPLES ) ),
		);
	}
}
