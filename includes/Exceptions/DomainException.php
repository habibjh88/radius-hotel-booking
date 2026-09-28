<?php
/**
 * Domain exception.
 *
 * @package RadiusTheme\RadiusHotelBooking\Exceptions
 */

namespace RadiusTheme\RadiusHotelBooking\Exceptions;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * A business-rule failure a service reports to its caller: "room taken",
 * "illegal transition", "guest banned".
 *
 * It carries a stable machine-readable code (the UI and add-ons branch on it,
 * e.g. `room_unavailable`, `passcode_required`), an HTTP status and optional
 * per-field errors. Controllers never build these responses by hand:
 * `ApiResponse::fromThrowable()` turns one into the standard envelope, and the
 * JS client exposes the code as `error.code`.
 *
 * The message is shown to staff or guests, so it must be translated.
 */
class DomainException extends RuntimeException {

	/**
	 * Machine-readable code.
	 *
	 * @var string
	 */
	private string $error_code;

	/**
	 * HTTP status.
	 *
	 * @var int
	 */
	private int $status;

	/**
	 * Per-field errors: field => message(s).
	 *
	 * @var array
	 */
	private array $field_errors;

	/**
	 * Extra data for the client (e.g. the conflicting booking reference).
	 *
	 * @var array
	 */
	private array $context;

	/**
	 * Create the exception.
	 *
	 * @param string $error_code   Stable code, snake_case.
	 * @param string $message      Translated, user-facing message.
	 * @param int    $status       HTTP status (400, 403, 404, 409, 422 …).
	 * @param array  $field_errors Field => message(s), for form errors.
	 * @param array  $context      Extra data returned to the client.
	 */
	public function __construct( string $error_code, string $message, int $status = 400, array $field_errors = array(), array $context = array() ) {
		parent::__construct( $message );
		$this->error_code   = $error_code;
		$this->status       = $status;
		$this->field_errors = $field_errors;
		$this->context      = $context;
	}

	/**
	 * The machine-readable code.
	 *
	 * @return string
	 */
	public function getErrorCode(): string {
		return $this->error_code;
	}

	/**
	 * The HTTP status.
	 *
	 * @return int
	 */
	public function getStatus(): int {
		return $this->status;
	}

	/**
	 * Per-field errors.
	 *
	 * @return array
	 */
	public function getFieldErrors(): array {
		return $this->field_errors;
	}

	/**
	 * Extra data for the client.
	 *
	 * @return array
	 */
	public function getContext(): array {
		return $this->context;
	}

	/**
	 * 404: a record does not exist (or the user may not know it exists).
	 *
	 * @param string $message Translated message.
	 * @return self
	 */
	public static function notFound( string $message ): self {
		return new self( 'not_found', $message, 404 );
	}

	/**
	 * 409: the request conflicts with the current state (room taken, stale price).
	 *
	 * @param string $error_code Code.
	 * @param string $message    Translated message.
	 * @param array  $context    Extra data.
	 * @return self
	 */
	public static function conflict( string $error_code, string $message, array $context = array() ): self {
		return new self( $error_code, $message, 409, array(), $context );
	}

	/**
	 * 422: invalid input, with per-field messages.
	 *
	 * @param array  $field_errors Field => message(s).
	 * @param string $message      Translated summary.
	 * @return self
	 */
	public static function invalid( array $field_errors, string $message = '' ): self {
		return new self(
			'validation_failed',
			'' !== $message ? $message : __( 'Please correct the highlighted fields.', 'radius-hotel-booking' ),
			422,
			$field_errors
		);
	}
}
