<?php
/**
 * Reports: the registry and the sales figures (M10).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Reports
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Reports;

use RadiusTheme\RadiusHotelBooking\Core\Events\EventDispatcher;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Services\Export\ExportWriter;
use RadiusTheme\RadiusHotelBooking\Repositories\ReportRepository;
use RadiusTheme\RadiusHotelBooking\Settings\PaymentSettings;
use RadiusTheme\RadiusHotelBooking\Support\Dates;
use RadiusTheme\RadiusHotelBooking\Support\Money;

defined( 'ABSPATH' ) || exit;

/**
 * Every report is a **definition** in the `rtbp_report_definitions` registry:
 *
 *     'sales' => array(
 *         'label'  => 'Sales',
 *         'access' => 'page.reports_sales',          // the key the endpoint enforces
 *         'build'  => callable( ReportRange $range, array $input ): array,
 *     )
 *
 * `GET reports/{name}` resolves the definition, checks its access key, and
 * returns `build()`'s payload, cached per (report, input) until a booking,
 * payment or block changes. Pro adds its reports (rooms, room availability,
 * occupancy, revenue breakdowns) to the same registry.
 *
 * **Sales** (10.1–10.7). All money comes from stored lines and payments;
 * nothing is re-priced.
 *
 * - *Revenue* — the frozen `total` of the period's lines that were sold: not
 *   cancelled, declined, no-show, nor on a booking whose money was all refunded.
 * - *Net sales* / *tax* — prices include the tax: tax = revenue × rate ÷
 *   (100 + rate), at the current invoice tax rate (the invoice's own formula).
 * - *Completed* — sold lines of fully paid bookings. *Unsuccessful* —
 *   cancelled or declined lines, and lines of refunded bookings. *No-shows* apart.
 * - *Collected* — the signed sum of the ledger (see `ReportRepository::collected()`).
 * - Chart and method table: a line's revenue goes to its booking's latest
 *   payment's method, or *Not paid yet*.
 */
class ReportService {

	/**
	 * Seconds a report is cached.
	 */
	public const TTL = 600;

	/**
	 * Option bumped whenever booked data changes (drops every cached report).
	 */
	public const VERSION_OPTION = 'rtbp_reports_version';

	/**
	 * Data access.
	 *
	 * @var ReportRepository
	 */
	private ReportRepository $reports;

	/**
	 * Constructor.
	 *
	 * @param ReportRepository|null $reports Data access.
	 */
	public function __construct( ?ReportRepository $reports = null ) {
		$this->reports = $reports ?? new ReportRepository();
	}

	/**
	 * Hook in: drop cached reports when booked data changes.
	 *
	 * @return void
	 */
	public static function init(): void {
		// Everything a cached figure reads: bookings and payments, blocks, rooms
		// and their states, room types and rate plans (names, the sellable rooms),
		// settings (the tax rate) — M10 critical review.
		$hooks = array(
			'rtbp_booking_created',
			'rtbp_booking_changed',
			'rtbp_booking_status_changed',
			'rtbp_payment_recorded',
			'rtbp_block_changed',
			'rtbp_room_created',
			'rtbp_room_deleted',
			'rtbp_room_moved',
			'rtbp_room_state_changed',
			'rtbp_room_type_created',
			'rtbp_room_type_updated',
			'rtbp_room_type_deleted',
			'rtbp_rate_plan_created',
			'rtbp_rate_plan_updated',
			'rtbp_rate_plan_deleted',
			'rtbp_settings_updated',
		);
		foreach ( $hooks as $hook ) {
			add_action( $hook, array( self::class, 'flush' ) );
		}
		// Edits with no hook of their own (a guest renamed, a room renumbered, a
		// floor renamed): the models' lifecycle events.
		foreach ( array( 'Guest', 'Room', 'Floor', 'RoomType' ) as $model ) {
			foreach ( array( 'created', 'updated', 'deleted' ) as $event ) {
				EventDispatcher::listen( 'RadiusTheme\\RadiusHotelBooking\\Models\\' . $model . '.' . $event, array( self::class, 'flush' ) );
			}
		}
	}

	/**
	 * Drop every cached report (the cache keys carry this version).
	 *
	 * @return void
	 */
	public static function flush(): void {
		update_option( self::VERSION_OPTION, (string) microtime( true ), false );
	}

