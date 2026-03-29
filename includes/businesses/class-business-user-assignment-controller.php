<?php
/**
 * Business user assignment controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Businesses;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles user-to-business assignment for multi-store enforcement.
 */
class Business_User_Assignment_Controller {
	/**
	 * Service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Service|null         $business_service Business service.
	 * @param Business_Context_Service|null $business_context_service Business context service.
	 */
	public function __construct( Business_Service $business_service = null, Business_Context_Service $business_context_service = null ) {
		$this->business_service         = $business_service ? $business_service : new Business_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'show_user_profile', array( $this, 'render_user_business_assignment' ) );
		add_action( 'edit_user_profile', array( $this, 'render_user_business_assignment' ) );
		add_action( 'personal_options_update', array( $this, 'save_user_business_assignment' ) );
		add_action( 'edit_user_profile_update', array( $this, 'save_user_business_assignment' ) );
	}

	/**
	 * Render allowed businesses section in user profile.
	 *
	 * @param \WP_User $user User.
	 * @return void
	 */
	public function render_user_business_assignment( $user ) {
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_settings' ) || ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();
		if ( in_array( 'administrator', $roles, true ) ) {
			return;
		}

		$target_roles = array( 'sm_admin', 'sm_mechanic', 'sm_client' );
		if ( empty( array_intersect( $roles, $target_roles ) ) ) {
			return;
		}

		$businesses = $this->business_service->get_businesses(
			array(
				'status'   => 'active',
				'per_page' => 300,
				'page'     => 1,
				'orderby'  => 'name',
				'order'    => 'ASC',
			)
		);
		$assigned   = $this->business_context_service->get_user_assigned_business_ids( $user->ID );

		echo '<h2>' . esc_html__( 'Super Mechanic - Negocios permitidos', 'super-mechanic' ) . '</h2>';
		echo '<table class="form-table" role="presentation">';
		echo '<tr>';
		echo '<th><label>' . esc_html__( 'Asignacion por negocio', 'super-mechanic' ) . '</label></th>';
		echo '<td>';
		wp_nonce_field( 'sm_user_business_assignment_' . $user->ID, 'sm_user_business_assignment_nonce' );
		echo '<fieldset>';
		echo '<legend class="screen-reader-text">' . esc_html__( 'Negocios permitidos', 'super-mechanic' ) . '</legend>';

		foreach ( $businesses as $business ) {
			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id <= 0 ) {
				continue;
			}

			$checked = in_array( $business_id, $assigned, true );
			echo '<label style="display:block;margin-bottom:6px;">';
			echo '<input type="checkbox" name="sm_allowed_business_ids[]" value="' . esc_attr( (string) $business_id ) . '" ' . checked( $checked, true, false ) . ' />';
			echo ' ' . esc_html( (string) $business['name'] . ' (#' . $business_id . ')' );
			echo '</label>';
		}

		echo '</fieldset>';
		echo '<p class="description">' . esc_html__( 'Dejar sin seleccion usa fallback por contexto activo o negocio por defecto. Aplica a sm_admin, sm_mechanic y sm_client.', 'super-mechanic' ) . '</p>';
		echo '</td>';
		echo '</tr>';
		echo '</table>';
	}

	/**
	 * Save allowed businesses for one user.
	 *
	 * @param int $user_id User ID.
	 * @return void
	 */
	public function save_user_business_assignment( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_settings' ) || ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( ! isset( $_POST['sm_user_business_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['sm_user_business_assignment_nonce'] ) ), 'sm_user_business_assignment_' . $user_id ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return;
		}

		$roles = is_array( $user->roles ) ? $user->roles : array();
		if ( in_array( 'administrator', $roles, true ) ) {
			delete_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS );
			return;
		}

		$raw = isset( $_POST['sm_allowed_business_ids'] ) ? wp_unslash( $_POST['sm_allowed_business_ids'] ) : array();
		if ( ! is_array( $raw ) ) {
			$raw = array();
		}

		$allowed = array();
		foreach ( $raw as $candidate ) {
			$business_id = $this->business_service->resolve_valid_business_id( absint( $candidate ) );
			if ( $business_id > 0 ) {
				$allowed[ $business_id ] = $business_id;
			}
		}

		$allowed = array_values( $allowed );
		if ( empty( $allowed ) ) {
			delete_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS );
		} else {
			update_user_meta( $user_id, Business_Context_Service::USER_META_ALLOWED_BUSINESS_IDS, $allowed );
		}

		$current_selected = $this->business_context_service->get_user_selected_business_id( $user_id );
		if ( $current_selected > 0 && ! empty( $allowed ) && ! in_array( $current_selected, $allowed, true ) ) {
			$this->business_context_service->set_user_selected_business_id( $allowed[0], $user_id );
		}
	}
}

