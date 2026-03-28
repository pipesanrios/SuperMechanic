<?php
/**
 * Appointment iCal feed controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Appointments;

use Super_Mechanic\Helpers\Feed_Token_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes a signed read-only ICS feed for appointments.
 */
class Appointment_Ical_Feed_Controller {
	/**
	 * Feed query flag.
	 */
	const FEED_FLAG = 'sm_appointments_ical';

	/**
	 * Appointment service.
	 *
	 * @var Appointment_Service
	 */
	protected $appointment_service;

	/**
	 * Feed builder service.
	 *
	 * @var Appointment_Ical_Feed_Service
	 */
	protected $feed_service;

	/**
	 * Feed token service.
	 *
	 * @var Feed_Token_Service
	 */
	protected $feed_token_service;

	/**
	 * Constructor.
	 *
	 * @param Appointment_Service|null           $appointment_service Appointment service.
	 * @param Appointment_Ical_Feed_Service|null $feed_service Feed service.
	 * @param Feed_Token_Service|null            $feed_token_service Feed token service.
	 */
	public function __construct( Appointment_Service $appointment_service = null, Appointment_Ical_Feed_Service $feed_service = null, Feed_Token_Service $feed_token_service = null ) {
		$this->appointment_service = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->feed_service        = $feed_service ? $feed_service : new Appointment_Ical_Feed_Service();
		$this->feed_token_service  = $feed_token_service ? $feed_token_service : new Feed_Token_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'template_redirect', array( $this, 'maybe_handle_feed_request' ) );
	}

	/**
	 * Build signed feed URL.
	 *
	 * @param array<string,mixed> $filters Optional feed filters.
	 * @param int                 $ttl Token TTL in seconds.
	 * @return string
	 */
	public function get_signed_feed_url( array $filters = array(), $ttl = 3600 ) {
		$normalized_filters = $this->feed_token_service->normalize_filters( $filters );
		$expires            = time() + max( 60, absint( $ttl ) );
		$token              = $this->feed_token_service->build_signed_token( $normalized_filters, $expires );
		$query              = array(
			self::FEED_FLAG => 1,
			'assigned_to'   => $normalized_filters['assigned_to'],
			'status'        => $normalized_filters['status'],
			'date_from'     => $normalized_filters['date_from'],
			'date_to'       => $normalized_filters['date_to'],
			'expires'       => $token['expires'],
			'sig'           => $token['sig'],
		);

		return add_query_arg( $query, home_url( '/' ) );
	}

	/**
	 * Serve ICS feed when query flag is present.
	 *
	 * @return void
	 */
	public function maybe_handle_feed_request() {
		if ( empty( $_GET[ self::FEED_FLAG ] ) ) {
			return;
		}

		$filters = $this->get_request_filters();
		$expires = isset( $_GET['expires'] ) ? absint( wp_unslash( $_GET['expires'] ) ) : 0;
		$sig     = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		if ( ! $this->feed_token_service->is_valid_token( $filters, $expires, $sig ) ) {
			$this->deny_feed( new WP_Error( 'sm_ical_feed_forbidden', __( 'Token de feed invalido o expirado.', 'super-mechanic' ) ), 403 );
		}

		$appointments = $this->appointment_service->get_appointments_for_ical_feed(
			array(
				'assigned_to' => $filters['assigned_to'],
				'status'      => $filters['status'],
				'date_from'   => $filters['date_from'],
				'date_to'     => $filters['date_to'],
				'limit'       => 250,
			)
		);
		$ics          = $this->feed_service->build_calendar( $appointments );

		$this->output_ics( $ics );
	}

	/**
	 * Get normalized request filters.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_request_filters() {
		return $this->feed_token_service->normalize_filters(
			array(
				'assigned_to' => isset( $_GET['assigned_to'] ) ? wp_unslash( $_GET['assigned_to'] ) : 0,
				'status'      => isset( $_GET['status'] ) ? wp_unslash( $_GET['status'] ) : '',
				'date_from'   => isset( $_GET['date_from'] ) ? wp_unslash( $_GET['date_from'] ) : '',
				'date_to'     => isset( $_GET['date_to'] ) ? wp_unslash( $_GET['date_to'] ) : '',
			)
		);
	}

	/**
	 * Output ICS payload.
	 *
	 * @param string $ics ICS payload.
	 * @return void
	 */
	protected function output_ics( $ics ) {
		if ( headers_sent() ) {
			exit;
		}

		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename="super-mechanic-appointments.ics"' );

		echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Deny feed request.
	 *
	 * @param WP_Error $error Error.
	 * @param int      $status_code Status code.
	 * @return void
	 */
	protected function deny_feed( WP_Error $error, $status_code = 403 ) {
		wp_die(
			esc_html( $error->get_error_message() ),
			esc_html__( 'Feed no disponible', 'super-mechanic' ),
			array( 'response' => absint( $status_code ) )
		);
	}
}
