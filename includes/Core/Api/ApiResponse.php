<?php
/**
 * Standardised REST response envelope.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api
 */

namespace RadiusTheme\RadiusHotelBooking\Core\Api;

use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use Throwable;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

/**
 * Class ApiResponse
 *
 * Provides a standardized structure for REST API responses.
 * Includes helper static methods for common response types.
 *
 * @package RadiusTheme\RadiusHotelBooking\Core\Api
 */
class ApiResponse {
	/**
	 * HTTP status code of the response.
	 *
	 * @var int
	 */
	private int $statusCode;

	/**
	 * Main response data.
	 *
	 * @var array
	 */
	private array $data;

	/**
	 * Optional response message.
	 *
	 * @var string|null
	 */
	private ?string $message;

	/**
	 * Optional validation or error messages.
	 *
	 * @var array|null
	 */
	private ?array $errors;

	/**
	 * Optional metadata (pagination, etc.).
	 *
	 * @var array
	 */
	private array $meta;

	/**
	 * Machine-readable error code (e.g. `room_unavailable`), sent as `code`.
	 *
	 * @var string|null
	 */
	private ?string $code = null;

	/**
	 * ApiResponse constructor.
	 *
	 * @param int         $statusCode HTTP status code.
	 * @param array       $data       Response data.
	 * @param string|null $message    Message string.
	 * @param array|null  $errors     Error data.
	 * @param array       $meta       Additional metadata.
	 */
	public function __construct(
		int $statusCode = 200,
		array $data = array(),
		?string $message = null,
		?array $errors = null,
		array $meta = array()
	) {
		$this->statusCode = $statusCode;
		$this->data       = $data;
		$this->message    = $message;
		$this->errors     = $errors;
		$this->meta       = $meta;
	}

	/**
	 * Create a success response.
	 *
	 * @param array       $data    The response data.
	 * @param string|null $message Optional message.
	 * @param array       $meta    Optional metadata.
	 *
	 * @return self
	 */
	public static function success( array $data = array(), ?string $message = null, array $meta = array() ): self {
		return new self( 200, $data, $message, null, $meta );
	}

	/**
	 * Create a 201 Created response.
	 *
	 * @param array  $data    The response data.
	 * @param string $message Optional success message.
	 *
	 * @return self
	 */
	public static function created( array $data = array(), string $message = 'Resource created successfully' ): self {
		return new self( 201, $data, $message );
	}

	/**
	 * Create an error response.
	 *
	 * @param string $message    Error message.
	 * @param int    $statusCode HTTP status code (default: 400).
	 * @param array  $errors     Error details.
	 *
	 * @return self
	 */
	public static function error( string $message, int $statusCode = 400, array $errors = array() ): self {
		return new self( $statusCode, array(), $message, $errors );
	}

	/**
	 * Create a 404 Not Found response.
	 *
	 * @param string $message Optional not found message.
	 *
	 * @return self
	 */
	public static function notFound( string $message = 'Resource not found' ): self {
		return new self( 404, array(), $message );
	}

	/**
	 * Create a 401 Unauthorized response.
	 *
	 * @param string $message Optional unauthorized message.
	 *
	 * @return self
	 */
	public static function unauthorized( string $message = 'Unauthorized' ): self {
		return new self( 401, array(), $message );
	}

	/**
	 * Create a 403 Forbidden response.
	 *
	 * @param string $message Optional forbidden message.
	 *
	 * @return self
	 */
	public static function forbidden( string $message = 'Forbidden' ): self {
		return new self( 403, array(), $message );
	}

	/**
	 * Create a 422 Validation Error response.
	 *
	 * @param array  $errors  Validation error messages.
	 * @param string $message Optional validation error message.
	 *
	 * @return self
	 */
	public static function validationError( array $errors, string $message = 'Validation failed' ): self {
		return new self( 422, array(), $message, $errors );
	}

	/**
	 * Attach a machine-readable error code.
	 *
	 * @param string $code Code.
	 * @return self
	 */
	public function withCode( string $code ): self {
		$this->code = $code;
		return $this;
	}

	/**
	 * Attach data, e.g. the details of an error (`{ key }` on `access_locked`).
	 *
	 * @param array $data Data.
	 * @return self
	 */
	public function withData( array $data ): self {
		$this->data = $data;
		return $this;
	}

	/**
	 * The response for an exception thrown while handling a request.
	 *
	 * A DomainException becomes its own status, code, message, field errors
	 * and context. Anything else is an unexpected failure: a 500 whose real
	 * message is only shown when WP_DEBUG is on (it may reveal SQL or paths),
	 * and is always written to the PHP error log.
	 *
	 * @param Throwable $e Exception.
	 * @return self
	 */
	public static function fromThrowable( Throwable $e ): self {
		if ( $e instanceof DomainException ) {
			$field_errors = array();
			foreach ( $e->getFieldErrors() as $field => $messages ) {
				$messages               = (array) $messages;
				$field_errors[ $field ] = array(
					'field'         => $field,
					'messages'      => $messages,
					'first_message' => (string) ( $messages[0] ?? '' ),
				);
			}

			return ( new self( $e->getStatus(), $e->getContext(), $e->getMessage(), $field_errors ? $field_errors : null ) )
				->withCode( $e->getErrorCode() );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- server-side record of an unexpected failure.
		error_log( sprintf( '[radius-hotel-booking] %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine() ) );

		$message = defined( 'WP_DEBUG' ) && WP_DEBUG
			? $e->getMessage()
			: __( 'Something went wrong. Please try again.', 'radius-hotel-booking' );

		return ( new self( 500, array(), $message ) )->withCode( 'server_error' );
	}

	/**
	 * Convert the response to an array format.
	 *
	 * @return array The formatted response.
	 */
	public function toArray(): array {
		$response = array(
			'success'     => $this->statusCode >= 200 && $this->statusCode < 300,
			'status_code' => $this->statusCode,
		);

		if ( $this->message ) {
			$response['message'] = $this->message;
		}

		if ( ! empty( $this->data ) ) {
			$response['data'] = $this->data;
		}

		if ( ! empty( $this->errors ) ) {
			$response['errors'] = $this->errors;
		}

		if ( ! empty( $this->meta ) ) {
			$response['meta'] = $this->meta;
		}

		if ( null !== $this->code ) {
			$response['code'] = $this->code;
		}

		/**
		 * Filter the API response array before sending.
		 *
		 * @since 1.0.0
		 *
		 * @param array $response The response array.
		 * @param ApiResponse $this The current instance.
		 */
		return apply_filters( 'rtbp_api_response_array', $response, $this );
	}

	/**
	 * Send the response as a WP_REST_Response.
	 *
	 * @return WP_REST_Response
	 */
	public function send(): WP_REST_Response {
		$responseArray = $this->toArray();

		/**
		 * Fires right before sending the REST API response.
		 *
		 * @since 1.0.0
		 *
		 * @param array $responseArray The final response data.
		 * @param int   $statusCode    The HTTP status code.
		 */
		do_action( 'rtbp_api_response_send', $responseArray, $this->statusCode );

		return new WP_REST_Response( $responseArray, $this->statusCode );
	}
}
