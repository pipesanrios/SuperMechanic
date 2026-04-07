<?php
/**
 * Queue installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Queue;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures queue table exists.
 */
class Queue_Installer {
	/**
	 * Get queue table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_queue';
	}

	/**
	 * Ensure queue table schema.
	 *
	 * @return void
	 */
	public function ensure_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL auto_increment,
			business_id bigint(20) unsigned NOT NULL default 1,
			job_type varchar(50) NOT NULL,
			payload longtext NOT NULL,
			status varchar(20) NOT NULL default 'pending',
			attempts int(11) NOT NULL default 0,
			max_attempts int(11) NOT NULL default 3,
			next_retry_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			processed_at datetime DEFAULT NULL,
			last_error text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY business_id (business_id),
			KEY job_type (job_type),
			KEY status (status),
			KEY next_retry_at (next_retry_at),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
