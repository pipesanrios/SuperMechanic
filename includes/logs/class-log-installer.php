<?php
/**
 * Log installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Logs;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures structured logs table exists.
 */
class Log_Installer {
	/**
	 * Get log table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_logs';
	}

	/**
	 * Ensure log table schema.
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
			log_type varchar(50) NOT NULL,
			source varchar(80) NOT NULL,
			reference_id bigint(20) unsigned NOT NULL default 0,
			status varchar(20) NOT NULL default 'info',
			message varchar(255) NOT NULL,
			context_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY business_id (business_id),
			KEY log_type (log_type),
			KEY source (source),
			KEY status (status),
			KEY reference_id (reference_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

