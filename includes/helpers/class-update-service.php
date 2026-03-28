<?php
/**
 * Private update service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Handles private plugin update checks and WordPress update API integration.
 */
class Update_Service {
	/**
	 * Update state key names.
	 */
	const RESULT_UPDATE_AVAILABLE = 'update_available';
	const RESULT_NO_UPDATE        = 'no_update';
	const RESULT_BLOCKED          = 'blocked';
	const RESULT_ERROR            = 'error';

	/**
	 * Check interval in seconds.
	 */
	const CHECK_TTL = 21600;

	/**
	 * Settings service dependency.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Provider dependency.
	 *
	 * @var Update_Provider_Interface
	 */
	protected $provider;

	/**
	 * License service dependency.
	 *
	 * @var License_Service
	 */
	protected $license_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null          $settings_service Settings service.
	 * @param Update_Provider_Interface|null $provider         Update provider.
	 * @param License_Service|null           $license_service  License service.
	 */
	public function __construct( Settings_Service $settings_service = null, Update_Provider_Interface $provider = null, License_Service $license_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->provider         = $provider ? $provider : new Local_Update_Provider();
		$this->license_service  = $license_service ? $license_service : new License_Service( $this->settings_service );
	}

	/**
	 * Register WordPress update hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'filter_update_plugins_transient' ) );
		add_filter( 'plugins_api', array( $this, 'filter_plugins_api' ), 10, 3 );
		add_action( 'admin_post_sm_private_update_package', array( $this, 'handle_private_package_request' ) );
		add_action( 'admin_post_nopriv_sm_private_update_package', array( $this, 'handle_private_package_request' ) );
	}

	/**
	 * Get normalized updates state from settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_updates_state() {
		$defaults = $this->get_default_updates_state();
		$stored   = $this->settings_service->get_group( 'updates' );
		$state    = array_merge( $defaults, is_array( $stored ) ? $stored : array() );

		$state['provider']          = sanitize_text_field( (string) $state['provider'] );
		$state['last_check_at']     = sanitize_text_field( (string) $state['last_check_at'] );
		$state['latest_version']    = sanitize_text_field( (string) $state['latest_version'] );
		$state['package_available'] = ! empty( $state['package_available'] );
		$state['message']           = sanitize_text_field( (string) $state['message'] );
		$state['last_result']       = sanitize_key( (string) $state['last_result'] );
		$state['requires']          = sanitize_text_field( (string) $state['requires'] );
		$state['tested']            = sanitize_text_field( (string) $state['tested'] );
		$state['changelog']         = sanitize_textarea_field( (string) $state['changelog'] );

		return $state;
	}

	/**
	 * Execute update check and persist state.
	 *
	 * @param bool $force Force provider call.
	 * @return array<string, mixed>
	 */
	public function check_for_updates( $force = false ) {
		$current_state = $this->get_updates_state();

		if ( ! $force && ! $this->is_check_expired( $current_state['last_check_at'] ) ) {
			return $current_state;
		}

		$plugin_data     = $this->get_plugin_context();
		$license_state   = $this->license_service->get_license_state();
		$provider_result = $this->provider->get_update_metadata(
			array(
				'plugin'          => $plugin_data['plugin_basename'],
				'slug'            => $plugin_data['slug'],
				'current_version' => $plugin_data['current_version'],
				'license'         => $license_state,
			)
		);

		if ( ! is_array( $provider_result ) ) {
			$provider_result = array();
		}

		$raw_latest_version = isset( $provider_result['latest_version'] ) ? sanitize_text_field( (string) $provider_result['latest_version'] ) : $plugin_data['current_version'];
		$latest_version     = '' !== $raw_latest_version ? $raw_latest_version : $plugin_data['current_version'];
		$raw_source_url     = isset( $provider_result['package_source_url'] ) ? esc_url_raw( (string) $provider_result['package_source_url'] ) : '';
		$source_url         = $this->is_source_url_allowed( $raw_source_url ) ? $raw_source_url : '';
		$has_update         = version_compare( $latest_version, $plugin_data['current_version'], '>' );
		$license_active     = License_Service::STATUS_ACTIVE === $license_state['status'];
		$package_available  = $has_update && $license_active && '' !== $source_url;

		$message = isset( $provider_result['message'] ) ? sanitize_text_field( (string) $provider_result['message'] ) : '';
		if ( $has_update && ! $license_active ) {
			$message = __( 'Private update available but license is not active.', 'super-mechanic' );
		} elseif ( $has_update && '' === $source_url ) {
			$message = __( 'Private update metadata found, but package source is not configured.', 'super-mechanic' );
		}

		$last_result = self::RESULT_NO_UPDATE;
		if ( $has_update && $package_available ) {
			$last_result = self::RESULT_UPDATE_AVAILABLE;
		} elseif ( $has_update && ! $package_available ) {
			$last_result = self::RESULT_BLOCKED;
		} elseif ( isset( $provider_result['last_result'] ) ) {
			$candidate = sanitize_key( (string) $provider_result['last_result'] );
			if ( '' !== $candidate ) {
				$last_result = $candidate;
			}
		}

		$next_state = array(
			'provider'           => $this->provider->get_provider_name(),
			'last_check_at'      => gmdate( 'c' ),
			'latest_version'     => $latest_version,
			'package_available'  => $package_available,
			'message'            => $message,
			'last_result'        => $last_result,
			'requires'           => isset( $provider_result['requires'] ) ? sanitize_text_field( (string) $provider_result['requires'] ) : '',
			'tested'             => isset( $provider_result['tested'] ) ? sanitize_text_field( (string) $provider_result['tested'] ) : '',
			'changelog'          => isset( $provider_result['changelog'] ) ? sanitize_textarea_field( (string) $provider_result['changelog'] ) : '',
			'package_source_url' => $source_url,
		);

		$this->persist_updates_state( $next_state );

		return $next_state;
	}

