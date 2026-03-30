<?php
/**
 * CRM pipeline service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use Super_Mechanic\Clients\Client_Repository;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Processes\Process_Repository;
use Super_Mechanic\Vehicles\Vehicle_Repository;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM pipeline business logic.
 */
class Crm_Pipeline_Service {
	/**
	 * Allowed stages.
	 *
	 * @var array<int, string>
	 */
	const STAGES = array( 'new_lead', 'contacted', 'quoted', 'negotiating', 'won', 'lost' );

	/**
	 * Repository.
	 *
	 * @var Crm_Pipeline_Repository
	 */
	protected $repository;

	/**
	 * Client repository.
	 *
	 * @var Client_Repository
	 */
	protected $client_repository;

	/**
	 * Vehicle repository.
	 *
	 * @var Vehicle_Repository
	 */
	protected $vehicle_repository;

	/**
	 * Process repository.
	 *
	 * @var Process_Repository
	 */
	protected $process_repository;

	/**
	 * Process service.
	 *
	 * @var Process_Service
	 */
	protected $process_service;

	/**
	 * CRM task service.
	 *
	 * @var Crm_Task_Service
	 */
	protected $task_service;

	/**
	 * Constructor.
	 *
	 * @param Crm_Pipeline_Repository|null $repository Repository.
	 */
	public function __construct( Crm_Pipeline_Repository $repository = null ) {
		$this->repository         = $repository ? $repository : new Crm_Pipeline_Repository();
		$this->client_repository  = new Client_Repository();
		$this->vehicle_repository = new Vehicle_Repository();
		$this->process_repository = new Process_Repository();
		$this->process_service    = new Process_Service();
		$this->task_service       = new Crm_Task_Service();
	}

	/**
	 * Create opportunity.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return int|WP_Error
	 */
	public function create_opportunity( array $data ) {
		$normalized = $this->normalize_data( $data );
		$valid      = $this->validate_data( $normalized );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $normalized['position'] <= 0 ) {
			$normalized['position'] = $this->repository->get_next_position( $normalized['stage'] );
		}

		$inserted = $this->repository->insert( $normalized );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_crm_pipeline_insert_failed', __( 'Could not create CRM opportunity.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update opportunity.
	 *
	 * @param int                 $id Opportunity ID.
	 * @param array<string, mixed> $data Payload.
	 * @return bool|WP_Error
	 */
	public function update_opportunity( $id, array $data ) {
		$id       = absint( $id );
		$existing = $this->repository->get_by_id( $id );

		if ( empty( $existing ) ) {
			return new WP_Error( 'sm_crm_pipeline_not_found', __( 'CRM opportunity does not exist.', 'super-mechanic' ) );
		}

		$normalized = $this->normalize_data( $data );
		$valid      = $this->validate_data( $normalized );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $normalized['position'] <= 0 ) {
			if ( $existing['stage'] === $normalized['stage'] ) {
				$normalized['position'] = absint( $existing['position'] );
			} else {
				$normalized['position'] = $this->repository->get_next_position( $normalized['stage'] );
			}
		}

