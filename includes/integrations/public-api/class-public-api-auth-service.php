<?php
/**
 * Public API auth service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Helpers\Settings_Service;
use WP_Error;
use WP_REST_Request;

defined( 'ABSPATH' ) || exit;

/**
 * Authenticates plugin-managed public API keys.
 */
class Public_API_Auth_Service {
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 * @param Business_Service|null $business_service Business service.
	 */
	public function __construct( Settings_Service $settings_service = null, Business_Service $business_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->business_service = $business_service ? $business_service : new Business_Service();
	}

	/**
	 * Authenticate request against configured public API keys.
	 *
	 * @param WP_REST_Request $request        Request.
	 * @param string          $required_scope Required scope.
	 * @param bool            $touch_usage    Whether to update usage timestamp.
	 * @return array<string,mixed>|WP_Error
	 */
	public function authenticate_request( WP_REST_Request $request, $required_scope = '', $touch_usage = true ) {
		$config = $this->get_public_api_config();

		if ( empty( $config['enabled'] ) ) {
			return new WP_Error( 'sm_public_api_disabled', __( 'La API pública está deshabilitada.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		$api_key = $this->extract_api_key_from_request( $request );
		if ( '' === $api_key ) {
			return new WP_Error( 'sm_public_api_missing_key', __( 'API key requerida.', 'super-mechanic' ), array( 'status' => 401 ) );
		}

		$record = $this->find_api_key_record( $api_key );
		if ( ! is_array( $record ) ) {
			return new WP_Error( 'sm_public_api_invalid_key', __( 'API key inválida.', 'super-mechanic' ), array( 'status' => 401 ) );
		}

		$business_id = isset( $record['business_id'] ) ? absint( $record['business_id'] ) : 0;
		$business_id = $this->business_service->resolve_valid_business_id( $business_id );

		if ( $business_id <= 0 ) {
			return new WP_Error( 'sm_public_api_invalid_business', __( 'La credencial no tiene un negocio válido activo.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		$scopes = isset( $record['scopes'] ) && is_array( $record['scopes'] ) ? $record['scopes'] : array();
		if ( ! $this->scope_is_allowed( $required_scope, $scopes ) ) {
			return new WP_Error( 'sm_public_api_scope_forbidden', __( 'La credencial no tiene permisos para este endpoint.', 'super-mechanic' ), array( 'status' => 403 ) );
		}

		if ( $touch_usage ) {
			$this->touch_key_usage( isset( $record['key_id'] ) ? (string) $record['key_id'] : '' );
		}

		return array(
			'key_id'       => isset( $record['key_id'] ) ? (string) $record['key_id'] : '',
			'label'        => isset( $record['label'] ) ? (string) $record['label'] : '',
			'business_id'  => $business_id,
			'scopes'       => $scopes,
			'status'       => isset( $record['status'] ) ? (string) $record['status'] : 'inactive',
			'last_used_at' => isset( $record['last_used_at'] ) ? (string) $record['last_used_at'] : '',
		);
	}

	/**
	 * Extract API key from headers.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	protected function extract_api_key_from_request( WP_REST_Request $request ) {
		$authorization = trim( (string) $request->get_header( 'authorization' ) );

		if ( '' !== $authorization && 0 === stripos( $authorization, 'Bearer ' ) ) {
			return trim( substr( $authorization, 7 ) );
		}

		return trim( (string) $request->get_header( 'x-sm-api-key' ) );
	}

	/**
	 * Resolve and validate one active API key record.
	 *
	 * @param string $api_key Raw API key.
	 * @return array<string,mixed>|null
	 */
	protected function find_api_key_record( $api_key ) {
		$hashed = $this->hash_api_key( $api_key );
		$keys   = $this->get_api_keys();

		foreach ( $keys as $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$status = isset( $record['status'] ) ? sanitize_key( (string) $record['status'] ) : '';
			if ( 'active' !== $status ) {
				continue;
			}

			$stored_hash = isset( $record['key_hash'] ) ? (string) $record['key_hash'] : '';
			if ( '' === $stored_hash || ! hash_equals( $stored_hash, $hashed ) ) {
				continue;
			}

			return $record;
		}

		return null;
	}

	/**
	 * Check scope access against a credential scope list.
	 *
	 * @param string              $required_scope Required scope.
	 * @param array<int, string> $scopes         Key scopes.
	 * @return bool
	 */
	protected function scope_is_allowed( $required_scope, array $scopes ) {
		if ( '' === $required_scope ) {
			return true;
		}

		$required_scope = $this->normalize_scope( $required_scope );
		$normalized     = array_values( array_unique( array_filter( array_map( array( $this, 'normalize_scope' ), $scopes ) ) ) );

		return in_array( '*', $normalized, true ) || in_array( $required_scope, $normalized, true );
	}

	/**
	 * Mark key usage timestamp.
	 *
	 * @param string $key_id Key identifier.
	 * @return void
	 */
	protected function touch_key_usage( $key_id ) {
		$key_id = sanitize_key( $key_id );
		if ( '' === $key_id ) {
			return;
		}

		$keys = $this->get_api_keys();
		$now  = gmdate( 'c' );

		foreach ( $keys as $index => $record ) {
			if ( ! is_array( $record ) ) {
				continue;
			}

			$current_key_id = isset( $record['key_id'] ) ? sanitize_key( (string) $record['key_id'] ) : '';
			if ( $current_key_id !== $key_id ) {
				continue;
			}

			$keys[ $index ]['last_used_at'] = $now;
			$this->settings_service->set_setting( 'public_api', 'api_keys', $keys );
			return;
		}
	}

	/**
	 * Get public API configuration group.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_public_api_config() {
		$config = $this->settings_service->get_group( 'public_api' );

		return is_array( $config ) ? $config : array();
	}

	/**
	 * Get configured API key records.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	protected function get_api_keys() {
		$config = $this->get_public_api_config();
		$keys   = isset( $config['api_keys'] ) && is_array( $config['api_keys'] ) ? $config['api_keys'] : array();

		return array_values( array_filter( $keys, 'is_array' ) );
	}

	/**
	 * Hash an API key with plugin-specific salt.
	 *
	 * @param string $api_key Raw key.
	 * @return string
	 */
	protected function hash_api_key( $api_key ) {
		return hash_hmac( 'sha256', (string) $api_key, wp_salt( 'sm_public_api_keys' ) );
	}

	/**
	 * Normalize one scope value while keeping `:` contract shape.
	 *
	 * @param mixed $scope Raw scope.
	 * @return string
	 */
	protected function normalize_scope( $scope ) {
		$scope = strtolower( trim( sanitize_text_field( (string) $scope ) ) );

		if ( '*' === $scope ) {
			return '*';
		}

		if ( '' === $scope || ! preg_match( '/^[a-z0-9_:-]+$/', $scope ) ) {
			return '';
		}

		return $scope;
	}
}
