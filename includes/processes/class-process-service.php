<?php
/**
 * Process service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Flows\Flow_Service;
use Super_Mechanic\Flows\Flow_Step_Repository;
use Super_Mechanic\Flows\Flow_Step_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Relations\Client_Vehicle_Repository;
use Super_Mechanic\Vehicles\Vehicle_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles process business rules.
 */
class Process_Service {
	protected $final_statuses = array(
		'completed',
		'delivered',
		'cancelled',
	);

	protected $allowed_process_types = array(
		'maintenance',
		'pre_delivery',
		'paperwork',
	);

	/**
	 * Process types that require vehicle.
	 *
	 * @var array<int, string>
	 */
	protected $vehicle_required_process_types = array(
		'maintenance',
	);

	protected $allowed_statuses = array(
		'draft',
		'pending',
		'in_progress',
		'waiting_approval',
		'waiting_parts',
		'completed',
		'delivered',
		'cancelled',
	);

	protected $repository;
	protected $transaction_repository;
	protected $vehicle_service;
	protected $client_service;
	protected $client_vehicle_repository;
	protected $event_dispatcher;
	protected $flow_service;
	protected $flow_step_service;
	protected $flow_step_repository;
	protected $access_control_service;
	protected $settings_service;
	protected $business_context_service;

