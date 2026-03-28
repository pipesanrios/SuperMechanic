<?php
/**
 * Google Calendar integration service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Helpers\Settings_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles OAuth state, token lifecycle and event payload mapping.
 */
class Google_Calendar_Service {
	/**
	 * Provider key.
	 */
	const PROVIDER = 'google_calendar';
	const WEBHOOK_PROCESS_HOOK = 'sm_google_calendar_process_webhook';
	const WATCH_RENEW_HOOK = 'sm_google_calendar_watch_renew';
	const WEBHOOK_NAMESPACE = 'super-mechanic/v1';
	const WEBHOOK_ROUTE = '/google-calendar/webhook';
	const WATCH_RENEW_THRESHOLD_SECONDS = DAY_IN_SECONDS;

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * API client.
	 *
	 * @var Google_Calendar_Client
	 */
	protected $client;

	/**
	 * Sync service.
	 *
	 * @var Google_Calendar_Sync_Service|null
	 */
	protected $sync_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null      $settings_service Settings.
	 * @param Google_Calendar_Client|null $client Client.
	 */
	public function __construct( Settings_Service $settings_service = null, Google_Calendar_Client $client = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->client           = $client ? $client : new Google_Calendar_Client();
		$this->sync_service     = null;
	}

	/**
	 * Register integration hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( self::WATCH_RENEW_HOOK, array( $this, 'handle_watch_renewal_cron' ) );
		add_action( self::WEBHOOK_PROCESS_HOOK, array( $this, 'handle_scheduled_webhook' ), 10, 1 );

		if ( ! wp_next_scheduled( self::WATCH_RENEW_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::WATCH_RENEW_HOOK );
		}
	}

	/**
	 * Attach sync service dependency.
	 *
	 * @param Google_Calendar_Sync_Service $sync_service Sync service.
	 * @return void
	 */
	public function set_sync_service( Google_Calendar_Sync_Service $sync_service ) {
		$this->sync_service = $sync_service;
	}

	/**
	 * Build callback URL for OAuth.
	 *
	 * @return string
	 */
	public function get_callback_url() {
		return admin_url( 'admin-post.php?action=sm_google_calendar_oauth_callback' );
	}

	/**
	 * Build REST webhook URL for Google push notifications.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return rest_url( trim( self::WEBHOOK_NAMESPACE . self::WEBHOOK_ROUTE, '/' ) );
	}

	/**
	 * Get integration settings group.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings() {
		return $this->settings_service->get_group( 'google_calendar' );
	}

	/**
	 * Determine if OAuth config is present.
	 *
	 * @return bool
	 */
	public function is_configured() {
		$settings = $this->get_settings();

		return ! empty( $settings['client_id'] ) && ! empty( $settings['client_secret'] );
	}

	/**
	 * Determine if sync is enabled.
	 *
	 * @return bool
	 */
	public function is_sync_enabled() {
		$settings = $this->get_settings();

		return ! empty( $settings['sync_enabled'] );
	}

	/**
	 * Determine if Google is connected.
	 *
	 * @return bool
	 */
	public function is_connected() {
		$settings = $this->get_settings();

		return ! empty( $settings['access_token'] ) || ! empty( $settings['refresh_token'] );
	}

