<?php
/**
 * Rate plan data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\RatePlan;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `rate_plans` (soft-deleted rows excluded).
 */
class RatePlanRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = RatePlan::class;

	/**
	 * Every plan in display order.
	 *
	 * @param bool $active_only Only active plans.
	 * @return RatePlan[]
	 */
	public function ordered( bool $active_only = false ): array {
		$query = RatePlan::query();
		if ( $active_only ) {
			$query->where( 'is_active', '=', 1 );
		}
		$rows = $query->orderBy( 'sort_order' )->orderBy( 'id' )->get();
		return array_map( static fn( $row ) => RatePlan::hydrate( $row ), $rows );
	}

	/**
	 * Whether a code is taken (removed plans included, so a code is never reused).
	 *
	 * @param string $code      Code.
	 * @param int    $except_id Ignore this plan.
	 * @return bool
	 */
	public function codeTaken( string $code, int $except_id = 0 ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uniqueness check.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE code = %s AND id <> %d LIMIT 1', rtbp_table( 'rate_plans' ), $code, $except_id ) );
	}

	/**
	 * The next sort position.
	 *
	 * @return int
	 */
	public function nextSortOrder(): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- small read.
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX(sort_order), -1 ) + 1 FROM %i WHERE deleted_at IS NULL', rtbp_table( 'rate_plans' ) ) );
	}

	/**
	 * Lock a live plan row until the transaction ends.
	 *
	 * @param int $plan_id Plan id.
	 * @return bool Whether the plan exists and is not removed.
	 */
	public function lock( int $plan_id ): bool {
		return $this->lockRow( 'rate_plans', $plan_id, true );
	}
}
