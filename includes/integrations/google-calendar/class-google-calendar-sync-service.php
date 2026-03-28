<?php
/**
 * Google Calendar sync service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles one-way outbound sync and controlled inbound reconciliation.
 */
class Google_Calendar_Sync_Service {
	/**
	 * Provider key.
	 */
	const PROVIDER = 'google_calendar';

	/**
	 * Integration service.
	 *
	 * @var Google_Calendar_Service
	 */
	protected $google_calendar_service;

	/**
	 * Sync repository.
	 *
	 * @var Google_Calendar_Sync_Repository
	 */
	protected $sync_repository;

	/**
	 * Inbound reconcile policy service.
	 *
	 * @var Google_Calendar_Inbound_Reconcile_Service
	 */
	protected $inbound_reconcile_service;

	/**
	 * Appointment service.
	 *
	 * @var Appointment_Service|null
	 */
	protected $appointment_service;

	/**
	 * Constructor.
	 *
	 * @param Google_Calendar_Service|null                  $google_calendar_service Service.
	 * @param Google_Calendar_Sync_Repository|null          $sync_repository Repository.
	 * @param Google_Calendar_Inbound_Reconcile_Service|null $inbound_reconcile_service Inbound policy service.
	 */
	public function __construct( Google_Calendar_Service $google_calendar_service = null, Google_Calendar_Sync_Repository $sync_repository = null, Google_Calendar_Inbound_Reconcile_Service $inbound_reconcile_service = null ) {
		$this->google_calendar_service    = $google_calendar_service ? $google_calendar_service : new Google_Calendar_Service();
		$this->sync_repository            = $sync_repository ? $sync_repository : new Google_Calendar_Sync_Repository();
		$this->inbound_reconcile_service  = $inbound_reconcile_service ? $inbound_reconcile_service : new Google_Calendar_Inbound_Reconcile_Service();
		$this->appointment_service        = null;
	}

	/**
	 * Attach appointment service dependency.
	 *
	 * @param Appointment_Service $appointment_service Appointment service.
	 * @return void
	 */
	public function set_appointment_service( Appointment_Service $appointment_service ) {
		$this->appointment_service = $appointment_service;
	}

	/**
	 * Sync one appointment to Google Calendar.
	 *
	 * @param array<string,mixed> $appointment Appointment.
	 * @return true|WP_Error
	 */
	public function sync_appointment( array $appointment ) {
		$appointment_id = isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;

		if ( $appointment_id <= 0 ) {
			return new WP_Error( 'sm_google_calendar_invalid_appointment', __( 'La cita no es valida para sincronizacion.', 'super-mechanic' ) );
		}

		if ( ! $this->google_calendar_service->is_sync_enabled() || ! $this->google_calendar_service->is_configured() || ! $this->google_calendar_service->is_connected() ) {
			return true;
		}

		$sync_row      = $this->sync_repository->get_by_appointment( $appointment_id, self::PROVIDER );
		$event_id      = is_array( $sync_row ) ? (string) $sync_row['external_event_id'] : '';
		$local_hash    = $this->build_sync_hash( $appointment );
		$hash_pair     = is_array( $sync_row ) ? $this->parse_hash_pair( (string) $sync_row['last_sync_hash'] ) : array( 'local' => '', 'remote' => '' );
		$last_hash     = (string) $hash_pair['local'];
		$expected_data = $this->google_calendar_service->build_event_payload( $appointment );

		if ( '' !== $last_hash && hash_equals( $last_hash, $local_hash ) && is_array( $sync_row ) && 'synced' === (string) $sync_row['sync_status'] ) {
			return true;
		}

		$response = $this->google_calendar_service->upsert_appointment_event( $appointment, $event_id );

		if ( is_wp_error( $response ) ) {
			$this->persist_sync_error( $appointment_id, $local_hash, (string) $hash_pair['remote'], $response );

			return $response;
		}

		$calendar_settings = $this->google_calendar_service->get_settings();
		$calendar_id       = ! empty( $calendar_settings['calendar_id'] ) ? (string) $calendar_settings['calendar_id'] : 'primary';
		$external_event_id = isset( $response['id'] ) ? sanitize_text_field( (string) $response['id'] ) : $event_id;
		$remote_hash       = $this->build_remote_hash( $response, $expected_data );

		$this->sync_repository->upsert_by_appointment(
			$appointment_id,
			self::PROVIDER,
			array(
				'business_id'          => ! empty( $appointment['business_id'] ) ? absint( $appointment['business_id'] ) : $this->resolve_business_id_for_appointment( $appointment_id ),
				'external_calendar_id' => $calendar_id,
				'external_event_id'    => $external_event_id,
				'sync_status'          => 'synced',
				'last_synced_at'       => current_time( 'mysql' ),
				'last_sync_hash'       => $this->compose_hash_pair( $local_hash, $remote_hash ),
				'last_error'           => '',
			)
		);

		return true;
	}

