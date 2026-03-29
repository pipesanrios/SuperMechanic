<?php
/**
 * Public webhook event catalog.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

defined( 'ABSPATH' ) || exit;

/**
 * Defines the allowed outbound public webhook events.
 */
class Public_Webhook_Event_Catalog {
	/**
	 * Map internal event keys to public event keys.
	 *
	 * @return array<string,string>
	 */
	public function get_internal_event_map() {
		return array(
			'process_created'            => 'process.created',
			'process_status_changed'     => 'process.status_changed',
			'appointment_created'        => 'appointment.created',
			'appointment_status_changed' => 'appointment.status_changed',
		);
	}

	/**
	 * Get the full list of public events.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_events() {
		return array_values( array_unique( array_values( $this->get_internal_event_map() ) ) );
	}

	/**
	 * Map one internal event key to the public contract event.
	 *
	 * @param string $internal_event Internal event key.
	 * @return string
	 */
	public function map_internal_event( $internal_event ) {
		$internal_event = sanitize_key( (string) $internal_event );
		$map            = $this->get_internal_event_map();

		return isset( $map[ $internal_event ] ) ? $map[ $internal_event ] : '';
	}

	/**
	 * Check whether one public event key is allowed.
	 *
	 * @param string $event_key Event key.
	 * @return bool
	 */
	public function is_supported_event( $event_key ) {
		$event_key = sanitize_text_field( (string) $event_key );

		return in_array( $event_key, $this->get_supported_events(), true );
	}

	/**
	 * Normalize event subscriptions from storage.
	 *
	 * @param mixed $raw_events Raw events.
	 * @return array<int,string>
	 */
	public function normalize_subscribed_events( $raw_events ) {
		if ( is_string( $raw_events ) ) {
			$decoded = json_decode( $raw_events, true );
			if ( is_array( $decoded ) ) {
				$raw_events = $decoded;
			}
		}

		if ( ! is_array( $raw_events ) ) {
			return array();
		}

		$supported  = $this->get_supported_events();
		$normalized = array();

		foreach ( $raw_events as $event_key ) {
			$event_key = sanitize_text_field( (string) $event_key );

			if ( '*' === $event_key ) {
				return array( '*' );
			}

			if ( in_array( $event_key, $supported, true ) ) {
				$normalized[] = $event_key;
			}
		}

		return array_values( array_unique( $normalized ) );
	}
}
