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
	 * Count pending tasks grouped by CRM opportunity ids.
	 *
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @return array<int,int> Map pipeline_id => pending_count.
	 */
	public function count_pending_by_pipeline_ids( array $pipeline_ids ) {
		return $this->count_by_pipeline_ids_and_status_for_business( $this->resolve_business_id(), $pipeline_ids, 'pending' );
	}

	/**
	 * Count overdue pending tasks grouped by CRM opportunity ids.
	 *
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @param string         $now_mysql    Current datetime in mysql format.
	 * @return array<int,int> Map pipeline_id => overdue_count.
	 */
	public function count_overdue_by_pipeline_ids( array $pipeline_ids, $now_mysql ) {
		return $this->count_overdue_by_pipeline_ids_for_business( $this->resolve_business_id(), $pipeline_ids, $now_mysql );
	}

	/**
	 * Count overdue pending tasks grouped by CRM opportunity ids for explicit business.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @param string         $now_mysql    Current datetime in mysql format.
	 * @return array<int,int> Map pipeline_id => overdue_count.
	 */
	public function count_overdue_by_pipeline_ids_for_business( $business_id, array $pipeline_ids, $now_mysql ) {
		global $wpdb;

		$pipeline_ids = $this->sanitize_pipeline_ids( $pipeline_ids );
		if ( empty( $pipeline_ids ) ) {
			return array();
		}

		$business_id      = absint( $business_id );
		$id_placeholders  = implode( ',', array_fill( 0, count( $pipeline_ids ), '%d' ) );
		$sql              = "SELECT crm_pipeline_id, COUNT(id) AS task_count
			FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND due_at IS NOT NULL
				AND due_at < %s
				AND crm_pipeline_id IN ({$id_placeholders})
			GROUP BY crm_pipeline_id";
		$params           = array_merge(
			array(
				$business_id,
				'pending',
				sanitize_text_field( (string) $now_mysql ),
			),
			$pipeline_ids
		);
		$query            = $wpdb->prepare( $sql, $params );
		$rows             = $wpdb->get_results( $query, ARRAY_A );
		$counts_by_id     = array_fill_keys( $pipeline_ids, 0 );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$pipeline_id = isset( $row['crm_pipeline_id'] ) ? absint( $row['crm_pipeline_id'] ) : 0;
				if ( $pipeline_id > 0 ) {
					$counts_by_id[ $pipeline_id ] = isset( $row['task_count'] ) ? absint( $row['task_count'] ) : 0;
				}
			}
		}

		return $counts_by_id;
	}

	/**
	 * Get latest task activity grouped by CRM opportunity ids.
	 *
	 * Activity is the max between updated_at and created_at.
	 *
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @return array<int,string> Map pipeline_id => latest mysql datetime.
	 */
	public function get_last_activity_by_pipeline_ids( array $pipeline_ids ) {
		return $this->get_last_activity_by_pipeline_ids_for_business( $this->resolve_business_id(), $pipeline_ids );
	}

	/**
	 * Get latest task activity grouped by CRM opportunity ids for explicit business.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @return array<int,string> Map pipeline_id => latest mysql datetime.
	 */
	public function get_last_activity_by_pipeline_ids_for_business( $business_id, array $pipeline_ids ) {
		global $wpdb;

		$pipeline_ids = $this->sanitize_pipeline_ids( $pipeline_ids );
		if ( empty( $pipeline_ids ) ) {
			return array();
		}

		$business_id     = absint( $business_id );
		$id_placeholders = implode( ',', array_fill( 0, count( $pipeline_ids ), '%d' ) );
		$sql             = "SELECT crm_pipeline_id, MAX(COALESCE(updated_at, created_at)) AS last_activity_at
			FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND crm_pipeline_id IN ({$id_placeholders})
			GROUP BY crm_pipeline_id";
		$params          = array_merge( array( $business_id ), $pipeline_ids );
		$query           = $wpdb->prepare( $sql, $params );
		$rows            = $wpdb->get_results( $query, ARRAY_A );
		$activity_by_id  = array();

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$pipeline_id = isset( $row['crm_pipeline_id'] ) ? absint( $row['crm_pipeline_id'] ) : 0;
				if ( $pipeline_id > 0 && ! empty( $row['last_activity_at'] ) ) {
					$activity_by_id[ $pipeline_id ] = sanitize_text_field( (string) $row['last_activity_at'] );
				}
			}
		}

		return $activity_by_id;
	}

	/**
	 * Count pending tasks grouped by CRM opportunity ids for explicit business.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @return array<int,int> Map pipeline_id => pending_count.
	 */
	public function count_pending_by_pipeline_ids_for_business( $business_id, array $pipeline_ids ) {
		return $this->count_by_pipeline_ids_and_status_for_business( $business_id, $pipeline_ids, 'pending' );
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
	 * Reassign one CRM task between users in active business context.
	 *
	 * @param int $id           Task ID.
	 * @param int $from_user_id Current assigned user ID.
	 * @param int $to_user_id   Destination user ID.
	 * @return bool
	 */
	public function reassign_task( $id, $from_user_id, $to_user_id ) {
		global $wpdb;

		$result = $wpdb->update(
			$this->get_table_name(),
			array(
				'assigned_user_id' => absint( $to_user_id ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array(
				'id'               => absint( $id ),
				'business_id'      => $this->resolve_business_id(),
				'assigned_user_id' => absint( $from_user_id ),
				'status'           => 'pending',
			),
			array( '%d', '%s' ),
			array( '%d', '%d', '%d', '%s' )
		);

		return false !== $result && $result > 0;
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

	/**
	 * Count tasks by pipeline ids and one status.
	 *
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @param string         $status       Status key.
	 * @return array<int,int> Map pipeline_id => count.
	 */
	protected function count_by_pipeline_ids_and_status( array $pipeline_ids, $status ) {
		return $this->count_by_pipeline_ids_and_status_for_business( $this->resolve_business_id(), $pipeline_ids, $status );
	}

	/**
	 * Count tasks by pipeline ids and status for explicit business.
	 *
	 * @param int            $business_id Business ID.
	 * @param array<int,int> $pipeline_ids Opportunity IDs.
	 * @param string         $status Status key.
	 * @return array<int,int> Map pipeline_id => count.
	 */
	protected function count_by_pipeline_ids_and_status_for_business( $business_id, array $pipeline_ids, $status ) {
		global $wpdb;

		$pipeline_ids = $this->sanitize_pipeline_ids( $pipeline_ids );
		if ( empty( $pipeline_ids ) ) {
			return array();
		}

		$business_id     = absint( $business_id );
		$id_placeholders = implode( ',', array_fill( 0, count( $pipeline_ids ), '%d' ) );
		$sql             = "SELECT crm_pipeline_id, COUNT(id) AS task_count
			FROM {$this->get_table_name()}
			WHERE business_id = %d
				AND status = %s
				AND crm_pipeline_id IN ({$id_placeholders})
			GROUP BY crm_pipeline_id";
		$params          = array_merge(
			array(
				$business_id,
				sanitize_key( (string) $status ),
			),
			$pipeline_ids
		);
		$query           = $wpdb->prepare( $sql, $params );
		$rows            = $wpdb->get_results( $query, ARRAY_A );
		$counts_by_id    = array_fill_keys( $pipeline_ids, 0 );

		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$pipeline_id = isset( $row['crm_pipeline_id'] ) ? absint( $row['crm_pipeline_id'] ) : 0;
				if ( $pipeline_id > 0 ) {
					$counts_by_id[ $pipeline_id ] = isset( $row['task_count'] ) ? absint( $row['task_count'] ) : 0;
				}
			}
		}

		return $counts_by_id;
	}

	/**
	 * Sanitize and de-duplicate opportunity ids.
	 *
	 * @param array<int,int> $pipeline_ids Raw IDs.
	 * @return array<int,int>
	 */
	protected function sanitize_pipeline_ids( array $pipeline_ids ) {
		$pipeline_ids = array_values( array_unique( array_filter( array_map( 'absint', $pipeline_ids ) ) ) );

		return $pipeline_ids;
	}
}