	/**
	 * Reconcile one linked appointment by external event ID.
	 *
	 * @param string $external_event_id External event ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reconcile_inbound_by_external_event_id( $external_event_id ) {
		$external_event_id = sanitize_text_field( (string) $external_event_id );
		if ( '' === $external_event_id ) {
			return new WP_Error( 'sm_google_calendar_event_missing', __( 'No hay external_event_id para reconciliar.', 'super-mechanic' ) );
		}

		if ( ! $this->google_calendar_service->is_sync_enabled() || ! $this->google_calendar_service->is_configured() || ! $this->google_calendar_service->is_connected() ) {
			return new WP_Error( 'sm_google_calendar_unavailable', __( 'Google Calendar no esta disponible para reconciliacion inbound.', 'super-mechanic' ) );
		}

		$sync_row = $this->sync_repository->get_by_external_event( self::PROVIDER, $external_event_id );
		if ( ! is_array( $sync_row ) ) {
			return new WP_Error( 'sm_google_calendar_mapping_missing', __( 'No existe mapeo local para el external_event_id indicado.', 'super-mechanic' ) );
		}

		return $this->reconcile_inbound_by_sync_row( $sync_row );
	}

	/**
	 * Reconcile inbound for linked appointments.
	 *
	 * @param int $limit Max linked rows.
	 * @return array<string,mixed>|WP_Error
	 */
	public function reconcile_inbound_for_linked_appointments( $limit = 100 ) {
		if ( ! $this->google_calendar_service->is_sync_enabled() || ! $this->google_calendar_service->is_configured() || ! $this->google_calendar_service->is_connected() ) {
			return new WP_Error( 'sm_google_calendar_unavailable', __( 'Google Calendar no esta disponible para reconciliacion inbound.', 'super-mechanic' ) );
		}

		$rows = $this->sync_repository->get_linked_rows( self::PROVIDER, $limit );
		$summary = array(
			'processed' => 0,
			'synced'    => 0,
			'conflict'  => 0,
			'rejected'  => 0,
			'error'     => 0,
		);

		foreach ( $rows as $row ) {
			$result = $this->reconcile_inbound_by_sync_row( $row );
			$summary['processed']++;

			if ( is_wp_error( $result ) ) {
				$summary['error']++;
				continue;
			}

			$status = isset( $result['sync_status'] ) ? sanitize_key( (string) $result['sync_status'] ) : 'error';
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			} else {
				$summary['error']++;
			}
		}

