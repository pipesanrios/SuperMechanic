<?php
/**
 * Execution log repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Persistence access for operational execution logs.
 */
class Execution_Log_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var Execution_Log_Installer
	 */
	protected $installer;

	/**
	 * Constructor.
	 *
	 * @param Execution_Log_Installer|null $installer Installer.
	 */
	public function __construct( Execution_Log_Installer $installer = null ) {
		$this->installer = $installer ? $installer : new Execution_Log_Installer();
		$this->installer->ensure_table();
	}

	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		return $this->installer->get_table_name();
	}

	/**
	 * Insert one execution log row.
	 *
	 * @param array<string,mixed> $data Row payload.
	 * @return int|false
	 */
	public function insert_log( array $data ) {
		global $wpdb;

		$context_json = wp_json_encode( isset( $data['context_json'] ) && is_array( $data['context_json'] ) ? $data['context_json'] : array() );
		if ( false === $context_json ) {
			$context_json = '{}';
		}

		$row = array(
			'business_id'    => isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0,
			'rule_key'       => isset( $data['rule_key'] ) ? sanitize_key( (string) $data['rule_key'] ) : '',
			'action_type'    => isset( $data['action_type'] ) ? sanitize_key( (string) $data['action_type'] ) : '',
			'execution_mode' => isset( $data['execution_mode'] ) ? sanitize_key( (string) $data['execution_mode'] ) : 'manual',
			'result'         => isset( $data['result'] ) ? sanitize_key( (string) $data['result'] ) : 'unknown',
			'affected_count' => isset( $data['affected_count'] ) ? absint( $data['affected_count'] ) : 0,
			'actor_user_id'  => isset( $data['actor_user_id'] ) ? absint( $data['actor_user_id'] ) : 0,
			'context_json'   => (string) $context_json,
			'created_at'     => current_time( 'mysql' ),
		);

		$formats = array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s' );
		$result  = $wpdb->insert( $this->get_table_name(), $row, $formats );
		if ( false === $result ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Count log rows by filters.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @return int
	 */
	public function count_logs( array $filters = array() ) {
		global $wpdb;

		$params = array();
		$where  = $this->build_filters_where_sql( $filters, $params );
		$sql    = "SELECT COUNT(*) FROM {$this->get_table_name()} {$where}";

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params );
		}

		$count = $wpdb->get_var( $sql );
		return absint( $count );
	}

	/**
	 * List log rows by filters with pagination.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @param int                 $page Page number.
	 * @param int                 $per_page Rows per page.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_logs( array $filters = array(), $page = 1, $per_page = 20 ) {
		global $wpdb;

		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 100, absint( $per_page ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$params = array();
		$where  = $this->build_filters_where_sql( $filters, $params );
		$sql    = "SELECT id, business_id, rule_key, action_type, execution_mode, result, affected_count, actor_user_id, context_json, created_at
			FROM {$this->get_table_name()}
			{$where}
			ORDER BY id DESC
			LIMIT %d OFFSET %d";

		$params[] = $per_page;
		$params[] = $offset;
		$sql      = $wpdb->prepare( $sql, $params );

		$rows = $wpdb->get_results( $sql, ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Build filters WHERE SQL.
	 *
	 * @param array<string,mixed> $filters Filters.
	 * @param array<int,mixed>    $params Prepared params by reference.
	 * @return string
	 */
	protected function build_filters_where_sql( array $filters, array &$params ) {
		$where = array();

		$rule_key = isset( $filters['rule_key'] ) ? sanitize_key( (string) $filters['rule_key'] ) : '';
		if ( '' !== $rule_key ) {
			$where[]  = 'rule_key = %s';
			$params[] = $rule_key;
		}

		$result = isset( $filters['result'] ) ? sanitize_key( (string) $filters['result'] ) : '';
		if ( '' !== $result ) {
			$where[]  = 'result = %s';
			$params[] = $result;
		}

		$business_id = isset( $filters['business_id'] ) ? absint( $filters['business_id'] ) : 0;
		if ( $business_id > 0 ) {
			$where[]  = 'business_id = %d';
			$params[] = $business_id;
		}

		$date = isset( $filters['date'] ) ? sanitize_text_field( (string) $filters['date'] ) : '';
		if ( '' !== $date && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			$where[]  = 'DATE(created_at) = %s';
			$params[] = $date;
		}

		return empty( $where ) ? '' : 'WHERE ' . implode( ' AND ', $where );
	}
}
