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

		if ( user_can( $user, 'sm_global_access' ) ) {
			return true;
		}

		$bootstrap_state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );
		if ( is_array( $bootstrap_state ) ) {
			$bootstrap_user_id = isset( $bootstrap_state['user_id'] ) ? absint( $bootstrap_state['user_id'] ) : 0;
			if ( $bootstrap_user_id > 0 && $bootstrap_user_id === $user_id ) {
				return true;
			}

			$managed_superadmin_ids = isset( $bootstrap_state['managed_superadmin_ids'] ) && is_array( $bootstrap_state['managed_superadmin_ids'] ) ? array_map( 'absint', $bootstrap_state['managed_superadmin_ids'] ) : array();
			if ( in_array( $user_id, $managed_superadmin_ids, true ) ) {
				return true;
			}
		}

		// Legacy fallback for installations with canonical superadmin identity but no bootstrap state yet.
		$user_email = sanitize_email( (string) $user->user_email );
		if ( '' !== $user_email && strtolower( $user_email ) === self::CANONICAL_SUPER_ADMIN_EMAIL ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve all Mekvort superadmin users.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_superadmin_rows() {
		$administrator_users = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'all',
				'number' => 500,
				'order'  => 'ASC',
				'orderby'=> 'ID',
			)
		);

		if ( ! is_array( $administrator_users ) ) {
			return array();
		}

		$rows = array();
		foreach ( $administrator_users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$user_id = absint( $user->ID );
			if ( $user_id <= 0 || ! $this->is_global_super_admin( $user_id ) ) {
				continue;
			}

			$rows[] = array(
				'user_id'              => $user_id,
				'display_name'         => sanitize_text_field( (string) $user->display_name ),
				'user_email'           => sanitize_email( (string) $user->user_email ),
				'is_bootstrap_superadmin' => $this->is_bootstrap_superadmin( $user_id ),
				'is_locked_superadmin' => $this->is_locked_superadmin( $user_id ),
			);
		}

		return $rows;
	}

	/**
	 * Resolve eligible WP admin candidates for superadmin promotion.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_superadmin_eligible_admin_rows() {
		$administrator_users = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'all',
				'number' => 500,
				'order'  => 'ASC',
				'orderby'=> 'ID',
			)
		);

		if ( ! is_array( $administrator_users ) ) {
			return array();
		}

		$rows = array();
		foreach ( $administrator_users as $user ) {
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$user_id = absint( $user->ID );
			if ( $user_id <= 0 || $this->is_global_super_admin( $user_id ) ) {
				continue;
			}

			$rows[] = array(
				'user_id'      => $user_id,
				'display_name' => sanitize_text_field( (string) $user->display_name ),
				'user_email'   => sanitize_email( (string) $user->user_email ),
			);
		}

		return $rows;
	}

	/**
	 * Assign Mekvort superadmin status to one WP administrator.
	 *
	 * @param int $actor_user_id Actor user ID.
	 * @param int $target_user_id Target user ID.
	 * @return array<string,mixed>
	 */
	public function assign_superadmin( $actor_user_id, $target_user_id ) {
		$actor_user_id  = absint( $actor_user_id );
		$target_user_id = absint( $target_user_id );

		if ( $actor_user_id <= 0 || ! $this->is_global_super_admin( $actor_user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Only existing Mekvort superadmins can assign superadmin.', 'super-mechanic' ),
			);
		}

		$target_user = get_userdata( $target_user_id );
		if ( ! $target_user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'Target user not found.', 'super-mechanic' ),
			);
		}

		if ( ! $this->is_wp_administrator_user( $target_user ) ) {
			return array(
				'success' => false,
				'message' => __( 'Only WordPress administrators are eligible for superadmin promotion.', 'super-mechanic' ),
			);
		}

		if ( $this->is_global_super_admin( $target_user_id ) ) {
			return array(
				'success' => true,
				'message' => __( 'User is already a Mekvort superadmin.', 'super-mechanic' ),
			);
		}

		$target_user->add_cap( 'sm_global_access', true );
		$this->persist_managed_superadmin_user_id( $target_user_id, true );

		return array(
			'success' => true,
			'message' => __( 'Superadmin assigned successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Revoke Mekvort superadmin status from one promoted superadmin.
	 *
	 * @param int $actor_user_id Actor user ID.
	 * @param int $target_user_id Target user ID.
	 * @return array<string,mixed>
	 */
	public function revoke_superadmin( $actor_user_id, $target_user_id ) {
		$actor_user_id  = absint( $actor_user_id );
		$target_user_id = absint( $target_user_id );

		if ( $actor_user_id <= 0 || ! $this->is_global_super_admin( $actor_user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Only existing Mekvort superadmins can revoke superadmin.', 'super-mechanic' ),
			);
		}

		$target_user = get_userdata( $target_user_id );
		if ( ! $target_user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'Target user not found.', 'super-mechanic' ),
			);
		}

		if ( $this->is_bootstrap_superadmin( $target_user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Locked bootstrap superadmin cannot be revoked from this flow.', 'super-mechanic' ),
			);
		}

		if ( $actor_user_id === $target_user_id ) {
			return array(
				'success' => false,
				'message' => __( 'Self-revocation is blocked to avoid accidental lockout.', 'super-mechanic' ),
			);
		}

		if ( ! $this->is_managed_superadmin( $target_user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Only managed superadmins can be revoked from this flow.', 'super-mechanic' ),
			);
		}

		if ( ! $this->is_global_super_admin( $target_user_id ) ) {
			return array(
				'success' => true,
				'message' => __( 'User is not a Mekvort superadmin.', 'super-mechanic' ),
			);
		}

		$target_user->remove_cap( 'sm_global_access' );
		$this->persist_managed_superadmin_user_id( $target_user_id, false );

		return array(
			'success' => true,
			'message' => __( 'Superadmin revoked successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Resolve if user is the bootstrap superadmin.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_bootstrap_superadmin( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$bootstrap_user_id = $this->get_bootstrap_superadmin_user_id();
		if ( $bootstrap_user_id > 0 && $bootstrap_user_id === $user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Resolve if user is a locked Mekvort superadmin in Roles & Access.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	public function is_locked_superadmin( $user_id ) {
		return $this->is_global_super_admin( $user_id );
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
		$is_locked_superadmin  = $this->is_locked_superadmin( $user_id );
		$accessible_businesses = $this->get_accessible_business_ids( $user_id );
		$memberships           = $is_locked_superadmin
			? $this->build_virtual_locked_superadmin_memberships( $user_id )
			: $this->membership_service->get_user_memberships( $user_id );
		$default_business_id   = $this->get_default_business_id( $user_id );
		$source                = $is_global ? 'global_superadmin' : ( ! empty( $accessible_businesses ) ? 'membership' : 'none' );

		return array(
			'user_id'                 => $user_id,
			'is_global'               => $is_global,
			'is_locked_superadmin'    => $is_locked_superadmin,
			'superadmin_roles'        => $is_locked_superadmin ? array( 'admin', 'mechanic', 'client' ) : array(),
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
			$is_locked_superadmin = ! empty( $access_scope['is_locked_superadmin'] );
			$accessible_ids    = isset( $access_scope['accessible_business_ids'] ) && is_array( $access_scope['accessible_business_ids'] ) ? array_map( 'absint', $access_scope['accessible_business_ids'] ) : array();
			$superadmin_roles  = isset( $access_scope['superadmin_roles'] ) && is_array( $access_scope['superadmin_roles'] ) ? array_map( 'sanitize_key', $access_scope['superadmin_roles'] ) : array();
			$memberships       = isset( $access_scope['memberships'] ) && is_array( $access_scope['memberships'] ) ? $access_scope['memberships'] : array();
			$consistency       = $is_locked_superadmin ? array() : $this->membership_service->validate_membership_consistency( $user_id );
			$consistency_warnings = $is_locked_superadmin ? array() : ( isset( $consistency['warning_keys'] ) && is_array( $consistency['warning_keys'] ) ? array_map( 'sanitize_key', $consistency['warning_keys'] ) : array() );
			$consistency_repairable = $is_locked_superadmin ? false : ! empty( $consistency['repairable'] );
			$dashboard_access = $this->has_dashboard_access( $user_roles, $user );
			$automation_access = user_can( $user, 'sm_manage_plugin' ) || $is_locked_superadmin;
			$warnings          = $this->detect_access_warnings( $user_roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user, $is_global, $accessible_ids, $consistency_warnings, $is_locked_superadmin );
			if ( $is_locked_superadmin ) {
				$operational_role = 'locked_superadmin';
			}

			$rows[] = array(
				'user_id'            => $user_id,
				'display_name'       => sanitize_text_field( $user->display_name ),
				'user_email'         => sanitize_email( $user->user_email ),
				'wp_roles'           => $user_roles,
				'operational_role'   => $operational_role,
				'business_id'        => $business_id,
				'is_global'          => $is_global,
				'is_locked_superadmin' => $is_locked_superadmin,
				'superadmin_roles'   => $superadmin_roles,
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

		if ( $this->is_locked_superadmin( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Locked superadmin memberships cannot be repaired from this flow.', 'super-mechanic' ),
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

		if ( ! in_array( $role_key, array( 'sm_admin', 'sm_mechanic', 'sm_client' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid operational role.', 'super-mechanic' ),
			);
		}

		if ( $this->is_locked_superadmin( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Locked superadmin role cannot be modified from this screen.', 'super-mechanic' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

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
	public function remove_operational_role( $user_id, $role_key = '' ) {
		$user_id = absint( $user_id );
		$role_key = sanitize_key( (string) $role_key );
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user ID.', 'super-mechanic' ),
			);
		}

		if ( $this->is_locked_superadmin( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Locked superadmin role cannot be modified from this screen.', 'super-mechanic' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

		if ( ! in_array( $role_key, array( 'sm_admin', 'sm_mechanic', 'sm_client' ), true ) ) {
			$user_roles = array_map( 'sanitize_key', (array) $user->roles );
			if ( in_array( 'sm_admin', $user_roles, true ) ) {
				$role_key = 'sm_admin';
			} elseif ( in_array( 'sm_mechanic', $user_roles, true ) ) {
				$role_key = 'sm_mechanic';
			} elseif ( in_array( 'sm_client', $user_roles, true ) ) {
				$role_key = 'sm_client';
			}
		}

		if ( '' === $role_key ) {
			return array(
				'success' => false,
				'message' => __( 'No operational role found to remove.', 'super-mechanic' ),
			);
		}

		$user->remove_role( $role_key );

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
	 * @param array<int,string> $consistency_warnings Consistency warning keys.
	 * @param bool              $is_locked_superadmin True when user is locked bootstrap superadmin.
	 * @return array<int,string>
	 */
	protected function detect_access_warnings( array $roles, $operational_role, $business_id, $dashboard_access, $automation_access, $user, $is_global = false, array $accessible_business_ids = array(), array $consistency_warnings = array(), $is_locked_superadmin = false ) {
		if ( $is_locked_superadmin ) {
			return array();
		}

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
	 * Persist managed superadmin list inside bootstrap state option.
	 *
	 * @param int  $user_id Target user ID.
	 * @param bool $is_add True to add, false to remove.
	 * @return void
	 */
	protected function persist_managed_superadmin_user_id( $user_id, $is_add ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$bootstrap_state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );
		if ( ! is_array( $bootstrap_state ) ) {
			$bootstrap_state = array();
		}

		$managed_superadmin_ids = isset( $bootstrap_state['managed_superadmin_ids'] ) && is_array( $bootstrap_state['managed_superadmin_ids'] ) ? array_map( 'absint', $bootstrap_state['managed_superadmin_ids'] ) : array();
		if ( $is_add ) {
			$managed_superadmin_ids[] = $user_id;
		} else {
			$managed_superadmin_ids = array_values( array_diff( $managed_superadmin_ids, array( $user_id ) ) );
		}

		$bootstrap_state['managed_superadmin_ids'] = array_values( array_unique( array_filter( $managed_superadmin_ids ) ) );
		update_option( Superadmin_Bootstrap_Service::OPTION_KEY, $bootstrap_state, false );
	}

	/**
	 * Resolve if user is listed as managed superadmin.
	 *
	 * @param int $user_id User ID.
	 * @return bool
	 */
	protected function is_managed_superadmin( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return false;
		}

		$bootstrap_state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );
		if ( ! is_array( $bootstrap_state ) ) {
			return false;
		}

		$managed_superadmin_ids = isset( $bootstrap_state['managed_superadmin_ids'] ) && is_array( $bootstrap_state['managed_superadmin_ids'] ) ? array_map( 'absint', $bootstrap_state['managed_superadmin_ids'] ) : array();
		return in_array( $user_id, $managed_superadmin_ids, true );
	}

	/**
	 * Verify if one user is a WordPress administrator.
	 *
	 * @param \WP_User $user User object.
	 * @return bool
	 */
	protected function is_wp_administrator_user( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$roles = array_map( 'sanitize_key', (array) $user->roles );
		return in_array( 'administrator', $roles, true ) || user_can( $user, 'manage_options' );
	}

	/**
	 * Resolve bootstrap superadmin user ID from persisted state.
	 *
	 * @return int
	 */
	protected function get_bootstrap_superadmin_user_id() {
		$bootstrap_state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );
		if ( ! is_array( $bootstrap_state ) ) {
			return 0;
		}

		return isset( $bootstrap_state['user_id'] ) ? absint( $bootstrap_state['user_id'] ) : 0;
	}

	/**
	 * Build virtual global memberships for locked superadmin.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	protected function build_virtual_locked_superadmin_memberships( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		$roles = array( 'admin', 'mechanic', 'client' );
		$rows  = array();
		foreach ( $roles as $index => $role ) {
			$rows[] = array(
				'id'               => 0 - ( $index + 1 ),
				'business_id'      => 0,
				'user_id'          => $user_id,
				'operational_role' => $role,
				'status'           => 'active',
				'is_primary'       => 0 === $index,
				'created_at'       => '',
				'updated_at'       => '',
				'is_virtual'       => true,
			);
		}

		return $rows;
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
			'duplicate_active_membership_role'        => __( 'Duplicate active membership detected for same business and role', 'super-mechanic' ),
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
