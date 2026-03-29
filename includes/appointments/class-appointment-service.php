<?php
/**
 * Appointment service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Sync_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles appointments business rules.
 */
class Appointment_Service {
	/**
	 * Repository.
	 *
	 * @var Appointment_Repository
	 */
	protected $repository;

	/**
	 * Clients service.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Vehicles service.
	 *
	 * @var Vehicle_Service
	 */
	protected $vehicle_service;

	/**
	 * Processes service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * Google Calendar sync service.
	 *
	 * @var Google_Calendar_Sync_Service
	 */
	protected $google_calendar_sync_service;
	/**
	 * Event dispatcher.
	 *
	 * @var Event_Dispatcher
	 */
	protected $event_dispatcher;
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Repository|null $repository      Repository.
	 * @param Client_Service|null         $client_service  Clients service.
	 * @param Vehicle_Service|null        $vehicle_service Vehicles service.
	 * @param Process_Service|null             $process_service Processes service.
	 * @param Google_Calendar_Sync_Service|null $google_calendar_sync_service Google sync service.
	 * @param Event_Dispatcher|null             $event_dispatcher Event dispatcher.
	 */
	public function __construct( Appointment_Repository $repository = null, Client_Service $client_service = null, Vehicle_Service $vehicle_service = null, Process_Service $process_service = null, Google_Calendar_Sync_Service $google_calendar_sync_service = null, Event_Dispatcher $event_dispatcher = null, Business_Context_Service $business_context_service = null ) {
		$this->repository                   = $repository ? $repository : new Appointment_Repository();
		$this->client_service               = $client_service ? $client_service : new Client_Service();
		$this->vehicle_service              = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->process_service              = $process_service ? $process_service : new Process_Service();
		$this->google_calendar_sync_service = $google_calendar_sync_service ? $google_calendar_sync_service : new Google_Calendar_Sync_Service();
		$this->event_dispatcher             = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->business_context_service     = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Create appointment.
	 *
	 * @param array<string,mixed> $data Appointment data.
	 * @return int|WP_Error
	 */
	public function create_appointment( array $data ) {
		$normalized = $this->normalize_appointment_data( $data );
		$valid      = $this->validate_appointment_data( $normalized, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $normalized );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_appointment_insert_failed', __( 'No fue posible crear la cita.', 'super-mechanic' ) );
		}

		$this->sync_appointment_after_write( $inserted );
		$this->dispatch_event(
			'appointment_created',
			array(
				'appointment_id' => $inserted,
				'appointment'    => $this->repository->get_by_id( $inserted ),
			)
		);

		return $inserted;
	}

