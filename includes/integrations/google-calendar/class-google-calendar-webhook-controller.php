<?php
/**
 * Google Calendar webhook REST controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Handles Google push webhook notifications with strict validation.
 */
class Google_Calendar_Webhook_Controller {
	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = Google_Calendar_Service::WEBHOOK_NAMESPACE;

	/**
	 * Integration service.
	 *
	 * @var Google_Calendar_Service
	 */
	protected $google_calendar_service;

	/**
	 * Constructor.
	 *
	 * @param Google_Calendar_Service|null $google_calendar_service Service.
	 */
	public function __construct( Google_Calendar_Service $google_calendar_service = null ) {
		$this->google_calendar_service = $google_calendar_service ? $google_calendar_service : new Google_Calendar_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register webhook route.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			Google_Calendar_Service::WEBHOOK_ROUTE,
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle incoming webhook.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ) {
		$result = $this->google_calendar_service->queue_webhook_notification( $request->get_headers() );

		if ( is_wp_error( $result ) ) {
			if ( 'sm_google_calendar_webhook_duplicate' === $result->get_error_code() ) {
				return new WP_REST_Response( array( 'ok' => true, 'duplicate' => true ), 200 );
			}

			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => $result->get_error_message(),
				),
				403
			);
		}

		return new WP_REST_Response( array( 'ok' => true, 'queued' => true ), 202 );
	}
}
