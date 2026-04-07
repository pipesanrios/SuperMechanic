<?php
/**
 * License installer.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Ensures local license table exists.
 */
class License_Installer {
	/**
	 * Get table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'sm_licenses';
	}

	/**
	 * Ensure schema exists.
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
			license_key varchar(191) NOT NULL default '',
			license_status varchar(20) NOT NULL default 'inactive',
			domain varchar(255) NOT NULL default '',
			plan_type varchar(20) NOT NULL default 'starter',
			expires_at datetime DEFAULT NULL,
			activated_at datetime DEFAULT NULL,
			last_checked_at datetime DEFAULT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY license_status (license_status),
			KEY plan_type (plan_type),
			KEY domain (domain)
		) {$charset_collate};";

		dbDelta( $sql );
	}
}

