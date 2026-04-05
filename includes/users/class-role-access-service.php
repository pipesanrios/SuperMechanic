<?php
/**
 * Role and access summary service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds role/access summaries and applies basic operational role updates.
 */
class Role_Access_Service {
	/**
	 * Canonical super admin email.
	 *
	 * @var string
	 */
	const CANONICAL_SUPER_ADMIN_EMAIL = 'admin@mardisom.com';

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Membership service.
	 *
	 * @var Business_Membership_Service
	 */
	protected $membership_service;

	/**
	 * Business repository.
	 *
	 * @var Business_Repository
	 */
	protected $business_repository;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null    $business_context_service Business context service.
	 * @param Business_Membership_Service|null $membership_service Membership service.
	 * @param Business_Repository|null         $business_repository Business repository.
	 */
	public function __construct( Business_Context_Service $business_context_service = null, Business_Membership_Service $membership_service = null, Business_Repository $business_repository = null ) {
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->membership_service       = $membership_service ? $membership_service : new Business_Membership_Service();
		$this->business_repository      = $business_repository ? $business_repository : new Business_Repository();
	}

	/**
	 * Resolve if user has global super admin access.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_global_super_admin( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$user_email = sanitize_email( (string) $user->user_email );
		if ( '' !== $user_email && strtolower( $user_email ) === self::CANONICAL_SUPER_ADMIN_EMAIL ) {
			return true;
		}

		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_network_options' ) ) {
			return true;
		}

		return user_can( $user, 'sm_global_access' );
	}

	/**
	 * Resolve accessible business IDs for user.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,int>
	 */
	public function get_accessible_business_ids( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		if ( $this->is_global_super_admin( $user_id ) ) {
			return $this->get_all_business_ids();
		}

		$memberships = $this->membership_service->get_active_user_memberships( $user_id );
		if ( empty( $memberships ) ) {
			return array();
		}

		$business_ids = array();
		foreach ( $memberships as $membership ) {
			if ( ! is_array( $membership ) ) {
				continue;
			}

			$business_id = isset( $membership['business_id'] ) ? absint( $membership['business_id'] ) : 0;
			if ( $business_id > 0 ) {
				$business_ids[] = $business_id;
			}
		}

		$business_ids = array_values( array_unique( $business_ids ) );
		sort( $business_ids );

		return $business_ids;
	}