	/**
	 * Update appointment.
	 *
	 * @param int                 $id   Appointment ID.
	 * @param array<string,mixed> $data Appointment data.
	 * @return bool|WP_Error
	 */
	public function update_appointment( $id, array $data ) {
		$id = absint( $id );
		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'La cita no existe.', 'super-mechanic' ) );
		}

		$previous = $this->repository->get_by_id( $id );

		$normalized = $this->normalize_appointment_data( $data );
		$valid      = $this->validate_appointment_data( $normalized, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$updated = $this->repository->update( $id, $normalized );

		if ( ! $updated ) {
			return new WP_Error( 'sm_appointment_update_failed', __( 'No fue posible actualizar la cita.', 'super-mechanic' ) );
		}

		$this->sync_appointment_after_write( $id );

		$current = $this->repository->get_by_id( $id );
		$this->dispatch_event(
			'appointment_updated',
			array(
				'appointment_id' => $id,
				'appointment'    => $current,
				'source'         => 'runtime',
			)
		);

		$old_status = is_array( $previous ) && ! empty( $previous['appointment_status'] ) ? sanitize_key( (string) $previous['appointment_status'] ) : '';
		$new_status = is_array( $current ) && ! empty( $current['appointment_status'] ) ? sanitize_key( (string) $current['appointment_status'] ) : '';
		if ( '' !== $new_status && $old_status !== $new_status ) {
			$this->dispatch_event(
				'appointment_status_changed',
				array(
					'appointment_id' => $id,
					'appointment'    => $current,
					'old_status'     => $old_status,
					'new_status'     => $new_status,
					'source'         => 'runtime',
				)
			);

			if ( 'cancelled' === $new_status ) {
				$this->dispatch_event(
					'appointment_cancelled',
					array(
						'appointment_id' => $id,
						'appointment'    => $current,
						'old_status'     => $old_status,
						'new_status'     => $new_status,
						'source'         => 'runtime',
					)
				);
			}
		}

		return true;
	}

	/**
	 * Apply inbound Google patch with an explicit allowed-field policy.
	 *
	 * This method intentionally avoids touching structural relations and can skip outbound sync
	 * to prevent reconciliation loops during manual inbound operations.
	 *
	 * @param int                 $id Appointment ID.
	 * @param array<string,mixed> $data Allowed inbound data.
	 * @param bool                $trigger_outbound_sync Whether to trigger outbound sync after write.
	 * @return bool|WP_Error
	 */
	public function apply_google_inbound_patch( $id, array $data, $trigger_outbound_sync = false ) {
		$id = absint( $id );
		if ( ! $id ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'La cita no existe.', 'super-mechanic' ) );
		}

		$current = $this->repository->get_by_id( $id );
		if ( ! is_array( $current ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'La cita no existe.', 'super-mechanic' ) );
		}

		$payload = array();

		if ( isset( $data['start_at'] ) ) {
			$start_at = $this->normalize_datetime_to_mysql( $data['start_at'] );
			if ( '' !== $start_at ) {
				$payload['start_at']         = $start_at;
				$payload['appointment_date'] = $this->normalize_date( '', $start_at );
			}
		}

		if ( isset( $data['notes'] ) ) {
			$payload['notes'] = sanitize_textarea_field( (string) $data['notes'] );
		}

		if ( isset( $data['appointment_status'] ) ) {
			$status = sanitize_key( (string) $data['appointment_status'] );
			if ( 'cancelled' === $status ) {
				$payload['appointment_status'] = 'cancelled';
			}
		}

		if ( empty( $payload ) ) {
			return true;
		}

		$old_status = isset( $current['appointment_status'] ) ? sanitize_key( (string) $current['appointment_status'] ) : '';

		$updated = $this->repository->update( $id, $payload );
		if ( ! $updated ) {
			return new WP_Error( 'sm_appointment_update_failed', __( 'No fue posible actualizar la cita.', 'super-mechanic' ) );
		}

		if ( $trigger_outbound_sync ) {
			$this->sync_appointment_after_write( $id );
		}

		$current_after = $this->repository->get_by_id( $id );
		$this->dispatch_event(
			'appointment_updated',
			array(
				'appointment_id' => $id,
				'appointment'    => $current_after,
				'source'         => 'google_inbound',
			)
		);

		$new_status = is_array( $current_after ) && ! empty( $current_after['appointment_status'] ) ? sanitize_key( (string) $current_after['appointment_status'] ) : '';
		if ( '' !== $new_status && $old_status !== $new_status ) {
			$this->dispatch_event(
				'appointment_status_changed',
				array(
					'appointment_id' => $id,
					'appointment'    => $current_after,
					'old_status'     => $old_status,
					'new_status'     => $new_status,
					'source'         => 'google_inbound',
				)
			);

			if ( 'cancelled' === $new_status ) {
				$this->dispatch_event(
					'appointment_cancelled',
					array(
						'appointment_id' => $id,
						'appointment'    => $current_after,
						'old_status'     => $old_status,
						'new_status'     => $new_status,
						'source'         => 'google_inbound',
					)
				);
			}
		}

		return true;
	}

	/**
	 * Delete appointment.
	 *
	 * @param int $id Appointment ID.
	 * @return bool|WP_Error
	 */
	public function delete_appointment( $id ) {
		$id = absint( $id );
		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_appointment_not_found', __( 'La cita no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_appointment_delete_failed', __( 'No fue posible eliminar la cita.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Cancel one appointment using an explicit business boundary.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param int    $business_id    Business ID.
	 * @param string $reason         Optional reason.
	 * @return array<string,mixed>|WP_Error
	 */
	public function cancel_appointment_for_business( $appointment_id, $business_id, $reason = '' ) {
		$appointment_id = absint( $appointment_id );
		$business_id    = max( 1, absint( $business_id ) );
		$reason         = mb_substr( sanitize_text_field( (string) $reason ), 0, 280 );

		if ( $appointment_id <= 0 ) {
			return new WP_Error(
				'sm_public_api_invalid_appointment_id',
				__( 'El identificador de cita no es válido.', 'super-mechanic' ),
				array( 'status' => 400 )
			);
		}

		$current = $this->repository->get_by_id_for_business( $appointment_id, $business_id );
		if ( ! is_array( $current ) ) {
			return new WP_Error(
				'sm_public_api_appointment_not_found',
				__( 'No se encontró la cita solicitada.', 'super-mechanic' ),
				array( 'status' => 404 )
			);
		}

		$current_status = sanitize_key( isset( $current['appointment_status'] ) ? (string) $current['appointment_status'] : '' );
		if ( 'cancelled' === $current_status ) {
			return array(
				'appointment'       => $current,
				'already_cancelled' => true,
			);
		}

		$allowed_transitions = array( 'scheduled', 'confirmed', 'in_progress' );
		if ( ! in_array( $current_status, $allowed_transitions, true ) ) {
			return new WP_Error(
				'sm_public_api_appointment_cancel_forbidden_status',
				__( 'La cita no puede cancelarse desde su estado actual.', 'super-mechanic' ),
				array( 'status' => 409 )
			);
		}

		$updated = $this->repository->update_status_for_business( $appointment_id, $business_id, 'cancelled' );
		if ( ! $updated ) {
			return new WP_Error(
				'sm_public_api_appointment_cancel_failed',
				__( 'No fue posible cancelar la cita.', 'super-mechanic' ),
				array( 'status' => 500 )
			);
		}

		$current_after = $this->repository->get_by_id_for_business( $appointment_id, $business_id );
		if ( ! is_array( $current_after ) ) {
			return new WP_Error(
				'sm_public_api_appointment_not_found',
				__( 'No se encontró la cita solicitada.', 'super-mechanic' ),
				array( 'status' => 404 )
			);
		}

		$this->dispatch_event(
			'appointment_status_changed',
			array(
				'appointment_id' => $appointment_id,
				'appointment'    => $current_after,
				'old_status'     => $current_status,
				'new_status'     => 'cancelled',
				'source'         => 'public_api',
				'reason'         => $reason,
			)
		);
		$this->dispatch_event(
			'appointment_cancelled',
			array(
				'appointment_id' => $appointment_id,
				'appointment'    => $current_after,
				'old_status'     => $current_status,
				'new_status'     => 'cancelled',
				'source'         => 'public_api',
				'reason'         => $reason,
			)
		);

		return array(
			'appointment'       => $current_after,
			'already_cancelled' => false,
		);
	}

	/**
	 * Confirm one appointment using an explicit business boundary.
	 *
	 * @param int    $appointment_id Appointment ID.
	 * @param int    $business_id    Business ID.
	 * @param string $reason         Optional reason.
	 * @return array<string,mixed>|WP_Error
	 */
	public function confirm_appointment_for_business( $appointment_id, $business_id, $reason = '' ) {
		$appointment_id = absint( $appointment_id );
		$business_id    = max( 1, absint( $business_id ) );
		$reason         = mb_substr( sanitize_text_field( (string) $reason ), 0, 280 );

		if ( $appointment_id <= 0 ) {
			return new WP_Error(
				'sm_public_api_invalid_appointment_id',
				__( 'El identificador de cita no es válido.', 'super-mechanic' ),
				array( 'status' => 400 )
			);
		}

		$current = $this->repository->get_by_id_for_business( $appointment_id, $business_id );
		if ( ! is_array( $current ) ) {
			return new WP_Error(
				'sm_public_api_appointment_not_found',
				__( 'No se encontró la cita solicitada.', 'super-mechanic' ),
				array( 'status' => 404 )
			);
		}

		$current_status = sanitize_key( isset( $current['appointment_status'] ) ? (string) $current['appointment_status'] : '' );
		if ( 'confirmed' === $current_status ) {
			return array(
				'appointment'       => $current,
				'already_confirmed' => true,
			);
		}

		if ( 'scheduled' !== $current_status ) {
			return new WP_Error(
				'sm_public_api_appointment_confirm_forbidden_status',
				__( 'La cita no puede confirmarse desde su estado actual.', 'super-mechanic' ),
				array( 'status' => 409 )
			);
		}

		$updated = $this->repository->update_status_for_business( $appointment_id, $business_id, 'confirmed' );
		if ( ! $updated ) {
			return new WP_Error(
				'sm_public_api_appointment_confirm_failed',
				__( 'No fue posible confirmar la cita.', 'super-mechanic' ),
				array( 'status' => 500 )
			);
		}

		$current_after = $this->repository->get_by_id_for_business( $appointment_id, $business_id );
		if ( ! is_array( $current_after ) ) {
			return new WP_Error(
				'sm_public_api_appointment_not_found',
				__( 'No se encontró la cita solicitada.', 'super-mechanic' ),
				array( 'status' => 404 )
			);
		}

		$this->dispatch_event(
			'appointment_status_changed',
			array(
				'appointment_id' => $appointment_id,
				'appointment'    => $current_after,
				'old_status'     => $current_status,
				'new_status'     => 'confirmed',
				'source'         => 'public_api',
				'reason'         => $reason,
			)
		);

		return array(
			'appointment'       => $current_after,
			'already_confirmed' => false,
		);
	}

	/**
	 * Override event dispatcher after bootstrap wiring.
	 *
	 * @param Event_Dispatcher $event_dispatcher Event dispatcher.
	 * @return void
	 */
	public function set_event_dispatcher( Event_Dispatcher $event_dispatcher ) {
		$this->event_dispatcher = $event_dispatcher;
	}

	/**
	 * Get one appointment.
	 *
	 * @param int $id Appointment ID.
	 * @return array<string,mixed>|null
	 */
	public function get_appointment( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get appointments.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_appointments( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	/**
	 * Count appointments.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return int
	 */
	public function count_appointments( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->count_all( $args );
	}

	/**
	 * Get appointments for iCal feed.
	 *
	 * @param array<string,mixed> $args Feed filter args.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_appointments_for_ical_feed( array $args = array() ) {
		$status = isset( $args['status'] ) ? sanitize_key( (string) $args['status'] ) : '';
		$allowed_statuses = array_keys( $this->get_status_options() );

		if ( '' !== $status && ! in_array( $status, $allowed_statuses, true ) ) {
			$status = '';
		}

		$assigned_to = isset( $args['assigned_to'] ) ? absint( $args['assigned_to'] ) : 0;
		$date_from   = $this->normalize_feed_date( isset( $args['date_from'] ) ? $args['date_from'] : '' );
		$date_to     = $this->normalize_feed_date( isset( $args['date_to'] ) ? $args['date_to'] : '' );

		// Keep feeds bounded by default to avoid exporting a full historical dataset.
		if ( '' === $date_from && '' === $date_to ) {
			$date_from = gmdate( 'Y-m-d' );
			$date_to   = gmdate( 'Y-m-d', strtotime( '+30 days', strtotime( $date_from . ' 00:00:00' ) ) );
		}

		$limit = isset( $args['limit'] ) ? absint( $args['limit'] ) : 250;
		$limit = max( 1, min( 500, $limit ) );

		return $this->repository->get_for_ical_feed(
			array(
				'appointment_status' => $status,
				'assigned_to'        => $assigned_to,
				'date_from'          => $date_from,
				'date_to'            => $date_to,
				'limit'              => $limit,
			)
		);
	}

	/**
	 * Get appointment statuses.
	 *
	 * @return array<string,string>
	 */
	public function get_status_options() {
		return array(
			'scheduled'   => __( 'Scheduled', 'super-mechanic' ),
			'confirmed'   => __( 'Confirmed', 'super-mechanic' ),
			'in_progress' => __( 'In progress', 'super-mechanic' ),
			'completed'   => __( 'Completed', 'super-mechanic' ),
			'cancelled'   => __( 'Cancelled', 'super-mechanic' ),
		);
	}

	/**
	 * Get client options.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_client_options() {
		return $this->client_service->get_clients(
			array(
				'per_page' => 200,
				'orderby'  => 'first_name',
				'order'    => 'ASC',
			)
		);
	}

	/**
	 * Get vehicle options.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_vehicle_options() {
		return $this->vehicle_service->get_vehicles(
			array(
				'per_page' => 300,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);
	}

	/**
	 * Get mechanics options.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_mechanic_options() {
		$users = get_users(
			array(
				'role__in' => array( 'sm_mechanic', 'sm_admin', 'administrator' ),
				'fields'   => array( 'ID', 'display_name' ),
				'orderby'  => 'display_name',
				'order'    => 'ASC',
				'number'   => 300,
			)
		);

		if ( ! is_array( $users ) ) {
			return array();
		}

		$options = array();
		foreach ( $users as $user ) {
			$options[] = array(
				'id'           => absint( $user->ID ),
				'display_name' => (string) $user->display_name,
			);
		}

		return $options;
	}

	/**
	 * Validate appointment data.
	 *
	 * @param array<string,mixed> $data      Data.
	 * @param bool                $is_update Is update.
	 * @param int                 $id        Appointment ID.
	 * @return true|WP_Error
	 */
	public function validate_appointment_data( array $data, $is_update = false, $id = 0 ) {
		$errors = new WP_Error();
		$client = ! empty( $data['client_id'] ) ? $this->client_service->get_client( $data['client_id'] ) : null;

		if ( empty( $data['client_id'] ) || ! is_array( $client ) ) {
			$errors->add( 'invalid_client', __( 'El cliente seleccionado no existe.', 'super-mechanic' ) );
		}

		$vehicle = $this->vehicle_service->get_vehicle( $data['vehicle_id'] );
		if ( empty( $data['vehicle_id'] ) || ! is_array( $vehicle ) ) {
			$errors->add( 'invalid_vehicle', __( 'El vehiculo seleccionado no existe.', 'super-mechanic' ) );
		}

		if ( is_array( $vehicle ) && absint( $vehicle['client_id'] ) !== absint( $data['client_id'] ) ) {
			$errors->add( 'vehicle_client_mismatch', __( 'El vehiculo no pertenece al cliente seleccionado.', 'super-mechanic' ) );
		}

		$process = null;
		if ( ! empty( $data['process_id'] ) ) {
			$process = $this->process_service->get_process( $data['process_id'] );
			if ( ! is_array( $process ) ) {
				$errors->add( 'invalid_process', __( 'El proceso asociado no existe.', 'super-mechanic' ) );
			} else {
				if ( absint( $process['client_id'] ) !== absint( $data['client_id'] ) ) {
					$errors->add( 'process_client_mismatch', __( 'El proceso no coincide con el cliente seleccionado.', 'super-mechanic' ) );
				}

				if ( absint( $process['vehicle_id'] ) !== absint( $data['vehicle_id'] ) ) {
					$errors->add( 'process_vehicle_mismatch', __( 'El proceso no coincide con el vehiculo seleccionado.', 'super-mechanic' ) );
				}
			}
		}

		if ( is_array( $client ) && ! empty( $client['business_id'] ) && absint( $client['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'La cita y el cliente deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( is_array( $vehicle ) && ! empty( $vehicle['business_id'] ) && absint( $vehicle['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'La cita y el vehículo deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( is_array( $process ) && ! empty( $process['business_id'] ) && absint( $process['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'La cita y el proceso deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( empty( $data['assigned_to'] ) || ! $this->is_valid_mechanic_user( $data['assigned_to'] ) ) {
			$errors->add( 'invalid_assigned_to', __( 'Debes asignar un mecanico valido.', 'super-mechanic' ) );
		}

		$statuses = array_keys( $this->get_status_options() );
		if ( empty( $data['appointment_status'] ) || ! in_array( $data['appointment_status'], $statuses, true ) ) {
			$errors->add( 'invalid_status', __( 'El estado de la cita no es valido.', 'super-mechanic' ) );
		}

		if ( empty( $data['appointment_date'] ) ) {
			$errors->add( 'invalid_appointment_date', __( 'La fecha de la cita es obligatoria.', 'super-mechanic' ) );
		}

		if ( empty( $data['start_at'] ) ) {
			$errors->add( 'invalid_start_at', __( 'La fecha y hora de inicio es obligatoria.', 'super-mechanic' ) );
		}

		if ( $is_update && $id <= 0 ) {
			$errors->add( 'invalid_appointment_id', __( 'El identificador de cita no es valido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Normalize appointment data.
	 *
	 * @param array<string,mixed> $data Raw data.
	 * @return array<string,mixed>
	 */
	protected function normalize_appointment_data( array $data ) {
		$start_at = $this->normalize_datetime_to_mysql( isset( $data['start_at'] ) ? $data['start_at'] : '' );
		$process  = ! empty( $data['process_id'] ) ? $this->process_service->get_process( absint( $data['process_id'] ) ) : null;
		$client   = ! empty( $data['client_id'] ) ? $this->client_service->get_client( absint( $data['client_id'] ) ) : null;
		$vehicle  = ! empty( $data['vehicle_id'] ) ? $this->vehicle_service->get_vehicle( absint( $data['vehicle_id'] ) ) : null;

		return array(
			'business_id'        => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_business_id_from_parents( $process, $client, $vehicle ),
			'client_id'          => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'vehicle_id'         => isset( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
			'process_id'         => isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0,
			'assigned_to'        => isset( $data['assigned_to'] ) ? absint( $data['assigned_to'] ) : 0,
			'appointment_status' => isset( $data['appointment_status'] ) ? sanitize_key( $data['appointment_status'] ) : 'scheduled',
			'appointment_date'   => $this->normalize_date( isset( $data['appointment_date'] ) ? $data['appointment_date'] : '', $start_at ),
			'start_at'           => $start_at,
			'notes'              => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
		);
	}

	/**
	 * Normalize datetime to mysql format.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_datetime_to_mysql( $value ) {
		$raw = sanitize_text_field( (string) $value );
		if ( '' === $raw ) {
			return '';
		}

		$timestamp = strtotime( $raw );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Normalize date.
	 *
	 * @param mixed  $value    Raw date value.
	 * @param string $start_at Start datetime.
	 * @return string
	 */
	protected function normalize_date( $value, $start_at ) {
		$raw = sanitize_text_field( (string) $value );
		if ( '' !== $raw ) {
			$timestamp = strtotime( $raw );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d', $timestamp );
			}
		}

		if ( '' !== $start_at ) {
			$timestamp = strtotime( $start_at );
			if ( false !== $timestamp ) {
				return gmdate( 'Y-m-d', $timestamp );
			}
		}

		return '';
	}

	/**
	 * Validate mechanic user.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function is_valid_mechanic_user( $user_id ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();

		return ! empty( array_intersect( $roles, array( 'sm_mechanic', 'sm_admin', 'administrator' ) ) );
	}

	/**
	 * Normalize a feed date to Y-m-d.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_feed_date( $value ) {
		$raw = sanitize_text_field( (string) $value );

		if ( '' === $raw ) {
			return '';
		}

		$timestamp = strtotime( $raw );

		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}

	/**
	 * Trigger one-way Google sync after local write.
	 *
	 * Local appointment persistence remains source of truth; remote errors are non-blocking.
	 *
	 * @param int $appointment_id Appointment ID.
	 * @return void
	 */
	protected function sync_appointment_after_write( $appointment_id ) {
		$appointment_id = absint( $appointment_id );
		if ( $appointment_id <= 0 ) {
			return;
		}

		$appointment = $this->repository->get_by_id( $appointment_id );
		if ( ! is_array( $appointment ) ) {
			return;
		}

		$result = $this->google_calendar_sync_service->sync_appointment( $appointment );

		if ( is_wp_error( $result ) ) {
			// Non-destructive sync: never break local appointment flow because remote provider failed.
			return;
		}
	}

	/**
	 * Dispatch internal event with safe payload.
	 *
	 * @param string               $event_name Event name.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return void
	 */
	protected function dispatch_event( $event_name, array $payload ) {
		if ( ! $this->event_dispatcher ) {
			return;
		}

		if ( ! isset( $payload['triggered_by'] ) && function_exists( 'get_current_user_id' ) ) {
			$payload['triggered_by'] = absint( get_current_user_id() );
		}

		$this->event_dispatcher->dispatch( sanitize_key( $event_name ), $payload );
	}

	/**
	 * Resolve business ID from structural parents.
	 *
	 * @param array<string,mixed>|null $process Process row.
	 * @param array<string,mixed>|null $client  Client row.
	 * @param array<string,mixed>|null $vehicle Vehicle row.
	 * @return int
	 */
	protected function resolve_business_id_from_parents( $process, $client, $vehicle ) {
		if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
			return max( 1, absint( $process['business_id'] ) );
		}

		if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
			return max( 1, absint( $client['business_id'] ) );
		}

		if ( is_array( $vehicle ) && ! empty( $vehicle['business_id'] ) ) {
			return max( 1, absint( $vehicle['business_id'] ) );
		}

		return $this->resolve_business_id();
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}
}
