<?php
/**
 * Role and access summary service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds role/access summaries and applies basic operational role updates.
 */
class Role_Access_Service {
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Business_Context_Service $business_context_service = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Get users access summary rows.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_role_access_rows() {
		$users = get_users(
			array(
				'orderby' => 'ID',
				'order'   => 'ASC',
				'fields'  => 'all',
				'number'  => 500,
			)
		);

		if ( ! is_array( $users ) ) {
			return array();
		}

		$rows = array();
		foreach ( $users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$user_roles       = array_map( 'sanitize_key', (array) $user->roles );
			$operational_role = $this->detect_operational_role( $user_roles, $user );
			$business_id      = absint( $this->business_context_service->resolve_business_id_for_user( absint( $user->ID ) ) );
			$dashboard_access = $this->has_dashboard_access( $user_roles, $user );
			$automation_access = user_can( $user, 'sm_manage_plugin' );
			$warnings         = $this->detect_access_warnings( $user_roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user );

			$rows[] = array(
				'user_id'            => absint( $user->ID ),
				'display_name'       => sanitize_text_field( $user->display_name ),
				'user_email'         => sanitize_email( $user->user_email ),
				'wp_roles'           => $user_roles,
				'operational_role'   => $operational_role,
				'business_id'        => $business_id,
				'dashboard_access'   => $dashboard_access,
				'automation_access'  => $automation_access,
				'warning_keys'       => $warnings,
				'status'             => empty( $warnings ) ? 'ok' : 'warning',
				'status_summary'     => $this->build_warning_summary( $warnings ),
			);
		}

		return $rows;
	}

	/**
	 * Assign one operational role to user.
	 *
	 * @param int    $user_id User ID.
	 * @param string $role_key Target role.
	 * @return array<string,mixed>
	 */
	public function assign_operational_role( $user_id, $role_key ) {
		$user_id  = absint( $user_id );
		$role_key = sanitize_key( (string) $role_key );
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user ID.', 'super-mechanic' ),
			);
		}

		if ( ! in_array( $role_key, array( 'sm_admin', 'sm_mechanic' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid operational role.', 'super-mechanic' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

		$user->remove_role( 'sm_admin' );
		$user->remove_role( 'sm_mechanic' );
		$user->add_role( $role_key );

		return array(
			'success' => true,
			'message' => sprintf( __( 'Operational role updated to %s.', 'super-mechanic' ), $role_key ),
		);
	}

	/**
	 * Remove operational role from user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function remove_operational_role( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user ID.', 'super-mechanic' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

		$user->remove_role( 'sm_admin' );
		$user->remove_role( 'sm_mechanic' );

		return array(
			'success' => true,
			'message' => __( 'Operational role removed.', 'super-mechanic' ),
		);
	}

	/**
	 * Detect operational role for one user.
	 *
	 * @param array<int,string> $roles Roles.
	 * @param \WP_User          $user User.
	 * @return string
	 */
	protected function detect_operational_role( array $roles, $user ) {
		if ( in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' ) ) {
			return 'administrator';
		}
		if ( in_array( 'sm_admin', $roles, true ) ) {
			return 'sm_admin';
		}
		if ( in_array( 'sm_mechanic', $roles, true ) ) {
			return 'sm_mechanic';
		}
		if ( in_array( 'sm_client', $roles, true ) ) {
			return 'sm_client';
		}

		return 'none';
	}

	/**
	 * Determine if one user has internal dashboard access.
	 *
	 * @param array<int,string> $roles Roles.
	 * @param \WP_User          $user User.
	 * @return bool
	 */
	protected function has_dashboard_access( array $roles, $user ) {
		if ( user_can( $user, 'sm_manage_plugin' ) ) {
			return true;
		}

		return in_array( 'sm_mechanic', $roles, true );
	}

	/**
	 * Detect useful access inconsistencies.
	 *
	 * @param array<int,string> $roles Roles.
	 * @param string            $operational_role Detected operational role.
	 * @param int               $business_id Business ID.
	 * @param bool              $dashboard_access Dashboard access.
	 * @param bool              $automation_access Automation/logs access.
	 * @param \WP_User          $user User.
	 * @return array<int,string>
	 */
	protected function detect_access_warnings( array $roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user ) {
		$warnings = array();
		$is_admin = in_array( $operational_role, array( 'administrator', 'sm_admin' ), true );

		if ( 'sm_mechanic' === $operational_role && $business_id <= 0 ) {
			$warnings[] = 'mechanic_without_business';
		}

		if ( in_array( 'sm_client', $roles, true ) && ( $dashboard_access || $automation_access || user_can( $user, 'sm_manage_plugin' ) ) ) {
			$warnings[] = 'client_with_internal_access';
		}

		if ( ! in_array( $operational_role, array( 'administrator', 'sm_admin', 'sm_mechanic' ), true ) && ( $dashboard_access || $automation_access ) ) {
			$warnings[] = 'internal_access_without_operational_role';
		}

		if ( $automation_access && ! $is_admin ) {
			$warnings[] = 'automation_access_role_mismatch';
		}

		return array_values( array_unique( $warnings ) );
	}

	/**
	 * Build readable warning summary.
	 *
	 * @param array<int,string> $warnings Warning keys.
	 * @return string
	 */
	protected function build_warning_summary( array $warnings ) {
		if ( empty( $warnings ) ) {
			return __( 'Consistent', 'super-mechanic' );
		}

		$labels = array(
			'mechanic_without_business'               => __( 'Mechanic without business_id', 'super-mechanic' ),
			'client_with_internal_access'             => __( 'Client has internal/admin exposure', 'super-mechanic' ),
			'internal_access_without_operational_role' => __( 'Internal access without operational role', 'super-mechanic' ),
			'automation_access_role_mismatch'         => __( 'Automation/logs access role mismatch', 'super-mechanic' ),
		);

		$summary = array();
		foreach ( $warnings as $warning_key ) {
			$warning_key = sanitize_key( (string) $warning_key );
			$summary[]   = isset( $labels[ $warning_key ] ) ? $labels[ $warning_key ] : $warning_key;
		}

		return implode( '; ', $summary );
	}
}
