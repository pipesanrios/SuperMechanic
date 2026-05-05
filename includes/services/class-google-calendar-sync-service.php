<?php
/**
 * Google Calendar sync payload service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Builds calendar-ready payloads without contacting Google APIs.
 */
class Google_Calendar_Sync_Service {
	/**
	 * Build a normalized calendar event payload.
	 *
	 * @param array<string,mixed> $args Event args.
	 * @return array<string,mixed>
	 */
	public function build_event_payload( array $args ) {
		$timezone = $this->normalize_timezone( isset( $args['timezone'] ) ? $args['timezone'] : '' );
		$start    = $this->normalize_datetime( isset( $args['start'] ) ? $args['start'] : '' );
		$end      = $this->normalize_datetime( isset( $args['end'] ) ? $args['end'] : '' );

		if ( '' !== $start && '' === $end ) {
			$end = $this->offset_datetime( $start, '+1 hour' );
		}

		if ( '' !== $start && '' !== $end && strtotime( $end ) < strtotime( $start ) ) {
			$end = $this->offset_datetime( $start, '+1 hour' );
		}

		$metadata = isset( $args['metadata'] ) && is_array( $args['metadata'] ) ? $args['metadata'] : array();

		return array(
			'summary'     => isset( $args['summary'] ) ? sanitize_text_field( (string) $args['summary'] ) : '',
			'description' => isset( $args['description'] ) ? sanitize_textarea_field( (string) $args['description'] ) : '',
			'start'       => array(
				'datetime' => $start,
			),
			'end'         => array(
				'datetime' => $end,
			),
			'timezone'    => $timezone,
			'attendees'   => $this->normalize_attendees( isset( $args['attendees'] ) ? $args['attendees'] : array() ),
			'metadata'    => array(
				'source'      => isset( $metadata['source'] ) ? sanitize_key( (string) $metadata['source'] ) : 'mekvort',
				'entity_type' => isset( $metadata['entity_type'] ) ? sanitize_key( (string) $metadata['entity_type'] ) : '',
				'entity_id'   => isset( $metadata['entity_id'] ) ? absint( $metadata['entity_id'] ) : 0,
				'business_id' => isset( $metadata['business_id'] ) ? absint( $metadata['business_id'] ) : 0,
			),
		);
	}

	/**
	 * Build a calendar payload from an appointment row.
	 *
	 * @param array<string,mixed> $appointment Appointment data.
	 * @return array<string,mixed>
	 */
	public function build_appointment_event_payload( array $appointment ) {
		$appointment_id = isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;
		$client_name    = isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '';
		$vehicle_label  = $this->build_vehicle_label( $appointment );
		$status         = isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : '';
		$summary_parts  = array_filter(
			array(
				'Appointment',
				$client_name,
				$vehicle_label,
			)
		);

		$description = array_filter(
			array(
				'' !== $status ? 'Status: ' . $status : '',
				isset( $appointment['process_title'] ) && '' !== (string) $appointment['process_title'] ? 'Process: ' . sanitize_text_field( (string) $appointment['process_title'] ) : '',
				isset( $appointment['mechanic_name'] ) && '' !== (string) $appointment['mechanic_name'] ? 'Assigned to: ' . sanitize_text_field( (string) $appointment['mechanic_name'] ) : '',
				isset( $appointment['notes'] ) ? sanitize_textarea_field( (string) $appointment['notes'] ) : '',
			)
		);

		return $this->build_event_payload(
			array(
				'summary'     => implode( ' - ', $summary_parts ),
				'description' => implode( "\n", $description ),
				'start'       => isset( $appointment['start_at'] ) ? $appointment['start_at'] : '',
				'end'         => isset( $appointment['end_at'] ) ? $appointment['end_at'] : '',
				'attendees'   => $this->build_appointment_attendees( $appointment ),
				'metadata'    => array(
					'source'      => 'mekvort',
					'entity_type' => 'appointment',
					'entity_id'   => $appointment_id,
					'business_id' => isset( $appointment['business_id'] ) ? absint( $appointment['business_id'] ) : 0,
				),
			)
		);
	}

	/**
	 * Build a calendar payload from a process row.
	 *
	 * @param array<string,mixed> $process Process data.
	 * @return array<string,mixed>
	 */
	public function build_process_event_payload( array $process ) {
		$process_id    = isset( $process['id'] ) ? absint( $process['id'] ) : 0;
		$title         = isset( $process['title'] ) ? sanitize_text_field( (string) $process['title'] ) : '';
		$process_type  = isset( $process['process_type'] ) ? sanitize_key( (string) $process['process_type'] ) : '';
		$status        = isset( $process['status'] ) ? sanitize_key( (string) $process['status'] ) : '';
		$client_name   = isset( $process['client_name'] ) ? sanitize_text_field( (string) $process['client_name'] ) : '';
		$vehicle_label = $this->build_vehicle_label( $process );
		$start         = $this->first_datetime(
			array(
				isset( $process['opened_at'] ) ? $process['opened_at'] : '',
				isset( $process['created_at'] ) ? $process['created_at'] : '',
				isset( $process['due_date'] ) ? $process['due_date'] : '',
			)
		);
		$end           = $this->first_datetime(
			array(
				isset( $process['due_date'] ) ? $process['due_date'] : '',
				isset( $process['completed_at'] ) ? $process['completed_at'] : '',
				isset( $process['closed_at'] ) ? $process['closed_at'] : '',
			)
		);

		$description = array_filter(
			array(
				'' !== $process_type ? 'Type: ' . $process_type : '',
				'' !== $status ? 'Status: ' . $status : '',
				'' !== $client_name ? 'Client: ' . $client_name : '',
				'' !== $vehicle_label ? 'Vehicle: ' . $vehicle_label : '',
				isset( $process['internal_notes'] ) ? sanitize_textarea_field( (string) $process['internal_notes'] ) : '',
			)
		);

		return $this->build_event_payload(
			array(
				'summary'     => '' !== $title ? $title : 'Process #' . $process_id,
				'description' => implode( "\n", $description ),
				'start'       => $start,
				'end'         => $end,
				'metadata'    => array(
					'source'      => 'mekvort',
					'entity_type' => 'process',
					'entity_id'   => $process_id,
					'business_id' => isset( $process['business_id'] ) ? absint( $process['business_id'] ) : 0,
				),
			)
		);
	}