	public function __construct( Process_Repository $repository = null, Vehicle_Service $vehicle_service = null, Client_Service $client_service = null, Client_Vehicle_Repository $client_vehicle_repository = null, Event_Dispatcher $event_dispatcher = null, Flow_Service $flow_service = null, Flow_Step_Service $flow_step_service = null, Flow_Step_Repository $flow_step_repository = null, Process_Transaction_Repository $transaction_repository = null, Access_Control_Service $access_control_service = null, Settings_Service $settings_service = null, Business_Context_Service $business_context_service = null ) {
		$this->repository                = $repository ? $repository : new Process_Repository();
		$this->vehicle_service           = $vehicle_service ? $vehicle_service : new Vehicle_Service();
		$this->client_service            = $client_service ? $client_service : new Client_Service();
		$this->client_vehicle_repository = $client_vehicle_repository ? $client_vehicle_repository : new Client_Vehicle_Repository();
		$this->event_dispatcher          = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->flow_service              = $flow_service ? $flow_service : new Flow_Service();
		$this->flow_step_service         = $flow_step_service ? $flow_step_service : new Flow_Step_Service( null, $this->flow_service );
		$this->flow_step_repository      = $flow_step_repository ? $flow_step_repository : new Flow_Step_Repository();
		$this->transaction_repository    = $transaction_repository ? $transaction_repository : new Process_Transaction_Repository();
		$this->access_control_service    = $access_control_service ? $access_control_service : new Access_Control_Service( $this->client_service, $this->client_vehicle_repository, $this->repository );
		$this->settings_service          = $settings_service ? $settings_service : new Settings_Service();
		$this->business_context_service  = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	public function create_process( array $data ) {
		$data  = $this->prepare_process_data( $data, false );
		$valid = $this->validate_process_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$stored_data = $this->format_process_data_for_storage( $data, false );
		$log_data    = null;

		if ( ! empty( $stored_data['flow_id'] ) && ! empty( $stored_data['current_step_id'] ) ) {
			$log_data = $this->build_initial_step_log_data( absint( $stored_data['flow_id'] ), absint( $stored_data['current_step_id'] ) );

			if ( is_wp_error( $log_data ) ) {
				return $log_data;
			}
		}

		$inserted = $this->transaction_repository->create_process_with_initial_log( $stored_data, $log_data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_process_insert_failed', __( 'No fue posible crear el proceso.', 'super-mechanic' ) );
		}

		$this->event_dispatcher->dispatch(
			'process_created',
			array(
				'process_id'   => $inserted,
				'triggered_by' => get_current_user_id(),
			)
		);

		return $inserted;
	}

	public function update_process( $id, array $data ) {
		$id      = absint( $id );
		$current = $this->repository->get_by_id( $id );

		if ( ! $id || ! $current ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_process_data( array_merge( $current, $data ), true );
		$valid = $this->validate_process_data( $data, true );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$stored_data = $this->format_process_data_for_storage( $data, true );
		$workflow    = $this->synchronize_workflow_state_for_update( $current, $data, $stored_data );
		$logs        = array();

		if ( is_wp_error( $workflow ) ) {
			return $workflow;
		}

		if ( absint( $current['current_step_id'] ) !== absint( $stored_data['current_step_id'] ) ) {
			$transition_log = $this->build_step_transition_log_data(
				absint( $stored_data['flow_id'] ),
				absint( $current['current_step_id'] ),
				absint( $stored_data['current_step_id'] )
			);

			if ( is_wp_error( $transition_log ) ) {
				return $transition_log;
			}

			$logs[] = $transition_log;
		}

		if ( $current['status'] !== $data['status'] ) {
			$status_log = $this->build_status_change_log_data(
				absint( $stored_data['flow_id'] ),
				absint( $stored_data['current_step_id'] ),
				$current['status'],
				$data['status']
			);

			if ( is_wp_error( $status_log ) ) {
				return $status_log;
			}

			if ( true !== $status_log ) {
				$logs[] = $status_log;
			}
		}

		$updated = $this->transaction_repository->update_process_with_logs( $id, $stored_data, $logs );

		if ( ! $updated ) {
			return new WP_Error( 'sm_process_update_failed', __( 'No fue posible actualizar el proceso.', 'super-mechanic' ) );
		}

		$step_changed   = absint( $current['current_step_id'] ) !== absint( $stored_data['current_step_id'] );
		$status_changed = $current['status'] !== $data['status'];

		if ( ! $step_changed && ! $status_changed ) {
			$this->event_dispatcher->dispatch(
				'process_updated',
				array(
					'process_id'   => $id,
					'triggered_by' => get_current_user_id(),
				)
			);
		}

		if ( $status_changed ) {
			if ( in_array( $data['status'], $this->final_statuses, true ) ) {
				$this->event_dispatcher->dispatch(
					'process_finalized',
					array(
						'process_id'   => $id,
						'old_status'   => $current['status'],
						'new_status'   => $data['status'],
						'triggered_by' => get_current_user_id(),
					)
				);
			} else {
				$this->event_dispatcher->dispatch(
					'process_status_changed',
					array(
						'process_id'   => $id,
						'old_status'   => $current['status'],
						'new_status'   => $data['status'],
						'triggered_by' => get_current_user_id(),
					)
				);
			}
		}

		return true;
	}

	public function delete_process( $id ) {
		$id = absint( $id );

		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_process_delete_failed', __( 'No fue posible eliminar el proceso.', 'super-mechanic' ) );
		}

		return true;
	}

	public function get_process( $id ) {
		return $this->repository->get_by_id( $id );
	}

	public function user_can_access_process( $user_id, $process_id ) {
		return $this->access_control_service->user_can_access_process( $user_id, $process_id );
	}

	public function get_processes( array $args = array() ) {
		$args = $this->normalize_list_business_scope( $args );

		return $this->repository->get_all( $args );
	}

	public function count_processes( array $args = array() ) {
		$args = $this->normalize_list_business_scope( $args );

		return $this->repository->count_all( $args );
	}

	public function get_process_step_logs( $process_id, $limit = 100 ) {
		return $this->repository->get_step_logs_by_process_id( $process_id, $limit );
	}

	public function count_open_processes() {
		return $this->repository->count_open_processes();
	}

	public function get_grouped_process_counts( $field ) {
		return $this->repository->get_grouped_counts( $field );
	}

	public function get_recent_activity_by_process_ids( array $process_ids, $limit = 20, $customer_visible_only = false ) {
		return $this->repository->get_recent_activity_by_process_ids( $process_ids, $limit, $customer_visible_only );
	}

	public function get_mechanic_processes( $user_id, array $args = array(), $limit = 20 ) {
		return $this->repository->get_mechanic_processes( $user_id, $args, $limit );
	}

	public function validate_process_data( array $data, $is_update = false ) {
		$errors = new WP_Error();

		$process_type     = isset( $data['process_type'] ) ? sanitize_key( (string) $data['process_type'] ) : '';
		$vehicle_required = $this->is_vehicle_required_for_process_type( $process_type );
		$vehicle          = ! empty( $data['vehicle_id'] ) ? $this->vehicle_service->get_vehicle( $data['vehicle_id'] ) : null;

		if ( $vehicle_required && ( empty( $data['vehicle_id'] ) || ! $vehicle ) ) {
			$errors->add( 'invalid_vehicle', __( 'Debes seleccionar un vehiculo valido.', 'super-mechanic' ) );
		} elseif ( ! empty( $data['vehicle_id'] ) && ! $vehicle ) {
			$errors->add( 'invalid_vehicle', __( 'Debes seleccionar un vehiculo valido.', 'super-mechanic' ) );
		}

		$client = ! empty( $data['client_id'] ) ? $this->client_service->get_client( $data['client_id'] ) : null;

		if ( ! empty( $data['client_id'] ) && ! $client ) {
			$errors->add( 'invalid_client', __( 'El cliente seleccionado no existe.', 'super-mechanic' ) );
		}

		if ( is_array( $vehicle ) && ! empty( $vehicle['business_id'] ) && absint( $vehicle['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'El proceso y el vehículo deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( is_array( $client ) && ! empty( $client['business_id'] ) && absint( $client['business_id'] ) !== absint( $data['business_id'] ) ) {
			$errors->add( 'invalid_business_context', __( 'El proceso y el cliente deben pertenecer al mismo negocio.', 'super-mechanic' ) );
		}

		if ( empty( $data['title'] ) ) {
			$errors->add( 'title_required', __( 'El titulo es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['process_type'], $this->allowed_process_types, true ) ) {
			$errors->add( 'invalid_process_type', __( 'El tipo de proceso no es valido.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['status'], $this->allowed_statuses, true ) ) {
			$errors->add( 'invalid_status', __( 'El estado del proceso no es valido.', 'super-mechanic' ) );
		}

		$flow_context = $this->resolve_process_flow_context( $data );

		if ( is_wp_error( $flow_context ) ) {
			$errors->add( $flow_context->get_error_code(), $flow_context->get_error_message() );
		}

		$date_fields = array( 'opened_at', 'due_date', 'completed_at' );

		foreach ( $date_fields as $field ) {
			if ( '' !== $data[ $field ] && ! $this->is_valid_datetime_value( $data[ $field ] ) ) {
				$errors->add( 'invalid_' . $field, sprintf( __( 'La fecha indicada en %s no es valida.', 'super-mechanic' ), $field ) );
			}
		}

		if ( '' !== $data['opened_at'] && '' !== $data['due_date'] && strtotime( $data['due_date'] ) < strtotime( $data['opened_at'] ) ) {
			$errors->add( 'invalid_due_date', __( 'La fecha objetivo no puede ser anterior a la apertura.', 'super-mechanic' ) );
		}

		if ( '' !== $data['opened_at'] && '' !== $data['completed_at'] && strtotime( $data['completed_at'] ) < strtotime( $data['opened_at'] ) ) {
			$errors->add( 'invalid_completed_at', __( 'La fecha de finalizacion no puede ser anterior a la apertura.', 'super-mechanic' ) );
		}

		if ( $is_update && $vehicle_required && empty( $data['vehicle_id'] ) ) {
			$errors->add( 'missing_vehicle', __( 'El proceso debe conservar un vehiculo valido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	public function get_process_type_options() {
		return array(
			'maintenance'  => __( 'Mantenimiento', 'super-mechanic' ),
			'pre_delivery' => __( 'Pre-entrega', 'super-mechanic' ),
			'paperwork'    => __( 'Tramites', 'super-mechanic' ),
		);
	}

	/**
	 * Determine if selected process type requires vehicle.
	 *
	 * @param string $process_type Process type slug.
	 * @return bool
	 */
	protected function is_vehicle_required_for_process_type( $process_type ) {
		return in_array( sanitize_key( (string) $process_type ), $this->vehicle_required_process_types, true );
	}

	public function get_status_options() {
		return array(
			'draft'            => __( 'Borrador', 'super-mechanic' ),
			'pending'          => __( 'Pendiente', 'super-mechanic' ),
			'in_progress'      => __( 'En progreso', 'super-mechanic' ),
			'waiting_approval' => __( 'Esperando aprobacion', 'super-mechanic' ),
			'waiting_parts'    => __( 'Esperando repuestos', 'super-mechanic' ),
			'completed'        => __( 'Completado', 'super-mechanic' ),
			'delivered'        => __( 'Entregado', 'super-mechanic' ),
			'cancelled'        => __( 'Cancelado', 'super-mechanic' ),
		);
	}

	/**
	 * Update only process status through the service layer.
	 *
	 * @param int    $id     Process ID.
	 * @param string $status New status.
	 * @return bool|WP_Error
	 */
	public function update_process_status( $id, $status ) {
		$status = sanitize_key( (string) $status );

		if ( ! in_array( $status, $this->allowed_statuses, true ) ) {
			return new WP_Error( 'sm_process_invalid_quick_status', __( 'El estado seleccionado no es válido para este proceso.', 'super-mechanic' ) );
		}

		return $this->update_process( $id, array( 'status' => $status ) );
	}

	public function get_vehicle_options() {
		$vehicles = $this->vehicle_service->get_vehicles(
			array(
				'per_page' => 200,
				'orderby'  => 'created_at',
				'order'    => 'DESC',
			)
		);

		if ( ! is_array( $vehicles ) ) {
			return array();
		}

		foreach ( $vehicles as &$vehicle ) {
			$vehicle_id = isset( $vehicle['id'] ) ? absint( $vehicle['id'] ) : 0;
			if ( $vehicle_id <= 0 ) {
				continue;
			}

			$current_owner = $this->client_vehicle_repository->get_current_owner( $vehicle_id );
			if ( is_array( $current_owner ) && ! empty( $current_owner['client_id'] ) ) {
				$vehicle['client_id'] = absint( $current_owner['client_id'] );
			}
		}
		unset( $vehicle );

		return $vehicles;
	}

	public function get_client_options() {
		return $this->client_service->get_clients(
			array(
				'per_page' => 200,
				'orderby'  => 'first_name',
				'order'    => 'ASC',
			)
		);
	}

	public function get_default_client_id_for_vehicle( $vehicle_id ) {
		$relation = $this->client_vehicle_repository->get_current_owner( $vehicle_id );
		if ( is_array( $relation ) && ! empty( $relation['client_id'] ) ) {
			return absint( $relation['client_id'] );
		}

		$vehicle = $this->vehicle_service->get_vehicle( $vehicle_id );

		return is_array( $vehicle ) && ! empty( $vehicle['client_id'] ) ? absint( $vehicle['client_id'] ) : 0;
	}

	public function is_active_status( $status ) {
		return ! in_array( sanitize_key( $status ), $this->final_statuses, true );
	}

	public function get_vehicle_process_history( $vehicle_id, $limit = 100 ) {
		$vehicle_id = absint( $vehicle_id );

		if ( $vehicle_id <= 0 ) {
			return array();
		}

		return $this->get_processes(
			array(
				'vehicle_id' => $vehicle_id,
				'per_page'   => absint( $limit ),
				'orderby'    => 'created_at',
				'order'      => 'DESC',
			)
		);
	}

	public function get_active_vehicle_process( $vehicle_id, $exclude_process_id = 0 ) {
		$exclude_process_id = absint( $exclude_process_id );

		foreach ( $this->get_vehicle_process_history( $vehicle_id, 100 ) as $process ) {
			if ( $exclude_process_id > 0 && absint( $process['id'] ) === $exclude_process_id ) {
				continue;
			}

			if ( $this->is_active_status( isset( $process['status'] ) ? $process['status'] : '' ) ) {
				return $process;
			}
		}

		return null;
	}

	/**
	 * Resolve the applicable flow ID for a process payload.
	 *
	 * @param array<string, mixed> $data Process data.
	 * @return int|WP_Error
	 */
	public function resolve_process_flow_id( array $data ) {
		$process_type = isset( $data['process_type'] ) ? sanitize_key( $data['process_type'] ) : '';
		$requested_id = isset( $data['flow_id'] ) ? absint( $data['flow_id'] ) : 0;

		if ( $requested_id > 0 ) {
			$flow = $this->flow_service->get_flow( $requested_id );

			if ( ! $flow || empty( $flow['is_active'] ) ) {
				return new WP_Error( 'sm_process_flow_invalid', __( 'El flujo seleccionado no existe o no esta activo.', 'super-mechanic' ) );
			}

			if ( sanitize_key( $flow['process_type'] ) !== $process_type ) {
				return new WP_Error( 'sm_process_flow_type_mismatch', __( 'El flujo seleccionado no corresponde al tipo de proceso indicado.', 'super-mechanic' ) );
			}

			return absint( $flow['id'] );
		}

		$flow = $this->flow_service->resolve_flow_for_process_type( $process_type );

		if ( is_wp_error( $flow ) ) {
			return $flow;
		}

		return absint( $flow['id'] );
	}

	/**
	 * Resolve the initial current step ID for a flow.
	 *
	 * @param int $flow_id Flow ID.
	 * @return int|WP_Error
	 */
	public function resolve_initial_step_id( $flow_id ) {
		$step = $this->flow_step_service->resolve_initial_step( $flow_id );

		if ( is_wp_error( $step ) ) {
			return $step;
		}

		return absint( $step['id'] );
	}

	/**
	 * Update the current step and register the transition log.
	 *
	 * @param int $process_id Process ID.
	 * @param int $step_id    Step ID.
	 * @return bool|WP_Error
	 */
	public function update_current_step( $process_id, $step_id ) {
		$process_id = absint( $process_id );
		$step_id    = absint( $step_id );
		$process    = $this->repository->get_by_id( $process_id );

		if ( ! $process ) {
			return new WP_Error( 'sm_process_not_found', __( 'El proceso no existe.', 'super-mechanic' ) );
		}

		if ( absint( $process['current_step_id'] ) === $step_id ) {
			return true;
		}

		$valid_transition = $this->flow_step_service->validate_step_transition( absint( $process['flow_id'] ), absint( $process['current_step_id'] ), $step_id );

		if ( is_wp_error( $valid_transition ) ) {
			return $valid_transition;
		}

		if ( ! $this->is_step_back_allowed( absint( $process['flow_id'] ), absint( $process['current_step_id'] ), $step_id ) ) {
			return new WP_Error( 'sm_process_step_back_disabled', __( 'La configuracion actual del taller no permite retroceder pasos.', 'super-mechanic' ) );
		}

		$log_data = $this->build_step_transition_log_data( absint( $process['flow_id'] ), absint( $process['current_step_id'] ), $step_id );

		if ( is_wp_error( $log_data ) ) {
			return $log_data;
		}

		$update_data     = array(
			'current_step_id' => $step_id,
		);
		$logs            = array( $log_data );
		$status_changed  = false;
		$previous_status = sanitize_key( $process['status'] );

		if ( $this->should_auto_complete_on_final_step() && $this->flow_step_service->is_final_step( absint( $process['flow_id'] ), $step_id ) ) {
			if ( ! $this->is_final_process_status( $previous_status ) ) {
				$this->mark_process_as_completed( $update_data );
				$status_log = $this->build_status_change_log_data(
					absint( $process['flow_id'] ),
					$step_id,
					$previous_status,
					'completed'
				);

				if ( is_wp_error( $status_log ) ) {
					return $status_log;
				}

				if ( true !== $status_log ) {
					$logs[] = $status_log;
				}

				$status_changed = true;
			}
		} elseif ( $this->is_final_process_status( $previous_status ) ) {
			return new WP_Error( 'sm_process_step_reopen_invalid', __( 'No puedes mover un proceso finalizado a un paso no final sin reabrirlo por una via controlada.', 'super-mechanic' ) );
		}

		$updated = $this->transaction_repository->update_process_with_logs(
			$process_id,
			$update_data,
			$logs
		);

		if ( ! $updated ) {
			return new WP_Error( 'sm_process_step_update_failed', __( 'No fue posible actualizar el paso actual del proceso.', 'super-mechanic' ) );
		}

		$this->event_dispatcher->dispatch(
			'process_step_changed',
			array(
				'process_id'    => $process_id,
				'from_step_id'  => absint( $process['current_step_id'] ),
				'to_step_id'    => $step_id,
				'triggered_by'  => get_current_user_id(),
			)
		);

		if ( $status_changed ) {
			$this->event_dispatcher->dispatch(
				'process_finalized',
				array(
					'process_id'   => $process_id,
					'old_status'   => $previous_status,
					'new_status'   => 'completed',
					'triggered_by' => get_current_user_id(),
				)
			);
		}

		return true;
	}

	/**
	 * Resolve normalized flow context for a process payload.
	 *
	 * @param array<string, mixed> $data Process data.
	 * @return array<string, int>|WP_Error
	 */
	public function resolve_process_flow_context( array $data ) {
		$flow_id = $this->resolve_process_flow_id( $data );

		if ( is_wp_error( $flow_id ) ) {
			return $flow_id;
		}

		$current_step_id = isset( $data['current_step_id'] ) ? absint( $data['current_step_id'] ) : 0;

		if ( $current_step_id > 0 ) {
			$step = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $current_step_id );

			if ( ! $step || empty( $step['is_active'] ) ) {
				return new WP_Error( 'sm_process_step_invalid', __( 'El paso actual no es valido para el flujo asignado.', 'super-mechanic' ) );
			}
		} else {
			$current_step_id = $this->resolve_initial_step_id( $flow_id );
		}

		if ( is_wp_error( $current_step_id ) ) {
			return $current_step_id;
		}

		return array(
			'flow_id'         => absint( $flow_id ),
			'current_step_id' => absint( $current_step_id ),
		);
	}

	protected function prepare_process_data( array $data, $is_update ) {
		$candidate_business_id = isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0;
		$prepared = array(
			'business_id'   => $candidate_business_id > 0
				? $this->normalize_business_id( $candidate_business_id )
				: $this->resolve_business_id_from_roots(
					isset( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
					isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0
				),
			'vehicle_id'     => isset( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
			'client_id'      => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'flow_id'        => isset( $data['flow_id'] ) ? absint( $data['flow_id'] ) : 0,
			'current_step_id'=> isset( $data['current_step_id'] ) ? absint( $data['current_step_id'] ) : 0,
			'process_type'   => isset( $data['process_type'] ) ? sanitize_key( $data['process_type'] ) : '',
			'status'         => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'draft',
			'title'          => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'internal_notes' => isset( $data['internal_notes'] ) ? sanitize_textarea_field( $data['internal_notes'] ) : '',
			'opened_at'      => isset( $data['opened_at'] ) ? sanitize_text_field( $data['opened_at'] ) : '',
			'due_date'       => isset( $data['due_date'] ) ? sanitize_text_field( $data['due_date'] ) : '',
			'completed_at'   => isset( $data['completed_at'] ) ? sanitize_text_field( $data['completed_at'] ) : '',
		);

		if ( 0 === $prepared['client_id'] && $prepared['vehicle_id'] > 0 ) {
			$prepared['client_id'] = $this->get_default_client_id_for_vehicle( $prepared['vehicle_id'] );
		}

		if ( ! $is_update && '' === $prepared['status'] ) {
			$prepared['status'] = 'draft';
		}

		return $prepared;
	}

	/**
	 * Register the initial step log for a process.
	 *
	 * @param int $process_id Process ID.
	 * @param int $flow_id    Flow ID.
	 * @param int $step_id    Initial step ID.
	 * @return int|WP_Error
	 */
	public function log_initial_step( $process_id, $flow_id, $step_id ) {
		$process_id = absint( $process_id );
		$log_data    = $this->build_initial_step_log_data( $flow_id, $step_id );

		if ( is_wp_error( $log_data ) ) {
			return $log_data;
		}

		$latest = $this->repository->get_latest_process_step_log( $process_id );

		if ( ! empty( $latest ) && 'step_initialized' === $latest['action_type'] && absint( $latest['flow_step_id'] ) === absint( $log_data['flow_step_id'] ) ) {
			return absint( $latest['id'] );
		}

		$log_data['process_id'] = $process_id;
		$inserted               = $this->repository->insert_process_step_log( $log_data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_process_initial_step_log_failed', __( 'No fue posible registrar el log inicial del proceso.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Register a step transition log.
	 *
	 * @param int $process_id   Process ID.
	 * @param int $from_step_id Previous step ID.
	 * @param int $to_step_id   New step ID.
	 * @return int|WP_Error
	 */
	public function log_step_transition( $process_id, $from_step_id, $to_step_id ) {
		$process_id   = absint( $process_id );
		$process      = $this->repository->get_by_id( $process_id );

		if ( ! $process ) {
			return new WP_Error( 'sm_process_step_transition_invalid', __( 'No fue posible registrar la transicion de paso del proceso.', 'super-mechanic' ) );
		}

		$log_data = $this->build_step_transition_log_data( absint( $process['flow_id'] ), $from_step_id, $to_step_id );

		if ( is_wp_error( $log_data ) ) {
			return $log_data;
		}

		$log_data['process_id'] = $process_id;
		$inserted               = $this->repository->insert_process_step_log( $log_data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_process_step_transition_log_failed', __( 'No fue posible registrar la transicion del proceso.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Register a process status change log on the current step context.
	 *
	 * @param int    $process_id Process ID.
	 * @param int    $step_id    Current step ID.
	 * @param string $from_status Previous status.
	 * @param string $to_status   New status.
	 * @return int|WP_Error
	 */
	public function log_process_status_change( $process_id, $step_id, $from_status, $to_status ) {
		$process_id = absint( $process_id );
		$process = $this->repository->get_by_id( $process_id );

		if ( ! $process ) {
			return new WP_Error( 'sm_process_status_log_invalid', __( 'No fue posible registrar el cambio de estado del proceso.', 'super-mechanic' ) );
		}

		$log_data = $this->build_status_change_log_data( absint( $process['flow_id'] ), $step_id, $from_status, $to_status );

		if ( is_wp_error( $log_data ) || true === $log_data ) {
			return $log_data;
		}

		$log_data['process_id'] = $process_id;
		$inserted               = $this->repository->insert_process_step_log( $log_data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_process_status_log_failed', __( 'No fue posible registrar el cambio de estado del proceso.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	protected function format_process_data_for_storage( array $data, $is_update ) {
		$flow_context = $this->resolve_process_flow_context( $data );
		$flow_id      = ! is_wp_error( $flow_context ) ? absint( $flow_context['flow_id'] ) : 0;
		$current_step = ! is_wp_error( $flow_context ) ? absint( $flow_context['current_step_id'] ) : 0;
		$completed_at = $this->normalize_datetime_value( $data['completed_at'] );
		$stored       = array(
			'business_id'    => absint( $data['business_id'] ),
			'vehicle_id'      => absint( $data['vehicle_id'] ),
			'client_id'       => absint( $data['client_id'] ),
			'flow_id'         => $flow_id,
			'process_type'    => $data['process_type'],
			'title'           => $data['title'],
			'description'     => '',
			'internal_notes'  => $data['internal_notes'],
			'current_step_id' => $current_step,
			'status'          => $data['status'],
			'priority'        => 'normal',
			'opened_at'       => $this->normalize_datetime_value( $data['opened_at'] ),
			'due_date'        => $this->normalize_datetime_value( $data['due_date'] ),
			'completed_at'    => $completed_at,
			'closed_at'       => $completed_at,
		);

		if ( ! $is_update ) {
			$stored['created_by']  = get_current_user_id();
			$stored['assigned_to'] = 0;
		}

		return $stored;
	}

	/**
	 * Enforce the minimal workflow rules for updates without introducing a new engine.
	 *
	 * @param array<string, mixed> $current     Current stored process.
	 * @param array<string, mixed> $data        Normalized process data.
	 * @param array<string, mixed> $stored_data Storage payload.
	 * @return true|WP_Error
	 */
	protected function synchronize_workflow_state_for_update( array $current, array &$data, array &$stored_data ) {
		$current_step_id = absint( $current['current_step_id'] );
		$target_step_id  = absint( $stored_data['current_step_id'] );
		$target_status   = sanitize_key( $data['status'] );
		$step_changed    = $current_step_id !== $target_step_id;

		if ( ! $step_changed ) {
			return true;
		}

		$valid_transition = $this->flow_step_service->validate_step_transition( absint( $stored_data['flow_id'] ), $current_step_id, $target_step_id );

		if ( is_wp_error( $valid_transition ) ) {
			return $valid_transition;
		}

		if ( ! $this->is_step_back_allowed( absint( $stored_data['flow_id'] ), $current_step_id, $target_step_id ) ) {
			return new WP_Error( 'sm_process_step_back_disabled', __( 'La configuracion actual del taller no permite retroceder pasos.', 'super-mechanic' ) );
		}

		if ( $this->should_auto_complete_on_final_step() && $this->flow_step_service->is_final_step( absint( $stored_data['flow_id'] ), $target_step_id ) ) {
			if ( ! $this->is_final_process_status( $target_status ) ) {
				$data['status']        = 'completed';
				$stored_data['status'] = 'completed';
				$this->mark_process_as_completed( $stored_data );
			}

			return true;
		}

		if ( $this->is_final_process_status( sanitize_key( $current['status'] ) ) && $this->is_final_process_status( $target_status ) ) {
			return new WP_Error( 'sm_process_step_reopen_invalid', __( 'No puedes retroceder desde un proceso finalizado manteniendo un estado terminal.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Check whether a process status is terminal.
	 *
	 * @param string $status Process status.
	 * @return bool
	 */
	protected function is_final_process_status( $status ) {
		return in_array( sanitize_key( $status ), $this->final_statuses, true );
	}

	/**
	 * Apply the minimum persisted fields for a completed process.
	 *
	 * @param array<string, mixed> $stored_data Process storage payload.
	 * @return void
	 */
	protected function mark_process_as_completed( array &$stored_data ) {
		$completed_at = current_time( 'mysql' );

		if ( empty( $stored_data['completed_at'] ) ) {
			$stored_data['completed_at'] = $completed_at;
		}

		if ( empty( $stored_data['closed_at'] ) ) {
			$stored_data['closed_at'] = $stored_data['completed_at'];
		}
	}

	protected function is_valid_datetime_value( $value ) {
		return false !== strtotime( $value );
	}

	/**
	 * Check whether workshop settings allow step back transitions.
	 *
	 * @param int $flow_id      Flow ID.
	 * @param int $from_step_id Current step ID.
	 * @param int $to_step_id   Target step ID.
	 * @return bool
	 */
	protected function is_step_back_allowed( $flow_id, $from_step_id, $to_step_id ) {
		if ( ! $this->is_step_back_transition( $flow_id, $from_step_id, $to_step_id ) ) {
			return true;
		}

		return (bool) $this->settings_service->get_setting( 'process', 'allow_step_back', true );
	}

	/**
	 * Detect a backwards adjacent step transition.
	 *
	 * @param int $flow_id      Flow ID.
	 * @param int $from_step_id Current step ID.
	 * @param int $to_step_id   Target step ID.
	 * @return bool
	 */
	protected function is_step_back_transition( $flow_id, $from_step_id, $to_step_id ) {
		$flow_id      = absint( $flow_id );
		$from_step_id = absint( $from_step_id );
		$to_step_id   = absint( $to_step_id );

		if ( ! $flow_id || ! $from_step_id || ! $to_step_id ) {
			return false;
		}

		$from_step = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $from_step_id );
		$to_step   = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $to_step_id );

		if ( ! $from_step || ! $to_step ) {
			return false;
		}

		return absint( $to_step['step_order'] ) < absint( $from_step['step_order'] );
	}

	/**
	 * Check whether final steps should auto-complete the process.
	 *
	 * @return bool
	 */
	protected function should_auto_complete_on_final_step() {
		return (bool) $this->settings_service->get_setting( 'process', 'auto_complete_on_final_step', true );
	}

	/**
	 * Build initial step log payload without persisting it.
	 *
	 * @param int $flow_id Flow ID.
	 * @param int $step_id Step ID.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function build_initial_step_log_data( $flow_id, $step_id ) {
		$flow_id = absint( $flow_id );
		$step_id = absint( $step_id );
		$step    = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $step_id );

		if ( ! $step ) {
			return new WP_Error( 'sm_process_initial_step_invalid', __( 'No fue posible registrar el paso inicial del proceso.', 'super-mechanic' ) );
		}

		return array(
			'flow_step_id'     => $step_id,
			'action_type'      => 'step_initialized',
			'message'          => sprintf( __( 'Proceso iniciado en el paso "%s".', 'super-mechanic' ), $step['step_label'] ),
			'internal_note'    => '',
			'customer_visible' => 0,
			'created_by'       => get_current_user_id(),
		);
	}

	/**
	 * Build step transition log payload without persisting it.
	 *
	 * @param int $flow_id      Flow ID.
	 * @param int $from_step_id Previous step ID.
	 * @param int $to_step_id   New step ID.
	 * @return array<string, mixed>|WP_Error
	 */
	protected function build_step_transition_log_data( $flow_id, $from_step_id, $to_step_id ) {
		$flow_id      = absint( $flow_id );
		$from_step_id = absint( $from_step_id );
		$to_step_id   = absint( $to_step_id );

		if ( ! $to_step_id ) {
			return new WP_Error( 'sm_process_step_transition_invalid', __( 'No fue posible registrar la transicion de paso del proceso.', 'super-mechanic' ) );
		}

		$to_step = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $to_step_id );

		if ( ! $to_step ) {
			return new WP_Error( 'sm_process_step_transition_invalid_target', __( 'El paso destino no pertenece al flujo actual del proceso.', 'super-mechanic' ) );
		}

		$from_label = '';
		if ( $from_step_id > 0 ) {
			$from_step  = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $from_step_id );
			$from_label = $from_step ? $from_step['step_label'] : '#' . $from_step_id;
		}

		$message = $from_step_id > 0
			? sprintf( __( 'Transicion de paso: "%1$s" -> "%2$s".', 'super-mechanic' ), $from_label, $to_step['step_label'] )
			: sprintf( __( 'Paso actual actualizado a "%s".', 'super-mechanic' ), $to_step['step_label'] );

		return array(
			'flow_step_id'     => $to_step_id,
			'action_type'      => 'step_transition',
			'message'          => $message,
			'internal_note'    => '',
			'customer_visible' => 0,
			'created_by'       => get_current_user_id(),
		);
	}

	/**
	 * Build status change log payload without persisting it.
	 *
	 * @param int    $flow_id      Flow ID.
	 * @param int    $step_id      Current step ID.
	 * @param string $from_status  Previous status.
	 * @param string $to_status    New status.
	 * @return array<string, mixed>|WP_Error|true
	 */
	protected function build_status_change_log_data( $flow_id, $step_id, $from_status, $to_status ) {
		$flow_id = absint( $flow_id );
		$step_id = absint( $step_id );

		if ( ! $step_id || $from_status === $to_status ) {
			return true;
		}

		$step = $this->flow_step_repository->get_by_flow_and_id( $flow_id, $step_id );

		if ( ! $step ) {
			return new WP_Error( 'sm_process_status_log_invalid', __( 'No fue posible registrar el cambio de estado del proceso.', 'super-mechanic' ) );
		}

		return array(
			'flow_step_id'     => $step_id,
			'action_type'      => 'status_changed',
			'message'          => sprintf( __( 'Estado del proceso actualizado de "%1$s" a "%2$s".', 'super-mechanic' ), sanitize_key( $from_status ), sanitize_key( $to_status ) ),
			'internal_note'    => '',
			'customer_visible' => 0,
			'created_by'       => get_current_user_id(),
		);
	}

	protected function normalize_datetime_value( $value ) {
		if ( '' === $value ) {
			return null;
		}

		$timestamp = strtotime( $value );

		return false === $timestamp ? null : gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Resolve business ID from parent entities.
	 *
	 * @param int $vehicle_id Vehicle ID.
	 * @param int $client_id  Client ID.
	 * @return int
	 */
	protected function resolve_business_id_from_roots( $vehicle_id, $client_id ) {
		$vehicle_id = absint( $vehicle_id );
		$client_id  = absint( $client_id );

		if ( $vehicle_id > 0 ) {
			$vehicle = $this->vehicle_service->get_vehicle( $vehicle_id );

			if ( is_array( $vehicle ) && ! empty( $vehicle['business_id'] ) ) {
				return max( 1, absint( $vehicle['business_id'] ) );
			}
		}

		if ( $client_id > 0 ) {
			$client = $this->client_service->get_client( $client_id );

			if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
				return max( 1, absint( $client['business_id'] ) );
			}
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

	/**
	 * Normalize explicit business filter by user tenancy scope.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	protected function normalize_list_business_scope( array $args ) {
		$candidate_business_id = isset( $args['business_id'] ) ? absint( $args['business_id'] ) : 0;
		$args['business_id']   = $candidate_business_id > 0 ? $this->normalize_business_id( $candidate_business_id ) : $this->resolve_business_id();

		return $args;
	}

	/**
	 * Normalize business ID against allowed businesses for current user.
	 *
	 * @param int $business_id Candidate business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id ) {
		return absint( $this->business_context_service->normalize_business_id( $business_id ) );
	}
}
