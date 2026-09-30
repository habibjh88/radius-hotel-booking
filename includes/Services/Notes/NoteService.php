<?php
/**
 * Notes on records (M09; reused by M03 and M12).
 *
 * @package RadiusTheme\RadiusHotelBooking\Services\Notes
 */

namespace RadiusTheme\RadiusHotelBooking\Services\Notes;

use RadiusTheme\RadiusHotelBooking\ActivityLog\ChangeDiff;
use RadiusTheme\RadiusHotelBooking\Exceptions\DomainException;
use RadiusTheme\RadiusHotelBooking\Models\Note;
use RadiusTheme\RadiusHotelBooking\Repositories\BookingRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\GuestRepository;
use RadiusTheme\RadiusHotelBooking\Repositories\NoteRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Author-stamped notes (9.8, 9.9) on any record type registered through
 * `rtbp_note_types`: `type => { read, add, edit, remove (access keys),
 * find (callable id → subject array|null), activity (action prefix) }`.
 * Free registers `guest` and `booking` (M03); staff records (M12, Pro) add
 * theirs. Every change is logged against the record.
 */
class NoteService {

	/**
	 * Longest note body, in characters.
	 */
	public const MAX_BODY = 5000;

	/**
	 * Notes.
	 *
	 * @var NoteRepository
	 */
	private NoteRepository $notes;

	/**
	 * Constructor.
	 *
	 * @param NoteRepository|null $notes Notes.
	 */
	public function __construct( ?NoteRepository $notes = null ) {
		$this->notes = $notes ?? new NoteRepository();
	}

	/**
	 * The registered record types.
	 *
	 * @return array<string, array{read: string, add: string, edit: string, remove: string, find: callable, activity: string}>
	 */
	public static function types(): array {
		$types = array(
			'guest' => array(
				'read'     => 'page.guests',
				'add'      => 'guests.note_add',
				'edit'     => 'guests.note_edit',
				'remove'   => 'guests.note_remove',
				'activity' => 'guests.note',
				'find'     => static function ( int $id ): ?array {
					$guest = ( new GuestRepository() )->find( $id );
					return $guest ? array(
						'type'  => 'guest',
						'id'    => (int) $guest->id,
						'label' => $guest->fullName(),
					) : null;
				},
			),
			// The booking record (M03, 3.13): general / caution / warning notes for the team.
			'booking' => array(
				'read'     => 'page.bookings',
				'add'      => 'bookings.note_add',
				'edit'     => 'bookings.note_edit',
				'remove'   => 'bookings.note_remove',
				'activity' => 'bookings.note',
				'find'     => static function ( int $id ): ?array {
					$booking = ( new BookingRepository() )->find( $id );
					return $booking ? array(
						'type'  => 'booking',
						'id'    => (int) $booking->id,
						'label' => (string) $booking->reference,
					) : null;
				},
			),
		);
		/**
		 * Record types notes can be attached to. Each entry names the access
		 * keys to read, add, edit and remove its notes, a `find( $id )`
		 * callable returning the activity subject `{ type, id, label }` (or
		 * null when missing), and the activity prefix (`<prefix>_add|_edit|
		 * _remove`, registered in the action catalogue).
		 *
		 * @param array $types Type => definition.
		 */
		return (array) apply_filters( 'rtbp_note_types', $types );
	}

	/**
	 * The access key a note action on a type needs ('' for an unknown type,
	 * which the middleware refuses).
	 *
	 * @param string $notable_type Type.
	 * @param string $action       read|add|edit|remove.
	 * @return string
	 */
	public static function accessKey( string $notable_type, string $action ): string {
		return (string) ( self::types()[ $notable_type ][ $action ] ?? '' );
	}

	/**
	 * The notes of a record, newest first.
	 *
	 * @param string $notable_type Type.
	 * @param int    $notable_id   Record id.
	 * @return Note[]
	 */
	public function list( string $notable_type, int $notable_id ): array {
		$this->subject( $notable_type, $notable_id );
		return $this->notes->forNotable( $notable_type, $notable_id );
	}

	/**
	 * Add a note.
	 *
	 * @param array $input `{ notable_type, notable_id, type, body }`.
	 * @return Note
	 * @throws \RuntimeException When the note cannot be written.
	 */
	public function add( array $input ): Note {
		$notable_type = sanitize_key( (string) ( $input['notable_type'] ?? '' ) );
		$notable_id   = absint( $input['notable_id'] ?? 0 );
		$subject      = $this->subject( $notable_type, $notable_id );
		$data         = $this->validate( $input );
		$user         = wp_get_current_user();

		$note = $this->notes->create(
			array_merge(
				$data,
				array(
					'notable_type' => $notable_type,
					'notable_id'   => $notable_id,
					'author_id'    => $user->ID ? (int) $user->ID : null,
					'author_name'  => $user->ID ? (string) $user->display_name : '',
				)
			)
		);
		if ( ! $note->id ) {
			throw new \RuntimeException( 'The note could not be saved.' );
		}
		$this->log( $notable_type, 'add', $subject, array( 'after' => $data ) );
		return $this->get( (int) $note->id );
	}