	/**
	 * Save integration config.
	 *
	 * @param array<string,mixed> $config Config.
	 * @return void
	 */
	public function save_config( array $config ) {
		$current = $this->get_settings();

		$client_secret = isset( $config['client_secret'] ) ? trim( (string) $config['client_secret'] ) : '';
		if ( '' === $client_secret && ! empty( $current['client_secret'] ) ) {
			$client_secret = (string) $current['client_secret'];
		}

		$this->settings_service->set_setting( 'google_calendar', 'client_id', sanitize_text_field( (string) $config['client_id'] ) );
		$this->settings_service->set_setting( 'google_calendar', 'client_secret', $client_secret );
		$this->settings_service->set_setting( 'google_calendar', 'calendar_id', sanitize_text_field( (string) $config['calendar_id'] ) );
		$this->settings_service->set_setting( 'google_calendar', 'sync_enabled', ! empty( $config['sync_enabled'] ) );
		$this->settings_service->set_setting( 'google_calendar', 'redirect_uri', esc_url_raw( $this->get_callback_url() ) );

		if ( ! empty( $config['sync_enabled'] ) && $this->is_connected() ) {
			$watch = $this->ensure_watch_channel();
			if ( is_wp_error( $watch ) ) {
				$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'error' );
				$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', $watch->get_error_message() );
			}
		}
	}

	/**
	 * Build authorization URL.
	 *
	 * @param string $state OAuth state.
	 * @return string|WP_Error
	 */
	public function get_authorization_url( $state ) {
		$settings = $this->get_settings();

		if ( empty( $settings['client_id'] ) || empty( $settings['client_secret'] ) ) {
			return new WP_Error( 'sm_google_calendar_not_configured', __( 'Google Calendar no esta configurado.', 'super-mechanic' ) );
		}

		return $this->client->build_authorization_url(
			(string) $settings['client_id'],
			$this->get_callback_url(),
			(string) $state
		);
	}

	/**
	 * Exchange code and persist tokens.
	 *
	 * @param string $code OAuth code.
	 * @return true|WP_Error
	 */
	public function exchange_code_and_store_tokens( $code ) {
		$settings = $this->get_settings();

		$response = $this->client->exchange_code_for_tokens(
			(string) $code,
			(string) $settings['client_id'],
			(string) $settings['client_secret'],
			$this->get_callback_url()
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->store_tokens_from_response( $response );
		$watch = $this->ensure_watch_channel();
		if ( is_wp_error( $watch ) ) {
			$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'error' );
			$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', $watch->get_error_message() );

			return true;
		}

		$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'connected' );
		$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', __( 'Cuenta conectada correctamente.', 'super-mechanic' ) );

		return true;
	}

	/**
	 * Disconnect integration by revoking local tokens.
	 *
	 * @return void
	 */
	public function disconnect() {
		$this->stop_watch_channel_best_effort();
		$this->clear_watch_state();
		$this->settings_service->set_setting( 'google_calendar', 'access_token', '' );
		$this->settings_service->set_setting( 'google_calendar', 'refresh_token', '' );
		$this->settings_service->set_setting( 'google_calendar', 'token_expires_at', '' );
		$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'disconnected' );
		$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', __( 'Cuenta desconectada.', 'super-mechanic' ) );
	}

	/**
	 * Create or update event in Google Calendar.
	 *
	 * @param array<string,mixed> $appointment Appointment.
	 * @param string              $existing_event_id Existing event ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function upsert_appointment_event( array $appointment, $existing_event_id = '' ) {
		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = $this->get_settings();
		$calendar_id = ! empty( $settings['calendar_id'] ) ? (string) $settings['calendar_id'] : 'primary';
		$event_data  = $this->build_event_payload( $appointment );

		if ( '' !== (string) $existing_event_id ) {
			return $this->client->update_event( (string) $access_token, $calendar_id, (string) $existing_event_id, $event_data );
		}

		return $this->client->create_event( (string) $access_token, $calendar_id, $event_data );
	}

	/**
	 * Fetch one remote Calendar event by ID.
	 *
	 * @param string $event_id External event ID.
	 * @param string $calendar_id Optional calendar ID override.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_remote_event( $event_id, $calendar_id = '' ) {
		$event_id = sanitize_text_field( (string) $event_id );
		if ( '' === $event_id ) {
			return new WP_Error( 'sm_google_calendar_event_missing', __( 'No existe un evento externo para reconciliar.', 'super-mechanic' ) );
		}

		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings = $this->get_settings();
		$calendar = '' !== (string) $calendar_id
			? sanitize_text_field( (string) $calendar_id )
			: ( ! empty( $settings['calendar_id'] ) ? (string) $settings['calendar_id'] : 'primary' );

		return $this->client->get_event( (string) $access_token, $calendar, $event_id );
	}

	/**
	 * Ensure active watch channel exists and is healthy.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function ensure_watch_channel() {
		$settings = $this->get_settings();
		$expires  = isset( $settings['watch_expiration'] ) ? (int) $settings['watch_expiration'] : 0;
		$channel  = isset( $settings['watch_channel_id'] ) ? (string) $settings['watch_channel_id'] : '';
		$resource = isset( $settings['watch_resource_id'] ) ? (string) $settings['watch_resource_id'] : '';

		if ( '' !== $channel && '' !== $resource && $expires > ( time() + self::WATCH_RENEW_THRESHOLD_SECONDS ) ) {
			return array(
				'channel_id'  => $channel,
				'resource_id' => $resource,
				'expiration'  => $expires,
				'status'      => 'active',
			);
		}

		return $this->renew_watch_channel();
	}

	/**
	 * Renew watch channel (create new, then stop previous best effort).
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	public function renew_watch_channel() {
		if ( ! $this->is_sync_enabled() || ! $this->is_configured() || ! $this->is_connected() ) {
			return new WP_Error( 'sm_google_calendar_watch_unavailable', __( 'Google Calendar no esta disponible para renovar canal.', 'super-mechanic' ) );
		}

		$previous = $this->get_settings();
		$started  = $this->start_watch_channel();

		if ( is_wp_error( $started ) ) {
			return $started;
		}

		if ( ! empty( $previous['watch_channel_id'] ) && ! empty( $previous['watch_resource_id'] ) ) {
			$this->stop_watch_channel_best_effort(
				(string) $previous['watch_channel_id'],
				(string) $previous['watch_resource_id']
			);
		}

		return $started;
	}

	/**
	 * Handle watch renewal cron.
	 *
	 * @return void
	 */
	public function handle_watch_renewal_cron() {
		if ( ! $this->is_sync_enabled() || ! $this->is_configured() || ! $this->is_connected() ) {
			return;
		}

		$settings = $this->get_settings();
		$expires  = isset( $settings['watch_expiration'] ) ? (int) $settings['watch_expiration'] : 0;

		if ( $expires > ( time() + self::WATCH_RENEW_THRESHOLD_SECONDS ) ) {
			return;
		}

		$result = $this->renew_watch_channel();
		if ( is_wp_error( $result ) ) {
			$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'error' );
			$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', $result->get_error_message() );
		}
	}

	/**
	 * Validate and enqueue webhook processing.
	 *
	 * @param array<string,mixed> $headers Webhook headers.
	 * @return array<string,mixed>|WP_Error
	 */
	public function queue_webhook_notification( array $headers ) {
		$validated = $this->validate_webhook_headers( $headers );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$channel_id     = (string) $validated['channel_id'];
		$message_number = (int) $validated['message_number'];
		$fingerprint    = md5( $channel_id . '|' . $message_number );
		$lock_key       = 'sm_gc_wh_lock_' . $fingerprint;

		if ( false !== get_transient( $lock_key ) ) {
			return array( 'queued' => false, 'duplicate' => true );
		}

		set_transient( $lock_key, 1, MINUTE_IN_SECONDS * 2 );

		$this->settings_service->set_setting( 'google_calendar', 'watch_last_message_number', $message_number );
		$this->settings_service->set_setting( 'google_calendar', 'watch_last_webhook_at', current_time( 'mysql' ) );

		wp_schedule_single_event(
			time(),
			self::WEBHOOK_PROCESS_HOOK,
			array(
				array(
					'channel_id'     => $channel_id,
					'resource_state' => (string) $validated['resource_state'],
					'fingerprint'    => $fingerprint,
				),
			)
		);

		return array( 'queued' => true, 'duplicate' => false );
	}

	/**
	 * Process scheduled webhook payload.
	 *
	 * @param array<string,mixed> $payload Payload.
	 * @return void
	 */
	public function handle_scheduled_webhook( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();
		$state   = isset( $payload['resource_state'] ) ? sanitize_key( (string) $payload['resource_state'] ) : '';

		if ( ! in_array( $state, array( 'sync', 'exists', 'not_exists' ), true ) ) {
			return;
		}

		$sync_service = $this->get_sync_service();
		if ( is_wp_error( $sync_service ) ) {
			return;
		}

		if ( 'sync' === $state ) {
			return;
		}

		$changes = $this->get_event_changes_snapshot();
		if ( is_wp_error( $changes ) ) {
			$sync_service->reconcile_inbound_for_linked_appointments( 50 );
			return;
		}

		$event_ids = isset( $changes['event_ids'] ) && is_array( $changes['event_ids'] ) ? $changes['event_ids'] : array();
		if ( ! empty( $event_ids ) ) {
			$sync_service->reconcile_inbound_for_external_event_ids( $event_ids, 50 );
		}
	}

	/**
	 * Validate webhook headers and idempotency baseline.
	 *
	 * @param array<string,mixed> $headers Request headers.
	 * @return array<string,mixed>|WP_Error
	 */
	public function validate_webhook_headers( array $headers ) {
		$channel_id     = $this->get_header_value( $headers, 'x-goog-channel-id' );
		$resource_id    = $this->get_header_value( $headers, 'x-goog-resource-id' );
		$channel_token  = $this->get_header_value( $headers, 'x-goog-channel-token' );
		$resource_state = sanitize_key( $this->get_header_value( $headers, 'x-goog-resource-state' ) );
		$message_number = absint( $this->get_header_value( $headers, 'x-goog-message-number' ) );

		if ( '' === $channel_id || '' === $resource_id || '' === $channel_token || '' === $resource_state ) {
			return new WP_Error( 'sm_google_calendar_webhook_invalid_headers', __( 'Headers webhook de Google incompletos.', 'super-mechanic' ) );
		}

		if ( ! in_array( $resource_state, array( 'sync', 'exists', 'not_exists' ), true ) ) {
			return new WP_Error( 'sm_google_calendar_webhook_invalid_state', __( 'Resource state no permitido.', 'super-mechanic' ) );
		}

		$settings = $this->get_settings();
		$stored_channel  = isset( $settings['watch_channel_id'] ) ? (string) $settings['watch_channel_id'] : '';
		$stored_resource = isset( $settings['watch_resource_id'] ) ? (string) $settings['watch_resource_id'] : '';
		$stored_hash     = isset( $settings['watch_token_hash'] ) ? (string) $settings['watch_token_hash'] : '';
		$last_message    = isset( $settings['watch_last_message_number'] ) ? absint( $settings['watch_last_message_number'] ) : 0;
		$incoming_hash   = hash( 'sha256', $channel_token );

		if ( ! hash_equals( $stored_channel, $channel_id ) || ! hash_equals( $stored_resource, $resource_id ) ) {
			return new WP_Error( 'sm_google_calendar_webhook_channel_mismatch', __( 'Channel/resource no coincide con el estado registrado.', 'super-mechanic' ) );
		}

		if ( '' === $stored_hash || ! hash_equals( $stored_hash, $incoming_hash ) ) {
			return new WP_Error( 'sm_google_calendar_webhook_token_invalid', __( 'Token de canal invalido.', 'super-mechanic' ) );
		}

		if ( $message_number > 0 && $message_number <= $last_message ) {
			return new WP_Error( 'sm_google_calendar_webhook_duplicate', __( 'Notificacion duplicada o fuera de orden.', 'super-mechanic' ) );
		}

		return array(
			'channel_id'     => $channel_id,
			'resource_state' => $resource_state,
			'message_number' => max( 1, $message_number ),
		);
	}

	/**
	 * Build Calendar event payload from appointment.
	 *
	 * @param array<string,mixed> $appointment Appointment row.
	 * @return array<string,mixed>
	 */
	public function build_event_payload( array $appointment ) {
		$timezone  = wp_timezone_string();
		if ( '' === $timezone ) {
			$timezone = 'UTC';
		}

		$id        = isset( $appointment['id'] ) ? absint( $appointment['id'] ) : 0;
		$status    = isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : 'scheduled';
		$client    = isset( $appointment['client_name'] ) ? sanitize_text_field( (string) $appointment['client_name'] ) : '';
		$vehicle   = trim(
			sprintf(
				'%s %s',
				isset( $appointment['vehicle_make'] ) ? (string) $appointment['vehicle_make'] : '',
				isset( $appointment['vehicle_model'] ) ? (string) $appointment['vehicle_model'] : ''
			)
		);
		$mechanic  = isset( $appointment['mechanic_name'] ) ? sanitize_text_field( (string) $appointment['mechanic_name'] ) : '';
		$notes     = isset( $appointment['notes'] ) ? sanitize_textarea_field( (string) $appointment['notes'] ) : '';
		$start_ts  = $this->normalize_datetime_timestamp( isset( $appointment['start_at'] ) ? $appointment['start_at'] : '' );
		$start_iso = gmdate( 'Y-m-d\TH:i:s\Z', $start_ts );
		$end_iso   = gmdate( 'Y-m-d\TH:i:s\Z', $start_ts + HOUR_IN_SECONDS );

		$description = implode(
			"\n",
			array_filter(
				array(
					'Estado: ' . $status,
					'' !== $client ? 'Cliente: ' . $client : '',
					'' !== $vehicle ? 'Vehiculo: ' . $vehicle : '',
					'' !== $mechanic ? 'Mecanico: ' . $mechanic : '',
					'' !== $notes ? 'Notas: ' . $notes : '',
				)
			)
		);

		return array(
			'summary'     => sprintf( 'Cita #%d - %s', $id, '' !== $vehicle ? $vehicle : __( 'Vehiculo', 'super-mechanic' ) ),
			'description' => $description,
			'start'       => array(
				'dateTime' => $start_iso,
				'timeZone' => $timezone,
			),
			'end'         => array(
				'dateTime' => $end_iso,
				'timeZone' => $timezone,
			),
		);
	}

	/**
	 * Build and persist OAuth state.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public function create_oauth_state( $user_id ) {
		$state = wp_generate_password( 32, false, false );
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state', $state );
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state_expires_at', gmdate( 'c', time() + 10 * MINUTE_IN_SECONDS ) );
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state_user_id', absint( $user_id ) );

		return $state;
	}

	/**
	 * Validate OAuth state.
	 *
	 * @param string $state State.
	 * @param int    $user_id User ID.
	 * @return bool
	 */
	public function validate_oauth_state( $state, $user_id ) {
		$settings     = $this->get_settings();
		$stored_state = isset( $settings['oauth_state'] ) ? (string) $settings['oauth_state'] : '';
		$expires      = isset( $settings['oauth_state_expires_at'] ) ? strtotime( (string) $settings['oauth_state_expires_at'] ) : 0;
		$stored_user  = isset( $settings['oauth_state_user_id'] ) ? absint( $settings['oauth_state_user_id'] ) : 0;

		return '' !== $stored_state
			&& hash_equals( $stored_state, (string) $state )
			&& $expires > time()
			&& $stored_user === absint( $user_id );
	}

	/**
	 * Clear OAuth state.
	 *
	 * @return void
	 */
	public function clear_oauth_state() {
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state', '' );
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state_expires_at', '' );
		$this->settings_service->set_setting( 'google_calendar', 'oauth_state_user_id', 0 );
	}

	/**
	 * Start new watch channel and persist watch metadata.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	protected function start_watch_channel() {
		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = $this->get_settings();
		$calendar_id = ! empty( $settings['calendar_id'] ) ? (string) $settings['calendar_id'] : 'primary';
		$channel_id  = wp_generate_uuid4();
		$token_plain = wp_generate_password( 48, false, false );
		$payload     = array(
			'id'      => $channel_id,
			'type'    => 'web_hook',
			'address' => esc_url_raw( $this->get_webhook_url() ),
			'token'   => $token_plain,
			'params'  => array(
				'ttl' => (string) DAY_IN_SECONDS,
			),
		);

		$response = $this->client->start_watch_channel( (string) $access_token, $calendar_id, $payload );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$resource_id  = isset( $response['resourceId'] ) ? sanitize_text_field( (string) $response['resourceId'] ) : '';
		$resource_uri = isset( $response['resourceUri'] ) ? esc_url_raw( (string) $response['resourceUri'] ) : '';
		$expiration   = isset( $response['expiration'] ) ? absint( (string) $response['expiration'] / 1000 ) : time() + DAY_IN_SECONDS;

		if ( '' === $resource_id ) {
			return new WP_Error( 'sm_google_calendar_watch_invalid', __( 'Google Calendar devolvio un watch channel invalido.', 'super-mechanic' ) );
		}

		$this->settings_service->set_setting( 'google_calendar', 'watch_channel_id', $channel_id );
		$this->settings_service->set_setting( 'google_calendar', 'watch_resource_id', $resource_id );
		$this->settings_service->set_setting( 'google_calendar', 'watch_resource_uri', $resource_uri );
		$this->settings_service->set_setting( 'google_calendar', 'watch_expiration', $expiration );
		$this->settings_service->set_setting( 'google_calendar', 'watch_token_hash', hash( 'sha256', $token_plain ) );
		$this->settings_service->set_setting( 'google_calendar', 'watch_last_message_number', 0 );
		$this->settings_service->set_setting( 'google_calendar', 'watch_last_webhook_at', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_next_sync_token', '' );
		$this->settings_service->set_setting( 'google_calendar', 'last_sync_result', 'watch_active' );
		$this->settings_service->set_setting( 'google_calendar', 'last_sync_message', __( 'Watch channel activo.', 'super-mechanic' ) );

		return array(
			'channel_id'  => $channel_id,
			'resource_id' => $resource_id,
			'expiration'  => $expiration,
			'status'      => 'created',
		);
	}

	/**
	 * Stop channel best effort.
	 *
	 * @param string $channel_id Optional channel ID.
	 * @param string $resource_id Optional resource ID.
	 * @return void
	 */
	protected function stop_watch_channel_best_effort( $channel_id = '', $resource_id = '' ) {
		$settings    = $this->get_settings();
		$channel_id  = '' !== (string) $channel_id ? sanitize_text_field( (string) $channel_id ) : ( isset( $settings['watch_channel_id'] ) ? (string) $settings['watch_channel_id'] : '' );
		$resource_id = '' !== (string) $resource_id ? sanitize_text_field( (string) $resource_id ) : ( isset( $settings['watch_resource_id'] ) ? (string) $settings['watch_resource_id'] : '' );

		if ( '' === $channel_id || '' === $resource_id ) {
			return;
		}

		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return;
		}

		$this->client->stop_watch_channel( (string) $access_token, $channel_id, $resource_id );
	}

	/**
	 * Clear stored watch state.
	 *
	 * @return void
	 */
	protected function clear_watch_state() {
		$this->settings_service->set_setting( 'google_calendar', 'watch_channel_id', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_resource_id', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_resource_uri', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_expiration', 0 );
		$this->settings_service->set_setting( 'google_calendar', 'watch_token_hash', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_last_message_number', 0 );
		$this->settings_service->set_setting( 'google_calendar', 'watch_last_webhook_at', '' );
		$this->settings_service->set_setting( 'google_calendar', 'watch_next_sync_token', '' );
	}

	/**
	 * Get incremental event changes snapshot.
	 *
	 * @return array<string,mixed>|WP_Error
	 */
	protected function get_event_changes_snapshot() {
		$access_token = $this->get_valid_access_token();
		if ( is_wp_error( $access_token ) ) {
			return $access_token;
		}

		$settings    = $this->get_settings();
		$calendar_id = ! empty( $settings['calendar_id'] ) ? (string) $settings['calendar_id'] : 'primary';
		$next_token  = isset( $settings['watch_next_sync_token'] ) ? sanitize_text_field( (string) $settings['watch_next_sync_token'] ) : '';
		$query       = array(
			'maxResults'  => 50,
			'showDeleted' => 'true',
		);

		if ( '' !== $next_token ) {
			$query['syncToken'] = $next_token;
		} else {
			$query['updatedMin'] = gmdate( 'c', time() - DAY_IN_SECONDS );
			$query['singleEvents'] = 'false';
		}

		$response = $this->client->list_event_changes( (string) $access_token, $calendar_id, $query );
		if ( is_wp_error( $response ) ) {
			$this->settings_service->set_setting( 'google_calendar', 'watch_next_sync_token', '' );

			return $response;
		}

		$items = isset( $response['items'] ) && is_array( $response['items'] ) ? $response['items'] : array();
		$ids   = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['id'] ) ) {
				continue;
			}
			$ids[] = sanitize_text_field( (string) $item['id'] );
		}

		$ids = array_values( array_unique( $ids ) );

		if ( ! empty( $response['nextSyncToken'] ) ) {
			$this->settings_service->set_setting( 'google_calendar', 'watch_next_sync_token', sanitize_text_field( (string) $response['nextSyncToken'] ) );
		}

		return array(
			'event_ids' => $ids,
		);
	}

	/**
	 * Resolve sync service.
	 *
	 * @return Google_Calendar_Sync_Service|WP_Error
	 */
	protected function get_sync_service() {
		if ( $this->sync_service instanceof Google_Calendar_Sync_Service ) {
			return $this->sync_service;
		}

		$sync = new Google_Calendar_Sync_Service( $this );
		$appointment_service = new Appointment_Service( null, null, null, null, $sync );
		$sync->set_appointment_service( $appointment_service );
		$this->sync_service = $sync;

		return $this->sync_service;
	}

	/**
	 * Read header value from mixed header map.
	 *
	 * @param array<string,mixed> $headers Headers.
	 * @param string              $key Header key.
	 * @return string
	 */
	protected function get_header_value( array $headers, $key ) {
		$key = strtolower( sanitize_text_field( (string) $key ) );

		foreach ( $headers as $header_key => $value ) {
			if ( strtolower( (string) $header_key ) !== $key ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = reset( $value );
			}

			return sanitize_text_field( (string) $value );
		}

		return '';
	}

	/**
	 * Get valid access token, refreshing if needed.
	 *
	 * @return string|WP_Error
	 */
	protected function get_valid_access_token() {
		$settings     = $this->get_settings();
		$access_token = isset( $settings['access_token'] ) ? (string) $settings['access_token'] : '';
		$refresh      = isset( $settings['refresh_token'] ) ? (string) $settings['refresh_token'] : '';
		$expires_ts   = isset( $settings['token_expires_at'] ) ? strtotime( (string) $settings['token_expires_at'] ) : 0;

		if ( '' !== $access_token && $expires_ts > ( time() + 60 ) ) {
			return $access_token;
		}

		if ( '' === $refresh ) {
			return new WP_Error( 'sm_google_calendar_token_missing', __( 'No hay refresh token de Google Calendar disponible.', 'super-mechanic' ) );
		}

		$response = $this->client->refresh_access_token(
			$refresh,
			(string) $settings['client_id'],
			(string) $settings['client_secret']
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->store_tokens_from_response( $response );

		$settings = $this->get_settings();

		return isset( $settings['access_token'] ) ? (string) $settings['access_token'] : new WP_Error( 'sm_google_calendar_token_invalid', __( 'No fue posible obtener un token de acceso valido.', 'super-mechanic' ) );
	}

	/**
	 * Persist token payload.
	 *
	 * @param array<string,mixed> $response OAuth response.
	 * @return void
	 */
	protected function store_tokens_from_response( array $response ) {
		$access_token = isset( $response['access_token'] ) ? sanitize_text_field( (string) $response['access_token'] ) : '';
		$refresh      = isset( $response['refresh_token'] ) ? sanitize_text_field( (string) $response['refresh_token'] ) : '';
		$expires_in   = isset( $response['expires_in'] ) ? absint( $response['expires_in'] ) : HOUR_IN_SECONDS;

		if ( '' !== $access_token ) {
			$this->settings_service->set_setting( 'google_calendar', 'access_token', $access_token );
			$this->settings_service->set_setting( 'google_calendar', 'token_expires_at', gmdate( 'c', time() + max( 60, $expires_in ) ) );
		}

		if ( '' !== $refresh ) {
			$this->settings_service->set_setting( 'google_calendar', 'refresh_token', $refresh );
		}
	}

	/**
	 * Normalize datetime value to timestamp.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	protected function normalize_datetime_timestamp( $value ) {
		$timestamp = strtotime( sanitize_text_field( (string) $value ) );
		if ( false === $timestamp ) {
			$timestamp = time();
		}

		return (int) $timestamp;
	}
}
