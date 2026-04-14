<?php
/**
 * Reset user handling service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Cleans non-protected runtime/business users during reset flows.
 */
class Reset_User_Handling_Service {
	/**
	 * Role access service.
	 *
	 * @var Role_Access_Service
	 */
	protected $role_access_service;

	/**
	 * Membership repository.
	 *
	 * @var Business_Membership_Repository
	 */
	protected $membership_repository;

	/**
	 * Constructor.
	 *
	 * @param Role_Access_Service|null          $role_access_service Role access service.
	 * @param Business_Membership_Repository|null $membership_repository Membership repository.
	 */
	public function __construct( Role_Access_Service $role_access_service = null, Business_Membership_Repository $membership_repository = null ) {
		$this->role_access_service  = $role_access_service ? $role_access_service : new Role_Access_Service();
		$this->membership_repository = $membership_repository ? $membership_repository : new Business_Membership_Repository();
	}

	/**
	 * Cleanup non-protected runtime/business users.
	 *
	 * Policy:
	 * - preserve all Mekvort superadmins (bootstrap + managed + global runtime)
	 * - remove non-protected users with plugin runtime roles/memberships
	 * - remove non-protected WordPress administrators
	 *
	 * @return array<string,mixed>
	 */
	public function cleanup_non_protected_runtime_users() {
		$protected_superadmin_ids = $this->resolve_protected_superadmin_ids();
		$candidate_user_ids       = $this->resolve_runtime_user_candidate_ids();

		$deleted_user_ids         = array();
		$preserved_user_ids       = array();
		$preserved_superadmin_ids = array();
		$failed_user_ids          = array();

		if ( ! function_exists( 'wp_delete_user' ) ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
		}

		foreach ( $candidate_user_ids as $candidate_user_id ) {
			$candidate_user_id = absint( $candidate_user_id );
			if ( $candidate_user_id <= 0 ) {
				continue;
			}

			if ( in_array( $candidate_user_id, $protected_superadmin_ids, true ) ) {
				$preserved_superadmin_ids[] = $candidate_user_id;
				continue;
			}

			$user = get_userdata( $candidate_user_id );
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			$deleted = wp_delete_user( $candidate_user_id );
			if ( $deleted ) {
				$deleted_user_ids[] = $candidate_user_id;
			} else {
				$failed_user_ids[] = $candidate_user_id;
			}
		}

		$memberships_deleted = 0;
		if ( ! empty( $deleted_user_ids ) ) {
			$memberships_deleted = $this->membership_repository->delete_memberships_by_user_ids( $deleted_user_ids );
		}

		$this->normalize_superadmin_bootstrap_state();

		return array(
			'protected_superadmin_ids' => array_values( array_unique( array_map( 'absint', $protected_superadmin_ids ) ) ),
			'deleted_user_ids'         => array_values( array_unique( array_map( 'absint', $deleted_user_ids ) ) ),
			'preserved_user_ids'       => array_values( array_unique( array_map( 'absint', $preserved_user_ids ) ) ),
			'preserved_superadmin_ids' => array_values( array_unique( array_map( 'absint', $preserved_superadmin_ids ) ) ),
			'failed_user_ids'          => array_values( array_unique( array_map( 'absint', $failed_user_ids ) ) ),
			'memberships_deleted'      => max( 0, (int) $memberships_deleted ),
		);
	}

	/**
	 * Resolve protected superadmin user IDs from current Mekvort model.
	 *
	 * @return array<int,int>
	 */
	protected function resolve_protected_superadmin_ids() {
		$ids   = array();
		$state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );

		if ( is_array( $state ) ) {
			if ( ! empty( $state['user_id'] ) ) {
				$ids[] = absint( $state['user_id'] );
			}

			if ( isset( $state['managed_superadmin_ids'] ) && is_array( $state['managed_superadmin_ids'] ) ) {
				foreach ( $state['managed_superadmin_ids'] as $managed_user_id ) {
					$ids[] = absint( $managed_user_id );
				}
			}
		}

		$administrator_ids = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ids',
				'number' => 500,
			)
		);

		if ( is_array( $administrator_ids ) ) {
			foreach ( $administrator_ids as $administrator_id ) {
				$administrator_id = absint( $administrator_id );
				if ( $administrator_id <= 0 ) {
					continue;
				}

				if ( $this->role_access_service->is_global_super_admin( $administrator_id ) ) {
					$ids[] = $administrator_id;
				}
			}
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	/**
	 * Resolve runtime/business user candidates from plugin roles and memberships.
	 *
	 * @return array<int,int>
	 */
	protected function resolve_runtime_user_candidate_ids() {
		$ids = array();

		$administrator_user_ids = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ids',
				'number' => 500,
			)
		);
		if ( is_array( $administrator_user_ids ) ) {
			foreach ( $administrator_user_ids as $administrator_user_id ) {
				$ids[] = absint( $administrator_user_id );
			}
		}

		$runtime_roles = array( 'sm_admin', 'sm_mechanic', 'sm_client' );
		foreach ( $runtime_roles as $runtime_role ) {
			$role_user_ids = get_users(
				array(
					'role'   => $runtime_role,
					'fields' => 'ids',
					'number' => 500,
				)
			);

			if ( ! is_array( $role_user_ids ) ) {
				continue;
			}

			foreach ( $role_user_ids as $role_user_id ) {
				$ids[] = absint( $role_user_id );
			}
		}

		$membership_user_ids = $this->membership_repository->get_distinct_user_ids();
		foreach ( $membership_user_ids as $membership_user_id ) {
			$ids[] = absint( $membership_user_id );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	/**
	 * Normalize bootstrap state after user cleanup, keeping only valid superadmins.
	 *
	 * @return void
	 */
	protected function normalize_superadmin_bootstrap_state() {
		$state = get_option( Superadmin_Bootstrap_Service::OPTION_KEY, array() );
		if ( ! is_array( $state ) ) {
			return;
		}

		$managed_ids = isset( $state['managed_superadmin_ids'] ) && is_array( $state['managed_superadmin_ids'] ) ? $state['managed_superadmin_ids'] : array();
		$clean_managed_ids = array();
		foreach ( $managed_ids as $managed_id ) {
			$managed_id = absint( $managed_id );
			if ( $managed_id <= 0 ) {
				continue;
			}

			$user = get_userdata( $managed_id );
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			if ( $this->role_access_service->is_global_super_admin( $managed_id ) ) {
				$clean_managed_ids[] = $managed_id;
			}
		}

		$state['managed_superadmin_ids'] = array_values( array_unique( array_map( 'absint', $clean_managed_ids ) ) );
		$state['updated_at']             = current_time( 'mysql' );

		update_option( Superadmin_Bootstrap_Service::OPTION_KEY, $state, false );
	}

}