	/**
	 * Resolve complete access scope for user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function get_access_scope( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'user_id'                  => 0,
				'is_global'                => false,
				'accessible_business_ids'  => array(),
				'default_business_id'      => 0,
				'memberships'              => array(),
				'source'                   => 'none',
			);
		}

		$is_global             = $this->is_global_super_admin( $user_id );
		$accessible_businesses = $this->get_accessible_business_ids( $user_id );
		$memberships           = $this->membership_service->get_user_memberships( $user_id );
		$default_business_id   = $this->get_default_business_id( $user_id );
		$source                = $is_global ? 'global_role' : ( ! empty( $accessible_businesses ) ? 'membership' : 'none' );

		return array(
			'user_id'                 => $user_id,
			'is_global'               => $is_global,
			'accessible_business_ids' => $accessible_businesses,
			'default_business_id'     => $default_business_id,
			'memberships'             => $memberships,
			'source'                  => $source,
		);
	}

	/**
	 * Verify user access to one business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	public function can_access_business( $user_id, $business_id ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		if ( $user_id <= 0 || $business_id <= 0 ) {
			return false;
		}

		if ( $this->is_global_super_admin( $user_id ) ) {
			return true;
		}

		return $this->membership_service->user_has_active_membership( $user_id, $business_id );
	}

	/**
	 * Resolve default business for one user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_default_business_id( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return 0;
		}

		if ( $this->is_global_super_admin( $user_id ) ) {
			$active_business_id = absint( $this->business_context_service->resolve_business_id_for_user( $user_id ) );
			if ( $active_business_id > 0 ) {
				return $active_business_id;
			}

			return absint( $this->business_repository->get_default_business_id() );
		}

		$primary_membership = $this->membership_service->get_user_primary_membership( $user_id );
		if ( is_array( $primary_membership ) ) {
			$primary_business_id = isset( $primary_membership['business_id'] ) ? absint( $primary_membership['business_id'] ) : 0;
			$primary_status      = isset( $primary_membership['status'] ) ? sanitize_key( (string) $primary_membership['status'] ) : 'inactive';
			if ( $primary_business_id > 0 && 'active' === $primary_status ) {
				return $primary_business_id;
			}
		}

		$active_memberships = $this->membership_service->get_active_user_memberships( $user_id );
		if ( ! empty( $active_memberships ) && isset( $active_memberships[0]['business_id'] ) ) {
			return absint( $active_memberships[0]['business_id'] );
		}

		return 0;
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

			$user_roles        = array_map( 'sanitize_key', (array) $user->roles );
			$operational_role  = $this->detect_operational_role( $user_roles, $user );
			$user_id           = absint( $user->ID );
			$access_scope      = $this->get_access_scope( $user_id );
			$business_id       = isset( $access_scope['default_business_id'] ) ? absint( $access_scope['default_business_id'] ) : 0;
			$is_global         = ! empty( $access_scope['is_global'] );
			$accessible_ids    = isset( $access_scope['accessible_business_ids'] ) && is_array( $access_scope['accessible_business_ids'] ) ? array_map( 'absint', $access_scope['accessible_business_ids'] ) : array();
			$memberships       = isset( $access_scope['memberships'] ) && is_array( $access_scope['memberships'] ) ? $access_scope['memberships'] : array();
			$consistency       = $this->membership_service->validate_membership_consistency( $user_id );
			$consistency_warnings = isset( $consistency['warning_keys'] ) && is_array( $consistency['warning_keys'] ) ? array_map( 'sanitize_key', $consistency['warning_keys'] ) : array();
			$consistency_repairable = ! empty( $consistency['repairable'] );
			$dashboard_access = $this->has_dashboard_access( $user_roles, $user );
			$automation_access = user_can( $user, 'sm_manage_plugin' );
			$warnings          = $this->detect_access_warnings( $user_roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user, $is_global, $accessible_ids, $consistency_warnings );

			$rows[] = array(
				'user_id'            => $user_id,
				'display_name'       => sanitize_text_field( $user->display_name ),
				'user_email'         => sanitize_email( $user->user_email ),
				'wp_roles'           => $user_roles,
				'operational_role'   => $operational_role,
				'business_id'        => $business_id,
				'is_global'          => $is_global,
				'accessible_business_ids' => $accessible_ids,
				'memberships'        => $memberships,
				'consistency_warning_keys' => $consistency_warnings,
				'consistency_repairable' => $consistency_repairable,
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
	 * Run safe membership consistency repair for one user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function repair_user_membership_consistency( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user ID for repair.', 'super-mechanic' ),
			);
		}

		return $this->membership_service->repair_membership_consistency( $user_id );
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
	 * @param bool              $is_global True when user has global access.
	 * @param array<int,int>    $accessible_business_ids Accessible business IDs.
	 * @return array<int,string>
	 */
	protected function detect_access_warnings( array $roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user, $is_global = false, array $accessible_business_ids = array(), array $consistency_warnings = array() ) {
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

		if ( ! $is_global && $dashboard_access && empty( $accessible_business_ids ) && in_array( $operational_role, array( 'sm_admin', 'sm_mechanic' ), true ) ) {
			$warnings[] = 'internal_access_without_active_membership';
		}

		if ( ! $is_global && count( $accessible_business_ids ) > 1 && ! in_array( $operational_role, array( 'administrator', 'sm_admin' ), true ) ) {
			$warnings[] = 'invalid_non_global_multi_business_scope';
		}

		foreach ( $consistency_warnings as $consistency_warning ) {
			$consistency_warning = sanitize_key( (string) $consistency_warning );
			if ( '' !== $consistency_warning ) {
				$warnings[] = $consistency_warning;
			}
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
			'internal_access_without_active_membership' => __( 'Internal access without active business membership', 'super-mechanic' ),
			'invalid_non_global_multi_business_scope' => __( 'Non-global user has invalid multi-business scope', 'super-mechanic' ),
			'multiple_primary_memberships'            => __( 'Multiple primary memberships detected', 'super-mechanic' ),
			'inactive_primary_membership'             => __( 'Primary membership is inactive', 'super-mechanic' ),
			'missing_active_primary_membership'       => __( 'Active membership exists but no active primary is set', 'super-mechanic' ),
			'duplicate_active_membership_simple'      => __( 'Duplicate active membership detected for same business', 'super-mechanic' ),
		);

		$summary = array();
		foreach ( $warnings as $warning_key ) {
			$warning_key = sanitize_key( (string) $warning_key );
			$summary[]   = isset( $labels[ $warning_key ] ) ? $labels[ $warning_key ] : $warning_key;
		}

		return implode( '; ', $summary );
	}

	/**
	 * Resolve all active business IDs.
	 *
	 * @return array<int,int>
	 */
	protected function get_all_business_ids() {
		$businesses = $this->business_repository->get_businesses(
			array(
				'status'   => 'active',
				'page'     => 1,
				'per_page' => 200,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);

		$business_ids = array();
		foreach ( $businesses as $business ) {
			if ( ! is_array( $business ) ) {
				continue;
			}

			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id > 0 ) {
				$business_ids[] = $business_id;
			}
		}

		$business_ids = array_values( array_unique( $business_ids ) );
		sort( $business_ids );

		if ( empty( $business_ids ) ) {
			$default_id = absint( $this->business_repository->get_default_business_id() );
			if ( $default_id > 0 ) {
				$business_ids[] = $default_id;
			}
		}

		return $business_ids;
	}
}
