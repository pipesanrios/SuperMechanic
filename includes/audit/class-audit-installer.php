<?php
/**
 * Audit installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures deep audit table exists.
 */
class Audit_Installer {
	/**
	 * Get audit table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_audit_log';
	}

	/**
	 * Ensure audit table schema.
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
			audit_type varchar(50) NOT NULL,
			entity_type varchar(80) NOT NULL,
			entity_id bigint(20) unsigned NOT NULL default 0,
			action varchar(30) NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL default 0,
			business_id bigint(20) unsigned NOT NULL default 1,
			before_json longtext DEFAULT NULL,
			after_json longtext DEFAULT NULL,
			context_json longtext DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY audit_type (audit_type),
			KEY entity_type (entity_type),
			KEY entity_id (entity_id),
			KEY action (action),
			KEY actor_user_id (actor_user_id),
			KEY business_id (business_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

