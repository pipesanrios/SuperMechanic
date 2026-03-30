<?php
/**
 * CRM task service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM task business logic.
 */
class Crm_Task_Service {
	/**
	 * Allowed statuses.
	 *
	 * @var array<int, string>
	 */
	const STATUSES = array( 'pending', 'completed', 'cancelled' );

	/**
	 * Allowed task types.
	 *
	 * @var array<int, string>
	 */
	const TASK_TYPES = array( 'call', 'follow_up', 'meeting', 'quote', 'reminder' );

	/**
	 * Repository.
	 *
	 * @var Crm_Task_Repository
	 */
	protected $repository;

	/**
	 * Pipeline repository.
	 *
	 * @var Crm_Pipeline_Repository
	 */
	protected $pipeline_repository;

	/**
	 * Constructor.
	 *
	 * @param Crm_Task_Repository|null $repository Repository.
	 */
	public function __construct( Crm_Task_Repository $repository = null ) {
		$this->repository          = $repository ? $repository : new Crm_Task_Repository();
		$this->pipeline_repository = new Crm_Pipeline_Repository();
	}

	/**
	 * Create one task.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return int|WP_Error
	 */
	public function create_task( array $data ) {
		$normalized = $this->normalize_data( $data );
		$valid      = $this->validate_data( $normalized );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $normalized );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_crm_task_insert_failed', __( 'Could not create CRM task.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update one task.
	 *
	 * @param int                  $id Task ID.
	 * @param array<string, mixed> $data Payload.
	 * @return bool|WP_Error
	 */
	public function update_task( $id, array $data ) {
		$id       = absint( $id );
		$existing = $this->repository->get_by_id( $id );

		if ( empty( $existing ) ) {
			return new WP_Error( 'sm_crm_task_not_found', __( 'CRM task does not exist.', 'super-mechanic' ) );
		}

		$normalized = $this->normalize_data(
			array_merge(
				$existing,
				$data
			)
		);
		$valid      = $this->validate_data( $normalized );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update( $id, $normalized ) ) {
			return new WP_Error( 'sm_crm_task_update_failed', __( 'Could not update CRM task.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Complete one task.
	 *
	 * @param int $id Task ID.
	 * @return bool|WP_Error
	 */
	public function complete_task( $id ) {
		$id       = absint( $id );
		$existing = $this->repository->get_by_id( $id );

		if ( empty( $existing ) ) {
			return new WP_Error( 'sm_crm_task_not_found', __( 'CRM task does not exist.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->complete( $id ) ) {
			return new WP_Error( 'sm_crm_task_complete_failed', __( 'Could not mark CRM task as completed.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete task.
	 *
	 * @param int $id Task ID.
	 * @return bool|WP_Error
	 */
	public function delete_task( $id ) {
		$id       = absint( $id );
		$existing = $this->repository->get_by_id( $id );

		if ( empty( $existing ) ) {
			return new WP_Error( 'sm_crm_task_not_found', __( 'CRM task does not exist.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_crm_task_delete_failed', __( 'Could not delete CRM task.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get one task.
	 *
	 * @param int $id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function get_task( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get tasks for one opportunity.
	 *
	 * @param int $crm_pipeline_id Opportunity ID.
	 * @return array<int, array<string, mixed>>|WP_Error
	 */
	public function get_tasks_by_pipeline_id( $crm_pipeline_id ) {
		$crm_pipeline_id = absint( $crm_pipeline_id );

		if ( $crm_pipeline_id <= 0 ) {
			return new WP_Error( 'sm_crm_task_pipeline_required', __( 'CRM opportunity is required.', 'super-mechanic' ) );
		}

		if ( ! $this->pipeline_repository->get_by_id( $crm_pipeline_id ) ) {
			return new WP_Error( 'sm_crm_task_invalid_pipeline', __( 'CRM opportunity is not valid for the active business.', 'super-mechanic' ) );
		}

		return $this->repository->get_by_pipeline_id( $crm_pipeline_id );
	}

	/**
	 * Get status catalog.
	 *
	 * @return array<int, string>
	 */
	public function get_status_catalog() {
		return self::STATUSES;
	}

	/**
	 * Get task type catalog.
	 *
	 * @return array<int, string>
	 */
	public function get_task_type_catalog() {
		return self::TASK_TYPES;
	}

	/**
	 * Get operational CRM task buckets.
	 *
	 * Overdue and upcoming are operational subsets of pending.
	 *
	 * @param int $upcoming_days Upcoming window in days.
	 * @param int $limit         Max rows per bucket.
	 * @return array<string, mixed>
	 */
	public function get_operational_buckets( $upcoming_days = 7, $limit = 10 ) {
		$upcoming_days = max( 1, absint( $upcoming_days ) );
		$limit         = max( 1, absint( $limit ) );

		$now_ts   = current_time( 'timestamp', true );
		$until_ts = strtotime( '+' . $upcoming_days . ' days', $now_ts );
		if ( false === $until_ts ) {
			$until_ts = $now_ts;
		}

		$now_mysql   = gmdate( 'Y-m-d H:i:s', $now_ts );
		$until_mysql = gmdate( 'Y-m-d H:i:s', $until_ts );

		return array(
			'pending'  => array(
				'count' => $this->repository->count_pending_tasks(),
				'items' => $this->repository->get_pending_tasks( $limit ),
			),
			'overdue'  => array(
				'count' => $this->repository->count_overdue_tasks( $now_mysql ),
				'items' => $this->repository->get_overdue_tasks( $now_mysql, $limit ),
			),
			'upcoming' => array(
				'count' => $this->repository->count_upcoming_tasks( $now_mysql, $until_mysql ),
				'items' => $this->repository->get_upcoming_tasks( $now_mysql, $until_mysql, $limit ),
			),
			'meta'     => array(
				'upcoming_days' => $upcoming_days,
				'now'           => $now_mysql,
				'until'         => $until_mysql,
			),
		);
	}

	/**
	 * Get tasks for calendar visible range.
	 *
	 * By default returns pending tasks with due date.
	 *
	 * @param string            $start_iso Range start.
	 * @param string            $end_iso   Range end.
	 * @param array<int,string> $statuses  Allowed statuses.
	 * @param int               $limit     Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_tasks_for_calendar( $start_iso, $end_iso, array $statuses = array( 'pending' ), $limit = 500 ) {
		$start_mysql = $this->normalize_datetime_to_mysql( (string) $start_iso );
		$end_mysql   = $this->normalize_datetime_to_mysql( (string) $end_iso );

		if ( '' === $start_mysql ) {
			$start_mysql = gmdate( 'Y-m-d 00:00:00' );
		}

		if ( '' === $end_mysql ) {
			$end_mysql = gmdate( 'Y-m-d 23:59:59', strtotime( '+35 days', strtotime( $start_mysql ) ) );
		}

		$validated_statuses = array_values(
			array_filter(
				array_map(
					'sanitize_key',
					$statuses
				),
				function ( $status ) {
					return in_array( $status, self::STATUSES, true );
				}
			)
		);

		if ( empty( $validated_statuses ) ) {
			$validated_statuses = array( 'pending' );
		}

		return $this->repository->get_for_calendar_range( $start_mysql, $end_mysql, $validated_statuses, $limit );
	}

	/**
	 * Normalize payload.
	 *
	 * @param array<string, mixed> $data Raw data.
	 * @return array<string, mixed>
	 */
	protected function normalize_data( array $data ) {
		return array(
			'crm_pipeline_id'  => isset( $data['crm_pipeline_id'] ) ? absint( $data['crm_pipeline_id'] ) : 0,
			'title'            => isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : '',
			'task_type'        => isset( $data['task_type'] ) ? sanitize_key( (string) $data['task_type'] ) : 'follow_up',
			'assigned_user_id' => ! empty( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : 0,
			'due_at'           => $this->normalize_due_at( isset( $data['due_at'] ) ? (string) $data['due_at'] : '' ),
			'status'           => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : 'pending',
			'notes'            => isset( $data['notes'] ) ? sanitize_textarea_field( (string) $data['notes'] ) : '',
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

		if ( empty( $data['crm_pipeline_id'] ) ) {
			$errors->add( 'crm_pipeline_required', __( 'CRM opportunity is required.', 'super-mechanic' ) );
		} elseif ( ! $this->pipeline_repository->get_by_id( $data['crm_pipeline_id'] ) ) {
			$errors->add( 'invalid_crm_pipeline', __( 'CRM opportunity is not valid for the active business.', 'super-mechanic' ) );
		}

		if ( '' === trim( (string) $data['title'] ) ) {
			$errors->add( 'title_required', __( 'Task title is required.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['status'], self::STATUSES, true ) ) {
			$errors->add( 'invalid_status', __( 'Task status is not valid.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['task_type'], self::TASK_TYPES, true ) ) {
			$errors->add( 'invalid_task_type', __( 'Task type is not valid.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['assigned_user_id'] ) && ! get_userdata( absint( $data['assigned_user_id'] ) ) ) {
			$errors->add( 'invalid_assigned_user', __( 'Assigned user is not valid.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Normalize due date value.
	 *
	 * @param string $due_at Raw due date.
	 * @return string|null
	 */
	protected function normalize_due_at( $due_at ) {
		$due_at = trim( sanitize_text_field( $due_at ) );

		if ( '' === $due_at ) {
			return null;
		}

		$normalized = str_replace( 'T', ' ', $due_at );

		if ( strlen( $normalized ) === 16 ) {
			$normalized .= ':00';
		}

		$timestamp = strtotime( $normalized );
		if ( false === $timestamp ) {
			return null;
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Normalize any datetime string to mysql datetime.
	 *
	 * @param string $value Raw datetime.
	 * @return string
	 */
	protected function normalize_datetime_to_mysql( $value ) {
		$value = trim( sanitize_text_field( (string) $value ) );
		if ( '' === $value ) {
			return '';
		}

		$timestamp = strtotime( $value );
		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}
}
