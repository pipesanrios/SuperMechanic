<?php
/**
 * Business membership repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Data access for business memberships.
 */
class Business_Membership_Repository {
	/**
	 * Allowed operational roles.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_ROLES = array( 'admin', 'mechanic', 'client' );

	/**
	 * Allowed status values.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_STATUS = array( 'active', 'inactive' );

	/**
	 * Installer dependency.
	 *
	 * @var Business_Membership_Installer
	 */
	protected $installer;

	/**
	 * Constructor.
	 *
	 * @param Business_Membership_Installer|null $installer Installer dependency.
	 */
	public function __construct( Business_Membership_Installer $installer = null ) {
		$this->installer = $installer ? $installer : new Business_Membership_Installer();
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
	 * Get membership by id.
	 *
	 * @param int $membership_id Membership ID.
	 * @return array<string,mixed>|null
	 */
	public function get_membership_by_id( $membership_id ) {
		global $wpdb;

		$membership_id = absint( $membership_id );
		if ( $membership_id <= 0 ) {
			return null;
		}

		$sql = "SELECT id, business_id, user_id, operational_role, status, is_primary, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE id = %d
			LIMIT 1";

		$row = $wpdb->get_row( $wpdb->prepare( $sql, $membership_id ), ARRAY_A );
		return is_array( $row ) ? $row : null;
	}

	/**
	 * Get memberships by user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $status Optional status filter.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_user_memberships( $user_id, $status = '' ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$params = array( $user_id );
		$where  = 'WHERE user_id = %d';
		$status = $this->sanitize_status_or_empty( $status );
		if ( '' !== $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql = "SELECT id, business_id, user_id, operational_role, status, is_primary, created_at, updated_at
			FROM {$this->get_table_name()}
			{$where}
			ORDER BY is_primary DESC, business_id ASC, id ASC";

		$sql  = $wpdb->prepare( $sql, $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Get one user primary membership.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>|null
	 */
	public function get_user_primary_membership( $user_id ) {
		global $wpdb;

		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return null;
		}

		$sql = "SELECT id, business_id, user_id, operational_role, status, is_primary, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE user_id = %d AND is_primary = 1
			ORDER BY status = 'active' DESC, id ASC
			LIMIT 1";

		$sql = $wpdb->prepare( $sql, $user_id );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Get one user membership for business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	public function get_user_membership_in_business( $user_id, $business_id ) {
		global $wpdb;

		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		if ( $user_id <= 0 || $business_id <= 0 ) {
			return null;
		}

		$sql = "SELECT id, business_id, user_id, operational_role, status, is_primary, created_at, updated_at
			FROM {$this->get_table_name()}
			WHERE user_id = %d AND business_id = %d
			ORDER BY status = 'active' DESC, is_primary DESC, id ASC
			LIMIT 1";

		$sql = $wpdb->prepare( $sql, $user_id, $business_id );
		$row = $wpdb->get_row( $sql, ARRAY_A );
		if ( ! is_array( $row ) ) {
			return null;
		}

		return $row;
	}

	/**
	 * Get business members.
	 *
	 * @param int    $business_id Business ID.
	 * @param string $status Optional status filter.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_business_members( $business_id, $status = '' ) {
		global $wpdb;

		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array();
		}

		$params = array( $business_id );
		$where  = 'WHERE business_id = %d';
		$status = $this->sanitize_status_or_empty( $status );
		if ( '' !== $status ) {
			$where   .= ' AND status = %s';
			$params[] = $status;
		}

		$sql = "SELECT id, business_id, user_id, operational_role, status, is_primary, created_at, updated_at
			FROM {$this->get_table_name()}
			{$where}
			ORDER BY operational_role ASC, is_primary DESC, user_id ASC, id ASC";

		$sql  = $wpdb->prepare( $sql, $params );
		$rows = $wpdb->get_results( $sql, ARRAY_A );

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Create membership.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $business_id Business ID.
	 * @param string $role Operational role.
	 * @param string $status Membership status.
	 * @param bool   $is_primary Primary flag.
	 * @return int|false
	 */
	public function create_membership( $user_id, $business_id, $role, $status = 'active', $is_primary = false ) {
		global $wpdb;

		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		$role        = $this->sanitize_role_or_empty( $role );
		$status      = $this->sanitize_status_or_empty( $status );

		if ( $user_id <= 0 || $business_id <= 0 || '' === $role || '' === $status ) {
			return false;
		}

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'business_id'      => $business_id,
				'user_id'          => $user_id,
				'operational_role' => $role,
				'status'           => $status,
				'is_primary'       => $is_primary ? 1 : 0,
				'created_at'       => current_time( 'mysql' ),
				'updated_at'       => current_time( 'mysql' ),
			),
			array( '%d', '%d', '%s', '%s', '%d', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return false;
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update membership role.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $role Role.
	 * @return bool
	 */
	public function update_membership_role( $membership_id, $role ) {
		global $wpdb;

		$membership_id = absint( $membership_id );
		$role          = $this->sanitize_role_or_empty( $role );
		if ( $membership_id <= 0 || '' === $role ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'operational_role' => $role,
				'updated_at'       => current_time( 'mysql' ),
			),
			array( 'id' => $membership_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Update membership status.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $status Status.
	 * @return bool
	 */
	public function update_membership_status( $membership_id, $status ) {
		global $wpdb;

		$membership_id = absint( $membership_id );
		$status        = $this->sanitize_status_or_empty( $status );
		if ( $membership_id <= 0 || '' === $status ) {
			return false;
		}

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'status'     => $status,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => $membership_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Mark membership as primary.
	 *
	 * @param int $membership_id Membership ID.
	 * @return bool
	 */
	public function set_primary_membership( $membership_id ) {
		global $wpdb;

		$membership = $this->get_membership_by_id( $membership_id );
		if ( ! is_array( $membership ) ) {
			return false;
		}

		$user_id = isset( $membership['user_id'] ) ? absint( $membership['user_id'] ) : 0;
		if ( $user_id <= 0 ) {
			return false;
		}

		$wpdb->update(
			$this->get_table_name(),
			array(
				'is_primary' => 0,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'user_id' => $user_id ),
			array( '%d', '%s' ),
			array( '%d' )
		);

		$updated = $wpdb->update(
			$this->get_table_name(),
			array(
				'is_primary' => 1,
				'status'     => 'active',
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => absint( $membership_id ) ),
			array( '%d', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Delete membership row.
	 *
	 * @param int $membership_id Membership ID.
	 * @return bool
	 */
	public function delete_membership( $membership_id ) {
		global $wpdb;

		$membership_id = absint( $membership_id );
		if ( $membership_id <= 0 ) {
			return false;
		}

		$deleted = $wpdb->delete(
			$this->get_table_name(),
			array( 'id' => $membership_id ),
			array( '%d' )
		);

		return false !== $deleted;
	}

	/**
	 * Deactivate active memberships for one user.
	 *
	 * @param int $user_id User ID.
	 * @param int $exclude_membership_id Optional membership ID to skip.
	 * @return bool
	 */
	public function deactivate_active_memberships_by_user( $user_id, $exclude_membership_id = 0 ) {
		global $wpdb;

		$user_id               = absint( $user_id );
		$exclude_membership_id = absint( $exclude_membership_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$where = 'user_id = %d AND status = %s';
		$args  = array( $user_id, 'active' );
		if ( $exclude_membership_id > 0 ) {
			$where .= ' AND id != %d';
			$args[] = $exclude_membership_id;
		}

		$sql = "UPDATE {$this->get_table_name()}
			SET status = %s, is_primary = 0, updated_at = %s
			WHERE {$where}";
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- prepared right below with dynamic args.
		$query = $wpdb->prepare(
			$sql,
			array_merge(
				array( 'inactive', current_time( 'mysql' ) ),
				$args
			)
		);

		return false !== $wpdb->query( $query );
	}

	/**
	 * Normalize status filter.
	 *
	 * @param string $status Status value.
	 * @return string
	 */
	protected function sanitize_status_or_empty( $status ) {
		$status = sanitize_key( (string) $status );
		if ( in_array( $status, self::ALLOWED_STATUS, true ) ) {
			return $status;
		}

		return '';
	}

	/**
	 * Normalize role filter.
	 *
	 * @param string $role Role value.
	 * @return string
	 */
	protected function sanitize_role_or_empty( $role ) {
		$role = sanitize_key( (string) $role );
		if ( in_array( $role, self::ALLOWED_ROLES, true ) ) {
			return $role;
		}

		return '';
	}
}