	/**
	 * The registered reports.
	 *
	 * @return array<string, array{label: string, access: string, build: callable}>
	 */
	public static function definitions(): array {
		$definitions = array(
			'sales' => array(
				'label'  => __( 'Sales', 'radius-hotel-booking' ),
				'access' => 'page.reports_sales',
				'build'  => static fn( ReportRange $range ) => ( new self() )->sales( $range ),
				'export' => static fn( ReportRange $range ) => ( new self() )->salesTables( $range ),
			),
		);

		/**
		 * Filters the reports `GET reports/{name}` can serve.
		 *
		 * Each entry: `label`, `access` (an access key, enforced by the endpoint),
		 * `build` (callable receiving the `ReportRange` and the raw input,
		 * returning the payload), `cache` (default true; false for live data),
		 * `modes` (default true; false when the date mode does not apply),
		 * `export` (optional callable, same arguments, returning the tables
		 * `ExportWriter` writes: `[ { title, columns, rows } ]`).
		 *
		 * @param array $definitions Name => definition.
		 */
		$definitions = (array) apply_filters( 'rtbp_report_definitions', $definitions );

		return array_filter(
			$definitions,
			static fn( $definition, $name ) => is_string( $name ) && preg_match( '/^[a-z0-9-]+$/', $name ) && is_array( $definition ) && ! empty( $definition['access'] ) && is_callable( $definition['build'] ?? null ),
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * One report's definition.
	 *
	 * @param string $name Report name.
	 * @return array
	 * @throws DomainException 404 when no such report exists.
	 */
	public static function definition( string $name ): array {
		$definitions = self::definitions();
		if ( ! isset( $definitions[ $name ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This report does not exist.', 'radius-hotel-booking' ) );
		}
		return $definitions[ $name ];
	}

	/**
	 * Run a report (cached).
	 *
	 * @param string $name  Report name.
	 * @param array  $input Request input (`from`, `to`, `mode`, and the report's own).
	 * @return array `{ range, …payload }`.
	 * @throws DomainException 404 / 422.
	 */
	public function run( string $name, array $input ): array {
		$definition = self::definition( $name );
		$input      = self::withoutMode( $definition, $input );
		$range      = ReportRange::fromInput( $input );
		ksort( $input );
		$key = 'rtbp_report_' . md5( wp_json_encode( array( $name, $input, $range->toArray(), get_option( self::VERSION_OPTION, '' ), determine_locale() ) ) );

		$cache  = false !== ( $definition['cache'] ?? true );
		$cached = $cache ? get_transient( $key ) : false;
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$payload = array_merge( array( 'range' => $range->toArray() ), (array) call_user_func( $definition['build'], $range, $input ) );
		if ( $cache ) {
			set_transient( $key, $payload, self::TTL );
		}
		return $payload;
	}

	/**
	 * A report that declares `modes => false` ignores the date mode (Pro's
	 * occupancy is about stays): the mode is dropped from its input, so its
	 * cache, file name and log never say *by booking date*.
	 *
	 * @param array $definition Report definition.
	 * @param array $input      Request input.
	 * @return array
	 */
	private static function withoutMode( array $definition, array $input ): array {
		if ( false === ( $definition['modes'] ?? true ) ) {
			unset( $input['mode'] );
		}
		return $input;
	}

	/**
	 * Export a report (10.14): every row, in a format of `rtbp_export_formats`.
	 * Logged (`reports.export`): figures leave the system.
	 *
	 * @param string $name   Report name.
	 * @param string $format Format key (`csv`, Pro adds `xlsx`).
	 * @param array  $input  Request input (as `run()`).
	 * @return array `{ filename, mime, content }` (raw bytes).
	 * @throws DomainException 404 unknown report, 422 bad input / format or a report without export.
	 */
	public function export( string $name, string $format, array $input ): array {
		$definition = self::definition( $name );
		if ( ! is_callable( $definition['export'] ?? null ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'format' => __( 'This report cannot be exported.', 'radius-hotel-booking' ) ) );
		}
		if ( ! isset( ExportWriter::formats()[ $format ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'format' => __( 'Choose an export format.', 'radius-hotel-booking' ) ) );
		}
		$input  = self::withoutMode( $definition, $input );
		$range  = ReportRange::fromInput( $input );
		$tables = (array) call_user_func( $definition['export'], $range, $input );
		$name_parts = array( 'report', $name, $range->from, $range->to );
		if ( 'created' === $range->mode ) {
			$name_parts[] = 'booked';
		} elseif ( 'departure' === $range->mode ) {
			$name_parts[] = 'departures';
		}
		$file = ExportWriter::write( $format, $tables, implode( '-', $name_parts ) );

		rtbp_activity(
			'reports.export',
			null,
			array(
				'after'       => array(
					'report' => $name,
					'format' => $format,
					'from'   => $range->from,
					'to'     => $range->to,
					'mode'   => $range->mode,
				),
				'description' => sprintf(
					/* translators: 1: report name, 2: first day, 3: last day, 4: file format. */
					__( 'Exported the %1$s report, %2$s to %3$s, as %4$s', 'radius-hotel-booking' ),
					(string) $definition['label'],
					Dates::format( Dates::local( $range->from ), 'date' ),
					Dates::format( Dates::local( $range->to ), 'date' ),
					strtoupper( $format )
				),
			)
		);
		return $file;
	}

	/**
	 * The sales report as tables: the figures, the payment methods, and the
	 * chart's buckets with one column per method.
	 *
	 * @param ReportRange $range Period.
	 * @return array[] Tables.
	 */
	public function salesTables( ReportRange $range ): array {
		$report = $this->sales( $range );
		$totals = $report['totals'];
		$label  = '' !== $totals['tax_label'] ? $totals['tax_label'] : __( 'Tax', 'radius-hotel-booking' );

		$chart   = $report['chart'];
		$columns = array( 'method' === $chart['mode'] ? __( 'Payment method', 'radius-hotel-booking' ) : ( 'month' === $chart['mode'] ? __( 'Month', 'radius-hotel-booking' ) : __( 'Day', 'radius-hotel-booking' ) ) );
		foreach ( $chart['series'] as $series ) {
			$columns[] = $series['label'];
		}
		$rows = array();
		foreach ( $chart['points'] as $point ) {
			$row = array( $point['label'] ?? $point['key'] );
			foreach ( $chart['series'] as $series ) {
				$row[] = (float) ( $point['values'][ $series['key'] ] ?? 0 );
			}
			$rows[] = $row;
		}

		return array(
			array(
				'title'   => self::periodTitle( __( 'Sales', 'radius-hotel-booking' ), $range ),
				'columns' => array( __( 'Figure', 'radius-hotel-booking' ), __( 'Value', 'radius-hotel-booking' ) ),
				'rows'    => array(
					array( __( 'Sales with tax included', 'radius-hotel-booking' ), $totals['revenue'] ),
					array( __( 'Net sales', 'radius-hotel-booking' ), $totals['net_sales'] ),
					/* translators: 1: tax name, 2: tax rate. */
					array( sprintf( __( '%1$s (%2$s%%)', 'radius-hotel-booking' ), $label, $totals['tax_rate'] ), $totals['tax'] ),
					array( __( 'Collected', 'radius-hotel-booking' ), $totals['collected'] ),
					array( __( 'Rooms sold', 'radius-hotel-booking' ), $totals['sold'] ),
					array( __( 'Completed', 'radius-hotel-booking' ), $totals['completed'] ),
					array( __( 'Unsuccessful', 'radius-hotel-booking' ), $totals['unsuccessful'] ),
					array( __( 'No-shows', 'radius-hotel-booking' ), $totals['no_show'] ),
				),
			),
			array(
				'title'   => __( 'Payment methods', 'radius-hotel-booking' ),
				'columns' => array( __( 'Method', 'radius-hotel-booking' ), __( 'Bookings', 'radius-hotel-booking' ), __( 'Rooms', 'radius-hotel-booking' ), __( 'Sales', 'radius-hotel-booking' ), __( 'Collected', 'radius-hotel-booking' ) ),
				'rows'    => array_map( static fn( $row ) => array( $row['label'], $row['bookings'], $row['lines'], $row['revenue'], $row['collected'] ), $report['methods'] ),
			),
			array(
				'title'   => __( 'Sales by payment method', 'radius-hotel-booking' ),
				'columns' => $columns,
				'rows'    => $rows,
			),
		);
	}

	/**
	 * A status in words, for exports (the same words as the screen's badges,
	 * `src/lib/status.js`); an unknown value is written as it is.
	 *
	 * @param string $status Stay, payment, grid or room status.
	 * @return string
	 */
	public static function statusLabel( string $status ): string {
		$labels = array(
			'pending'        => __( 'Awaiting approval', 'radius-hotel-booking' ),
			'confirmed'      => __( 'Confirmed', 'radius-hotel-booking' ),
			'checked_in'     => __( 'Checked in', 'radius-hotel-booking' ),
			'checked_out'    => __( 'Checked out', 'radius-hotel-booking' ),
			'cancelled'      => __( 'Cancelled', 'radius-hotel-booking' ),
			'declined'       => __( 'Declined', 'radius-hotel-booking' ),
			'no_show'        => __( 'No-show', 'radius-hotel-booking' ),
			'unpaid'         => __( 'Unpaid', 'radius-hotel-booking' ),
			'partially_paid' => __( 'Partially paid', 'radius-hotel-booking' ),
			'paid'           => __( 'Paid', 'radius-hotel-booking' ),
			'refunded'       => __( 'Refunded', 'radius-hotel-booking' ),
			'free'           => __( 'Free', 'radius-hotel-booking' ),
			'booked'         => __( 'Booked', 'radius-hotel-booking' ),
			'held'           => __( 'Held', 'radius-hotel-booking' ),
			'blocked'        => __( 'Blocked', 'radius-hotel-booking' ),
			'maintenance'    => __( 'Maintenance', 'radius-hotel-booking' ),
			'out_of_service' => __( 'Out of service', 'radius-hotel-booking' ),
		);
		return $labels[ $status ] ?? $status;
	}

	/**
	 * A table title with its period and date mode.
	 *
	 * @param string      $name  Report name.
	 * @param ReportRange $range Period.
	 * @return string
	 */
	public static function periodTitle( string $name, ReportRange $range ): string {
		return sprintf(
			/* translators: 1: report name, 2: first day, 3: last day, 4: "by arrival date", "by departure date" or "by booking date". */
			__( '%1$s, %2$s to %3$s, %4$s', 'radius-hotel-booking' ),
			$name,
			$range->from,
			$range->to,
			array(
				'created'   => __( 'by booking date', 'radius-hotel-booking' ),
				'departure' => __( 'by departure date', 'radius-hotel-booking' ),
			)[ $range->mode ] ?? __( 'by arrival date', 'radius-hotel-booking' )
		);
	}

	/**
	 * The sales report.
	 *
	 * @param ReportRange $range Period.
	 * @return array `{ totals, chart, methods }`.
	 */
	public function sales( ReportRange $range ): array {
		$unit   = $range->chartMode();
		$groups = $this->reports->salesGroups( $range, $unit );

		$revenue      = 0.0;
		$completed    = 0;
		$unsuccessful = 0;
		$no_show      = 0;
		$sold_lines   = 0;
		$by_bucket    = array();
		foreach ( $groups as $group ) {
			if ( 'failed' === $group['outcome'] ) {
				$unsuccessful += $group['lines'];
				continue;
			}
			if ( 'no_show' === $group['outcome'] ) {
				$no_show += $group['lines'];
				continue;
			}
			$revenue    += $group['total'];
			$sold_lines += $group['lines'];
			if ( $group['paid'] ) {
				$completed += $group['lines'];
			}
			$method = $group['method'];
			$bucket = 'method' === $unit ? $method : $group['bucket'];

			$by_bucket[ $bucket ][ $method ] = ( $by_bucket[ $bucket ][ $method ] ?? 0.0 ) + $group['total'];
		}

		// The method table counts each booking once: grouped without dates (a
		// booking whose rooms arrive on two days sits in two chart buckets).
		// One booking has one payment status and one latest method, so it falls
		// in a single sold group here.
		$methods = array();
		foreach ( 'method' === $unit ? $groups : $this->reports->salesGroups( $range, 'method' ) as $group ) {
			if ( 'sold' !== $group['outcome'] ) {
				continue;
			}
			$method             = $group['method'];
			$methods[ $method ] = array(
				'lines'    => ( $methods[ $method ]['lines'] ?? 0 ) + $group['lines'],
				'bookings' => ( $methods[ $method ]['bookings'] ?? 0 ) + $group['bookings'],
				'revenue'  => ( $methods[ $method ]['revenue'] ?? 0.0 ) + $group['total'],
			);
		}

		$collected = $this->reports->collected( $range );
		// Every method the hotel accepts is listed, at zero when unused in the period (R1 review).
		$accepted = array_map( static fn( $method ) => (string) $method['key'], PaymentSettings::enabled_methods() );
		foreach ( array_merge( array_keys( $collected ), $accepted ) as $method ) {
			$methods[ $method ] = $methods[ $method ] ?? array(
				'lines'    => 0,
				'bookings' => 0,
				'revenue'  => 0.0,
			);
		}

		$rate = max( 0.0, (float) rtbp_setting( 'invoices', 'taxRate', 0 ) );
		// Per booking, like its invoice (rounded each time), then added up.
		$tax = 0.0;
		if ( $rate > 0 ) {
			foreach ( $this->reports->soldPerBooking( $range ) as $total ) {
				$tax += Money::round( $total * $rate / ( 100 + $rate ) );
			}
			$tax = Money::round( $tax );
		}

		$method_rows = array();
		foreach ( $methods as $method => $row ) {
			$method_rows[] = array(
				'key'       => $method,
				'label'     => self::methodLabel( $method ),
				'lines'     => $row['lines'],
				'bookings'  => $row['bookings'],
				'revenue'   => Money::round( $row['revenue'] ),
				'collected' => Money::round( $collected[ $method ] ?? 0.0 ),
			);
		}
		usort( $method_rows, static fn( $a, $b ) => array( $b['revenue'], $a['label'] ) <=> array( $a['revenue'], $b['label'] ) );

		return array(
			'totals'  => array(
				'revenue'      => Money::round( $revenue ),
				'net_sales'    => Money::round( $revenue - $tax ),
				'tax'          => $tax,
				'tax_rate'     => $rate,
				'tax_label'    => (string) rtbp_setting( 'invoices', 'taxLabel', '' ),
				'collected'    => Money::round( array_sum( $collected ) ),
				'sold'         => $sold_lines,
				'completed'    => $completed,
				'unsuccessful' => $unsuccessful,
				'no_show'      => $no_show,
			),
			'chart'   => $this->chart( $range, $unit, $by_bucket ),
			'methods' => $method_rows,
		);
	}

	/**
	 * The chart: one series per payment method; days or months zero-filled.
	 *
	 * @param ReportRange $range     Period.
	 * @param string      $unit      `method`, `day` or `month`.
	 * @param array       $by_bucket Bucket => method => amount.
	 * @return array `{ mode, series: [ { key, label } ], points: [ { key, values: { method: amount } } ] }`.
	 */
	private function chart( ReportRange $range, string $unit, array $by_bucket ): array {
		// Every accepted method has its bar or legend entry, at zero when unused.
		$accepted = array_map( static fn( $method ) => (string) $method['key'], PaymentSettings::enabled_methods() );
		if ( 'method' === $unit ) {
			foreach ( $accepted as $method ) {
				$by_bucket[ $method ] = $by_bucket[ $method ] ?? array();
			}
			$points = array();
			foreach ( $by_bucket as $method => $amounts ) {
				$points[] = array(
					'key'    => (string) $method,
					'label'  => self::methodLabel( (string) $method ),
					'values' => array( 'sales' => Money::round( array_sum( $amounts ) ) ),
				);
			}
			return array(
				'mode'   => 'method',
				'series' => array(
					array(
						'key'   => 'sales',
						'label' => __( 'Sales', 'radius-hotel-booking' ),
					),
				),
				'points' => $points,
			);
		}

		$series = array_fill_keys( $accepted, true );
		foreach ( $by_bucket as $amounts ) {
			foreach ( array_keys( $amounts ) as $method ) {
				$series[ (string) $method ] = true;
			}
		}
		$series = array_keys( $series );
		sort( $series );

		$points = array();
		foreach ( $range->buckets( $unit ) as $bucket ) {
			$values = array();
			foreach ( $series as $method ) {
				$values[ '' !== $method ? $method : 'unpaid' ] = Money::round( $by_bucket[ $bucket ][ $method ] ?? 0.0 );
			}
			$points[] = array(
				'key'    => $bucket,
				'values' => $values,
			);
		}
		return array(
			'mode'   => $unit,
			'series' => array_map(
				static fn( $method ) => array(
					'key'   => '' !== $method ? $method : 'unpaid',
					'label' => self::methodLabel( $method ),
				),
				$series
			),
			'points' => $points,
		);
	}

	/**
	 * A method's name; `''` = no payment yet.
	 *
	 * @param string $key Method key.
	 * @return string
	 */
	public static function methodLabel( string $key ): string {
		return '' === $key ? __( 'Not paid yet', 'radius-hotel-booking' ) : PaymentSettings::method_label( $key, true );
	}
}
