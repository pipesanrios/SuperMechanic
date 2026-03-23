<?php
/**
 * Roles manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and removes plugin roles.
 */
class Roles {
	/**
	 * Plugin role definitions.
	 *
	 * @return array<string, array<string, string>>
	 */
	protected function get_roles() {
		return array(
			'sm_admin'    => array(
				'display_name' => __( 'Super Mechanic Admin', 'super-mechanic' ),
				'base_role'    => 'administrator',
			),
			'sm_mechanic' => array(
				'display_name' => __( 'Super Mechanic Mechanic', 'super-mechanic' ),
				'base_role'    => 'subscriber',
			),
			'sm_client'   => array(
				'display_name' => __( 'Super Mechanic Client', 'super-mechanic' ),
				'base_role'    => 'subscriber',
			),
		);
	}

	/**
	 * Register plugin roles.
	 *
	 * @return void
	 */
	public function register_roles() {
		foreach ( $this->get_roles() as $role_key => $role_config ) {
			if ( get_role( $role_key ) ) {
				continue;
			}

			add_role( $role_key, $role_config['display_name'], $this->get_base_capabilities( $role_config['base_role'] ) );
		}
	}

	/**
	 * Remove plugin roles.
	 *
	 * @return void
	 */
	public function remove_roles() {
		foreach ( array_keys( $this->get_roles() ) as $role_key ) {
			if ( get_role( $role_key ) ) {
				remove_role( $role_key );
			}
		}
	}

	/**
	 * Get the base capabilities inherited from a WordPress role.
	 *
	 * @param string $role_name Base role name.
	 * @return array<string, bool>
	 */
	protected function get_base_capabilities( $role_name ) {
		$role = get_role( $role_name );

		if ( ! $role || empty( $role->capabilities ) || ! is_array( $role->capabilities ) ) {
			return array( 'read' => true );
		}

		return $role->capabilities;
	}
}
