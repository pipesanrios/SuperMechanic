<?php
/**
 * Operational rules installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Automation;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures persistent rules table exists.
 */
class Operational_Rules_Installer {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'sm_operational_rules';
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
			rule_key varchar(100) NOT NULL,
			enabled tinyint(1) NOT NULL DEFAULT 1,
			execution_mode varchar(20) NOT NULL DEFAULT 'manual',
			thresholds_json longtext NULL,
			limits_json longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY business_rule (business_id, rule_key),
			KEY business_id (business_id),
			KEY rule_key (rule_key),
			KEY execution_mode (execution_mode)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}
