<?php
/**
 * Google Calendar API client.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Google_Calendar;

use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Lightweight Google OAuth + Calendar HTTP client.
 */
class Google_Calendar_Client {
	/**
	 * OAuth authorize endpoint.
	 */
	const AUTH_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

	/**
	 * OAuth token endpoint.
	 */
	const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

	/**
	 * Calendar API base.
	 */
	const CALENDAR_API_BASE = 'https://www.googleapis.com/calendar/v3';

	/**
	 * Build authorization URL.
	 *
	 * @param string $client_id Client ID.
	 * @param string $redirect_uri Redirect URI.
	 * @param string $state OAuth state.
	 * @return string
	 */
	public function build_authorization_url( $client_id, $redirect_uri, $state ) {
		$query = array(
			'client_id'     => sanitize_text_field( (string) $client_id ),
			'redirect_uri'  => esc_url_raw( (string) $redirect_uri ),
			'response_type' => 'code',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'scope'         => 'https://www.googleapis.com/auth/calendar.events',
			'state'         => sanitize_text_field( (string) $state ),
		);

		return add_query_arg( $query, self::AUTH_ENDPOINT );
	}

	/**
	 * Exchange authorization code for tokens.
	 *
	 * @param string $code Auth code.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @param string $redirect_uri Redirect URI.
	 * @return array<string,mixed>|WP_Error
	 */
	public function exchange_code_for_tokens( $code, $client_id, $client_secret, $redirect_uri ) {
		return $this->request_tokens(
			array(
				'code'          => sanitize_text_field( (string) $code ),
				'client_id'     => sanitize_text_field( (string) $client_id ),
				'client_secret' => (string) $client_secret,
				'redirect_uri'  => esc_url_raw( (string) $redirect_uri ),
				'grant_type'    => 'authorization_code',
			)
		);
	}

	/**
	 * Refresh access token.
	 *
	 * @param string $refresh_token Refresh token.
	 * @param string $client_id Client ID.
	 * @param string $client_secret Client secret.
	 * @return array<string,mixed>|WP_Error
	 */
	public function refresh_access_token( $refresh_token, $client_id, $client_secret ) {
		return $this->request_tokens(
			array(
				'refresh_token' => sanitize_text_field( (string) $refresh_token ),
				'client_id'     => sanitize_text_field( (string) $client_id ),
				'client_secret' => (string) $client_secret,
				'grant_type'    => 'refresh_token',
			)
		);
	}

	/**
	 * Create event.
	 *
	 * @param string              $access_token Access token.
	 * @param string              $calendar_id Calendar ID.
	 * @param array<string,mixed> $event_payload Event payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function create_event( $access_token, $calendar_id, array $event_payload ) {
		$endpoint = sprintf(
			'%s/calendars/%s/events',
			self::CALENDAR_API_BASE,
			rawurlencode( sanitize_text_field( (string) $calendar_id ) )
		);

		return $this->api_request( 'POST', $endpoint, $access_token, $event_payload );
	}

	/**
	 * Update event.
	 *
	 * @param string              $access_token Access token.
	 * @param string              $calendar_id Calendar ID.
	 * @param string              $event_id Event ID.
	 * @param array<string,mixed> $event_payload Event payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function update_event( $access_token, $calendar_id, $event_id, array $event_payload ) {
		$endpoint = sprintf(
			'%s/calendars/%s/events/%s',
			self::CALENDAR_API_BASE,
			rawurlencode( sanitize_text_field( (string) $calendar_id ) ),
			rawurlencode( sanitize_text_field( (string) $event_id ) )
		);

		return $this->api_request( 'PATCH', $endpoint, $access_token, $event_payload );
	}

	/**
	 * Get one event by ID.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param string $event_id Event ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function get_event( $access_token, $calendar_id, $event_id ) {
		$endpoint = sprintf(
			'%s/calendars/%s/events/%s',
			self::CALENDAR_API_BASE,
			rawurlencode( sanitize_text_field( (string) $calendar_id ) ),
			rawurlencode( sanitize_text_field( (string) $event_id ) )
		);

		return $this->api_request( 'GET', $endpoint, $access_token );
	}

	/**
	 * Start Google Calendar watch channel.
	 *
	 * @param string $access_token Access token.
	 * @param string $calendar_id Calendar ID.
	 * @param array<string,mixed> $payload Watch payload.
	 * @return array<string,mixed>|WP_Error
	 */
	public function start_watch_channel( $access_token, $calendar_id, array $payload ) {
		$endpoint = sprintf(
			'%s/calendars/%s/events/watch',
			self::CALENDAR_API_BASE,
			rawurlencode( sanitize_text_field( (string) $calendar_id ) )
		);

		return $this->api_request( 'POST', $endpoint, $access_token, $payload );
	}

