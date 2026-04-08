<?php
/**
 * Demo admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Demo\Demo_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Handles demo mode admin UI and orchestration actions.
 */
class Demo_Admin_Controller {
	/**
	 * Demo service dependency.
	 *
	 * @var Demo_Service
	 */
	protected $demo_service;

	/**
	 * Constructor.
	 *
	 * @param Demo_Service|null $demo_service Demo service.
	 */
	public function __construct( Demo_Service $demo_service = null ) {
		$this->demo_service = $demo_service ? $demo_service : new Demo_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 110 );
		add_action( 'admin_post_sm_54d_demo_toggle', array( $this, 'handle_toggle_demo_mode' ) );
		add_action( 'admin_post_sm_54d_demo_seed', array( $this, 'handle_seed_demo_dataset' ) );
		add_action( 'in_admin_header', array( $this, 'render_demo_mode_banner' ) );
	}

	/**
	 * Register demo submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Demo', 'super-mechanic' ),
			__( 'Demo', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-demo',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render demo page.
	 *
	 * @return void
	 */
	public function render_page() {
		$this->assert_permission();

		$business_id = isset( $_GET['business_id'] ) ? absint( wp_unslash( $_GET['business_id'] ) ) : 0;
		$state       = $this->demo_service->get_demo_state( $business_id );
		$dataset     = isset( $state['dataset'] ) && is_array( $state['dataset'] ) ? $state['dataset'] : array();
		$is_demo     = ! empty( $state['is_demo_mode'] );

		echo '<div class="wrap sm-admin-shell sm-demo-page">';
		echo '<h1>' . esc_html__( 'Demo', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Commercial readiness controls: demo mode, demo dataset and safe sensitive-data masking.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_status_section( $state, $is_demo );
		$this->render_actions_section( $state, $is_demo );
		$this->render_dataset_section( $dataset );
		$this->render_masking_section();

		echo '</div>';
	}

	/**
	 * Handle demo mode toggle action.
	 *
	 * @return void
	 */
	public function handle_toggle_demo_mode() {
		$this->assert_permission();
		check_admin_referer( 'sm_54d_demo_toggle' );

		$toggle = isset( $_POST['toggle_state'] ) ? sanitize_key( (string) wp_unslash( $_POST['toggle_state'] ) ) : 'disable';
		if ( 'enable' === $toggle ) {
			$this->demo_service->enable_demo_mode();
			$this->redirect_with_notice( 'success', __( 'Demo mode enabled.', 'super-mechanic' ) );
		}

		$this->demo_service->disable_demo_mode();
		$this->redirect_with_notice( 'success', __( 'Demo mode disabled.', 'super-mechanic' ) );
	}

	/**
	 * Handle demo dataset seed action.
	 *
	 * @return void
	 */
	public function handle_seed_demo_dataset() {
		$this->assert_permission();
		check_admin_referer( 'sm_54d_demo_seed' );

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		$result      = $this->demo_service->seed_demo_dataset( $business_id );

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message(), $business_id );
		}

		$this->redirect_with_notice( 'success', __( 'Demo dataset seeded successfully.', 'super-mechanic' ), $business_id );
	}

	/**
	 * Render demo mode banner on plugin pages.
	 *
	 * @return void
	 */
	public function render_demo_mode_banner() {
		if ( ! $this->is_plugin_page() ) {
			return;
		}
		if ( ! $this->demo_service->is_demo_mode() ) {
			return;
		}

		echo '<div class="sm-demo-mode-banner">';
		echo '<div class="sm-demo-mode-banner-inner">';
		echo '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Demo Mode', 'super-mechanic' ) . '</span>';
		echo '<p>' . esc_html__( 'Sensitive values may be masked and demo dataset is active for showcase usage.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render status card.
	 *
	 * @param array<string,mixed> $state Demo state.
	 * @param bool                $is_demo Demo status.
	 * @return void
	 */
	protected function render_status_section( array $state, $is_demo ) {
		$meta = isset( $state['meta'] ) && is_array( $state['meta'] ) ? $state['meta'] : array();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Demo mode state', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-grid-cards sm-grid-cards-compact">';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'Current state', 'super-mechanic' ) . '</div><div class="sm-kpi-value">' . esc_html( $is_demo ? __( 'Active', 'super-mechanic' ) : __( 'Inactive', 'super-mechanic' ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'First enabled', 'super-mechanic' ) . '</div><div class="sm-kpi-value sm-kpi-value-small">' . esc_html( isset( $meta['enabled_first_at'] ) ? (string) $meta['enabled_first_at'] : '-' ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">' . esc_html__( 'Last seeded', 'super-mechanic' ) . '</div><div class="sm-kpi-value sm-kpi-value-small">' . esc_html( isset( $meta['last_seed_at'] ) ? (string) $meta['last_seed_at'] : '-' ) . '</div></div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render action forms.
	 *
	 * @param array<string,mixed> $state Demo state.
	 * @param bool                $is_demo Demo status.
	 * @return void
	 */
	protected function render_actions_section( array $state, $is_demo ) {
		$business_id = isset( $state['business_id'] ) ? absint( $state['business_id'] ) : 0;

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Actions', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-form-actions">';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_54d_demo_toggle' );
		echo '<input type="hidden" name="action" value="sm_54d_demo_toggle" />';
		echo '<input type="hidden" name="toggle_state" value="' . esc_attr( $is_demo ? 'disable' : 'enable' ) . '" />';
		echo '<button type="submit" class="button button-primary">' . esc_html( $is_demo ? __( 'Disable Demo Mode', 'super-mechanic' ) : __( 'Enable Demo Mode', 'super-mechanic' ) ) . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sm-inline-form">';
		wp_nonce_field( 'sm_54d_demo_seed' );
		echo '<input type="hidden" name="action" value="sm_54d_demo_seed" />';
		echo '<label>';
		echo '<span>' . esc_html__( 'Business ID (optional)', 'super-mechanic' ) . '</span>';
		echo '<input type="number" min="0" name="business_id" value="' . esc_attr( (string) $business_id ) . '" />';
		echo '</label>';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Seed demo dataset', 'super-mechanic' ) . '</button>';
		echo '</form>';

		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render dataset summary.
	 *
	 * @param array<string,mixed> $dataset Dataset payload.
	 * @return void
	 */
	protected function render_dataset_section( array $dataset ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Demo dataset summary', 'super-mechanic' ) . '</h2></div>';

		if ( empty( $dataset['available'] ) ) {
			echo '<p class="sm-empty">' . esc_html( isset( $dataset['message'] ) ? (string) $dataset['message'] : __( 'No demo dataset available.', 'super-mechanic' ) ) . '</p>';
			echo '</section>';
			return;
		}

		echo '<div class="sm-grid-cards sm-grid-cards-compact">';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Business ID</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['business_id'] ) ? $dataset['business_id'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Clients</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['clients'] ) ? $dataset['clients'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Vehicles</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['vehicles'] ) ? $dataset['vehicles'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Processes</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['processes'] ) ? $dataset['processes'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Quotes</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['quotes'] ) ? $dataset['quotes'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Invoices</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['invoices'] ) ? $dataset['invoices'] : 0 ) ) . '</div></div>';
		echo '<div class="sm-kpi-card"><div class="sm-kpi-label">Payments</div><div class="sm-kpi-value">' . esc_html( (string) absint( isset( $dataset['payments'] ) ? $dataset['payments'] : 0 ) ) . '</div></div>';
		echo '</div>';
		echo '</section>';
	}

	/**
	 * Render masking helper section.
	 *
	 * @return void
	 */
	protected function render_masking_section() {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Sensitive data masking helper', 'super-mechanic' ) . '</h2></div>';
		echo '<p class="sm-card-copy">' . esc_html__( 'Preview value (auto-masked when Demo Mode is active):', 'super-mechanic' ) . ' <code>' . esc_html( $this->demo_service->mask_sensitive_value( 'john.doe@supermechanic.local' ) ) . '</code></p>';
		echo '</section>';
	}

	/**
	 * Render query notice.
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
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @param int    $business_id Business ID.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message, $business_id = 0 ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'super-mechanic-demo',
					'business_id'       => absint( $business_id ),
					'sm_notice_type'    => sanitize_key( (string) $type ),
					'sm_notice_message' => sanitize_text_field( (string) $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Check plugin page scope.
	 *
	 * @return bool
	 */
	protected function is_plugin_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		return '' !== $page && 0 === strpos( $page, 'super-mechanic' );
	}
}
