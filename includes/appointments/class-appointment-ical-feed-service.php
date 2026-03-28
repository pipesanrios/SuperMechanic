<?php
/**
 * Appointment iCal feed service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

defined( 'ABSPATH' ) || exit;

/**
 * Builds ICS payload for appointments.
 */
class Appointment_Ical_Feed_Service {
	/**
	 * Build ICS payload.
	 *
	 * @param array<int,array<string,mixed>> $appointments Appointments.
	 * @return string
	 */
	public function build_calendar( array $appointments ) {
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$host     = is_string( $host ) && '' !== $host ? $host : 'localhost';
		$lines    = array(
			'BEGIN:VCALENDAR',
			'PRODID:-//Super Mechanic//Appointments Feed//EN',
			'VERSION:2.0',
			'CALSCALE:GREGORIAN',
			'METHOD:PUBLISH',
			'X-WR-CALNAME:Super Mechanic - Appointments',
			'X-WR-TIMEZONE:UTC',
		);

		foreach ( $appointments as $appointment ) {
			$event_lines = $this->build_event_lines( $appointment, $host );

			foreach ( $event_lines as $line ) {
				$lines[] = $line;
			}
		}

		$lines[] = 'END:VCALENDAR';

		return implode( "\r\n", array_map( array( $this, 'fold_ical_line' ), $lines ) ) . "\r\n";
	}

	/**
	 * Build VEVENT lines.
	 *
	 * @param array<string,mixed> $appointment Row.
	 * @param string              $host Host.
	 * @return array<int,string>
	 */
	protected function build_event_lines( array $appointment, $host ) {
		$id         = isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;
		$start_ts   = $this->resolve_start_timestamp( $appointment );
		$end_ts     = $start_ts + HOUR_IN_SECONDS;
		$updated_ts = isset( $appointment['updated_at'] ) ? strtotime( (string) $appointment['updated_at'] ) : false;
		$updated_ts = false === $updated_ts ? time() : $updated_ts;

		$status_label  = $this->humanize_status( isset( $appointment['appointment_status'] ) ? (string) $appointment['appointment_status'] : '' );
		$client_label  = $this->build_client_label( $appointment );
		$vehicle_label = $this->build_vehicle_label( $appointment );
		$mechanic      = isset( $appointment['mechanic_name'] ) ? sanitize_text_field( (string) $appointment['mechanic_name'] ) : '';

		$summary = sprintf( 'Cita %s - %s', '#' . $id, $vehicle_label );
		$description_parts = array(
			'Estado: ' . $status_label,
			'Cliente: ' . $client_label,
			'Vehiculo: ' . $vehicle_label,
		);

		if ( '' !== $mechanic ) {
			$description_parts[] = 'Mecanico: ' . $mechanic;
		}

		$notes = isset( $appointment['notes'] ) ? sanitize_textarea_field( (string) $appointment['notes'] ) : '';
		if ( '' !== $notes ) {
			$description_parts[] = 'Notas: ' . $notes;
		}

		$description = implode( "\n", $description_parts );

		return array(
			'BEGIN:VEVENT',
			'UID:' . $this->escape_ical_text( 'sm-appointment-' . $id . '@' . $host ),
			'DTSTAMP:' . gmdate( 'Ymd\THis\Z', $updated_ts ),
			'DTSTART:' . gmdate( 'Ymd\THis\Z', $start_ts ),
			'DTEND:' . gmdate( 'Ymd\THis\Z', $end_ts ),
			'SUMMARY:' . $this->escape_ical_text( $summary ),
			'DESCRIPTION:' . $this->escape_ical_text( $description ),
			'STATUS:' . $this->map_event_status( isset( $appointment['appointment_status'] ) ? (string) $appointment['appointment_status'] : '' ),
			'END:VEVENT',
		);
	}

	/**
	 * Resolve event start timestamp.
	 *
	 * @param array<string,mixed> $appointment Row.
	 * @return int
	 */
	protected function resolve_start_timestamp( array $appointment ) {
		$start_at = isset( $appointment['start_at'] ) ? (string) $appointment['start_at'] : '';
		$start_ts = '' !== $start_at ? strtotime( $start_at ) : false;

		if ( false !== $start_ts ) {
			return $start_ts;
		}

		$date = isset( $appointment['appointment_date'] ) ? (string) $appointment['appointment_date'] : '';
		$ts   = '' !== $date ? strtotime( $date . ' 09:00:00' ) : false;

		return false === $ts ? time() : $ts;
	}

	/**
	 * Build client display label.
	 *
	 * @param array<string,mixed> $appointment Row.
	 * @return string
	 */
	protected function build_client_label( array $appointment ) {
		$name = isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '';

		return '' !== $name ? $name : __( 'Cliente sin nombre', 'super-mechanic' );
	}

	/**
	 * Build vehicle display label.
	 *
	 * @param array<string,mixed> $appointment Row.
	 * @return string
	 */
	protected function build_vehicle_label( array $appointment ) {
		$make  = isset( $appointment['vehicle_make'] ) ? sanitize_text_field( (string) $appointment['vehicle_make'] ) : '';
		$model = isset( $appointment['vehicle_model'] ) ? sanitize_text_field( (string) $appointment['vehicle_model'] ) : '';
		$label = trim( $make . ' ' . $model );
		$plate = isset( $appointment['vehicle_plate'] ) ? sanitize_text_field( (string) $appointment['vehicle_plate'] ) : '';

		if ( '' !== $plate ) {
			$label .= ' - ' . $this->mask_plate( $plate );
		}

		if ( '' === trim( $label ) ) {
			$label = __( 'Vehiculo sin identificar', 'super-mechanic' );
		}

		return $label;
	}

	/**
	 * Mask plate to reduce sensitive exposure.
	 *
	 * @param string $plate Plate.
	 * @return string
	 */
	protected function mask_plate( $plate ) {
		$plate = preg_replace( '/\s+/', '', strtoupper( (string) $plate ) );
		$len   = strlen( $plate );

		if ( $len <= 4 ) {
			return $plate;
		}

		return str_repeat( '*', $len - 4 ) . substr( $plate, -4 );
	}

	/**
	 * Humanize status.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function humanize_status( $status ) {
		$status = sanitize_key( $status );

		if ( '' === $status ) {
			return __( 'Unknown', 'super-mechanic' );
		}

		return ucwords( str_replace( '_', ' ', $status ) );
	}

	/**
	 * Map appointment status to iCal event status.
	 *
	 * @param string $status Appointment status.
	 * @return string
	 */
	protected function map_event_status( $status ) {
		$status = sanitize_key( $status );

		if ( 'cancelled' === $status ) {
			return 'CANCELLED';
		}

		return 'CONFIRMED';
	}

	/**
	 * Escape a text value for iCal.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	protected function escape_ical_text( $value ) {
		$value = (string) $value;
		$value = str_replace( '\\', '\\\\', $value );
		$value = str_replace( ';', '\;', $value );
		$value = str_replace( ',', '\,', $value );
		$value = preg_replace( "/\r\n|\r|\n/", '\\n', $value );

		return (string) $value;
	}

	/**
	 * Fold long iCal lines according RFC 5545.
	 *
	 * @param string $line Raw line.
	 * @return string
	 */
	protected function fold_ical_line( $line ) {
		$line = (string) $line;

		if ( strlen( $line ) <= 75 ) {
			return $line;
		}

		$parts = str_split( $line, 75 );

		return implode( "\r\n ", $parts );
	}
}
