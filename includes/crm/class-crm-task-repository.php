<?php
/**
 * CRM task repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\CRM;

use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles CRM task persistence.
 */
class Crm_Task_Repository {
	/**
	 * Get CRM tasks table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		$tables = Schema::get_tables();

		return $tables['crm_tasks'];
	}

	/**
	 * Get one task by id scoped to active business.
	 *
	 * @param int $id Task ID.
	 * @return array<string, mixed>|null
	 */
	public function get_by_id( $id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id = %d AND business_id = %d LIMIT 1",
			absint( $id ),
			$this->resolve_business_id()
		);
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get tasks by CRM opportunity id scoped to active business.
	 *
	 * @param int $crm_pipeline_id CRM opportunity ID.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_by_pipeline_id( $crm_pipeline_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE business_id = %d AND crm_pipeline_id = %d ORDER BY due_at ASC, id DESC",
			$this->resolve_business_id(),
			absint( $crm_pipeline_id )
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Insert task.
	 *
	 * @param array<string, mixed> $data Task payload.
	 * @return int|false
	 */
	public function insert( array $data ) {
		global $wpdb;

		$now                 = current_time( 'mysql' );
		$data['business_id'] = $this->resolve_business_id();
		$data['created_at']  = $now;
		$data['updated_at']  = $now;

		$result = $wpdb->insert( $this->get_table_name(), $data, $this->get_formats_for_data( $data ) );

		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update task by id scoped to active business.
	 *
	 * @param int                 $id Task ID.
	 * @param array<string, mixed> $data Task payload.
	 * @return bool
	 */
	public function update( $id, array $data ) {
		global $wpdb;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->get_table_name(),
			$data,
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			$this->get_formats_for_data( $data ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete task by id scoped to active business.
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public function delete( $id ) {
		global $wpdb;

		$result = $wpdb->delete(
			$this->get_table_name(),
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Mark task as completed scoped to active business.
	 *
	 * @param int $id Task ID.
	 * @return bool
	 */
	public function complete( $id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => 'completed',
				'updated_at' => current_time( 'mysql' ),
			),
			array(
				'id'          => absint( $id ),
				'business_id' => $this->resolve_business_id(),
			),
			array( '%s', '%s' ),
			array( '%d', '%d' )
		);

		return false !== $result;
	}

	/**
	 * Get pending tasks for active business.
	 *
	 * @param int $limit Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_pending_tasks( $limit = 20 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
			ORDER BY
				CASE WHEN due_at IS NULL THEN 1 ELSE 0 END ASC,
				due_at ASC,
				id DESC
			LIMIT %d",
			$this->resolve_business_id(),
			'pending',
			$limit
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get overdue tasks for active business.
	 *
	 * @param string $now_mysql Current datetime in mysql format.
	 * @param int    $limit     Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_overdue_tasks( $now_mysql, $limit = 20 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND due_at IS NOT NULL
				AND due_at < %s
			ORDER BY due_at ASC, id DESC
			LIMIT %d",
			$this->resolve_business_id(),
			'pending',
			sanitize_text_field( (string) $now_mysql ),
			$limit
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get upcoming tasks for active business.
	 *
	 * @param string $now_mysql   Current datetime in mysql format.
	 * @param string $until_mysql End datetime in mysql format.
	 * @param int    $limit       Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_upcoming_tasks( $now_mysql, $until_mysql, $limit = 20 ) {
		global $wpdb;

		$limit = max( 1, absint( $limit ) );
		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND due_at IS NOT NULL
				AND due_at >= %s
				AND due_at <= %s
			ORDER BY due_at ASC, id DESC
			LIMIT %d",
			$this->resolve_business_id(),
			'pending',
			sanitize_text_field( (string) $now_mysql ),
			sanitize_text_field( (string) $until_mysql ),
			$limit
		);
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count pending tasks.
	 *
	 * @return int
	 */
	public function count_pending_tasks() {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$this->get_table_name()} WHERE business_id = %d AND status = %s",
			$this->resolve_business_id(),
			'pending'
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Count overdue tasks.
	 *
	 * @param string $now_mysql Current datetime in mysql format.
	 * @return int
	 */
	public function count_overdue_tasks( $now_mysql ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND due_at IS NOT NULL
				AND due_at < %s",
			$this->resolve_business_id(),
			'pending',
			sanitize_text_field( (string) $now_mysql )
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Count upcoming tasks.
	 *
	 * @param string $now_mysql   Current datetime in mysql format.
	 * @param string $until_mysql End datetime in mysql format.
	 * @return int
	 */
	public function count_upcoming_tasks( $now_mysql, $until_mysql ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT COUNT(id) FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND due_at IS NOT NULL
				AND due_at >= %s
				AND due_at <= %s",
			$this->resolve_business_id(),
			'pending',
			sanitize_text_field( (string) $now_mysql ),
			sanitize_text_field( (string) $until_mysql )
		);

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get tasks by visible calendar range for active business.
	 *
	 * Default usage is pending tasks with due date.
	 *
	 * @param string           $start_mysql Range start in mysql datetime.
	 * @param string           $end_mysql   Range end in mysql datetime.
	 * @param array<int,string> $statuses    Allowed statuses.
	 * @param int              $limit       Max rows.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_for_calendar_range( $start_mysql, $end_mysql, array $statuses = array( 'pending' ), $limit = 500 ) {
		global $wpdb;

		$start_mysql = sanitize_text_field( (string) $start_mysql );
		$end_mysql   = sanitize_text_field( (string) $end_mysql );
		$limit       = max( 1, absint( $limit ) );
		$statuses    = array_values( array_unique( array_filter( array_map( 'sanitize_key', $statuses ) ) ) );

		if ( empty( $statuses ) ) {
			$statuses = array( 'pending' );
		}

		$in_placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params          = array_merge(
			array(
				$this->resolve_business_id(),
				$start_mysql,
				$end_mysql,
			),
			$statuses,
			array( $limit )
		);

		$sql = "SELECT *
			FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND due_at IS NOT NULL
				AND due_at >= %s
				AND due_at <= %s
				AND status IN ({$in_placeholders})
			ORDER BY due_at ASC, id DESC
			LIMIT %d";

		$query = $wpdb->prepare( $sql, $params );
		$rows  = $wpdb->get_results( $query, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Resolve active business id.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		$context_service = new Business_Context_Service();

		return absint( $context_service->resolve_business_id() );
	}

	/**
	 * Build wpdb formats dynamically by payload keys.
	 *
	 * @param array<string, mixed> $data Payload.
	 * @return array<int, string>
	 */
	protected function get_formats_for_data( array $data ) {
		$format_map = array(
			'business_id'      => '%d',
			'crm_pipeline_id'  => '%d',
			'title'            => '%s',
			'task_type'        => '%s',
			'assigned_user_id' => '%d',
			'due_at'           => '%s',
			'status'           => '%s',
			'notes'            => '%s',
			'created_at'       => '%s',
			'updated_at'       => '%s',
		);
		$formats    = array();

		foreach ( array_keys( $data ) as $key ) {
			if ( isset( $format_map[ $key ] ) ) {
				$formats[] = $format_map[ $key ];
			}
		}

		return $formats;
	}
}
