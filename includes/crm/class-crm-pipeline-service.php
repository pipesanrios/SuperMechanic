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
	 * Stages where follow-up suggestion should appear when no pending task exists.
	 *
	 * @var array<int,string>
	 */
	const FOLLOW_UP_SUGGESTION_STAGES = array( 'contacted', 'quoted' );

	/**
	 * Days threshold to mark an opportunity as inactive in UI signals.
	 *
	 * @var int
	 */
	const INACTIVITY_DAYS = 7;

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
	 * CRM alert service.
	 *
	 * @var Crm_Alert_Service
	 */
	protected $alert_service;

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
		$this->alert_service      = new Crm_Alert_Service();
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

		$this->maybe_create_initial_follow_up_task( (int) $inserted, $normalized );

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

		$task_delete_result = $this->task_service->delete_tasks_by_pipeline_id( $id );
		if ( is_wp_error( $task_delete_result ) ) {
			return $task_delete_result;
		}

		if ( ! $this->alert_service->resolve_active_alerts_by_pipeline_id( $id ) ) {
			return new WP_Error( 'sm_crm_pipeline_alert_cleanup_failed', __( 'Could not resolve CRM alerts for this opportunity.', 'super-mechanic' ) );
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
		$requires_attention = $this->normalize_boolean_filter( isset( $args['requires_attention'] ) ? $args['requires_attention'] : false );
		$overdue_only       = $this->normalize_boolean_filter( isset( $args['overdue'] ) ? $args['overdue'] : false );
		$rows               = $this->repository->get_all( $args );

		if ( empty( $rows ) || ( ! $requires_attention && ! $overdue_only ) ) {
			return $rows;
		}

		$signals_by_id = $this->get_automation_signals_for_opportunities( $rows );
		$filtered      = array();

		foreach ( $rows as $row ) {
			$id      = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			$signals = ( $id > 0 && isset( $signals_by_id[ $id ] ) ) ? $signals_by_id[ $id ] : $this->get_default_automation_signal_payload();

			$matches_requires_attention = ! $requires_attention || ! empty( $signals['requires_attention'] );
			$matches_overdue           = ! $overdue_only || ! empty( $signals['overdue_task_count'] );

			if ( $matches_requires_attention && $matches_overdue ) {
				$filtered[] = $row;
			}
		}

		return $filtered;
	}

	/**
	 * Count opportunities.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_opportunities( array $args = array() ) {
		$requires_attention = $this->normalize_boolean_filter( isset( $args['requires_attention'] ) ? $args['requires_attention'] : false );
		$overdue_only       = $this->normalize_boolean_filter( isset( $args['overdue'] ) ? $args['overdue'] : false );

		if ( $requires_attention || $overdue_only ) {
			$count_args = $args;
			$count_args['page']     = 1;
			$count_args['per_page'] = 500;

			return count( $this->get_opportunities( $count_args ) );
		}

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
	 * Get automation signals for one opportunity.
	 *
	 * Signals are computed at runtime only (not persisted).
	 *
	 * @param array<string,mixed> $opportunity Opportunity row.
	 * @return array<string,mixed>
	 */
	public function get_opportunity_automation_signals( array $opportunity ) {
		$signals_map = $this->get_automation_signals_for_opportunities( array( $opportunity ) );
		$id          = isset( $opportunity['id'] ) ? absint( $opportunity['id'] ) : 0;

		return ( $id > 0 && isset( $signals_map[ $id ] ) ) ? $signals_map[ $id ] : $this->get_default_automation_signal_payload();
	}

	/**
	 * Get automation signals for list/kanban with aggregate task queries.
	 *
	 * @param array<int,array<string,mixed>> $opportunities Opportunity rows.
	 * @return array<int,array<string,mixed>> Map opportunity_id => signals.
	 */
	public function get_automation_signals_for_opportunities( array $opportunities ) {
		$signals_by_id = array();
		$pipeline_ids  = array();

		foreach ( $opportunities as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id > 0 ) {
				$pipeline_ids[] = $id;
			}
		}

		$pipeline_ids = array_values( array_unique( array_filter( $pipeline_ids ) ) );
		if ( empty( $pipeline_ids ) ) {
			return $signals_by_id;
		}

		$persisted_alerts_map = $this->alert_service->get_active_alerts_by_pipeline_ids( $pipeline_ids );
		$fallback_rows        = array();

		foreach ( $opportunities as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}

			if ( empty( $persisted_alerts_map[ $id ] ) ) {
				$fallback_rows[] = $row;
			}
		}

		$runtime_fallback_map = $this->build_runtime_automation_signals_for_opportunities( $fallback_rows );

		foreach ( $opportunities as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}

			if ( ! empty( $persisted_alerts_map[ $id ] ) ) {
				$signals_by_id[ $id ] = $this->map_persisted_alerts_to_signal_payload( $persisted_alerts_map[ $id ], $row );
				continue;
			}

			$signals_by_id[ $id ] = isset( $runtime_fallback_map[ $id ] )
				? $runtime_fallback_map[ $id ]
				: $this->get_default_automation_signal_payload();
		}

		return $signals_by_id;
	}

	/**
	 * Build runtime automation signals for fallback use.
	 *
	 * @param array<int,array<string,mixed>> $opportunities Opportunity rows.
	 * @return array<int,array<string,mixed>>
	 */
	protected function build_runtime_automation_signals_for_opportunities( array $opportunities ) {
		$signals_by_id = array();
		$pipeline_ids  = array();

		foreach ( $opportunities as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id > 0 ) {
				$pipeline_ids[] = $id;
			}
		}

		$pipeline_ids = array_values( array_unique( array_filter( $pipeline_ids ) ) );
		if ( empty( $pipeline_ids ) ) {
			return $signals_by_id;
		}

		$now_mysql         = current_time( 'mysql' );
		$now_ts            = strtotime( $now_mysql );
		$pending_count_map = $this->task_service->get_pending_counts_by_pipeline_ids( $pipeline_ids );
		$overdue_count_map = $this->task_service->get_overdue_counts_by_pipeline_ids( $pipeline_ids, $now_mysql );
		$task_activity_map = $this->task_service->get_last_activity_by_pipeline_ids( $pipeline_ids );

		foreach ( $opportunities as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id <= 0 ) {
				continue;
			}

			$pending_count = isset( $pending_count_map[ $id ] ) ? absint( $pending_count_map[ $id ] ) : 0;
			$overdue_count = isset( $overdue_count_map[ $id ] ) ? absint( $overdue_count_map[ $id ] ) : 0;
			$stage         = isset( $row['stage'] ) ? sanitize_key( (string) $row['stage'] ) : '';
			$has_process   = ! empty( $row['process_id'] );

			$base_activity_at = '';
			if ( ! empty( $row['updated_at'] ) ) {
				$base_activity_at = sanitize_text_field( (string) $row['updated_at'] );
			} elseif ( ! empty( $row['created_at'] ) ) {
				$base_activity_at = sanitize_text_field( (string) $row['created_at'] );
			}

			$last_activity_at = $base_activity_at;
			if ( isset( $task_activity_map[ $id ] ) && $this->is_datetime_more_recent( $task_activity_map[ $id ], $last_activity_at ) ) {
				$last_activity_at = $task_activity_map[ $id ];
			}

			$inactive_attention = false;
			if ( ! empty( $last_activity_at ) && false !== $now_ts ) {
				$last_activity_ts = strtotime( (string) $last_activity_at );
				if ( false !== $last_activity_ts ) {
					$inactive_attention = ( $now_ts - $last_activity_ts ) > ( self::INACTIVITY_DAYS * DAY_IN_SECONDS );
				}
			}

			$suggest_follow_up = in_array( $stage, self::FOLLOW_UP_SUGGESTION_STAGES, true ) && 0 === $pending_count;
			$conversion_pending = ( 'won' === $stage ) && ! $has_process;
			$requires_attention = $suggest_follow_up || $conversion_pending || ( $overdue_count > 0 ) || $inactive_attention;

			$signals_by_id[ $id ] = array(
				'pending_task_count'    => $pending_count,
				'overdue_task_count'    => $overdue_count,
				'suggest_follow_up'     => $suggest_follow_up,
				'conversion_pending'    => $conversion_pending,
				'inactive_attention'    => $inactive_attention,
				'requires_attention'    => $requires_attention,
				'last_activity_at'      => $last_activity_at,
				'alert_source'          => 'runtime',
				'persisted_alert_types' => array(),
			);
		}

		return $signals_by_id;
	}

	/**
	 * Map persisted active alerts into UI-compatible signal payload.
	 *
	 * @param array<int,array<string,mixed>> $alert_rows Persisted alert rows.
	 * @param array<string,mixed>             $opportunity Opportunity row.
	 * @return array<string,mixed>
	 */
	protected function map_persisted_alerts_to_signal_payload( array $alert_rows, array $opportunity ) {
		$types = array();

		foreach ( $alert_rows as $alert_row ) {
			$alert_type = isset( $alert_row['alert_type'] ) ? sanitize_key( (string) $alert_row['alert_type'] ) : '';
			if ( '' !== $alert_type ) {
				$types[ $alert_type ] = true;
			}
		}

		$overdue_count      = ! empty( $types['overdue_task'] ) ? 1 : 0;
		$suggest_follow_up  = ! empty( $types['follow_up_needed'] );
		$conversion_pending = ! empty( $types['conversion_pending'] );
		$inactive_attention = ! empty( $types['inactive_opportunity'] );
		$requires_attention = ( $overdue_count > 0 ) || $suggest_follow_up || $conversion_pending || $inactive_attention;

		$last_activity_at = '';
		if ( ! empty( $opportunity['updated_at'] ) ) {
			$last_activity_at = sanitize_text_field( (string) $opportunity['updated_at'] );
		} elseif ( ! empty( $opportunity['created_at'] ) ) {
			$last_activity_at = sanitize_text_field( (string) $opportunity['created_at'] );
		}

		return array(
			'pending_task_count'    => 0,
			'overdue_task_count'    => $overdue_count,
			'suggest_follow_up'     => $suggest_follow_up,
			'conversion_pending'    => $conversion_pending,
			'inactive_attention'    => $inactive_attention,
			'requires_attention'    => $requires_attention,
			'last_activity_at'      => $last_activity_at,
			'alert_source'          => 'persisted',
			'persisted_alert_types' => array_values( array_keys( $types ) ),
		);
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

	/**
	 * Create one initial follow-up task on opportunity creation, idempotent.
	 *
	 * Auto-creation is intentionally limited to opportunity creation only.
	 *
	 * @param int                 $opportunity_id Opportunity ID.
	 * @param array<string,mixed> $opportunity    Normalized opportunity payload.
	 * @return void
	 */
	protected function maybe_create_initial_follow_up_task( $opportunity_id, array $opportunity ) {
		$opportunity_id = absint( $opportunity_id );
		if ( $opportunity_id <= 0 ) {
			return;
		}

		// Idempotent guard: if any task already exists, do not auto-create.
		if ( $this->task_service->has_any_task_for_pipeline( $opportunity_id ) ) {
			return;
		}

		$due_at = gmdate( 'Y-m-d H:i:s', strtotime( '+1 day', current_time( 'timestamp', true ) ) );
		$title  = __( 'Initial follow-up', 'super-mechanic' );
		if ( ! empty( $opportunity['title'] ) ) {
			$title = sprintf(
				/* translators: %s opportunity title */
				__( 'Initial follow-up: %s', 'super-mechanic' ),
				sanitize_text_field( (string) $opportunity['title'] )
			);
		}

		$this->task_service->create_task(
			array(
				'crm_pipeline_id'  => $opportunity_id,
				'title'            => $title,
				'task_type'        => 'follow_up',
				'assigned_user_id' => isset( $opportunity['assigned_user_id'] ) ? absint( $opportunity['assigned_user_id'] ) : 0,
				'due_at'           => $due_at,
				'status'           => 'pending',
				'notes'            => __( 'Auto-created on opportunity creation (39D-1).', 'super-mechanic' ),
			)
		);
	}

	/**
	 * Return default automation signal payload.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_default_automation_signal_payload() {
		return array(
			'pending_task_count'    => 0,
			'overdue_task_count'    => 0,
			'suggest_follow_up'     => false,
			'conversion_pending'    => false,
			'inactive_attention'    => false,
			'requires_attention'    => false,
			'last_activity_at'      => '',
			'alert_source'          => 'none',
			'persisted_alert_types' => array(),
		);
	}

	/**
	 * Compare two mysql datetime values and report if A is more recent than B.
	 *
	 * @param string $candidate Candidate datetime.
	 * @param string $baseline  Baseline datetime.
	 * @return bool
	 */
	protected function is_datetime_more_recent( $candidate, $baseline ) {
		$candidate_ts = strtotime( (string) $candidate );
		$baseline_ts  = strtotime( (string) $baseline );

		if ( false === $candidate_ts ) {
			return false;
		}

		if ( false === $baseline_ts ) {
			return true;
		}

		return $candidate_ts > $baseline_ts;
	}

	/**
	 * Normalize boolean-like filter values from query args.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	protected function normalize_boolean_filter( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = sanitize_key( (string) $value );

		return in_array( $value, array( '1', 'yes', 'true', 'on' ), true );
	}
}
