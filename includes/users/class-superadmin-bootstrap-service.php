<?php
/**
 * Superadmin bootstrap service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Establishes the initial Mekvort superadmin baseline.
 */
class Superadmin_Bootstrap_Service {
	/**
	 * Persisted bootstrap option key.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'sm_superadmin_bootstrap_state';

	/**
	 * Ensure bootstrap baseline exists.
	 *
	 * @return bool
	 */
	public function ensure_bootstrap_superadmin() {
		$state = get_option( self::OPTION_KEY, array() );
		if ( is_array( $state ) && ! empty( $state['user_id'] ) ) {
			$user_id = absint( $state['user_id'] );
			if ( $user_id > 0 ) {
				$user = get_userdata( $user_id );
				if ( $user instanceof \WP_User ) {
					$this->grant_global_access_capability( $user_id );
					$this->revoke_global_access_from_other_admins( $user_id );
					$this->persist_bootstrap_state( $user_id, 'persisted_bootstrap_state' );
					return true;
				}
			}
		}

		$primary_admin_id = $this->resolve_primary_admin_user_id();
		if ( $primary_admin_id <= 0 ) {
			return false;
		}

		$this->grant_global_access_capability( $primary_admin_id );
		$this->revoke_global_access_from_other_admins( $primary_admin_id );

		return $this->persist_bootstrap_state( $primary_admin_id, 'primary_wp_admin' );
	}

	/**
	 * Resolve primary WordPress administrator by lowest user ID.
	 *
	 * @return int
	 */
	protected function resolve_primary_admin_user_id() {
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ids',
			)
		);

		if ( is_array( $admins ) && ! empty( $admins[0] ) ) {
			return absint( $admins[0] );
		}

		return 0;
	}

	/**
	 * Grant direct global-access capability to one user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	protected function grant_global_access_capability( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		if ( ! user_can( $user, 'sm_global_access' ) ) {
			$user->add_cap( 'sm_global_access', true );
		}
	}

	/**
	 * Revoke direct global-access capability from other administrators.
	 *
	 * @param int $primary_admin_id Primary admin user ID.
	 * @return void
	 */
	protected function revoke_global_access_from_other_admins( $primary_admin_id ) {
		$primary_admin_id = absint( $primary_admin_id );
		if ( $primary_admin_id <= 0 ) {
			return;
		}

		$admin_ids = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ids',
			)
		);

		if ( ! is_array( $admin_ids ) ) {
			return;
		}

		foreach ( $admin_ids as $admin_id ) {
			$admin_id = absint( $admin_id );
			if ( $admin_id <= 0 || $admin_id === $primary_admin_id ) {
				continue;
			}

			$user = get_userdata( $admin_id );
			if ( ! $user instanceof \WP_User ) {
				continue;
			}

			if ( user_can( $user, 'sm_global_access' ) ) {
				$user->remove_cap( 'sm_global_access' );
			}
		}
	}

	/**
	 * Persist normalized bootstrap state payload.
	 *
	 * @param int    $user_id Superadmin user ID.
	 * @param string $source Bootstrap source label.
	 * @return bool
	 */
	protected function persist_bootstrap_state( $user_id, $source ) {
		$user_id = absint( $user_id );
		$source  = sanitize_key( (string) $source );
		if ( $user_id <= 0 ) {
			return false;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return false;
		}

		$existing_state = get_option( self::OPTION_KEY, array() );
		$bootstrapped_at = '';
		$managed_superadmin_ids = array();
		if ( is_array( $existing_state ) && ! empty( $existing_state['bootstrapped_at'] ) ) {
			$bootstrapped_at = sanitize_text_field( (string) $existing_state['bootstrapped_at'] );
		}
		if ( is_array( $existing_state ) && isset( $existing_state['managed_superadmin_ids'] ) && is_array( $existing_state['managed_superadmin_ids'] ) ) {
			$managed_superadmin_ids = array_values( array_unique( array_filter( array_map( 'absint', $existing_state['managed_superadmin_ids'] ) ) ) );
		}
		if ( '' === $bootstrapped_at ) {
			$bootstrapped_at = current_time( 'mysql' );
		}

		$bootstrap_state = array(
			'user_id'        => $user_id,
			'user_email'     => sanitize_email( (string) $user->user_email ),
			'bootstrapped_at' => $bootstrapped_at,
			'source'         => ( '' !== $source ) ? $source : 'primary_wp_admin',
			'managed_superadmin_ids' => $managed_superadmin_ids,
			'updated_at'     => current_time( 'mysql' ),
		);

		return (bool) update_option( self::OPTION_KEY, $bootstrap_state, false );
	}
}