		return $summary;
	}

	/**
	 * Reconcile inbound for a specific list of external event IDs.
	 *
	 * @param array<int,string> $external_event_ids External event IDs.
	 * @param int               $limit Max IDs to process.
	 * @return array<string,mixed>
	 */
	public function reconcile_inbound_for_external_event_ids( array $external_event_ids, $limit = 50 ) {
		$limit   = max( 1, min( 200, absint( $limit ) ) );
		$summary = array(
			'processed' => 0,
			'synced'    => 0,
			'conflict'  => 0,
			'rejected'  => 0,
			'error'     => 0,
		);

		$event_ids = array_slice( array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $external_event_ids ) ) ) ), 0, $limit );

		foreach ( $event_ids as $event_id ) {
			$result = $this->reconcile_inbound_by_external_event_id( $event_id );
			$summary['processed']++;

			if ( is_wp_error( $result ) ) {
				$summary['error']++;
				continue;
			}

			$status = isset( $result['sync_status'] ) ? sanitize_key( (string) $result['sync_status'] ) : 'error';
			if ( isset( $summary[ $status ] ) ) {
				$summary[ $status ]++;
			} else {
				$summary['error']++;
			}
		}

		return $summary;
	}

	/**
	 * Reconcile inbound by sync row.
	 *
	 * @param array<string,mixed> $sync_row Sync row.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function reconcile_inbound_by_sync_row( array $sync_row ) {
		$appointment_service = $this->get_appointment_service();
		if ( is_wp_error( $appointment_service ) ) {
			return $appointment_service;
		}

		$appointment_id = isset( $sync_row['appointment_id'] ) ? absint( $sync_row['appointment_id'] ) : 0;
		$event_id       = isset( $sync_row['external_event_id'] ) ? sanitize_text_field( (string) $sync_row['external_event_id'] ) : '';
		$calendar_id    = isset( $sync_row['external_calendar_id'] ) ? sanitize_text_field( (string) $sync_row['external_calendar_id'] ) : '';

		if ( $appointment_id <= 0 || '' === $event_id ) {
			return new WP_Error( 'sm_google_calendar_mapping_invalid', __( 'Fila de mapeo invalida para reconciliacion inbound.', 'super-mechanic' ) );
		}

		$appointment = $appointment_service->get_appointment( $appointment_id );
		if ( ! is_array( $appointment ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'La cita local asociada al mapeo no existe.', 'super-mechanic' ) );
		}

		$remote_event = $this->google_calendar_service->get_remote_event( $event_id, $calendar_id );
		if ( is_wp_error( $remote_event ) ) {
			$this->persist_sync_error( $appointment_id, $this->build_sync_hash( $appointment ), $this->extract_remote_hash_from_row( $sync_row ), $remote_event );

			return $remote_event;
		}

		$expected_payload = $this->google_calendar_service->build_event_payload( $appointment );
		$hash_pair        = $this->parse_hash_pair( isset( $sync_row['last_sync_hash'] ) ? (string) $sync_row['last_sync_hash'] : '' );
		$evaluation       = $this->inbound_reconcile_service->evaluate(
			$appointment,
			$remote_event,
			(string) $hash_pair['local'],
			(string) $hash_pair['remote'],
			$expected_payload
		);

		$local_hash  = isset( $evaluation['local_hash'] ) ? sanitize_text_field( (string) $evaluation['local_hash'] ) : $this->build_sync_hash( $appointment );
		$remote_hash = isset( $evaluation['remote_hash'] ) ? sanitize_text_field( (string) $evaluation['remote_hash'] ) : '';
		$decision    = isset( $evaluation['decision'] ) ? sanitize_key( (string) $evaluation['decision'] ) : 'error';
		$reason      = isset( $evaluation['reason'] ) ? sanitize_textarea_field( (string) $evaluation['reason'] ) : '';

		if ( 'rejected' === $decision ) {
			$this->persist_non_synced_status( $sync_row, 'rejected', $local_hash, $remote_hash, $reason );

			return array(
				'appointment_id' => $appointment_id,
				'sync_status'    => 'rejected',
			);
		}

		if ( 'conflict' === $decision ) {
			$this->persist_non_synced_status( $sync_row, 'conflict', $local_hash, $remote_hash, $reason );

			return array(
				'appointment_id' => $appointment_id,
				'sync_status'    => 'conflict',
			);
		}

		if ( 'apply' === $decision ) {
			$patch = isset( $evaluation['inbound_patch'] ) && is_array( $evaluation['inbound_patch'] ) ? $evaluation['inbound_patch'] : array();
			$apply = $appointment_service->apply_google_inbound_patch( $appointment_id, $patch, false );

			if ( is_wp_error( $apply ) ) {
				$this->persist_sync_error( $appointment_id, $local_hash, $remote_hash, $apply );

				return $apply;
			}

			$refreshed = $appointment_service->get_appointment( $appointment_id );
			$local_hash = is_array( $refreshed ) ? $this->build_sync_hash( $refreshed ) : $local_hash;
		}

		$this->sync_repository->upsert_by_appointment(
			$appointment_id,
			self::PROVIDER,
			array(
				'business_id'          => ! empty( $appointment['business_id'] ) ? absint( $appointment['business_id'] ) : $this->resolve_business_id_for_appointment( $appointment_id ),
				'external_calendar_id' => '' !== $calendar_id ? $calendar_id : 'primary',
				'external_event_id'    => $event_id,
				'sync_status'          => 'synced',
				'last_synced_at'       => current_time( 'mysql' ),
				'last_sync_hash'       => $this->compose_hash_pair( $local_hash, $remote_hash ),
				'last_error'           => '',
			)
		);

		return array(
			'appointment_id' => $appointment_id,
			'sync_status'    => 'synced',
		);
	}

	/**
	 * Persist sync error.
	 *
	 * @param int      $appointment_id Appointment ID.
	 * @param string   $local_hash Local sync hash.
	 * @param string   $remote_hash Remote sync hash.
	 * @param WP_Error $error Error.
	 * @return void
	 */
	protected function persist_sync_error( $appointment_id, $local_hash, $remote_hash, WP_Error $error ) {
		$message = sanitize_textarea_field( $error->get_error_message() );
		if ( strlen( $message ) > 500 ) {
			$message = substr( $message, 0, 500 );
		}

		$calendar_settings = $this->google_calendar_service->get_settings();
		$calendar_id       = ! empty( $calendar_settings['calendar_id'] ) ? (string) $calendar_settings['calendar_id'] : 'primary';

		$this->sync_repository->upsert_by_appointment(
			absint( $appointment_id ),
			self::PROVIDER,
			array(
				'business_id'          => $this->resolve_business_id_for_appointment( $appointment_id ),
				'external_calendar_id' => $calendar_id,
				'sync_status'          => 'error',
				'last_synced_at'       => '',
				'last_sync_hash'       => $this->compose_hash_pair( $local_hash, $remote_hash ),
				'last_error'           => $message,
			)
		);
	}

	/**
	 * Persist non-synced but controlled reconcile status.
	 *
	 * @param array<string,mixed> $sync_row Sync row.
	 * @param string              $status Status.
	 * @param string              $local_hash Local hash.
	 * @param string              $remote_hash Remote hash.
	 * @param string              $message Message.
	 * @return void
	 */
	protected function persist_non_synced_status( array $sync_row, $status, $local_hash, $remote_hash, $message ) {
		$appointment_id = isset( $sync_row['appointment_id'] ) ? absint( $sync_row['appointment_id'] ) : 0;
		$calendar_id    = isset( $sync_row['external_calendar_id'] ) ? sanitize_text_field( (string) $sync_row['external_calendar_id'] ) : 'primary';
		$event_id       = isset( $sync_row['external_event_id'] ) ? sanitize_text_field( (string) $sync_row['external_event_id'] ) : '';

		$this->sync_repository->upsert_by_appointment(
			$appointment_id,
			self::PROVIDER,
			array(
				'business_id'          => $this->resolve_business_id_for_appointment( $appointment_id ),
				'external_calendar_id' => $calendar_id,
				'external_event_id'    => $event_id,
				'sync_status'          => sanitize_key( (string) $status ),
				'last_synced_at'       => '',
				'last_sync_hash'       => $this->compose_hash_pair( $local_hash, $remote_hash ),
				'last_error'           => sanitize_textarea_field( (string) $message ),
			)
		);
	}

	/**
	 * Build deterministic local sync hash.
	 *
	 * @param array<string,mixed> $appointment Appointment.
	 * @return string
	 */
	protected function build_sync_hash( array $appointment ) {
		$payload = array(
			'id'                 => isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0,
			'appointment_status' => isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : '',
			'appointment_date'   => isset( $appointment['appointment_date'] ) ? sanitize_text_field( (string) $appointment['appointment_date'] ) : '',
			'start_at'           => isset( $appointment['start_at'] ) ? sanitize_text_field( (string) $appointment['start_at'] ) : '',
			'assigned_to'        => isset( $appointment['assigned_to'] ) ? absint( $appointment['assigned_to'] ) : 0,
			'client_name'        => isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '',
			'vehicle_make'       => isset( $appointment['vehicle_make'] ) ? sanitize_text_field( (string) $appointment['vehicle_make'] ) : '',
			'vehicle_model'      => isset( $appointment['vehicle_model'] ) ? sanitize_text_field( (string) $appointment['vehicle_model'] ) : '',
			'notes'              => isset( $appointment['notes'] ) ? sanitize_textarea_field( (string) $appointment['notes'] ) : '',
		);

		return hash( 'sha256', (string) wp_json_encode( $payload ) );
	}

	/**
	 * Build deterministic remote hash using allowed+protected remote view.
	 *
	 * @param array<string,mixed> $remote_event Remote event.
	 * @param array<string,mixed> $expected_payload Expected payload.
	 * @return string
	 */
	protected function build_remote_hash( array $remote_event, array $expected_payload ) {
		$evaluation = $this->inbound_reconcile_service->evaluate(
			array(
				'start_at'           => '',
				'appointment_date'   => '',
				'notes'              => '',
				'appointment_status' => '',
			),
			$remote_event,
			'',
			'',
			$expected_payload
		);

		return isset( $evaluation['remote_hash'] ) ? sanitize_text_field( (string) $evaluation['remote_hash'] ) : '';
	}

	/**
	 * Compose persisted hash pair.
	 *
	 * @param string $local_hash Local hash.
	 * @param string $remote_hash Remote hash.
	 * @return string
	 */
	protected function compose_hash_pair( $local_hash, $remote_hash ) {
		$local_hash  = sanitize_text_field( (string) $local_hash );
		$remote_hash = sanitize_text_field( (string) $remote_hash );

		if ( '' === $remote_hash ) {
			return $local_hash;
		}

		return 'L:' . $local_hash . '|R:' . $remote_hash;
	}

	/**
	 * Parse persisted hash value (legacy + pair format).
	 *
	 * @param string $value Stored hash string.
	 * @return array<string,string>
	 */
	protected function parse_hash_pair( $value ) {
		$value = sanitize_text_field( (string) $value );

		if ( preg_match( '/^L:([a-f0-9]{64})\|R:([a-f0-9]{64})$/', $value, $matches ) ) {
			return array(
				'local'  => $matches[1],
				'remote' => $matches[2],
			);
		}

		if ( preg_match( '/^[a-f0-9]{64}$/', $value ) ) {
			return array(
				'local'  => $value,
				'remote' => '',
			);
		}

		return array(
			'local'  => '',
			'remote' => '',
		);
	}

	/**
	 * Extract remote hash from current sync row.
	 *
	 * @param array<string,mixed> $sync_row Sync row.
	 * @return string
	 */
	protected function extract_remote_hash_from_row( array $sync_row ) {
		$pair = $this->parse_hash_pair( isset( $sync_row['last_sync_hash'] ) ? (string) $sync_row['last_sync_hash'] : '' );

		return (string) $pair['remote'];
	}

	/**
	 * Resolve appointment service dependency.
	 *
	 * @return Appointment_Service|WP_Error
	 */
	protected function get_appointment_service() {
		if ( $this->appointment_service instanceof Appointment_Service ) {
			return $this->appointment_service;
		}

		return new WP_Error( 'sm_google_calendar_appointment_service_missing', __( 'Appointment_Service no esta enlazado para reconciliacion inbound.', 'super-mechanic' ) );
	}

	/**
	 * Resolve business ID for one appointment context.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return int
	 */
	protected function resolve_business_id_for_appointment( $appointment_id ) {
		$appointment_id = absint( $appointment_id );

		if ( $appointment_id > 0 && $this->appointment_service instanceof Appointment_Service ) {
			$appointment = $this->appointment_service->get_appointment( $appointment_id );

			if ( is_array( $appointment ) && ! empty( $appointment['business_id'] ) ) {
				return max( 1, absint( $appointment['business_id'] ) );
			}
		}

		$context_service = new Business_Context_Service();

		return max( 1, absint( $context_service->resolve_business_id() ) );
	}
}
