<?php
/**
 * Onboarding admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Onboarding\Onboarding_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders onboarding checklist and recommendations.
 */
class Onboarding_Admin_Controller {
	/**
	 * Onboarding service.
	 *
	 * @var Onboarding_Service
	 */
	protected $onboarding_service;

	/**
	 * Constructor.
	 *
	 * @param Onboarding_Service|null $onboarding_service Service dependency.
	 */
	public function __construct( Onboarding_Service $onboarding_service = null ) {
		$this->onboarding_service = $onboarding_service ? $onboarding_service : new Onboarding_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 109 );
		add_action( 'admin_post_sm_51d_onboarding_mark_complete', array( $this, 'handle_mark_complete' ) );
		add_action( 'admin_post_sm_51d_onboarding_reset', array( $this, 'handle_reset' ) );
		add_action( 'admin_notices', array( $this, 'render_incomplete_notice' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Onboarding', 'super-mechanic' ),
			__( 'Onboarding', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-onboarding',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render onboarding page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$state = $this->onboarding_service->get_onboarding_state();
		$next  = isset( $state['next_step'] ) && is_array( $state['next_step'] ) ? $state['next_step'] : array();

		echo '<div class="wrap sm-admin-shell sm-onboarding-page">';
		echo '<h1>' . esc_html__( 'Onboarding', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Checklist and recommended next step for base setup activation.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_checklist( $state );
		$this->render_next_step( $next );
		$this->render_actions( $state );

		echo '</div>';
	}

	/**
	 * Render onboarding checklist.
	 *
	 * @param array<string,mixed> $state State payload.
	 * @return void
	 */
	protected function render_checklist( array $state ) {
		$items = array(
			array(
				'label' => __( 'License configured', 'super-mechanic' ),
				'key'   => 'has_license',
				'url'   => admin_url( 'admin.php?page=super-mechanic-license' ),
			),
			array(
				'label' => __( 'Branding basics configured', 'super-mechanic' ),
				'key'   => 'has_branding_basic',
				'url'   => admin_url( 'admin.php?page=super-mechanic-branding' ),
			),
			array(
				'label' => __( 'Active business available', 'super-mechanic' ),
				'key'   => 'has_business',
				'url'   => admin_url( 'admin.php?page=super-mechanic-businesses' ),
			),
			array(
				'label' => __( 'Business admin access available', 'super-mechanic' ),
				'key'   => 'has_business_admin',
				'url'   => admin_url( 'admin.php?page=super-mechanic-roles' ),
			),
		);

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Setup checklist', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-table-wrap"><table class="sm-table"><thead><tr>';
		echo '<th>' . esc_html__( 'Step', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Action', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $items as $item ) {
			$key   = isset( $item['key'] ) ? sanitize_key( (string) $item['key'] ) : '';
			$ok    = ! empty( $state[ $key ] );
			$label = isset( $item['label'] ) ? (string) $item['label'] : $key;
			$url   = isset( $item['url'] ) ? esc_url( (string) $item['url'] ) : '#';

			echo '<tr>';
			echo '<td>' . esc_html( $label ) . '</td>';
			echo '<td>' . wp_kses_post( $this->render_state_badge( $ok ) ) . '</td>';
			echo '<td><a class="button button-secondary button-small" href="' . $url . '">' . esc_html__( 'Open', 'super-mechanic' ) . '</a></td>';
			echo '</tr>';
		}

		echo '</tbody></table></div>';
		echo '</section>';
	}

	/**
	 * Render next recommended step.
	 *
	 * @param array<string,mixed> $next_step Next step.
	 * @return void
	 */
	protected function render_next_step( array $next_step ) {
		$label       = isset( $next_step['label'] ) ? (string) $next_step['label'] : __( 'No next step', 'super-mechanic' );
		$description = isset( $next_step['description'] ) ? (string) $next_step['description'] : '';
		$url         = isset( $next_step['url'] ) ? esc_url( (string) $next_step['url'] ) : '';

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Next recommended step', 'super-mechanic' ) . '</h2></div>';
		echo '<p><strong>' . esc_html( $label ) . '</strong></p>';
		if ( '' !== $description ) {
			echo '<p class="sm-card-copy">' . esc_html( $description ) . '</p>';
		}
		if ( '' !== $url ) {
			echo '<p><a class="button button-primary" href="' . $url . '">' . esc_html__( 'Go to next step', 'super-mechanic' ) . '</a></p>';
		}
		echo '</section>';
	}

	/**
	 * Render manual onboarding actions.
	 *
	 * @param array<string,mixed> $state State payload.
	 * @return void
	 */
	protected function render_actions( array $state ) {
		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Onboarding state actions', 'super-mechanic' ) . '</h2></div>';
		echo '<div class="sm-form-actions">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_51d_onboarding_mark_complete' );
		echo '<input type="hidden" name="action" value="sm_51d_onboarding_mark_complete" />';
		echo '<button type="submit" class="button button-primary">' . esc_html__( 'Mark onboarding complete', 'super-mechanic' ) . '</button>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'sm_51d_onboarding_reset' );
		echo '<input type="hidden" name="action" value="sm_51d_onboarding_reset" />';
		echo '<button type="submit" class="button button-secondary">' . esc_html__( 'Reset onboarding state', 'super-mechanic' ) . '</button>';
		echo '</form>';
		echo '</div>';

		if ( ! empty( $state['manual_completed_at'] ) ) {
			echo '<p class="sm-card-copy">' . esc_html__( 'Manual completion marked at:', 'super-mechanic' ) . ' ' . esc_html( (string) $state['manual_completed_at'] ) . '</p>';
		}
		echo '</section>';
	}

	/**
	 * Handle mark complete action.
	 *
	 * @return void
	 */
	public function handle_mark_complete() {
		$this->assert_permission();
		check_admin_referer( 'sm_51d_onboarding_mark_complete' );
		$this->onboarding_service->mark_onboarding_complete();
		$this->redirect_with_notice( 'success', __( 'Onboarding marked as complete.', 'super-mechanic' ) );
	}

	/**
	 * Handle reset action.
	 *
	 * @return void
	 */
	public function handle_reset() {
		$this->assert_permission();
		check_admin_referer( 'sm_51d_onboarding_reset' );
		$this->onboarding_service->reset_onboarding_state();
		$this->redirect_with_notice( 'success', __( 'Onboarding state reset.', 'super-mechanic' ) );
	}

	/**
	 * Render incomplete onboarding notice.
	 *
	 * @return void
	 */
	public function render_incomplete_notice() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			return;
		}
		if ( $this->is_onboarding_page() ) {
			return;
		}
		if ( ! $this->is_plugin_page() ) {
			return;
		}
		if ( $this->onboarding_service->is_onboarding_complete() ) {
			return;
		}

		$url = esc_url( admin_url( 'admin.php?page=super-mechanic-onboarding' ) );
		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Onboarding is not complete yet.', 'super-mechanic' ) . ' ';
		echo '<a href="' . $url . '">' . esc_html__( 'Review setup checklist', 'super-mechanic' ) . '</a>';
		echo '</p></div>';
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
	 * @param string $type Notice type.
	 * @param string $message Notice message.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'super-mechanic-onboarding',
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

	/**
	 * Check onboarding page.
	 *
	 * @return bool
	 */
	protected function is_onboarding_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		return 'super-mechanic-onboarding' === $page;
	}

	/**
	 * Render step badge.
	 *
	 * @param bool $ok Step state.
	 * @return string
	 */
	protected function render_state_badge( $ok ) {
		if ( $ok ) {
			return '<span class="sm-badge sm-badge-success">' . esc_html__( 'Complete', 'super-mechanic' ) . '</span>';
		}

		return '<span class="sm-badge sm-badge-warning">' . esc_html__( 'Pending', 'super-mechanic' ) . '</span>';
	}
}
