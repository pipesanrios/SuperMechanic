<?php
/**
 * Roles and access admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles basic operational roles management.
 */
class Admin_Roles_Controller {
	/**
	 * Role access service.
	 *
	 * @var Role_Access_Service
	 */
	protected $role_access_service;

	/**
	 * Feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $notice;

	/**
	 * Constructor.
	 *
	 * @param Role_Access_Service|null $role_access_service Service.
	 */
	public function __construct( Role_Access_Service $role_access_service = null ) {
		$this->role_access_service = $role_access_service ? $role_access_service : new Role_Access_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 100 );
	}

	/**
	 * Register Roles & Access submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Roles & Access', 'super-mechanic' ),
			__( 'Roles & Access', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-roles',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$this->maybe_handle_role_update_request();
		$rows = $this->role_access_service->get_role_access_rows();

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Roles & Access', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Operational role visibility and safe internal access management.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Admin only', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_notice();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Users access summary', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( sprintf( __( 'Total: %d', 'super-mechanic' ), count( $rows ) ) ) . '</span></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'ID', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Name', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Email', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'WP roles', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Operational role', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Business', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Dashboard access', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Automation/Logs access', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="10">' . esc_html__( 'No users found for access summary.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$user_id          = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
				$display_name     = isset( $row['display_name'] ) ? sanitize_text_field( (string) $row['display_name'] ) : '';
				$user_email       = isset( $row['user_email'] ) ? sanitize_email( (string) $row['user_email'] ) : '';
				$wp_roles         = isset( $row['wp_roles'] ) && is_array( $row['wp_roles'] ) ? $row['wp_roles'] : array();
				$operational_role = isset( $row['operational_role'] ) ? sanitize_key( (string) $row['operational_role'] ) : 'none';
				$business_id      = isset( $row['business_id'] ) ? absint( $row['business_id'] ) : 0;
				$dashboard_access = ! empty( $row['dashboard_access'] );
				$automation_access = ! empty( $row['automation_access'] );
				$status           = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'ok';
				$status_summary   = isset( $row['status_summary'] ) ? sanitize_text_field( (string) $row['status_summary'] ) : '';

				echo '<tr>';
				echo '<td>' . esc_html( $user_id ) . '</td>';
				echo '<td>' . esc_html( $display_name ) . '</td>';
				echo '<td>' . esc_html( $user_email ) . '</td>';
				echo '<td>' . esc_html( empty( $wp_roles ) ? '—' : implode( ', ', array_map( 'sanitize_key', $wp_roles ) ) ) . '</td>';
				echo '<td>' . esc_html( $operational_role ) . '</td>';
				echo '<td>' . esc_html( $business_id > 0 ? (string) $business_id : '—' ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_yes_no_badge( $dashboard_access ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_yes_no_badge( $automation_access ) ) . '</td>';
				echo '<td>' . wp_kses_post( $this->render_status_badge( $status, $status_summary ) ) . '</td>';
				echo '<td>';
				echo '<form method="post" class="sm-inline-form">';
				wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
				echo '<input type="hidden" name="sm_roles_access_action" value="assign_sm_admin" />';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
				echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Assign sm_admin', 'super-mechanic' ) . '</button>';
				echo '</form>';
				echo '<form method="post" class="sm-inline-form">';
				wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
				echo '<input type="hidden" name="sm_roles_access_action" value="assign_sm_mechanic" />';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
				echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Assign sm_mechanic', 'super-mechanic' ) . '</button>';
				echo '</form>';
				echo '<form method="post" class="sm-inline-form">';
				wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
				echo '<input type="hidden" name="sm_roles_access_action" value="remove_operational_role" />';
				echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
				echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Remove operational role', 'super-mechanic' ) . '</button>';
				echo '</form>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
		echo '</section>';
		echo '</div>';
	}

	/**
	 * Handle role management update request.
	 *
	 * @return void
	 */
	protected function maybe_handle_role_update_request() {
		if ( 'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? sanitize_text_field( (string) wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) : '' ) ) {
			return;
		}

		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'You are not allowed to update user roles.', 'super-mechanic' ),
			);
			return;
		}

		$nonce = isset( $_POST['sm_roles_access_nonce'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['sm_roles_access_nonce'] ) ) : '';
		if ( '' === $nonce || ! wp_verify_nonce( $nonce, 'sm_roles_access_update' ) ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'Security validation failed for role update.', 'super-mechanic' ),
			);
			return;
		}

		$user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		$action  = isset( $_POST['sm_roles_access_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_roles_access_action'] ) ) : '';

		if ( $user_id <= 0 || '' === $action ) {
			$this->notice = array(
				'type'    => 'error',
				'message' => __( 'Invalid role update payload.', 'super-mechanic' ),
			);
			return;
		}

		$result = array(
			'success' => false,
			'message' => __( 'Unknown role action.', 'super-mechanic' ),
		);

		if ( 'assign_sm_admin' === $action ) {
			$result = $this->role_access_service->assign_operational_role( $user_id, 'sm_admin' );
		} elseif ( 'assign_sm_mechanic' === $action ) {
			$result = $this->role_access_service->assign_operational_role( $user_id, 'sm_mechanic' );
		} elseif ( 'remove_operational_role' === $action ) {
			$result = $this->role_access_service->remove_operational_role( $user_id );
		}

		$this->notice = array(
			'type'    => ! empty( $result['success'] ) ? 'success' : 'error',
			'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : __( 'Role update result unavailable.', 'super-mechanic' ),
		);
	}

	/**
	 * Render feedback notice.
	 *
	 * @return void
	 */
	protected function render_notice() {
		if ( empty( $this->notice ) || ! is_array( $this->notice ) ) {
			return;
		}

		$type    = isset( $this->notice['type'] ) ? sanitize_key( (string) $this->notice['type'] ) : 'success';
		$message = isset( $this->notice['message'] ) ? sanitize_text_field( (string) $this->notice['message'] ) : '';
		$class   = 'success' === $type ? 'notice notice-success' : 'notice notice-error';
		echo '<div class="' . esc_attr( $class ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Render compact yes/no badge.
	 *
	 * @param bool $allowed Access flag.
	 * @return string
	 */
	protected function render_yes_no_badge( $allowed ) {
		if ( $allowed ) {
			return '<span class="sm-badge sm-badge-success">' . esc_html__( 'Yes', 'super-mechanic' ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-neutral">' . esc_html__( 'No', 'super-mechanic' ) . '</span>';
	}

	/**
	 * Render status badge with summary.
	 *
	 * @param string $status Status key.
	 * @param string $summary Summary text.
	 * @return string
	 */
	protected function render_status_badge( $status, $summary ) {
		$status  = sanitize_key( (string) $status );
		$summary = sanitize_text_field( (string) $summary );
		$class   = 'sm-badge sm-badge-success';
		$label   = __( 'OK', 'super-mechanic' );
		if ( 'warning' === $status ) {
			$class = 'sm-badge sm-badge-warning';
			$label = __( 'Warning', 'super-mechanic' );
		}

		return '<span class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span><div class="sm-list-meta">' . esc_html( $summary ) . '</div>';
	}
}
