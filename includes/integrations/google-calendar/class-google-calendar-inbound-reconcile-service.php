<?php
/**
 * Google Calendar inbound reconciliation policy.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

defined( 'ABSPATH' ) || exit;

/**
 * Applies explicit inbound policy while keeping local appointments as source of truth.
 */
class Google_Calendar_Inbound_Reconcile_Service {
	/**
	 * Maximum inbound notes length.
	 */
	const MAX_NOTES_LENGTH = 2000;

	/**
	 * Evaluate inbound reconciliation for one appointment/event pair.
	 *
	 * @param array<string,mixed> $appointment Local appointment.
	 * @param array<string,mixed> $remote_event Remote Google event payload.
	 * @param string              $last_local_hash Last persisted local hash.
	 * @param string              $last_remote_hash Last persisted remote hash.
	 * @param array<string,mixed> $expected_event_payload Expected payload generated from local appointment.
	 * @return array<string,mixed>
	 */
	public function evaluate( array $appointment, array $remote_event, $last_local_hash, $last_remote_hash, array $expected_event_payload ) {
		$local_state  = $this->build_local_state( $appointment );
		$remote_state = $this->build_remote_state( $remote_event, $expected_event_payload );
		$local_hash   = $this->hash_state( $local_state );
		$remote_hash  = $this->hash_state( $remote_state );

		if ( ! empty( $remote_state['forbidden_touched'] ) ) {
			return array(
				'decision'      => 'rejected',
				'reason'        => sanitize_text_field( (string) $remote_state['forbidden_reason'] ),
				'local_hash'    => $local_hash,
				'remote_hash'   => $remote_hash,
				'inbound_patch' => array(),
			);
		}

		$inbound_patch  = $this->build_inbound_patch( $remote_state, $local_state );
		$remote_changed = '' === (string) $last_remote_hash ? ! empty( $inbound_patch ) : ! hash_equals( (string) $last_remote_hash, $remote_hash );
		$local_changed  = '' !== (string) $last_local_hash && ! hash_equals( (string) $last_local_hash, $local_hash );

		if ( ! $remote_changed ) {
			return array(
				'decision'      => 'noop',
				'reason'        => '',
				'local_hash'    => $local_hash,
				'remote_hash'   => $remote_hash,
				'inbound_patch' => array(),
			);
		}

		if ( $local_changed ) {
			return array(
				'decision'      => 'conflict',
				'reason'        => __( 'Conflicto: cambios locales y remotos detectados desde la ultima base sincronizada.', 'super-mechanic' ),
				'local_hash'    => $local_hash,
				'remote_hash'   => $remote_hash,
				'inbound_patch' => array(),
			);
		}

		return array(
			'decision'      => empty( $inbound_patch ) ? 'noop' : 'apply',
			'reason'        => '',
			'local_hash'    => $local_hash,
			'remote_hash'   => $remote_hash,
			'inbound_patch' => $inbound_patch,
		);
	}

	/**
	 * Build state hash.
	 *
	 * @param array<string,mixed> $state State.
	 * @return string
	 */
	protected function hash_state( array $state ) {
		return hash( 'sha256', (string) wp_json_encode( $state ) );
	}

	/**
	 * Build local state for conflict policy.
	 *
	 * @param array<string,mixed> $appointment Local appointment.
	 * @return array<string,mixed>
	 */
	protected function build_local_state( array $appointment ) {
		return array(
			'start_at'           => isset( $appointment['start_at'] ) ? sanitize_text_field( (string) $appointment['start_at'] ) : '',
			'appointment_date'   => isset( $appointment['appointment_date'] ) ? sanitize_text_field( (string) $appointment['appointment_date'] ) : '',
			'notes'              => isset( $appointment['notes'] ) ? sanitize_textarea_field( (string) $appointment['notes'] ) : '',
			'appointment_status' => isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : '',
		);
	}

	/**
	 * Build normalized remote state and detect forbidden updates.
	 *
	 * @param array<string,mixed> $remote_event Remote event payload.
	 * @param array<string,mixed> $expected_event_payload Expected generated payload.
	 * @return array<string,mixed>
	 */
	protected function build_remote_state( array $remote_event, array $expected_event_payload ) {
		$summary = isset( $remote_event['summary'] ) ? sanitize_text_field( (string) $remote_event['summary'] ) : '';
		$status  = isset( $remote_event['status'] ) ? sanitize_key( (string) $remote_event['status'] ) : '';
		$start   = $this->extract_remote_start_datetime( $remote_event );
		$notes   = $this->extract_remote_notes( $remote_event );

		$expected_summary = isset( $expected_event_payload['summary'] ) ? sanitize_text_field( (string) $expected_event_payload['summary'] ) : '';
		$expected_meta    = $this->extract_expected_meta_lines( $expected_event_payload );
		$remote_meta      = $this->extract_remote_meta_lines( $remote_event );

		if ( '' !== $expected_summary && '' !== $summary && $summary !== $expected_summary ) {
			return array(
				'start_at'           => $start,
				'notes'              => $notes,
				'appointment_status' => $this->resolve_remote_status( $status ),
				'forbidden_touched'  => true,
				'forbidden_reason'   => __( 'Rechazado: Google modifico summary (campo no permitido).', 'super-mechanic' ),
			);
		}

		foreach ( array( 'cliente', 'vehiculo', 'mecanico' ) as $meta_key ) {
			if ( isset( $remote_meta[ $meta_key ] ) && isset( $expected_meta[ $meta_key ] ) && $remote_meta[ $meta_key ] !== $expected_meta[ $meta_key ] ) {
				return array(
					'start_at'           => $start,
					'notes'              => $notes,
					'appointment_status' => $this->resolve_remote_status( $status ),
					'forbidden_touched'  => true,
					'forbidden_reason'   => __( 'Rechazado: Google modifico metadatos estructurales no permitidos.', 'super-mechanic' ),
				);
			}
		}

		return array(
			'start_at'           => $start,
			'notes'              => $notes,
			'appointment_status' => $this->resolve_remote_status( $status ),
			'forbidden_touched'  => false,
			'forbidden_reason'   => '',
		);
	}

