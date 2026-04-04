<?php
/**
 * Execution log installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures operational execution log table exists.
 */
class Execution_Log_Installer {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'sm_execution_logs';
	}

	/**
	 * Create table if missing.
	 *
	 * @return void
	 */
	public function ensure_table() {
		global $wpdb;

		$table_name = $this->get_table_name();
		$existing   = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$table_name
			)
		);

		if ( $existing === $table_name ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			business_id bigint(20) unsigned NOT NULL,
			rule_key varchar(100) NOT NULL DEFAULT '',
			action_type varchar(50) NOT NULL,
			execution_mode varchar(20) NOT NULL DEFAULT 'manual',
			result varchar(20) NOT NULL,
			affected_count int(11) NOT NULL DEFAULT 0,
			actor_user_id bigint(20) unsigned NOT NULL,
			context_json longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY business_id (business_id),
			KEY rule_key (rule_key),
			KEY action_type (action_type),
			KEY execution_mode (execution_mode),
			KEY result (result),
			KEY actor_user_id (actor_user_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