	/**
	 * Validate a normalized event payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return array<string,mixed>
	 */
	public function validate_event_payload( array $payload ) {
		$errors = array();

		foreach ( array( 'summary', 'description', 'timezone' ) as $key ) {
			if ( empty( $payload[ $key ] ) || ! is_string( $payload[ $key ] ) ) {
				$errors[ $key ] = 'required';
			}
		}

		foreach ( array( 'start', 'end' ) as $key ) {
			$datetime = isset( $payload[ $key ]['datetime'] ) ? (string) $payload[ $key ]['datetime'] : '';
			if ( '' === $datetime || false === strtotime( $datetime ) ) {
				$errors[ $key . '.datetime' ] = 'required';
			}
		}

		$metadata = isset( $payload['metadata'] ) && is_array( $payload['metadata'] ) ? $payload['metadata'] : array();
		if ( empty( $metadata['source'] ) ) {
			$errors['metadata.source'] = 'required';
		}
		if ( empty( $metadata['entity_type'] ) ) {
			$errors['metadata.entity_type'] = 'required';
		}
		if ( empty( $metadata['entity_id'] ) ) {
			$errors['metadata.entity_id'] = 'required';
		}

		return array(
			'is_valid' => empty( $errors ),
			'errors'   => $errors,
			'payload'  => $payload,
		);
	}

	/**
	 * Build a readable vehicle label from common joined vehicle fields.
	 *
	 * @param array<string,mixed> $row Domain row.
	 * @return string
	 */
	protected function build_vehicle_label( array $row ) {
		$parts = array_filter(
			array(
				isset( $row['vehicle_make'] ) ? sanitize_text_field( (string) $row['vehicle_make'] ) : '',
				isset( $row['vehicle_model'] ) ? sanitize_text_field( (string) $row['vehicle_model'] ) : '',
				isset( $row['vehicle_plate'] ) ? sanitize_text_field( (string) $row['vehicle_plate'] ) : '',
			)
		);

		return implode( ' ', $parts );
	}

	/**
	 * Build attendees from appointment data when email is available.
	 *
	 * @param array<string,mixed> $appointment Appointment data.
	 * @return array<int,array<string,string>>
	 */
	protected function build_appointment_attendees( array $appointment ) {
		$email = isset( $appointment['client_email'] ) ? sanitize_email( (string) $appointment['client_email'] ) : '';
		if ( '' === $email || ! is_email( $email ) ) {
			return array();
		}

		return array(
			array(
				'email'        => $email,
				'display_name' => isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '',
			),
		);
	}

	/**
	 * Normalize attendees.
	 *
	 * @param mixed $attendees Attendees.
	 * @return array<int,array<string,string>>
	 */
	protected function normalize_attendees( $attendees ) {
		if ( ! is_array( $attendees ) ) {
			return array();
		}

		$normalized = array();
		foreach ( $attendees as $attendee ) {
			if ( ! is_array( $attendee ) ) {
				continue;
			}

			$email = isset( $attendee['email'] ) ? sanitize_email( (string) $attendee['email'] ) : '';
			if ( '' === $email || ! is_email( $email ) ) {
				continue;
			}

			$normalized[] = array(
				'email'        => $email,
				'display_name' => isset( $attendee['display_name'] ) ? sanitize_text_field( (string) $attendee['display_name'] ) : '',
			);
		}

		return $normalized;
	}

	/**
	 * Return the first valid normalized datetime from candidates.
	 *
	 * @param array<int,mixed> $values Candidate values.
	 * @return string
	 */
	protected function first_datetime( array $values ) {
		foreach ( $values as $value ) {
			$datetime = $this->normalize_datetime( $value );
			if ( '' !== $datetime ) {
				return $datetime;
			}
		}

		return '';
	}

	/**
	 * Normalize datetime to RFC3339 UTC string.
	 *
	 * @param mixed $value Raw datetime.
	 * @return string
	 */
	protected function normalize_datetime( $value ) {
		$raw = sanitize_text_field( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		$timestamp = strtotime( $raw );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'c', $timestamp );
	}

	/**
	 * Offset a normalized datetime.
	 *
	 * @param string $datetime Datetime.
	 * @param string $offset   Offset expression.
	 * @return string
	 */
	protected function offset_datetime( $datetime, $offset ) {
		$timestamp = strtotime( $offset, strtotime( $datetime ) );

		return false === $timestamp ? '' : gmdate( 'c', $timestamp );
	}

	/**
	 * Resolve a timezone string.
	 *
	 * @param mixed $timezone Candidate timezone.
	 * @return string
	 */
	protected function normalize_timezone( $timezone ) {
		$timezone = sanitize_text_field( (string) $timezone );

		if ( '' !== $timezone ) {
			return $timezone;
		}

		$wp_timezone = function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : '';

		return '' !== $wp_timezone ? $wp_timezone : 'UTC';
	}
}
