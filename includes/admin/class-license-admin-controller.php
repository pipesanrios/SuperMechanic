<?php
/**
 * License admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Licensing\License_Service;
use Super_Mechanic\Licensing\Plan_Limits_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders local license page.
 */
class License_Admin_Controller {
	/**
	 * Service dependency.
	 *
	 * @var License_Service
	 */
	protected $license_service;

	/**
	 * Plan limits service.
	 *
	 * @var Plan_Limits_Service
	 */
	protected $plan_limits_service;

	/**
	 * Constructor.
	 *
	 * @param License_Service|null $license_service Service dependency.
	 */
	public function __construct( License_Service $license_service = null ) {
		$this->license_service     = $license_service ? $license_service : new License_Service();
		$this->plan_limits_service = new Plan_Limits_Service( $this->license_service );
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 107 );
		add_action( 'admin_post_sm_51a_license_activate', array( $this, 'handle_activate' ) );
		add_action( 'admin_post_sm_51a_license_deactivate', array( $this, 'handle_deactivate' ) );
		add_action( 'admin_post_sm_55e2_license_start_trial', array( $this, 'handle_start_trial' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'License', 'super-mechanic' ),
			__( 'License', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-license',
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

		$license         = $this->license_service->get_license();
		$status          = isset( $license['license_status'] ) ? (string) $license['license_status'] : 'inactive';
		$valid           = $this->license_service->is_license_valid_for_current_site();
		$effective_state = $this->license_service->get_effective_license_state();
		$trial_state     = $this->license_service->get_trial_state();
		$plan            = $this->plan_limits_service->get_current_plan_type();
		$limits          = $this->plan_limits_service->get_plan_limits( $plan );
		$usage           = $this->plan_limits_service->get_current_usage();
		$exceed          = $this->plan_limits_service->get_exceeded_limits();

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'License', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Local license activation and status for this installation.', 'super-mechanic' ) . '</p>';

		$this->render_notice();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Current license state', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-filter-grid">';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'License key', 'super-mechanic' ) . '</label><div>' . esc_html( $this->mask_license_key( isset( $license['license_key'] ) ? (string) $license['license_key'] : '' ) ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Status', 'super-mechanic' ) . '</label><div>' . esc_html( $status ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Effective state', 'super-mechanic' ) . '</label><div>' . esc_html( $effective_state ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Domain', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $license['domain'] ) ? (string) $license['domain'] : '' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Plan', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $license['plan_type'] ) ? (string) $license['plan_type'] : 'starter' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Expires at', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $license['expires_at'] ) ? (string) $license['expires_at'] : '' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Activated at', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $license['activated_at'] ) ? (string) $license['activated_at'] : '' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Current site domain', 'super-mechanic' ) . '</label><div>' . esc_html( $this->license_service->get_current_domain() ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Valid for current site', 'super-mechanic' ) . '</label><div>' . esc_html( $valid ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Trial start', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $trial_state['trial_start_at'] ) ? (string) $trial_state['trial_start_at'] : '' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Trial end', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $trial_state['trial_end_at'] ) ? (string) $trial_state['trial_end_at'] : '' ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Trial active', 'super-mechanic' ) . '</label><div>' . esc_html( ! empty( $trial_state['is_active'] ) ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Trial days remaining', 'super-mechanic' ) . '</label><div>' . esc_html( isset( $trial_state['days_remaining'] ) ? (string) absint( $trial_state['days_remaining'] ) : '0' ) . '</div></div>';
		echo '</div>';
		echo '</section>';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Plan limits and usage', 'super-mechanic' ) . '</h2></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Current plan limits with non-blocking visibility. Exceeded resources are shown as warnings only.', 'super-mechanic' ) . '</p>';
		if ( ! $this->license_service->is_license_active() ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'No active license detected. Starter limits are used as fallback.', 'super-mechanic' ) . '</p></div>';
		}
		if ( ! empty( $exceed ) ) {
			echo '<div class="notice notice-warning inline"><p>' . esc_html__( 'Some resources are currently above plan limits (warning only, no hard block).', 'super-mechanic' ) . '</p></div>';
		}
		if ( in_array( $effective_state, array( 'inactive', 'expired', 'revoked' ), true ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html__( 'Creation operations are currently restricted by effective license state.', 'super-mechanic' ) . '</p></div>';
		}

		echo '<div class="sm-filter-grid">';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Effective plan', 'super-mechanic' ) . '</label><div>' . esc_html( $plan ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Exceeded resources', 'super-mechanic' ) . '</label><div>' . esc_html( empty( $exceed ) ? '0' : (string) count( $exceed ) ) . '</div></div>';
		echo '<div class="sm-filter-field"><label>' . esc_html__( 'Effective license state', 'super-mechanic' ) . '</label><div>' . esc_html( $effective_state ) . '</div></div>';
		echo '</div>';

		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Resource', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Limit', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Current usage', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		$resource_map = $this->plan_limits_service->get_resource_map();
		foreach ( $resource_map as $resource_key => $config ) {
			$resource_key = sanitize_key( (string) $resource_key );
			$status_row   = $this->plan_limits_service->get_limit_status( $resource_key );
			$resource     = isset( $config['label'] ) ? (string) $config['label'] : $resource_key;
			$limit_key    = isset( $config['limit_key'] ) ? (string) $config['limit_key'] : '';
			$limit_raw    = '' !== $limit_key && array_key_exists( $limit_key, $limits ) ? $limits[ $limit_key ] : null;
			$limit_label  = null === $limit_raw ? __( 'Unlimited', 'super-mechanic' ) : (string) absint( $limit_raw );
			$used_value   = isset( $usage[ $resource_key ] ) ? absint( $usage[ $resource_key ] ) : 0;
			$status_label = ! empty( $status_row['is_exceeded'] ) ? __( 'Exceeded', 'super-mechanic' ) : __( 'Within limit', 'super-mechanic' );

			echo '<tr>';
			echo '<td>' . esc_html( $resource ) . '</td>';
			echo '<td>' . esc_html( $limit_label ) . '</td>';
			echo '<td>' . esc_html( (string) $used_value ) . '</td>';
			echo '<td>' . wp_kses_post( $this->render_limit_badge( ! empty( $status_row['is_exceeded'] ), $status_label ) ) . '</td>';
			echo '</tr>';
		}

		echo '</tbody></table>';
		echo '</div>';
		echo '</section>';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Trial', 'super-mechanic' ) . '</h2></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Start/restart a local trial window. Reads remain available when expired; creation is controlled by limits/state.', 'super-mechanic' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_55e2_license_start_trial' );
		echo '<input type="hidden" name="action" value="sm_55e2_license_start_trial" />';
		echo '<div class="sm-filter-grid">';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Trial days', 'super-mechanic' ) . '</span><input type="number" min="1" max="90" name="trial_days" value="14" /></label>';
		echo '</div>';
		echo '<div class="sm-form-actions"><button type="submit" class="button button-secondary">' . esc_html__( 'Start trial', 'super-mechanic' ) . '</button></div>';
		echo '</form>';
		echo '</section>';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Activate / Save', 'super-mechanic' ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sm-license-form">';
		wp_nonce_field( 'sm_51a_license_activate' );
		echo '<input type="hidden" name="action" value="sm_51a_license_activate" />';
		echo '<div class="sm-filter-grid">';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'License key', 'super-mechanic' ) . '</span><input type="text" name="license_key" value="" required /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Plan type', 'super-mechanic' ) . '</span>';
		echo '<select name="plan_type">';
		echo '<option value="starter">starter</option>';
		echo '<option value="pro">pro</option>';
		echo '<option value="enterprise">enterprise</option>';
		echo '</select></label>';
		echo '</div>';
		echo '<div class="sm-form-actions"><button type="submit" class="button button-primary">' . esc_html__( 'Activate / Save', 'super-mechanic' ) . '</button></div>';
		echo '</form>';
		echo '</section>';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Deactivate', 'super-mechanic' ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_51a_license_deactivate' );
		echo '<input type="hidden" name="action" value="sm_51a_license_deactivate" />';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Deactivate', 'super-mechanic' ) . '</button>';
		echo '</form>';
		echo '</section>';
		echo '</div>';
	}

	/**
	 * Handle activation.
	 *
	 * @return void
	 */
	public function handle_activate() {
		$this->assert_permission();
		check_admin_referer( 'sm_51a_license_activate' );

		$license_key = isset( $_POST['license_key'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['license_key'] ) ) : '';
		$plan_type   = isset( $_POST['plan_type'] ) ? sanitize_key( (string) wp_unslash( $_POST['plan_type'] ) ) : 'starter';
		$result      = $this->license_service->activate_license( $license_key, $plan_type );

		$this->redirect_with_notice(
			! empty( $result['success'] ) ? 'success' : 'error',
			isset( $result['message'] ) ? (string) $result['message'] : __( 'License update finished.', 'super-mechanic' )
		);
	}

	/**
	 * Handle deactivation.
	 *
	 * @return void
	 */
	public function handle_deactivate() {
		$this->assert_permission();
		check_admin_referer( 'sm_51a_license_deactivate' );

		$result = $this->license_service->deactivate_license();
		$this->redirect_with_notice(
			! empty( $result['success'] ) ? 'success' : 'error',
			isset( $result['message'] ) ? (string) $result['message'] : __( 'License update finished.', 'super-mechanic' )
		);
	}

	/**
	 * Handle trial start action.
	 *
	 * @return void
	 */
	public function handle_start_trial() {
		$this->assert_permission();
		check_admin_referer( 'sm_55e2_license_start_trial' );

		$days   = isset( $_POST['trial_days'] ) ? absint( wp_unslash( $_POST['trial_days'] ) ) : 14;
		$result = $this->license_service->start_trial( $days );

		$this->redirect_with_notice(
			! empty( $result['success'] ) ? 'success' : 'error',
			isset( $result['message'] ) ? (string) $result['message'] : __( 'Trial update finished.', 'super-mechanic' )
		);
	}

	/**
	 * Render page notice.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$type    = isset( $_GET['sm_notice_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_type'] ) ) : '';
		$message = isset( $_GET['sm_notice_message'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['sm_notice_message'] ) ) : '';
		if ( '' === $type || '' === $message ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( 'success' === $type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
	}

	/**
	 * Ensure capability.
	 *
	 * @return void
	 */
	protected function assert_permission() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}
	}

	/**
	 * Redirect with notice.
	 *
	 * @param string $type Type.
	 * @param string $message Message.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'super-mechanic-license',
					'sm_notice_type'    => sanitize_key( (string) $type ),
					'sm_notice_message' => sanitize_text_field( (string) $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Mask key for safe display.
	 *
	 * @param string $license_key License key.
	 * @return string
	 */
	protected function mask_license_key( $license_key ) {
		$license_key = trim( (string) $license_key );
		if ( '' === $license_key ) {
			return '-';
		}

		$length = strlen( $license_key );
		if ( $length <= 4 ) {
			return str_repeat( '*', $length );
		}

		return str_repeat( '*', $length - 4 ) . substr( $license_key, -4 );
	}

	/**
	 * Render limit status badge.
	 *
	 * @param bool   $is_exceeded Exceeded flag.
	 * @param string $label Label.
	 * @return string
	 */
	protected function render_limit_badge( $is_exceeded, $label ) {
		if ( $is_exceeded ) {
			return '<span class="sm-badge sm-badge-warning">' . esc_html( $label ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-success">' . esc_html( $label ) . '</span>';
	}
}