	/**
	 * Build secure package endpoint for WordPress update payload.
	 *
	 * @param string $version Target version.
	 * @param string $source  Source URL.
	 * @return string
	 */
	public function get_secure_package_url( $version, $source ) {
		$version = sanitize_text_field( (string) $version );
		$source  = esc_url_raw( (string) $source );

		if ( '' === $version || '' === $source || ! $this->is_source_url_allowed( $source ) ) {
			return '';
		}

		$expires = time() + HOUR_IN_SECONDS;
		$plugin  = plugin_basename( SM_PLUGIN_FILE );
		$payload = $plugin . '|' . $version . '|' . $source . '|' . $expires;
		$sig     = hash_hmac( 'sha256', $payload, wp_salt( 'sm_private_updates' ) );

		return add_query_arg(
			array(
				'action'  => 'sm_private_update_package',
				'plugin'  => rawurlencode( $plugin ),
				'version' => rawurlencode( $version ),
				'source'  => rawurlencode( $source ),
				'expires' => $expires,
				'sig'     => rawurlencode( $sig ),
			),
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Integrate private update metadata into update_plugins transient.
	 *
	 * @param mixed $transient Existing transient.
	 * @return mixed
	 */
	public function filter_update_plugins_transient( $transient ) {
		if ( ! is_object( $transient ) ) {
			$transient = new \stdClass();
		}

		$plugin_data = $this->get_plugin_context();
		$state       = $this->check_for_updates();

		if ( empty( $transient->response ) || ! is_array( $transient->response ) ) {
			$transient->response = array();
		}

		if ( empty( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
			$transient->no_update = array();
		}

		unset( $transient->response[ $plugin_data['plugin_basename'] ] );

		$has_update = version_compare( $state['latest_version'], $plugin_data['current_version'], '>' );
		$package    = '';
		if ( $has_update && ! empty( $state['package_available'] ) && ! empty( $state['package_source_url'] ) ) {
			$package = $this->get_secure_package_url( $state['latest_version'], (string) $state['package_source_url'] );
		}

		$item = (object) array(
			'id'           => $plugin_data['plugin_basename'],
			'slug'         => $plugin_data['slug'],
			'plugin'       => $plugin_data['plugin_basename'],
			'new_version'  => $state['latest_version'],
			'url'          => '',
			'package'      => $package,
			'requires'     => $state['requires'],
			'tested'       => $state['tested'],
			'compatibility' => new \stdClass(),
		);

		if ( $has_update && '' !== $package ) {
			$transient->response[ $plugin_data['plugin_basename'] ] = $item;
			unset( $transient->no_update[ $plugin_data['plugin_basename'] ] );
		} else {
			$transient->no_update[ $plugin_data['plugin_basename'] ] = $item;
		}

		return $transient;
	}

	/**
	 * Provide plugin information for WordPress update details modal.
	 *
	 * @param false|object|array<string, mixed> $result Existing API result.
	 * @param string                             $action API action.
	 * @param object                             $args   API args.
	 * @return false|object|array<string, mixed>
	 */
	public function filter_plugins_api( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! is_object( $args ) ) {
			return $result;
		}

		$plugin_data = $this->get_plugin_context();
		$requested   = isset( $args->slug ) ? sanitize_key( (string) $args->slug ) : '';

		if ( $plugin_data['slug'] !== $requested ) {
			return $result;
		}

		$state = $this->check_for_updates();
		$package = '';
		if ( ! empty( $state['package_available'] ) && ! empty( $state['package_source_url'] ) ) {
			$package = $this->get_secure_package_url( $state['latest_version'], (string) $state['package_source_url'] );
		}

		return (object) array(
			'name'          => 'Super Mechanic',
			'slug'          => $plugin_data['slug'],
			'version'       => $state['latest_version'],
			'author'        => '<a href="https://mardisom.com">Mardisom Devs</a>',
			'requires'      => '' !== $state['requires'] ? $state['requires'] : '6.4',
			'tested'        => $state['tested'],
			'requires_php'  => '7.4',
			'last_updated'  => $state['last_check_at'],
			'download_link' => $package,
			'sections'      => array(
				'description' => esc_html__( 'Private update metadata for Super Mechanic.', 'super-mechanic' ),
				'changelog'   => '' !== $state['changelog'] ? wp_kses_post( wpautop( $state['changelog'] ) ) : esc_html__( 'No changelog provided by the update provider.', 'super-mechanic' ),
			),
		);
	}

	/**
	 * Handle secured package endpoint and redirect to provider source URL.
	 *
	 * @return void
	 */
	public function handle_private_package_request() {
		$plugin  = isset( $_GET['plugin'] ) ? sanitize_text_field( wp_unslash( $_GET['plugin'] ) ) : '';
		$version = isset( $_GET['version'] ) ? sanitize_text_field( wp_unslash( $_GET['version'] ) ) : '';
		$source  = isset( $_GET['source'] ) ? esc_url_raw( wp_unslash( $_GET['source'] ) ) : '';
		$expires = isset( $_GET['expires'] ) ? absint( $_GET['expires'] ) : 0;
		$sig     = isset( $_GET['sig'] ) ? sanitize_text_field( wp_unslash( $_GET['sig'] ) ) : '';

		$expected_plugin = plugin_basename( SM_PLUGIN_FILE );
		$payload         = $plugin . '|' . $version . '|' . $source . '|' . $expires;
		$expected_sig    = hash_hmac( 'sha256', $payload, wp_salt( 'sm_private_updates' ) );

		if ( $expected_plugin !== $plugin || '' === $source || '' === $version ) {
			wp_die( esc_html__( 'Invalid update package request.', 'super-mechanic' ), esc_html__( 'Private updates', 'super-mechanic' ), array( 'response' => 400 ) );
		}

		if ( $expires < time() || ! hash_equals( $expected_sig, $sig ) ) {
			wp_die( esc_html__( 'Expired or invalid update token.', 'super-mechanic' ), esc_html__( 'Private updates', 'super-mechanic' ), array( 'response' => 403 ) );
		}

		if ( ! $this->is_source_url_allowed( $source ) ) {
			wp_die( esc_html__( 'Package URL is not allowed.', 'super-mechanic' ), esc_html__( 'Private updates', 'super-mechanic' ), array( 'response' => 403 ) );
		}

		$license_state = $this->license_service->get_license_state();
		if ( License_Service::STATUS_ACTIVE !== $license_state['status'] ) {
			wp_die( esc_html__( 'License is not active for private package download.', 'super-mechanic' ), esc_html__( 'Private updates', 'super-mechanic' ), array( 'response' => 403 ) );
		}

		wp_safe_redirect( $source );
		exit;
	}

	/**
	 * Persist updates state fields.
	 *
	 * @param array<string, mixed> $state State to persist.
	 * @return void
	 */
	protected function persist_updates_state( array $state ) {
		$this->settings_service->set_setting( 'updates', 'provider', sanitize_text_field( (string) $state['provider'] ) );
		$this->settings_service->set_setting( 'updates', 'last_check_at', sanitize_text_field( (string) $state['last_check_at'] ) );
		$this->settings_service->set_setting( 'updates', 'latest_version', sanitize_text_field( (string) $state['latest_version'] ) );
		$this->settings_service->set_setting( 'updates', 'package_available', ! empty( $state['package_available'] ) );
		$this->settings_service->set_setting( 'updates', 'message', sanitize_text_field( (string) $state['message'] ) );
		$this->settings_service->set_setting( 'updates', 'last_result', sanitize_key( (string) $state['last_result'] ) );
		$this->settings_service->set_setting( 'updates', 'requires', sanitize_text_field( (string) $state['requires'] ) );
		$this->settings_service->set_setting( 'updates', 'tested', sanitize_text_field( (string) $state['tested'] ) );
		$this->settings_service->set_setting( 'updates', 'changelog', sanitize_textarea_field( (string) $state['changelog'] ) );
		$this->settings_service->set_setting( 'updates', 'package_source_url', esc_url_raw( (string) $state['package_source_url'] ) );
	}

	/**
	 * Check if last check timestamp is expired.
	 *
	 * @param string $last_check_at Last check date.
	 * @return bool
	 */
	protected function is_check_expired( $last_check_at ) {
		$last_check_timestamp = strtotime( (string) $last_check_at );

		if ( false === $last_check_timestamp || $last_check_timestamp <= 0 ) {
			return true;
		}

		return ( time() - $last_check_timestamp ) >= self::CHECK_TTL;
	}

	/**
	 * Validate provider source URL.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	protected function is_source_url_allowed( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = isset( $parts['scheme'] ) ? strtolower( (string) $parts['scheme'] ) : '';
		$host   = strtolower( (string) $parts['host'] );
		$site   = wp_parse_url( home_url(), PHP_URL_HOST );
		$site   = is_string( $site ) ? strtolower( $site ) : '';

		if ( 'https' === $scheme ) {
			return true;
		}

		if ( in_array( $host, array( 'localhost', '127.0.0.1' ), true ) ) {
			return true;
		}

		return '' !== $site && $site === $host;
	}

	/**
	 * Resolve plugin context data.
	 *
	 * @return array<string, string>
	 */
	protected function get_plugin_context() {
		$plugin_basename = plugin_basename( SM_PLUGIN_FILE );
		$slug            = dirname( $plugin_basename );
		if ( '.' === $slug || '' === $slug ) {
			$slug = sanitize_title( basename( $plugin_basename, '.php' ) );
		}

		return array(
			'plugin_basename' => $plugin_basename,
			'slug'            => sanitize_key( $slug ),
			'current_version' => sanitize_text_field( (string) SM_PLUGIN_VERSION ),
		);
	}

	/**
	 * Default updates state.
	 *
	 * @return array<string, mixed>
	 */
	protected function get_default_updates_state() {
		return array(
			'provider'           => $this->provider->get_provider_name(),
			'last_check_at'      => '',
			'latest_version'     => sanitize_text_field( (string) SM_PLUGIN_VERSION ),
			'package_available'  => false,
			'message'            => '',
			'last_result'        => self::RESULT_NO_UPDATE,
			'requires'           => '',
			'tested'             => '',
			'changelog'          => '',
			'package_source_url' => '',
		);
	}
}

