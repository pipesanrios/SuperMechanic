<?php
/**
 * Branding admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Branding\Branding_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders and applies white-label settings in plugin admin pages.
 */
class Branding_Admin_Controller {
	/**
	 * Branding service.
	 *
	 * @var Branding_Service
	 */
	protected $branding_service;

	/**
	 * Constructor.
	 *
	 * @param Branding_Service|null $branding_service Service dependency.
	 */
	public function __construct( Branding_Service $branding_service = null ) {
		$this->branding_service = $branding_service ? $branding_service : new Branding_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 108 );
		add_action( 'admin_post_sm_51b_branding_save', array( $this, 'handle_save' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_head', array( $this, 'render_branding_runtime_styles' ) );
		add_action( 'in_admin_header', array( $this, 'render_branding_banner' ) );
		add_filter( 'admin_footer_text', array( $this, 'filter_admin_footer_text' ) );
	}

	/**
	 * Enqueue branding page assets.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		if ( ! $this->is_branding_page() ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script(
			'sm-admin-branding',
			SM_PLUGIN_URL . 'assets/js/admin-branding.js',
			array( 'wp-color-picker', 'jquery' ),
			defined( 'SM_PLUGIN_VERSION' ) ? SM_PLUGIN_VERSION : false,
			true
		);
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Branding', 'super-mechanic' ),
			__( 'Branding', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-branding',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$settings = $this->branding_service->get_settings();

		echo '<div class="wrap sm-admin-shell sm-branding-page">';
		echo '<h1>' . esc_html__( 'Branding', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Configure base white-label settings for Super Mechanic admin pages.', 'super-mechanic' ) . '</p>';
		$this->render_notice();

		echo '<section class="sm-card sm-section">';
		echo '<div class="sm-section-heading"><h2>' . esc_html__( 'Brand settings', 'super-mechanic' ) . '</h2></div>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="sm-branding-form">';
		wp_nonce_field( 'sm_51b_branding_save' );
		echo '<input type="hidden" name="action" value="sm_51b_branding_save" />';
		echo '<div class="sm-filter-grid">';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'System name', 'super-mechanic' ) . '</span><input type="text" name="system_name" value="' . esc_attr( isset( $settings['system_name'] ) ? (string) $settings['system_name'] : '' ) . '" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Logo URL', 'super-mechanic' ) . '</span><input type="url" name="logo_url" value="' . esc_attr( isset( $settings['logo_url'] ) ? (string) $settings['logo_url'] : '' ) . '" placeholder="https://example.com/logo.png" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Logo attachment ID (optional)', 'super-mechanic' ) . '</span><input type="number" min="0" step="1" name="logo_attachment_id" value="' . esc_attr( (string) ( isset( $settings['logo_attachment_id'] ) ? absint( $settings['logo_attachment_id'] ) : 0 ) ) . '" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Primary color', 'super-mechanic' ) . '</span><input type="text" name="primary_color" class="sm-color-picker" value="' . esc_attr( isset( $settings['primary_color'] ) ? (string) $settings['primary_color'] : '' ) . '" placeholder="#2271b1" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Secondary color', 'super-mechanic' ) . '</span><input type="text" name="secondary_color" class="sm-color-picker" value="' . esc_attr( isset( $settings['secondary_color'] ) ? (string) $settings['secondary_color'] : '' ) . '" placeholder="#135e96" /></label>';
		echo '<label class="sm-filter-field"><span>' . esc_html__( 'Admin footer text', 'super-mechanic' ) . '</span><input type="text" name="admin_footer_text" value="' . esc_attr( isset( $settings['admin_footer_text'] ) ? (string) $settings['admin_footer_text'] : '' ) . '" /></label>';
		echo '</div>';
		echo '<div class="sm-form-actions"><button type="submit" class="button button-primary">' . esc_html__( 'Save branding', 'super-mechanic' ) . '</button></div>';
		echo '</form>';
		echo '</section>';

		echo '</div>';
	}

	/**
	 * Handle save action.
	 *
	 * @return void
	 */
	public function handle_save() {
		$this->assert_permission();
		check_admin_referer( 'sm_51b_branding_save' );

		$payload = array(
			'system_name'        => isset( $_POST['system_name'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['system_name'] ) ) : '',
			'logo_url'           => isset( $_POST['logo_url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['logo_url'] ) ) : '',
			'logo_attachment_id' => isset( $_POST['logo_attachment_id'] ) ? absint( wp_unslash( $_POST['logo_attachment_id'] ) ) : 0,
			'primary_color'      => isset( $_POST['primary_color'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['primary_color'] ) ) : '',
			'secondary_color'    => isset( $_POST['secondary_color'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['secondary_color'] ) ) : '',
			'admin_footer_text'  => isset( $_POST['admin_footer_text'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['admin_footer_text'] ) ) : '',
		);

		$this->branding_service->save_settings( $payload );
		$this->redirect_with_notice( 'success', __( 'Branding settings saved successfully.', 'super-mechanic' ) );
	}

	/**
	 * Render plugin admin runtime variables.
	 *
	 * @return void
	 */
	public function render_branding_runtime_styles() {
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		$primary   = sanitize_hex_color( $this->branding_service->get_primary_color() );
		$secondary = sanitize_hex_color( (string) $this->branding_service->get( 'secondary_color', '#135e96' ) );
		$primary   = $primary ? $primary : '#2271b1';
		$secondary = $secondary ? $secondary : '#135e96';

		echo '<style id="sm-branding-runtime">';
		echo '.sm-admin-shell{--sm-primary:' . esc_attr( $primary ) . ';--sm-primary-strong:' . esc_attr( $secondary ) . ';}';
		echo '.sm-admin-shell > h1{color:var(--sm-primary-strong);}';
		echo '.sm-branding-banner{background:#fff;border:1px solid #dbe3f0;border-radius:14px;margin:14px 20px 0 2px;padding:10px 14px;}';
		echo '.sm-branding-banner-inner{display:flex;gap:12px;align-items:center;}';
		echo '.sm-branding-logo{width:28px;height:28px;object-fit:contain;border-radius:6px;}';
		echo '.sm-branding-system-name{font-size:14px;font-weight:700;color:var(--sm-primary-strong);}';
		echo '.sm-branding-footer{font-size:12px;color:#5f6b85;}';
		echo '</style>';
	}

	/**
	 * Render top brand banner in plugin pages.
	 *
	 * @return void
	 */
	public function render_branding_banner() {
		if ( ! $this->is_plugin_page() ) {
			return;
		}

		$system_name = $this->branding_service->get_system_name();
		$logo_url    = $this->branding_service->get_logo_url();
		$footer_text = sanitize_text_field( (string) $this->branding_service->get( 'admin_footer_text', '' ) );

		echo '<div class="sm-branding-banner">';
		echo '<div class="sm-branding-banner-inner">';
		if ( '' !== $logo_url ) {
			echo '<img class="sm-branding-logo" src="' . esc_url( $logo_url ) . '" alt="' . esc_attr__( 'Brand logo', 'super-mechanic' ) . '" />';
		}
		echo '<div class="sm-branding-copy">';
		echo '<div class="sm-branding-system-name">' . esc_html( $system_name ) . '</div>';
		if ( '' !== $footer_text ) {
			echo '<div class="sm-branding-footer">' . esc_html( $footer_text ) . '</div>';
		}
		echo '</div>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Replace admin footer text on plugin pages when configured.
	 *
	 * @param string $footer_text Existing footer text.
	 * @return string
	 */
	public function filter_admin_footer_text( $footer_text ) {
		if ( ! $this->is_plugin_page() ) {
			return $footer_text;
		}

		$custom_footer = sanitize_text_field( (string) $this->branding_service->get( 'admin_footer_text', '' ) );
		if ( '' === $custom_footer ) {
			return $footer_text;
		}

		return $custom_footer;
	}

	/**
	 * Render query-string notice.
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
					'page'              => 'super-mechanic-branding',
					'sm_notice_type'    => sanitize_key( (string) $type ),
					'sm_notice_message' => sanitize_text_field( (string) $message ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Check if current admin page belongs to plugin.
	 *
	 * @return bool
	 */
	protected function is_plugin_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		if ( '' === $page ) {
			return false;
		}

		return 0 === strpos( $page, 'super-mechanic' );
	}

	/**
	 * Check if current admin page is Branding.
	 *
	 * @return bool
	 */
	protected function is_branding_page() {
		$page = isset( $_GET['page'] ) ? sanitize_key( (string) wp_unslash( $_GET['page'] ) ) : '';
		return 'super-mechanic-branding' === $page;
	}
}
