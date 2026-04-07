<?php
/**
 * License repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Licensing;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for local license state.
 */
class License_Repository {
	/**
	 * Installer dependency.
	 *
	 * @var License_Installer
	 */
	protected $installer;

	/**
	 * Constructor.
	 *
	 * @param License_Installer|null $installer Installer dependency.
	 */
	public function __construct( License_Installer $installer = null ) {
		$this->installer = $installer ? $installer : new License_Installer();
		$this->installer->ensure_table();
	}

	/**
	 * Get latest license row.
	 *
	 * @return array<string,mixed>|null
	 */
	public function get_license() {
		global $wpdb;

		$query = "SELECT id, license_key, license_status, domain, plan_type, expires_at, activated_at, last_checked_at, created_at, updated_at
			FROM {$this->installer->get_table_name()}
			ORDER BY id DESC
			LIMIT 1";
		$row   = $wpdb->get_row( $query, ARRAY_A );

		return is_array( $row ) ? $row : null;
	}

	/**
	 * Create or update current license row.
	 *
	 * @param array<string,mixed> $data License payload.
	 * @return bool
	 */
	public function save_license( array $data ) {
		global $wpdb;

		$current = $this->get_license();
		$now     = current_time( 'mysql' );
		$row     = array(
			'license_key'    => isset( $data['license_key'] ) ? (string) $data['license_key'] : '',
			'license_status' => isset( $data['license_status'] ) ? (string) $data['license_status'] : 'inactive',
			'domain'         => isset( $data['domain'] ) ? (string) $data['domain'] : '',
			'plan_type'      => isset( $data['plan_type'] ) ? (string) $data['plan_type'] : 'starter',
			'expires_at'     => isset( $data['expires_at'] ) ? (string) $data['expires_at'] : null,
			'activated_at'   => isset( $data['activated_at'] ) ? (string) $data['activated_at'] : null,
			'last_checked_at'=> isset( $data['last_checked_at'] ) ? (string) $data['last_checked_at'] : null,
			'updated_at'     => $now,
		);
		$formats = array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' );

		if ( is_array( $current ) && isset( $current['id'] ) ) {
			$updated = $wpdb->update(
				$this->installer->get_table_name(),
				$row,
				array( 'id' => absint( $current['id'] ) ),
				$formats,
				array( '%d' )
			);
			return false !== $updated;
		}

		$row['created_at'] = $now;
		$inserted          = $wpdb->insert(
			$this->installer->get_table_name(),
			$row,
			array_merge( $formats, array( '%s' ) )
		);

		return false !== $inserted;
	}
}