	/**
	 * Build allowed inbound patch against local state.
	 *
	 * @param array<string,mixed> $remote_state Remote state.
	 * @param array<string,mixed> $local_state Local state.
	 * @return array<string,mixed>
	 */
	protected function build_inbound_patch( array $remote_state, array $local_state ) {
		$patch = array();

		if ( isset( $remote_state['start_at'] ) && '' !== (string) $remote_state['start_at'] && (string) $remote_state['start_at'] !== (string) $local_state['start_at'] ) {
			$patch['start_at'] = (string) $remote_state['start_at'];
		}

		if ( isset( $remote_state['notes'] ) && (string) $remote_state['notes'] !== (string) $local_state['notes'] ) {
			$patch['notes'] = (string) $remote_state['notes'];
		}

		if ( 'cancelled' === (string) $remote_state['appointment_status'] && 'cancelled' !== (string) $local_state['appointment_status'] ) {
			$patch['appointment_status'] = 'cancelled';
		}

		return $patch;
	}

	/**
	 * Extract normalized start datetime.
	 *
	 * @param array<string,mixed> $remote_event Remote event payload.
	 * @return string
	 */
	protected function extract_remote_start_datetime( array $remote_event ) {
		$start = isset( $remote_event['start'] ) && is_array( $remote_event['start'] ) ? $remote_event['start'] : array();
		$raw   = isset( $start['dateTime'] ) ? (string) $start['dateTime'] : '';

		if ( '' === $raw && isset( $start['date'] ) ) {
			$raw = (string) $start['date'] . ' 09:00:00';
		}

		$timestamp = strtotime( sanitize_text_field( $raw ) );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Extract allowed notes text from remote description.
	 *
	 * @param array<string,mixed> $remote_event Remote event payload.
	 * @return string
	 */
	protected function extract_remote_notes( array $remote_event ) {
		$description = isset( $remote_event['description'] ) ? sanitize_textarea_field( (string) $remote_event['description'] ) : '';
		$lines       = preg_split( '/\r\n|\r|\n/', $description );
		$notes       = '';

		if ( is_array( $lines ) ) {
			foreach ( $lines as $line ) {
				$line = trim( (string) $line );
				if ( 0 === stripos( $line, 'Notas:' ) ) {
					$notes = trim( substr( $line, strlen( 'Notas:' ) ) );
					break;
				}
			}
		}

		if ( '' === $notes ) {
			$notes = $description;
		}

		$notes = sanitize_textarea_field( $notes );
		if ( strlen( $notes ) > self::MAX_NOTES_LENGTH ) {
			$notes = substr( $notes, 0, self::MAX_NOTES_LENGTH );
		}

		return $notes;
	}

	/**
	 * Resolve inbound status according to allowed policy.
	 *
	 * @param string $remote_status Remote event status.
	 * @return string
	 */
	protected function resolve_remote_status( $remote_status ) {
		return 'cancelled' === sanitize_key( (string) $remote_status ) ? 'cancelled' : '';
	}

	/**
	 * Extract expected protected metadata labels from expected description.
	 *
	 * @param array<string,mixed> $expected_event_payload Expected event payload.
	 * @return array<string,string>
	 */
	protected function extract_expected_meta_lines( array $expected_event_payload ) {
		$description = isset( $expected_event_payload['description'] ) ? sanitize_textarea_field( (string) $expected_event_payload['description'] ) : '';

		return $this->extract_meta_lines( $description );
	}

	/**
	 * Extract remote protected metadata labels from event description.
	 *
	 * @param array<string,mixed> $remote_event Remote event payload.
	 * @return array<string,string>
	 */
	protected function extract_remote_meta_lines( array $remote_event ) {
		$description = isset( $remote_event['description'] ) ? sanitize_textarea_field( (string) $remote_event['description'] ) : '';

		return $this->extract_meta_lines( $description );
	}

	/**
	 * Extract known metadata keys from a multi-line description.
	 *
	 * @param string $description Description text.
	 * @return array<string,string>
	 */
	protected function extract_meta_lines( $description ) {
		$lines = preg_split( '/\r\n|\r|\n/', (string) $description );
		$meta  = array();

		if ( ! is_array( $lines ) ) {
			return $meta;
		}

		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line ) {
				continue;
			}

			if ( 0 === stripos( $line, 'Cliente:' ) ) {
				$meta['cliente'] = trim( substr( $line, strlen( 'Cliente:' ) ) );
			} elseif ( 0 === stripos( $line, 'Vehiculo:' ) ) {
				$meta['vehiculo'] = trim( substr( $line, strlen( 'Vehiculo:' ) ) );
			} elseif ( 0 === stripos( $line, 'Mecanico:' ) ) {
				$meta['mecanico'] = trim( substr( $line, strlen( 'Mecanico:' ) ) );
			}
		}

		return $meta;
	}
}
