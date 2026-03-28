<?php
/**
 * Reusable permission service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Centralizes reusable role and portal permission checks.
 */
class Permission_Service {
	/**
	 * Access control service.
	 *
	 * @var Access_Control_Service
	 */
	protected $access_control_service;

	/**
	 * Constructor.
	 *
	 * @param Access_Control_Service|null $access_control_service Access control service.
	 */
	public function __construct( Access_Control_Service $access_control_service = null ) {
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service();
	}

	/**
	 * Get the linked client ID for a user.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_client_id_by_user_id( $user_id ) {
		return $this->access_control_service->get_client_id_by_user_id( $user_id );
	}

	/**
	 * Check if a user can access the client portal.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public function user_can_access_client_portal( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! is_user_logged_in() ) {
			return new WP_Error( 'sm_permission_login_required', __( 'Debe iniciar sesión para acceder a esta sección.', 'super-mechanic' ) );
		}

		if ( ! user_can( $user_id, 'sm_view_own_processes' ) && ! user_can( $user_id, 'sm_view_own_vehicles' ) ) {
			return new WP_Error( 'sm_permission_client_forbidden', __( 'No tiene permisos para acceder a esta sección.', 'super-mechanic' ) );
		}

		if ( ! $this->get_client_id_by_user_id( $user_id ) ) {
			return new WP_Error( 'sm_permission_missing_client', __( 'No hay un cliente vinculado a su usuario.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Check if a user can access the mechanic portal.
	 *
	 * @param int $user_id User ID.
	 * @return true|WP_Error
	 */
	public function user_can_access_mechanic_portal( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id || ! is_user_logged_in() ) {
			return new WP_Error( 'sm_permission_login_required', __( 'Debe iniciar sesión para acceder a esta sección.', 'super-mechanic' ) );
		}

		if ( ! user_can( $user_id, 'sm_manage_processes' ) ) {
			return new WP_Error( 'sm_permission_mechanic_forbidden', __( 'No tiene permisos para acceder al portal mecánico.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Check whether a user can access a process in client context.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	public function user_can_access_client_process( $user_id, $process_id ) {
		$portal_access = $this->user_can_access_client_portal( $user_id );

		if ( is_wp_error( $portal_access ) ) {
			return $portal_access;
		}

		if ( ! $this->access_control_service->user_can_access_process( $user_id, $process_id ) ) {
			return new WP_Error( 'sm_permission_client_process_forbidden', __( 'No tienes acceso a este proceso.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Check whether a user can access a process in mechanic context.
	 *
	 * @param int $user_id    User ID.
	 * @param int $process_id Process ID.
	 * @return true|WP_Error
	 */
	public function user_can_access_mechanic_process( $user_id, $process_id ) {
		$portal_access = $this->user_can_access_mechanic_portal( $user_id );

		if ( is_wp_error( $portal_access ) ) {
			return $portal_access;
		}

		if ( ! $this->access_control_service->user_can_access_process( $user_id, $process_id ) ) {
			return new WP_Error( 'sm_permission_mechanic_process_forbidden', __( 'No puedes acceder a este proceso.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Convert a permission result into a user-facing message.
	 *
	 * @param true|WP_Error $permission Permission result.
	 * @return string
	 */
	public function get_error_message( $permission ) {
		if ( is_wp_error( $permission ) ) {
			return '<p>' . esc_html( $permission->get_error_message() ) . '</p>';
		}

		return '';
	}
}