	/**
	 * Stop Google Calendar watch channel.
	 *
	 * @param string $access_token Access token.
	 * @param string $channel_id Channel ID.
	 * @param string $resource_id Resource ID.
	 * @return array<string,mixed>|WP_Error
	 */
	public function stop_watch_channel( $access_token, $channel_id, $resource_id ) {
		$endpoint = self::CALENDAR_API_BASE . '/channels/stop';

		return $this->api_request(
			'POST',
			$endpoint,
			$access_token,
			array(
				'id'         => sanitize_text_field( (string) $channel_id ),
				'resourceId' => sanitize_text_field( (string) $resource_id ),
			)
		);
	}

	/**
	 * List event changes for incremental webhook processing.
	 *
	 * @param string              $access_token Access token.
	 * @param string              $calendar_id Calendar ID.
	 * @param array<string,mixed> $query Query params.
	 * @return array<string,mixed>|WP_Error
	 */
	public function list_event_changes( $access_token, $calendar_id, array $query ) {
		$endpoint = sprintf(
			'%s/calendars/%s/events',
			self::CALENDAR_API_BASE,
			rawurlencode( sanitize_text_field( (string) $calendar_id ) )
		);

		return $this->api_request( 'GET', $endpoint, $access_token, array(), $query );
	}

	/**
	 * Request OAuth tokens.
	 *
	 * @param array<string,mixed> $body Body.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function request_tokens( array $body ) {
		$response = wp_remote_post(
			self::TOKEN_ENDPOINT,
			array(
				'timeout' => 20,
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sm_google_token_http_error', __( 'No fue posible comunicarse con Google OAuth.', 'super-mechanic' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( (string) $raw, true );

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'sm_google_token_failed', __( 'Google OAuth devolvio un error al solicitar tokens.', 'super-mechanic' ) );
		}

		return $data;
	}

	/**
	 * Perform authenticated Calendar API request.
	 *
	 * @param string              $method HTTP method.
	 * @param string              $endpoint Endpoint.
	 * @param string              $access_token Access token.
	 * @param array<string,mixed> $payload Payload.
	 * @param array<string,mixed> $query Query params.
	 * @return array<string,mixed>|WP_Error
	 */
	protected function api_request( $method, $endpoint, $access_token, array $payload = array(), array $query = array() ) {
		if ( ! empty( $query ) ) {
			$endpoint = add_query_arg( $query, $endpoint );
		}

		$args = array(
			'method'  => strtoupper( (string) $method ),
			'timeout' => 20,
			'headers' => array(
				'Authorization' => 'Bearer ' . sanitize_text_field( (string) $access_token ),
				'Content-Type'  => 'application/json; charset=utf-8',
			),
		);

		if ( ! empty( $payload ) ) {
			$args['body'] = wp_json_encode( $payload );
		}

		$response = wp_remote_request( $endpoint, $args );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'sm_google_calendar_http_error', __( 'No fue posible comunicarse con Google Calendar.', 'super-mechanic' ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$raw    = wp_remote_retrieve_body( $response );
		$data   = json_decode( (string) $raw, true );

		if ( $status >= 200 && $status < 300 && '' === trim( (string) $raw ) ) {
			return array();
		}

		if ( $status < 200 || $status >= 300 || ! is_array( $data ) ) {
			return new WP_Error( 'sm_google_calendar_api_failed', __( 'Google Calendar devolvio un error al sincronizar el evento.', 'super-mechanic' ) );
		}

		return $data;
	}
}
