<?php
/**
 * Google Calendar OAuth controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

defined( 'ABSPATH' ) || exit;

/**
 * Handles OAuth connect/callback/disconnect actions.
 */
class Google_Calendar_Auth_Controller {
	/**
	 * Integration service.
	 *
	 * @var Google_Calendar_Client_Service
	 */
	protected $google_calendar_service;

	/**
	 * Constructor.
	 *
	 * @param Google_Calendar_Client_Service|null $google_calendar_service Service.
	 */
	public function __construct( Google_Calendar_Client_Service $google_calendar_service = null ) {
		$this->google_calendar_service = $google_calendar_service ? $google_calendar_service : new Google_Calendar_Client_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_sm_google_calendar_oauth_connect', array( $this, 'handle_oauth_connect' ) );
		add_action( 'admin_post_sm_google_calendar_oauth_callback', array( $this, 'handle_oauth_callback' ) );
		add_action( 'admin_post_sm_google_calendar_oauth_disconnect', array( $this, 'handle_oauth_disconnect' ) );
	}

	/**
	 * Start OAuth flow.
	 *
	 * @return void
	 */
	public function handle_oauth_connect() {
		$this->assert_permissions();
		check_admin_referer( 'sm_google_calendar_oauth_connect', 'sm_google_calendar_oauth_nonce' );

		if ( ! $this->google_calendar_service->is_configured() ) {
			$this->redirect_with_notice( 'error', __( 'Configura Client ID y Client Secret antes de conectar Google Calendar.', 'super-mechanic' ) );
		}

		$user_id = get_current_user_id();
		$state   = $this->google_calendar_service->create_oauth_state( $user_id );
		$url     = $this->google_calendar_service->get_authorization_url( $state );

		if ( is_wp_error( $url ) ) {
			$this->redirect_with_notice( 'error', $url->get_error_message() );
		}

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Handle OAuth callback.
	 *
	 * @return void
	 */
	public function handle_oauth_callback() {
		$this->assert_permissions();

		$code  = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';

		if ( '' === $code || '' === $state ) {
			$this->google_calendar_service->clear_oauth_state();
			$this->redirect_with_notice( 'error', __( 'Respuesta OAuth invalida de Google Calendar.', 'super-mechanic' ) );
		}

		if ( ! $this->google_calendar_service->validate_oauth_state( $state, get_current_user_id() ) ) {
			$this->google_calendar_service->clear_oauth_state();
			$this->redirect_with_notice( 'error', __( 'Estado OAuth invalido o expirado.', 'super-mechanic' ) );
		}

		$result = $this->google_calendar_service->exchange_code_and_store_tokens( $code );
		$this->google_calendar_service->clear_oauth_state();

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_notice( 'error', $result->get_error_message() );
		}

		$this->redirect_with_notice( 'success', __( 'Google Calendar conectado correctamente.', 'super-mechanic' ) );
	}

	/**
	 * Disconnect OAuth tokens.
	 *
	 * @return void
	 */
	public function handle_oauth_disconnect() {
		$this->assert_permissions();
		check_admin_referer( 'sm_google_calendar_oauth_disconnect', 'sm_google_calendar_oauth_nonce' );

		$this->google_calendar_service->disconnect();
		$this->redirect_with_notice( 'success', __( 'Google Calendar desconectado.', 'super-mechanic' ) );
	}

	/**
	 * Assert permissions.
	 *
	 * @return void
	 */
	protected function assert_permissions() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'No tienes permisos para gestionar Google Calendar.', 'super-mechanic' ) );
		}
	}

	/**
	 * Redirect settings with notice.
	 *
	 * @param string $type Type.
	 * @param string $message Message.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $message ) {
		$target = add_query_arg(
			array(
				'page'                => 'super-mechanic-settings',
				'sm_google_gc_notice' => sanitize_key( (string) $type ),
				'sm_google_gc_msg'    => sanitize_text_field( (string) $message ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}
}
