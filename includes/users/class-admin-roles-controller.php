<?php
/**
 * Roles and access admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

use Super_Mechanic\Businesses\Business_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and handles operational roles + business memberships management.
 */
class Admin_Roles_Controller {
	/**
	 * Role access service.
	 *
	 * @var Role_Access_Service
	 */
	protected $role_access_service;

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
	 * Feedback notice.
	 *
	 * @var array<string,string>|null
	 */
	protected $notice;

	/**
	 * Constructor.
	 *
	 * @param Role_Access_Service|null         $role_access_service Role access service.
	 * @param Business_Membership_Service|null $membership_service Membership service.
	 * @param Business_Repository|null         $business_repository Business repository.
	 */
	public function __construct( Role_Access_Service $role_access_service = null, Business_Membership_Service $membership_service = null, Business_Repository $business_repository = null ) {
		$this->role_access_service = $role_access_service ? $role_access_service : new Role_Access_Service();
		$this->membership_service  = $membership_service ? $membership_service : new Business_Membership_Service();
		$this->business_repository = $business_repository ? $business_repository : new Business_Repository();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 100 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_sm_roles_membership_action', array( $this, 'ajax_membership_action' ) );
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
	 * Enqueue page assets.
	 *
	 * @param string $hook_suffix Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook_suffix ) {
		if ( ! is_string( $hook_suffix ) || false === strpos( $hook_suffix, 'super-mechanic_page_super-mechanic-roles' ) ) {
			return;
		}

		wp_enqueue_script(
			'sm-admin-roles-access',
			SM_PLUGIN_URL . 'assets/js/admin-roles-access.js',
			array( 'jquery' ),
			defined( 'SM_PLUGIN_VERSION' ) ? SM_PLUGIN_VERSION : '0.1.0',
			true
		);

		wp_localize_script(
			'sm-admin-roles-access',
			'smRolesAccess',
			array(
				'ajax_url'  => admin_url( 'admin-ajax.php' ),
				'nonce'     => wp_create_nonce( 'sm_roles_membership_action' ),
				'messages'  => array(
					'unexpected' => __( 'Unexpected error while processing membership action.', 'super-mechanic' ),
				),
			)
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
		$rows       = $this->role_access_service->get_role_access_rows();
		$businesses = $this->business_repository->get_businesses(
			array(
				'status'   => 'active',
				'page'     => 1,
				'per_page' => 200,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);
		$business_labels = $this->build_business_labels_map( $businesses );

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Roles & Access', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Operational role visibility and business membership management.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Admin only', 'super-mechanic' ) . '</span>';
		echo '</div>';

		$this->render_notice();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Users access summary', 'super-mechanic' ) . '</h2><span class="sm-badge sm-badge-neutral">' . esc_html( sprintf( __( 'Total: %d', 'super-mechanic' ), count( $rows ) ) ) . '</span></div>';
		echo '<div class="sm-roles-columns-toolbar">';
		echo '<span class="sm-roles-columns-title">' . esc_html__( 'Visible columns:', 'super-mechanic' ) . '</span>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="wp_roles" checked="checked" /> ' . esc_html__( 'WP roles', 'super-mechanic' ) . '</label>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="business" checked="checked" /> ' . esc_html__( 'Business', 'super-mechanic' ) . '</label>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="memberships" checked="checked" /> ' . esc_html__( 'Memberships', 'super-mechanic' ) . '</label>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="dashboard_access" /> ' . esc_html__( 'Dashboard access', 'super-mechanic' ) . '</label>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="automation_access" /> ' . esc_html__( 'Automation/Logs', 'super-mechanic' ) . '</label>';
		echo '<label><input type="checkbox" class="sm-roles-column-toggle" value="status" checked="checked" /> ' . esc_html__( 'Status', 'super-mechanic' ) . '</label>';
		echo '</div>';
		echo '<div class="sm-table-wrap"><table class="sm-table sm-roles-access-table"><thead><tr>';
		echo '<th data-col="id">' . esc_html__( 'ID', 'super-mechanic' ) . '</th>';
		echo '<th data-col="name">' . esc_html__( 'Name', 'super-mechanic' ) . '</th>';
		echo '<th data-col="email">' . esc_html__( 'Email', 'super-mechanic' ) . '</th>';
		echo '<th data-col="wp_roles">' . esc_html__( 'WP roles', 'super-mechanic' ) . '</th>';
		echo '<th data-col="operational_role">' . esc_html__( 'Operational role', 'super-mechanic' ) . '</th>';
		echo '<th data-col="business">' . esc_html__( 'Business', 'super-mechanic' ) . '</th>';
		echo '<th data-col="memberships">' . esc_html__( 'Memberships', 'super-mechanic' ) . '</th>';
		echo '<th data-col="dashboard_access">' . esc_html__( 'Dashboard access', 'super-mechanic' ) . '</th>';
		echo '<th data-col="automation_access">' . esc_html__( 'Automation/Logs access', 'super-mechanic' ) . '</th>';
		echo '<th data-col="status">' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th data-col="actions">' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $rows ) ) {
			echo '<tr><td colspan="11">' . esc_html__( 'No users found for access summary.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $rows as $row ) {
				$user_id          = isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0;
				$display_name     = isset( $row['display_name'] ) ? sanitize_text_field( (string) $row['display_name'] ) : '';
				$user_email       = isset( $row['user_email'] ) ? sanitize_email( (string) $row['user_email'] ) : '';
				$wp_roles         = isset( $row['wp_roles'] ) && is_array( $row['wp_roles'] ) ? $row['wp_roles'] : array();
				$operational_role = isset( $row['operational_role'] ) ? sanitize_key( (string) $row['operational_role'] ) : 'none';
				$business_id      = isset( $row['business_id'] ) ? absint( $row['business_id'] ) : 0;
				$is_global        = ! empty( $row['is_global'] );
				$memberships      = isset( $row['memberships'] ) && is_array( $row['memberships'] ) ? $row['memberships'] : array();
				$is_locked_superadmin = ! empty( $row['is_locked_superadmin'] );
				$superadmin_roles = isset( $row['superadmin_roles'] ) && is_array( $row['superadmin_roles'] ) ? array_map( 'sanitize_key', $row['superadmin_roles'] ) : array();
				if ( $is_locked_superadmin && empty( $superadmin_roles ) ) {
					$superadmin_roles = array( 'admin', 'mechanic', 'client' );
				}
				$consistency_repairable = ! empty( $row['consistency_repairable'] );
				$dashboard_access = ! empty( $row['dashboard_access'] );
				$automation_access = ! empty( $row['automation_access'] );
				$status           = isset( $row['status'] ) ? sanitize_key( (string) $row['status'] ) : 'ok';
				$status_summary   = isset( $row['status_summary'] ) ? sanitize_text_field( (string) $row['status_summary'] ) : '';

				echo '<tr>';
				echo '<td data-col="id">' . esc_html( $user_id ) . '</td>';
				echo '<td data-col="name">' . esc_html( $display_name ) . '</td>';
				echo '<td data-col="email">' . esc_html( $user_email ) . '</td>';
				echo '<td data-col="wp_roles">' . esc_html( empty( $wp_roles ) ? '—' : implode( ', ', array_map( 'sanitize_key', $wp_roles ) ) ) . '</td>';
				echo '<td data-col="operational_role">';
				if ( $is_locked_superadmin ) {
					echo '<span class="sm-badge sm-badge-primary">' . esc_html__( 'Locked superadmin', 'super-mechanic' ) . '</span>';
					echo '<div class="sm-list-meta">' . esc_html( implode( ' + ', $superadmin_roles ) ) . '</div>';
				} else {
					echo esc_html( $operational_role );
				}
				echo '</td>';
				echo '<td data-col="business">' . esc_html( $this->format_business_label( $business_id, $is_global ) ) . '</td>';
				echo '<td data-col="memberships">' . $this->render_memberships_cell( $user_id, $memberships, $businesses, $business_labels, $is_locked_superadmin, $superadmin_roles ) . '</td>';
				echo '<td data-col="dashboard_access">' . wp_kses_post( $this->render_yes_no_badge( $dashboard_access ) ) . '</td>';
				echo '<td data-col="automation_access">' . wp_kses_post( $this->render_yes_no_badge( $automation_access ) ) . '</td>';
				echo '<td data-col="status">' . wp_kses_post( $this->render_status_badge( $status, $status_summary ) ) . '</td>';
				echo '<td data-col="actions">';
				echo '<div class="sm-role-actions">';
				if ( $is_locked_superadmin ) {
					echo '<span class="sm-list-meta">' . esc_html__( 'Locked superadmin: role controls disabled.', 'super-mechanic' ) . '</span>';
				} else {
					echo '<form method="post" class="sm-inline-form">';
					wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
					echo '<input type="hidden" name="sm_roles_access_action" value="assign_sm_admin" />';
					echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
					echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Assign admin', 'super-mechanic' ) . '</button>';
					echo '</form>';
					echo '<form method="post" class="sm-inline-form">';
					wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
					echo '<input type="hidden" name="sm_roles_access_action" value="assign_sm_mechanic" />';
					echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
					echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Assign mechanic', 'super-mechanic' ) . '</button>';
					echo '</form>';
					echo '<form method="post" class="sm-inline-form">';
					wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
					echo '<input type="hidden" name="sm_roles_access_action" value="remove_operational_role" />';
					echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
					echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Remove role', 'super-mechanic' ) . '</button>';
					echo '</form>';
					if ( $consistency_repairable ) {
						echo '<form method="post" class="sm-inline-form">';
						wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
						echo '<input type="hidden" name="sm_roles_access_action" value="repair_membership_consistency" />';
						echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
						echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Run safe repair', 'super-mechanic' ) . '</button>';
						echo '</form>';
					}
				}
				echo '</div>';
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table></div>';
		echo '</section>';

		$this->render_superadmin_controls_section();
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
		} elseif ( 'repair_membership_consistency' === $action ) {
			$result = $this->role_access_service->repair_user_membership_consistency( $user_id );
		} elseif ( 'assign_superadmin' === $action ) {
			$result = $this->role_access_service->assign_superadmin( get_current_user_id(), $user_id );
		} elseif ( 'revoke_superadmin' === $action ) {
			$result = $this->role_access_service->revoke_superadmin( get_current_user_id(), $user_id );
		}

		$this->notice = array(
			'type'    => ! empty( $result['success'] ) ? 'success' : 'error',
			'message' => isset( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : __( 'Role update result unavailable.', 'super-mechanic' ),
		);
	}

	/**
	 * AJAX membership actions handler.
	 *
	 * @return void
	 */
	public function ajax_membership_action() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'super-mechanic' ) ), 403 );
		}

		check_ajax_referer( 'sm_roles_membership_action', 'nonce' );

		$action_key = isset( $_POST['membership_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['membership_action'] ) ) : '';
		$result     = array(
			'success' => false,
			'message' => __( 'Invalid membership action.', 'super-mechanic' ),
		);

		$target_user_id = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
		if ( $target_user_id > 0 && $this->role_access_service->is_locked_superadmin( $target_user_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Locked superadmin memberships cannot be modified from this screen.', 'super-mechanic' ),
				),
				400
			);
		}

		if ( 'create_membership' === $action_key ) {
			$result = $this->membership_service->create_membership(
				isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0,
				isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0,
				isset( $_POST['role'] ) ? sanitize_key( (string) wp_unslash( $_POST['role'] ) ) : ''
			);
		} elseif ( 'update_membership_role' === $action_key ) {
			$result = $this->membership_service->update_membership_role(
				isset( $_POST['membership_id'] ) ? absint( wp_unslash( $_POST['membership_id'] ) ) : 0,
				isset( $_POST['role'] ) ? sanitize_key( (string) wp_unslash( $_POST['role'] ) ) : ''
			);
		} elseif ( 'set_membership_status' === $action_key ) {
			$result = $this->membership_service->set_membership_status(
				isset( $_POST['membership_id'] ) ? absint( wp_unslash( $_POST['membership_id'] ) ) : 0,
				isset( $_POST['status'] ) ? sanitize_key( (string) wp_unslash( $_POST['status'] ) ) : ''
			);
		} elseif ( 'set_primary_membership' === $action_key ) {
			$result = $this->membership_service->set_primary_membership(
				isset( $_POST['membership_id'] ) ? absint( wp_unslash( $_POST['membership_id'] ) ) : 0
			);
		} elseif ( 'remove_membership' === $action_key ) {
			$result = $this->membership_service->remove_membership(
				isset( $_POST['membership_id'] ) ? absint( wp_unslash( $_POST['membership_id'] ) ) : 0
			);
		} elseif ( in_array( $action_key, array( 'transfer', 'transfer_user_to_business' ), true ) ) {
			$user_id            = isset( $_POST['user_id'] ) ? absint( wp_unslash( $_POST['user_id'] ) ) : 0;
			$target_business_id = 0;
			if ( isset( $_POST['target_business_id'] ) ) {
				$target_business_id = absint( wp_unslash( $_POST['target_business_id'] ) );
			} elseif ( isset( $_POST['business_id'] ) ) {
				$target_business_id = absint( wp_unslash( $_POST['business_id'] ) );
			} elseif ( isset( $_POST['target_business'] ) ) {
				$target_business_id = absint( wp_unslash( $_POST['target_business'] ) );
			} elseif ( isset( $_POST['destination_business_id'] ) ) {
				$target_business_id = absint( wp_unslash( $_POST['destination_business_id'] ) );
			}
			$role = isset( $_POST['role'] ) ? sanitize_key( (string) wp_unslash( $_POST['role'] ) ) : '';
			$mode = isset( $_POST['mode'] ) ? sanitize_key( (string) wp_unslash( $_POST['mode'] ) ) : '';

			if ( $user_id <= 0 ) {
				$result = array(
					'success' => false,
					'message' => __( 'Invalid transfer payload: user_id is required.', 'super-mechanic' ),
				);
			} elseif ( $target_business_id <= 0 ) {
				$result = array(
					'success' => false,
					'message' => __( 'Invalid transfer payload: target_business_id is required.', 'super-mechanic' ),
				);
			} elseif ( ! in_array( $role, array( 'admin', 'mechanic', 'client' ), true ) ) {
				$result = array(
					'success' => false,
					'message' => __( 'Invalid transfer payload: role must be admin, mechanic or client.', 'super-mechanic' ),
				);
			} elseif ( ! in_array( $mode, array( 'replace', 'add' ), true ) ) {
				$result = array(
					'success' => false,
					'message' => __( 'Invalid transfer payload: mode must be replace or add.', 'super-mechanic' ),
				);
			} else {
				$result = $this->membership_service->transfer_user_to_business(
					$user_id,
					$target_business_id,
					$role,
					$mode
				);
			}
		}

		if ( ! empty( $result['success'] ) ) {
			wp_send_json_success(
				array(
					'message' => isset( $result['message'] ) ? (string) $result['message'] : __( 'Membership action completed.', 'super-mechanic' ),
				)
			);
		}

		wp_send_json_error(
			array(
				'message' => isset( $result['message'] ) ? (string) $result['message'] : __( 'Membership action failed.', 'super-mechanic' ),
			),
			400
		);
	}

	/**
	 * Render memberships cell.
	 *
	 * @param int                               $user_id User ID.
	 * @param array<int,array<string,mixed>>    $memberships Memberships.
	 * @param array<int,array<string,mixed>>    $businesses Businesses.
	 * @return string
	 */
	protected function render_memberships_cell( $user_id, array $memberships, array $businesses, array $business_labels = array(), $is_locked_superadmin = false, array $superadmin_roles = array() ) {
		$business_options = $this->render_business_select_options( $businesses );
		$role_options     = $this->render_role_select_options();
		$has_businesses   = '' !== trim( $business_options );
		ob_start();

		echo '<div class="sm-membership-panel">';
		if ( $is_locked_superadmin ) {
			echo '<div class="sm-membership-block sm-membership-current">';
			echo '<h4 class="sm-membership-title">' . esc_html__( 'Current memberships', 'super-mechanic' ) . '</h4>';
			echo '<div class="sm-membership-cards">';
			echo '<div class="sm-membership-item">';
			echo '<div class="sm-membership-meta">';
			echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Scope:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html__( 'Global scope', 'super-mechanic' ) . '</span></div>';
			echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Roles:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html( implode( ' + ', $superadmin_roles ) ) . '</span></div>';
			echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Mode:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html__( 'Locked superadmin', 'super-mechanic' ) . '</span></div>';
			echo '</div>';
			echo '</div>';
			echo '</div>';
			echo '<span class="sm-list-meta">' . esc_html__( 'Add membership and transfer controls are disabled for locked superadmin users.', 'super-mechanic' ) . '</span>';
			echo '</div>';
			echo '</div>';

			return (string) ob_get_clean();
		}

		echo '<div class="sm-membership-block sm-membership-current">';
		echo '<h4 class="sm-membership-title">' . esc_html__( 'Current memberships', 'super-mechanic' ) . '</h4>';
		if ( empty( $memberships ) ) {
			echo '<div class="sm-list-meta">' . esc_html__( 'No membership assigned', 'super-mechanic' ) . '</div>';
		} else {
			echo '<div class="sm-membership-cards">';
			foreach ( $memberships as $membership ) {
				if ( ! is_array( $membership ) ) {
					continue;
				}

				$membership_id = isset( $membership['id'] ) ? absint( $membership['id'] ) : 0;
				$business_id   = isset( $membership['business_id'] ) ? absint( $membership['business_id'] ) : 0;
				$role          = isset( $membership['operational_role'] ) ? sanitize_key( (string) $membership['operational_role'] ) : '';
				$status        = isset( $membership['status'] ) ? sanitize_key( (string) $membership['status'] ) : 'inactive';
				$is_primary    = ! empty( $membership['is_primary'] );

				echo '<div class="sm-membership-item" data-membership-id="' . esc_attr( (string) $membership_id ) . '">';
				echo '<div class="sm-membership-meta">';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Business:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html( $this->resolve_business_display_label( $business_id, $business_labels ) ) . '</span></div>';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Role:', 'super-mechanic' ) . '</span> <span class="sm-membership-value"><select class="sm-membership-role sm-membership-select-inline">' . $this->render_role_select_options( $role ) . '</select></span></div>';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Status:', 'super-mechanic' ) . '</span> <span class="sm-membership-value sm-membership-badge">' . esc_html( $status ) . '</span></div>';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Primary:', 'super-mechanic' ) . '</span> <span class="sm-membership-value sm-membership-badge">' . esc_html( $is_primary ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</span></div>';
				echo '</div>';
				echo '<div class="sm-membership-item-actions">';
				echo '<div class="sm-membership-actions">';
				echo '<button type="button" class="button button-small sm-membership-action" data-action="update_membership_role">' . esc_html__( 'Change', 'super-mechanic' ) . '</button>';
				if ( 'active' === $status ) {
					echo '<button type="button" class="button button-small sm-membership-action" data-action="set_membership_status" data-status="inactive">' . esc_html__( 'Deactivate', 'super-mechanic' ) . '</button>';
				} else {
					echo '<button type="button" class="button button-small sm-membership-action" data-action="set_membership_status" data-status="active">' . esc_html__( 'Activate', 'super-mechanic' ) . '</button>';
				}
				if ( ! $is_primary ) {
					echo '<button type="button" class="button button-small sm-membership-action" data-action="set_primary_membership">' . esc_html__( 'Set primary', 'super-mechanic' ) . '</button>';
				}
				echo '<button type="button" class="button button-small sm-membership-action" data-action="remove_membership">' . esc_html__( 'Remove', 'super-mechanic' ) . '</button>';
				echo '</div>';
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="sm-membership-block sm-membership-add" data-user-id="' . esc_attr( (string) $user_id ) . '">';
		echo '<h4 class="sm-membership-title">' . esc_html__( 'Add membership', 'super-mechanic' ) . '</h4>';
		if ( $has_businesses ) {
			echo '<label class="sm-membership-field">';
			echo '<span class="sm-membership-field-label">' . esc_html__( 'Business', 'super-mechanic' ) . '</span>';
			echo '<select class="sm-membership-business sm-membership-select" name="business_id" data-field="business_id">';
			echo '<option value="">' . esc_html__( 'Select business', 'super-mechanic' ) . '</option>';
			echo $business_options;
			echo '</select>';
			echo '</label>';
		} else {
			echo '<span class="sm-list-meta">' . esc_html__( 'No businesses available for assignment.', 'super-mechanic' ) . '</span>';
		}
		echo '<label class="sm-membership-field">';
		echo '<span class="sm-membership-field-label">' . esc_html__( 'Role', 'super-mechanic' ) . '</span>';
		echo '<select class="sm-membership-role sm-membership-select">' . $role_options . '</select>';
		echo '</label>';
		echo '<button type="button" class="button button-small button-primary sm-membership-action" data-action="create_membership"' . disabled( $has_businesses, false, false ) . '>' . esc_html__( 'Add membership', 'super-mechanic' ) . '</button>';
		echo '<span class="sm-membership-feedback" aria-live="polite"></span>';
		echo '</div>';

		echo '<div class="sm-membership-block sm-membership-transfer" data-user-id="' . esc_attr( (string) $user_id ) . '">';
		echo '<h4 class="sm-membership-title">' . esc_html__( 'Transfer / Move user', 'super-mechanic' ) . '</h4>';
		if ( $has_businesses ) {
			echo '<label class="sm-membership-field">';
			echo '<span class="sm-membership-field-label">' . esc_html__( 'Target business', 'super-mechanic' ) . '</span>';
			echo '<select class="sm-transfer-business sm-membership-select" name="target_business_id" data-field="target_business_id">';
			echo '<option value="">' . esc_html__( 'Select target business', 'super-mechanic' ) . '</option>';
			echo $business_options;
			echo '</select>';
			echo '</label>';
		} else {
			echo '<span class="sm-list-meta">' . esc_html__( 'No businesses available for assignment.', 'super-mechanic' ) . '</span>';
		}
		echo '<label class="sm-membership-field">';
		echo '<span class="sm-membership-field-label">' . esc_html__( 'Role', 'super-mechanic' ) . '</span>';
		echo '<select class="sm-transfer-role sm-membership-select">' . $role_options . '</select>';
		echo '</label>';
		echo '<label class="sm-membership-field">';
		echo '<span class="sm-membership-field-label">' . esc_html__( 'Mode', 'super-mechanic' ) . '</span>';
		echo '<select class="sm-transfer-mode sm-membership-select">';
		echo '<option value="replace">' . esc_html__( 'Move and replace current active business', 'super-mechanic' ) . '</option>';
		echo '<option value="add">' . esc_html__( 'Add as additional business membership', 'super-mechanic' ) . '</option>';
		echo '</select>';
		echo '</label>';
		echo '<button type="button" class="button button-small sm-membership-action" data-action="transfer"' . disabled( $has_businesses, false, false ) . '>' . esc_html__( 'Transfer / Move user', 'super-mechanic' ) . '</button>';
		echo '<span class="sm-membership-feedback" aria-live="polite"></span>';
		echo '</div>';
		echo '</div>';

		return (string) ob_get_clean();
	}

	/**
	 * Build business labels map.
	 *
	 * @param array<int,array<string,mixed>> $businesses Businesses.
	 * @return array<int,string>
	 */
	protected function build_business_labels_map( array $businesses ) {
		$labels = array();
		foreach ( $businesses as $business ) {
			if ( ! is_array( $business ) ) {
				continue;
			}

			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id <= 0 ) {
				continue;
			}

			$name = isset( $business['name'] ) ? sanitize_text_field( (string) $business['name'] ) : '';
			if ( '' !== $name ) {
				$labels[ $business_id ] = 'B' . $business_id . ' · ' . $name;
			} else {
				$labels[ $business_id ] = sprintf( 'Business #%d', $business_id );
			}
		}

		return $labels;
	}

	/**
	 * Resolve readable business label.
	 *
	 * @param int              $business_id Business ID.
	 * @param array<int,string> $business_labels Label map.
	 * @return string
	 */
	protected function resolve_business_display_label( $business_id, array $business_labels ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return __( 'Business not assigned', 'super-mechanic' );
		}

		if ( isset( $business_labels[ $business_id ] ) ) {
			return (string) $business_labels[ $business_id ];
		}

		return sprintf( 'Business #%d', $business_id );
	}

	/**
	 * Render business select options.
	 *
	 * @param array<int,array<string,mixed>> $businesses Businesses.
	 * @return string
	 */
	protected function render_business_select_options( array $businesses ) {
		$html = '';
		foreach ( $businesses as $business ) {
			if ( ! is_array( $business ) ) {
				continue;
			}
			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			$name        = isset( $business['name'] ) ? sanitize_text_field( (string) $business['name'] ) : '';
			if ( $business_id <= 0 ) {
				continue;
			}
			$label = 'B' . $business_id . ( '' !== $name ? ' · ' . $name : '' );
			$html .= '<option value="' . esc_attr( (string) $business_id ) . '">' . esc_html( $label ) . '</option>';
		}

		return $html;
	}

	/**
	 * Render role select options.
	 *
	 * @param string $selected_role Optional selected role.
	 * @return string
	 */
	protected function render_role_select_options( $selected_role = 'mechanic' ) {
		$selected_role = sanitize_key( (string) $selected_role );
		$roles         = array( 'admin', 'mechanic', 'client' );
		$html          = '';
		foreach ( $roles as $role ) {
			$html .= '<option value="' . esc_attr( $role ) . '"' . selected( $selected_role, $role, false ) . '>' . esc_html( $role ) . '</option>';
		}

		return $html;
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

	/**
	 * Render superadmin assignment/revocation controls.
	 *
	 * @return void
	 */
	protected function render_superadmin_controls_section() {
		$is_actor_superadmin = $this->role_access_service->is_global_super_admin( get_current_user_id() );
		$current_superadmins = $this->role_access_service->get_superadmin_rows();
		$eligible_admins     = $this->role_access_service->get_superadmin_eligible_admin_rows();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Superadmin assignment controls', 'super-mechanic' ) . '</h2></div>';
		echo '<p class="sm-list-meta">' . esc_html__( 'Only existing Mekvort superadmins can assign or revoke superadmin status. Only WordPress administrators are eligible.', 'super-mechanic' ) . '</p>';

		if ( ! $is_actor_superadmin ) {
			echo '<p class="sm-list-meta">' . esc_html__( 'Current user is not a Mekvort superadmin. Controls are disabled.', 'super-mechanic' ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<div class="sm-membership-panel">';
		echo '<div class="sm-membership-block sm-membership-current">';
		echo '<h4 class="sm-membership-title">' . esc_html__( 'Current Mekvort superadmins', 'super-mechanic' ) . '</h4>';
		if ( empty( $current_superadmins ) ) {
			echo '<div class="sm-list-meta">' . esc_html__( 'No superadmins detected.', 'super-mechanic' ) . '</div>';
		} else {
			echo '<div class="sm-membership-cards">';
			foreach ( $current_superadmins as $superadmin_row ) {
				if ( ! is_array( $superadmin_row ) ) {
					continue;
				}

				$user_id              = isset( $superadmin_row['user_id'] ) ? absint( $superadmin_row['user_id'] ) : 0;
				$display_name         = isset( $superadmin_row['display_name'] ) ? sanitize_text_field( (string) $superadmin_row['display_name'] ) : '';
				$user_email           = isset( $superadmin_row['user_email'] ) ? sanitize_email( (string) $superadmin_row['user_email'] ) : '';
				$is_bootstrap_superadmin = ! empty( $superadmin_row['is_bootstrap_superadmin'] );

				echo '<div class="sm-membership-item">';
				echo '<div class="sm-membership-meta">';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'User:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html( $display_name ) . ' (#' . esc_html( (string) $user_id ) . ')</span></div>';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Email:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html( $user_email ) . '</span></div>';
				echo '<div class="sm-membership-line"><span class="sm-membership-label">' . esc_html__( 'Type:', 'super-mechanic' ) . '</span> <span class="sm-membership-value">' . esc_html( $is_bootstrap_superadmin ? __( 'Locked bootstrap superadmin', 'super-mechanic' ) : __( 'Managed superadmin', 'super-mechanic' ) ) . '</span></div>';
				echo '</div>';
				echo '<div class="sm-membership-item-actions">';
				if ( ! $is_bootstrap_superadmin ) {
					echo '<form method="post" class="sm-inline-form">';
					wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
					echo '<input type="hidden" name="sm_roles_access_action" value="revoke_superadmin" />';
					echo '<input type="hidden" name="user_id" value="' . esc_attr( (string) $user_id ) . '" />';
					echo '<button type="submit" class="button button-secondary button-small">' . esc_html__( 'Revoke superadmin', 'super-mechanic' ) . '</button>';
					echo '</form>';
				} else {
					echo '<span class="sm-list-meta">' . esc_html__( 'Locked baseline user cannot be revoked here.', 'super-mechanic' ) . '</span>';
				}
				echo '</div>';
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		echo '<div class="sm-membership-block sm-membership-add">';
		echo '<h4 class="sm-membership-title">' . esc_html__( 'Assign Mekvort superadmin', 'super-mechanic' ) . '</h4>';
		if ( empty( $eligible_admins ) ) {
			echo '<span class="sm-list-meta">' . esc_html__( 'No eligible WordPress administrators available for promotion.', 'super-mechanic' ) . '</span>';
		} else {
			echo '<form method="post">';
			wp_nonce_field( 'sm_roles_access_update', 'sm_roles_access_nonce' );
			echo '<input type="hidden" name="sm_roles_access_action" value="assign_superadmin" />';
			echo '<label class="sm-membership-field">';
			echo '<span class="sm-membership-field-label">' . esc_html__( 'WordPress administrator', 'super-mechanic' ) . '</span>';
			echo '<select class="sm-membership-select" name="user_id">';
			foreach ( $eligible_admins as $eligible_row ) {
				if ( ! is_array( $eligible_row ) ) {
					continue;
				}
				$user_id      = isset( $eligible_row['user_id'] ) ? absint( $eligible_row['user_id'] ) : 0;
				$display_name = isset( $eligible_row['display_name'] ) ? sanitize_text_field( (string) $eligible_row['display_name'] ) : '';
				$user_email   = isset( $eligible_row['user_email'] ) ? sanitize_email( (string) $eligible_row['user_email'] ) : '';
				if ( $user_id <= 0 ) {
					continue;
				}
				$label = $display_name . ' (' . $user_email . ')';
				echo '<option value="' . esc_attr( (string) $user_id ) . '">' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			echo '</label>';
			echo '<button type="submit" class="button button-primary button-small">' . esc_html__( 'Assign superadmin', 'super-mechanic' ) . '</button>';
			echo '</form>';
		}
		echo '</div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render business label.
	 *
	 * @param int  $business_id Business ID.
	 * @param bool $is_global Is global.
	 * @return string
	 */
	protected function format_business_label( $business_id, $is_global ) {
		if ( $is_global ) {
			return __( 'Global scope', 'super-mechanic' );
		}
		if ( $business_id <= 0 ) {
			return __( 'No membership assigned', 'super-mechanic' );
		}

		return (string) $business_id;
	}
}
