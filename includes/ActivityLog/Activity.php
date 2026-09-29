<?php
/**
 * The activity emitter.
 *
 * @package RadiusTheme\RadiusHotelBooking\ActivityLog
 */

namespace RadiusTheme\RadiusHotelBooking\ActivityLog;

use RadiusTheme\RadiusHotelBooking\Abstracts\BaseModel;
use WP_User;

defined( 'ABSPATH' ) || exit;

/**
 * Builds a normalised activity event and fires `do_action( 'rtbp_activity',
 * $event )` (M14, ADR-015). The free plugin **stores nothing**: Pro's
 * ActivityLogger stores the event, and without Pro the event is simply heard
 * by nobody. Called through the global `rtbp_activity()`.
 *
 * The event:
 *
 *   action, kind, time (site-local), time_gmt,
 *   actor   { id, name, roles }   the current user, or `actor` in the context
 *   ip, user_agent
 *   subject { type, id, label }   null when the action has no single record
 *   description
 *   changes { before, after }     only changed fields, secrets masked
 *   meta    { … }                 every other context key, secrets masked
 */
final class Activity {

	/**
	 * Build and fire an event.
	 *
	 * @param string                  $action  Action key (the access key where one exists).
	 * @param BaseModel|array|null    $subject A model, `[ type, id, label ]`, or null.
	 * @param array                   $context `before`, `after`, `description`, `actor` (user id)
	 *                                         and any extra keys (kept under `meta`).
	 * @return array The event, as fired (empty when a filter dropped it).
	 */
	public static function emit( string $action, $subject = null, array $context = array() ): array {
		$actor = self::actor( $context['actor'] ?? null );

		$before  = (array) ( $context['before'] ?? array() );
		$after   = (array) ( $context['after'] ?? array() );
		$changes = ( $before || $after ) ? ChangeDiff::between( $before, $after ) : null;

		$meta = array_diff_key( $context, array_flip( array( 'before', 'after', 'description', 'actor' ) ) );

		$event = array(
			'action'      => $action,
			'kind'        => ActionCatalog::kind( $action ),
			'time'        => current_time( 'mysql' ),
			'time_gmt'    => current_time( 'mysql', true ),
			'actor'       => $actor,
			'ip'          => self::ip(),
			'user_agent'  => self::user_agent(),
			'subject'     => self::subject( $subject ),
			'description' => (string) ( $context['description'] ?? ActionCatalog::label( $action ) ),
			'changes'     => $changes,
			'meta'        => $meta ? ChangeDiff::mask( $meta ) : (object) array(),
		);

		/**
		 * Filters an activity event before it fires. Return an empty value to
		 * drop it.
		 *
		 * @param array $event Event.
		 */
		$event = apply_filters( 'rtbp_activity_event', $event );
		if ( ! is_array( $event ) || ! $event ) {
			return array();
		}

		/**
		 * Fires for every activity event. The free plugin stores nothing; the
		 * Pro activity log stores it (M14).
		 *
		 * @param array $event Event (see Activity).
		 */
		do_action( 'rtbp_activity', $event );

		return $event;
	}

	/**
	 * The actor: a given user id, or the current user (0 = nobody signed in).
	 *
	 * @param int|null $user_id User id.
	 * @return array{id: int, name: string, roles: string[]}
	 */
	private static function actor( $user_id ): array {
		$user = null !== $user_id ? get_user_by( 'id', (int) $user_id ) : wp_get_current_user();
		if ( ! $user instanceof WP_User || ! $user->exists() ) {
			return array(
				'id'    => 0,
				'name'  => '',
				'roles' => array(),
			);
		}
		return array(
			'id'    => (int) $user->ID,
			'name'  => (string) $user->display_name,
			'roles' => array_values( (array) $user->roles ),
		);
	}

	/**
	 * The request's IP address. The TCP peer only: forwarded headers can be
	 * set by anyone, so they are trusted only through the `rtbp_activity_ip`
	 * filter (Pro reads them behind configured proxies).
	 *
	 * @return string
	 */
	private static function ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$ip = false !== filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';

		/**
		 * Filters the IP address recorded with an activity event.
		 *
		 * @param string $ip The TCP peer address ('' when unknown, e.g. WP-CLI).
		 */
		return (string) apply_filters( 'rtbp_activity_ip', $ip );
	}

	/**
	 * The request's user agent, trimmed.
	 *
	 * @return string
	 */
	private static function user_agent(): string {
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
		return mb_substr( $agent, 0, 255 );
	}

	/**
	 * Normalise the subject.
	 *
	 * @param BaseModel|array|null $subject Subject.
	 * @return array{type: string, id: string, label: string}|null
	 */
	private static function subject( $subject ): ?array {
		if ( $subject instanceof BaseModel ) {
			$type  = strtolower( preg_replace( '/([a-z])([A-Z])/', '$1_$2', rtbp_class_basename( $subject ) ) );
			// A booking's reference, a room's number, or a name / title.
			$label = $subject->reference ?? $subject->number ?? $subject->name ?? $subject->title ?? '';
			return array(
				'type'  => $type,
				'id'    => (string) $subject->id,
				'label' => (string) $label,
			);
		}
		if ( is_array( $subject ) && isset( $subject['type'] ) ) {
			return array(
				'type'  => sanitize_key( (string) $subject['type'] ),
				'id'    => (string) ( $subject['id'] ?? '' ),
				'label' => (string) ( $subject['label'] ?? '' ),
			);
		}
		return null;
	}
}
