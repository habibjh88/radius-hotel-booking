<?php
/**
 * InvoiceVersion data access.
 *
 * @package RadiusTheme\RadiusHotelBooking\Repositories
 */

namespace RadiusTheme\RadiusHotelBooking\Repositories;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseRepository;
use RadiusTheme\RadiusHotelBooking\Models\InvoiceVersion;

defined( 'ABSPATH' ) || exit;

/**
 * Queries on `invoice_versions`.
 */
class InvoiceVersionRepository extends BaseRepository {

	/**
	 * Model class.
	 *
	 * @var string
	 */
	protected string $model = InvoiceVersion::class;

	/**
	 * An invoice's versions, oldest first.
	 *
	 * @param int $invoice_id Invoice id.
	 * @return InvoiceVersion[]
	 */
	public function forInvoice( int $invoice_id ): array {
		$rows = InvoiceVersion::query()->where( 'invoice_id', '=', $invoice_id )->orderBy( 'version', 'ASC' )->get();
		return array_map( static fn( $row ) => InvoiceVersion::hydrate( $row ), $rows );
	}
}