	/**
	 * Change a note's text or type.
	 *
	 * @param int   $id    Note id.
	 * @param array $input `{ type?, body? }`.
	 * @return Note
	 */
	public function edit( int $id, array $input ): Note {
		$note    = $this->get( $id );
		$subject = $this->subject( (string) $note->notable_type, (int) $note->notable_id );
		$data    = $this->validate(
			array(
				'type' => $input['type'] ?? $note->type,
				'body' => $input['body'] ?? $note->body,
			)
		);
		$diff    = ChangeDiff::between(
			array(
				'type' => (string) $note->type,
				'body' => (string) $note->body,
			),
			$data
		);
		if ( ! $diff['after'] ) {
			return $note;
		}
		$this->notes->update( $id, array_merge( $data, array( 'edited_by' => get_current_user_id() ? get_current_user_id() : null ) ) );
		$this->log( (string) $note->notable_type, 'edit', $subject, $diff );
		return $this->get( $id );
	}

	/**
	 * Remove a note.
	 *
	 * @param int $id Note id.
	 * @return void
	 */
	public function remove( int $id ): void {
		$note    = $this->get( $id );
		$subject = $this->subject( (string) $note->notable_type, (int) $note->notable_id );
		$this->notes->delete( $id );
		$this->log(
			(string) $note->notable_type,
			'remove',
			$subject,
			array(
				'before' => array(
					'type'   => (string) $note->type,
					'body'   => (string) $note->body,
					'author' => (string) $note->author_name,
				),
			)
		);
	}

	/**
	 * One note, or 404.
	 *
	 * @param int $id Id.
	 * @return Note
	 * @throws DomainException 404.
	 */
	public function get( int $id ): Note {
		$note = $id > 0 ? $this->notes->find( $id ) : null;
		if ( ! $note instanceof Note ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This note does not exist.', 'radius-hotel-booking' ) );
		}
		return $note;
	}

	/**
	 * The notable type of a note ('' when missing), for the access check.
	 *
	 * @param int $id Note id.
	 * @return string
	 */
	public function notableTypeOf( int $id ): string {
		return $this->notes->notableTypeOf( $id );
	}

	/**
	 * The record a note belongs to, or 404/422.
	 *
	 * @param string $notable_type Type.
	 * @param int    $notable_id   Id.
	 * @return array Activity subject.
	 * @throws DomainException 422 unknown type, 404 missing record.
	 */
	private function subject( string $notable_type, int $notable_id ): array {
		$types = self::types();
		if ( ! isset( $types[ $notable_type ] ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( array( 'notable_type' => __( 'Notes cannot be added here.', 'radius-hotel-booking' ) ) );
		}
		$subject = $notable_id > 0 ? call_user_func( $types[ $notable_type ]['find'], $notable_id ) : null;
		if ( ! $subject ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::notFound( __( 'This record does not exist.', 'radius-hotel-booking' ) );
		}
		return $subject;
	}

	/**
	 * Validated `{ type, body }`.
	 *
	 * @param array $input Input.
	 * @return array
	 * @throws DomainException 422.
	 */
	private function validate( array $input ): array {
		$errors = array();
		$type   = sanitize_key( (string) ( $input['type'] ?? 'general' ) );
		if ( ! in_array( $type, Note::TYPES, true ) ) {
			$errors['type'] = __( 'Choose the kind of note.', 'radius-hotel-booking' );
		}
		$body = trim( sanitize_textarea_field( (string) ( $input['body'] ?? '' ) ) );
		if ( '' === $body || mb_strlen( $body ) > self::MAX_BODY ) {
			/* translators: %d: longest note, in characters. */
			$errors['body'] = sprintf( __( 'Write the note (up to %d characters).', 'radius-hotel-booking' ), self::MAX_BODY );
		}
		if ( $errors ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- sent as JSON; React escapes it (phpcs.xml).
			throw DomainException::invalid( $errors );
		}
		return array(
			'type' => $type,
			'body' => $body,
		);
	}

	/**
	 * Log a note change against its record.
	 *
	 * @param string $notable_type Type.
	 * @param string $action       add|edit|remove.
	 * @param array  $subject      Record subject.
	 * @param array  $context      before/after.
	 * @return void
	 */
	private function log( string $notable_type, string $action, array $subject, array $context ): void {
		$prefix = (string) ( self::types()[ $notable_type ]['activity'] ?? '' );
		if ( '' === $prefix ) {
			return;
		}
		$descriptions = array(
			/* translators: %s: the record (e.g. a guest's name). */
			'add'    => __( 'Added a note on %s', 'radius-hotel-booking' ),
			/* translators: %s: the record (e.g. a guest's name). */
			'edit'   => __( 'Edited a note on %s', 'radius-hotel-booking' ),
			/* translators: %s: the record (e.g. a guest's name). */
			'remove' => __( 'Removed a note on %s', 'radius-hotel-booking' ),
		);
		rtbp_activity(
			$prefix . '_' . $action,
			$subject,
			array_merge( $context, array( 'description' => sprintf( $descriptions[ $action ], $subject['label'] ) ) )
		);
	}
}