		if ( ! $this->repository->update( $id, $normalized ) ) {
			return new WP_Error( 'sm_crm_pipeline_update_failed', __( 'Could not update CRM opportunity.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete opportunity.
	 *
	 * @param int $id Opportunity ID.
	 * @return bool|WP_Error
	 */
	public function delete_opportunity( $id ) {
		$id = absint( $id );

		if ( ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_crm_pipeline_not_found', __( 'CRM opportunity does not exist.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_crm_pipeline_delete_failed', __( 'Could not delete CRM opportunity.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get one opportunity.
	 *
	 * @param int $id Opportunity ID.
	 * @return array<string, mixed>|null
	 */
	public function get_opportunity( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get opportunities list.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_opportunities( array $args = array() ) {
		return $this->repository->get_all( $args );
	}

	/**
	 * Count opportunities.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_opportunities( array $args = array() ) {
		return $this->repository->count_all( $args );
	}

	/**
	 * Return allowed stage options.
	 *
	 * @return array<int, string>
	 */
	public function get_stage_catalog() {
		return self::STAGES;
	}

	/**
	 * Get CRM task status catalog.
	 *
	 * @return array<int, string>
	 */
	public function get_task_status_catalog() {
		return $this->task_service->get_status_catalog();
	}

	/**
	 * Get CRM task type catalog.
	 *
	 * @return array<int, string>
	 */
	public function get_task_type_catalog() {
		return $this->task_service->get_task_type_catalog();
	}

	/**
	 * Get tasks for one opportunity.
	 *
	 * @param int $opportunity_id Opportunity ID.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function get_tasks_for_opportunity( $opportunity_id ) {
		return $this->task_service->get_tasks_by_pipeline_id( $opportunity_id );
	}

	/**
	 * Get one task.
	 *
	 * @param int $task_id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function get_task( $task_id ) {
		return $this->task_service->get_task( $task_id );
	}

	/**
	 * Create task for one opportunity.
	 *
	 * @param int                  $opportunity_id Opportunity ID.
	 * @param array<string, mixed> $data Payload.
	 * @return int|WP_Error
	 */
	public function create_task_for_opportunity( $opportunity_id, array $data ) {
		$data['crm_pipeline_id'] = absint( $opportunity_id );

		return $this->task_service->create_task( $data );
	}

	/**
	 * Update one task.
	 *
	 * @param int                  $task_id Task ID.
	 * @param array<string, mixed> $data Payload.
	 * @return bool|WP_Error
	 */
	public function update_task( $task_id, array $data ) {
		return $this->task_service->update_task( $task_id, $data );
	}

	/**
	 * Complete one task.
	 *
	 * @param int $task_id Task ID.
	 * @return bool|WP_Error
	 */
	public function complete_task( $task_id ) {
		return $this->task_service->complete_task( $task_id );
	}

	/**
	 * Get operational task buckets for CRM.
	 *
	 * @param int $upcoming_days Upcoming window in days.
	 * @param int $limit         Max rows per bucket.
	 * @return array<string, mixed>
	 */
	public function get_task_operational_buckets( $upcoming_days = 7, $limit = 10 ) {
		return $this->task_service->get_operational_buckets( $upcoming_days, $limit );
	}

	/**
	 * Quickly update stage from list context.
	 *
	 * @param int    $id Opportunity ID.
	 * @param string $stage Target stage.
	 * @return bool|WP_Error
	 */
	public function quick_update_stage( $id, $stage ) {
		$id       = absint( $id );
		$stage    = sanitize_key( (string) $stage );
		$existing = $this->repository->get_by_id( $id );

		if ( empty( $existing ) ) {
			return new WP_Error( 'sm_crm_pipeline_not_found', __( 'CRM opportunity does not exist.', 'super-mechanic' ) );
		}

		if ( ! in_array( $stage, self::STAGES, true ) ) {
			return new WP_Error( 'sm_crm_pipeline_invalid_stage', __( 'Selected stage is not valid.', 'super-mechanic' ) );
		}

		if ( (string) $existing['stage'] === $stage ) {
			return true;
		}

		$position = $this->repository->get_next_position( $stage );

		if ( ! $this->repository->update_stage( $id, $stage, $position ) ) {
			return new WP_Error( 'sm_crm_pipeline_stage_update_failed', __( 'Could not update CRM stage.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Create a process from CRM opportunity.
	 *
	 * @param int $id Opportunity ID.
	 * @return int|WP_Error
	 */
	public function create_process_from_opportunity( $id, $process_type ) {
		$id          = absint( $id );
		$process_type = sanitize_key( (string) $process_type );
		$opportunity = $this->repository->get_by_id( $id );

		if ( empty( $opportunity ) ) {
			return new WP_Error( 'sm_crm_pipeline_not_found', __( 'CRM opportunity does not exist.', 'super-mechanic' ) );
		}

		if ( ! empty( $opportunity['process_id'] ) ) {
			return new WP_Error( 'sm_crm_pipeline_already_linked', __( 'This opportunity is already linked to a process.', 'super-mechanic' ) );
		}

		$allowed_process_types = array_keys( $this->process_service->get_process_type_options() );
		if ( ! in_array( $process_type, $allowed_process_types, true ) ) {
			return new WP_Error( 'sm_crm_pipeline_invalid_process_type', __( 'Selected process type is not valid.', 'super-mechanic' ) );
		}

		if ( 'maintenance' === $process_type && empty( $opportunity['vehicle_id'] ) ) {
			return new WP_Error( 'sm_crm_pipeline_vehicle_required', __( 'Vehicle is required for maintenance processes.', 'super-mechanic' ) );
		}

		$payload = array(
			'business_id'   => absint( $opportunity['business_id'] ),
			'client_id'     => absint( $opportunity['client_id'] ),
			'vehicle_id'    => absint( $opportunity['vehicle_id'] ),
			'process_type'  => $process_type,
			'status'        => 'draft',
			'title'         => sprintf(
				/* translators: %s opportunity title */
				__( 'CRM Opportunity: %s', 'super-mechanic' ),
				(string) $opportunity['title']
			),
			'internal_notes' => sprintf(
				/* translators: %d opportunity id */
				__( 'Created from CRM opportunity #%d.', 'super-mechanic' ),
				$id
			),
		);

		$process_id = $this->process_service->create_process( $payload );

		if ( is_wp_error( $process_id ) ) {
			return $process_id;
		}

		if ( ! $this->repository->update_process_link( $id, absint( $process_id ) ) ) {
			return new WP_Error( 'sm_crm_pipeline_link_process_failed', __( 'Process created but CRM link could not be saved.', 'super-mechanic' ) );
		}

		return absint( $process_id );
	}

	/**
	 * Link an existing process to CRM opportunity.
	 *
	 * @param int $id Opportunity ID.
	 * @param int $process_id Process ID.
	 * @return bool|WP_Error
	 */
	public function link_existing_process( $id, $process_id ) {
		$id          = absint( $id );
		$process_id  = absint( $process_id );
		$opportunity = $this->repository->get_by_id( $id );

		if ( empty( $opportunity ) ) {
			return new WP_Error( 'sm_crm_pipeline_not_found', __( 'CRM opportunity does not exist.', 'super-mechanic' ) );
		}

		if ( $process_id <= 0 ) {
			return new WP_Error( 'sm_crm_pipeline_process_required', __( 'Process ID is required.', 'super-mechanic' ) );
		}

		if ( ! empty( $opportunity['process_id'] ) ) {
			return new WP_Error( 'sm_crm_pipeline_already_linked', __( 'This opportunity is already linked to a process.', 'super-mechanic' ) );
		}

		$process = $this->process_repository->get_by_id( $process_id );

		if ( empty( $process ) ) {
			return new WP_Error( 'sm_crm_pipeline_invalid_process', __( 'The selected process is not valid for the active business.', 'super-mechanic' ) );
		}

		if ( absint( $process['client_id'] ) !== absint( $opportunity['client_id'] ) ) {
			return new WP_Error( 'sm_crm_pipeline_client_mismatch', __( 'The process client does not match the CRM opportunity client.', 'super-mechanic' ) );
		}

		if ( ! empty( $opportunity['vehicle_id'] ) && absint( $process['vehicle_id'] ) !== absint( $opportunity['vehicle_id'] ) ) {
			return new WP_Error( 'sm_crm_pipeline_vehicle_mismatch', __( 'The process vehicle does not match the CRM opportunity vehicle.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->update_process_link( $id, $process_id ) ) {
			return new WP_Error( 'sm_crm_pipeline_link_process_failed', __( 'Could not link process to CRM opportunity.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Normalize payload.
	 *
	 * @param array<string, mixed> $data Raw payload.
	 * @return array<string, mixed>
	 */
	protected function normalize_data( array $data ) {
		return array(
			'client_id'        => isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0,
			'vehicle_id'       => ! empty( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
			'process_id'       => ! empty( $data['process_id'] ) ? absint( $data['process_id'] ) : 0,
			'stage'            => isset( $data['stage'] ) ? sanitize_key( (string) $data['stage'] ) : 'new_lead',
			'title'            => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'estimated_value'  => isset( $data['estimated_value'] ) ? (float) $data['estimated_value'] : 0.0,
			'currency'         => isset( $data['currency'] ) ? strtoupper( sanitize_text_field( (string) $data['currency'] ) ) : 'USD',
			'assigned_user_id' => ! empty( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : 0,
			'notes'            => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'position'         => isset( $data['position'] ) ? absint( $data['position'] ) : 0,
		);
	}

	/**
	 * Validate payload.
	 *
	 * @param array<string, mixed> $data Normalized payload.
	 * @return true|WP_Error
	 */
	protected function validate_data( array $data ) {
		$errors = new WP_Error();

		if ( empty( $data['client_id'] ) ) {
			$errors->add( 'client_required', __( 'Client is required.', 'super-mechanic' ) );
		} elseif ( ! $this->client_repository->get_by_id( $data['client_id'] ) ) {
			$errors->add( 'invalid_client', __( 'Client is not valid for the active business.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['vehicle_id'] ) && ! $this->vehicle_repository->get_by_id( $data['vehicle_id'] ) ) {
			$errors->add( 'invalid_vehicle', __( 'Vehicle is not valid for the active business.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['process_id'] ) && ! $this->process_repository->get_by_id( $data['process_id'] ) ) {
			$errors->add( 'invalid_process', __( 'Process is not valid for the active business.', 'super-mechanic' ) );
		}

		if ( empty( $data['stage'] ) || ! in_array( $data['stage'], self::STAGES, true ) ) {
			$errors->add( 'invalid_stage', __( 'Stage is not valid.', 'super-mechanic' ) );
		}

		if ( '' === trim( (string) $data['title'] ) ) {
			$errors->add( 'title_required', __( 'Title is required.', 'super-mechanic' ) );
		}

		if ( $data['estimated_value'] < 0 ) {
			$errors->add( 'estimated_value_invalid', __( 'Estimated value cannot be negative.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}
}
