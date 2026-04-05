<?php
/**
 * Business membership installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures business membership table exists.
 */
class Business_Membership_Installer {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'sm_business_user_roles';
	}

	/**
	 * Create/update table.
	 *
	 * @return void
	 */
	public function ensure_table() {
		global $wpdb;

		$table_name = $this->get_table_name();

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			business_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			operational_role varchar(20) NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			is_primary tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY business_user_role (business_id,user_id,operational_role),
			KEY business_id (business_id),
			KEY user_id (user_id),
			KEY operational_role (operational_role),
			KEY status (status),
			KEY user_primary_status (user_id,is_primary,status),
			KEY business_status_role (business_id,status,operational_role)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

