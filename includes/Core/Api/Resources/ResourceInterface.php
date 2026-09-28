<?php
/**
 * RadiusHotelBooking API Authentication Middleware
 *
 * This middleware checks if the user is authenticated before allowing access to the API.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api\Middleware
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api\Resources;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * Core/Api/Resources/ResourceInterface.php
 * Interface for API resources (transformers)
 */
interface ResourceInterface {
	/**
	 * Transforms the given data into a specific format or structure.
	 *
	 * @param mixed $data The input data to be transformed.
	 *
	 * @return array The transformed data as an array.
	 */
	public function transform( $data ): array;
}
