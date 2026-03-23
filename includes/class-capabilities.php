<?php
/**
 * Capabilities manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and removes plugin capabilities.
 */
class Capabilities {
	/**
	 * Get capability map by role.
	 *
	 * @return array<string, array<int, string>>
	 */
	public function get_role_capability_map() {
		return array(
			'administrator' => $this->get_all_capabilities(),
			'sm_admin'      => $this->get_all_capabilities(),
			'sm_mechanic'   => array(
				'sm_manage_processes',
				'sm_manage_vehicles',
				'sm_view_own_processes',
			),
			'sm_client'     => array(
				'sm_view_own_vehicles',
				'sm_view_own_processes',
			),
		);
	}

	/**
	 * Add plugin capabilities to registered roles.
	 *
	 * @return void
	 */
	public function add_capabilities() {
		foreach ( $this->get_role_capability_map() as $role_name => $capabilities ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->add_cap( $capability );
			}
		}
	}

	/**
	 * Remove plugin capabilities from registered roles.
	 *
	 * @return void
	 */
	public function remove_capabilities() {
		foreach ( $this->get_role_capability_map() as $role_name => $capabilities ) {
			$role = get_role( $role_name );

			if ( ! $role ) {
				continue;
			}

			foreach ( $capabilities as $capability ) {
				$role->remove_cap( $capability );
			}
		}
	}

	/**
	 * Get all plugin capabilities.
	 *
	 * @return array<int, string>
	 */
	protected function get_all_capabilities() {
		return array(
			'sm_manage_plugin',
			'sm_manage_clients',
			'sm_manage_vehicles',
			'sm_manage_processes',
			'sm_manage_flows',
			'sm_manage_settings',
			'sm_view_own_vehicles',
			'sm_view_own_processes',
		);
	}
}
