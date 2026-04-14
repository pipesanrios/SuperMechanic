<?php
/**
 * DB security service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Database\DB_Security_Repository;
use Super_Mechanic\Database\Schema;

defined( 'ABSPATH' ) || exit;

/**
 * Coordinates master-password validation and DB admin operations.
 */
class DB_Security_Service {
	/**
	 * Security settings group key.
	 */
	const SECURITY_GROUP = 'security';

	/**
	 * Transient prefix for one-time plain passwords.
	 */
	const MASTER_PASSWORD_TRANSIENT_PREFIX = 'sm_master_pwd_';

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * DB security repository.
	 *
	 * @var DB_Security_Repository
	 */
	protected $repository;
	/**
	 * DB export formatter service.
	 *
	 * @var DB_Export_Format_Service
	 */
	protected $export_format_service;
	/**
	 * DB import validator.
	 *
	 * @var DB_Import_Validator
	 */
	protected $import_validator;
	/**
	 * Reset engine service.
	 *
	 * @var Reset_Engine_Service
	 */
	protected $reset_engine_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null      $settings_service Settings service.
	 * @param DB_Security_Repository|null $repository      Repository.
	 */
	public function __construct( Settings_Service $settings_service = null, DB_Security_Repository $repository = null ) {
		$this->settings_service      = $settings_service ? $settings_service : new Settings_Service();
		$this->repository            = $repository ? $repository : new DB_Security_Repository();
		$this->export_format_service = new DB_Export_Format_Service();
		$this->import_validator      = new DB_Import_Validator();
		$this->reset_engine_service  = new Reset_Engine_Service( $this->settings_service );
	}

	/**
	 * Ensure a master password exists and return plain value only when newly generated.
	 *
	 * @param bool $send_email Whether to email the generated password to admin.
	 * @return array<string, mixed> Generated state and optional plain password.
	 */
	public function ensure_master_password_exists( $send_email = false ) {
		$hash = (string) $this->settings_service->get_setting( self::SECURITY_GROUP, 'master_password_hash', '' );
		if ( '' !== $hash ) {
			return array(
				'generated'      => false,
				'master_password' => '',
			);
		}

		return $this->generate_master_password( $send_email );
	}

	/**
	 * Generate and persist a new master password hash.
	 *
	 * @param bool $send_email Whether to email the generated password to admin.
	 * @return array<string, mixed> Result with plain password for one-time display.
	 */
	public function generate_master_password( $send_email = false ) {
		$plain_password = $this->create_plain_master_password();
		$stored         = $this->store_master_password_hash( $plain_password );
		$token          = '';

		if ( ! $stored ) {
			return array(
				'success' => false,
				'message' => __( 'Could not store the master password hash.', 'super-mechanic' ),
			);
		}

		$token = $this->store_one_time_master_password( $plain_password );

		if ( $send_email ) {
			$this->send_master_password_email( $plain_password );
		}

		return array(
			'success'         => true,
			'generated'       => true,
			'master_password' => $plain_password,
			'token'           => $token,
			'message'         => __( 'Master password generated successfully.', 'super-mechanic' ),
		);
	}

	/**
	 * Verify a master password input.
	 *
	 * @param string $input Raw password input.
	 * @return true|\WP_Error
	 */
	public function verify_master_password( $input ) {
		$hash  = (string) $this->settings_service->get_setting( self::SECURITY_GROUP, 'master_password_hash', '' );
		$input = (string) $input;

		if ( '' === $hash ) {
			return new \WP_Error( 'sm_master_password_missing', __( 'Master password is not configured.', 'super-mechanic' ) );
		}

		if ( '' === $input ) {
			return new \WP_Error( 'sm_master_password_empty', __( 'Master password is required.', 'super-mechanic' ) );
		}

		if ( ! wp_check_password( $input, $hash ) ) {
			return new \WP_Error( 'sm_master_password_invalid', __( 'Invalid master password.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Consume one-time master password from transient token.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	public function consume_one_time_master_password( $token ) {
		$token = sanitize_key( (string) $token );
		if ( '' === $token ) {
			return '';
		}

		$key   = self::MASTER_PASSWORD_TRANSIENT_PREFIX . $token;
		$value = get_transient( $key );

		if ( ! is_string( $value ) || '' === $value ) {
			return '';
		}

		delete_transient( $key );

		return $value;
	}

	/**
	 * Export plugin DB payload after master-password verification.
	 *
	 * @param string $master_password Raw master password input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function export_plugin_data( $master_password ) {
		$verified = $this->verify_master_password( $master_password );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$tables_export = $this->repository->export_plugin_data();
		if ( is_wp_error( $tables_export ) ) {
			return $tables_export;
		}

		return array(
			'generated_at'    => current_time( 'mysql', true ),
			'schema_version'  => Schema::get_schema_version(),
			'plugin_version'  => defined( 'SM_PLUGIN_VERSION' ) ? (string) SM_PLUGIN_VERSION : '0.1.0',
			'tables'          => $tables_export,
		);
	}

	/**
	 * Export plugin DB payload as downloadable file in requested format.
	 *
	 * @param string $master_password Raw master password input.
	 * @param string $format          Export format: json/csv/excel.
	 * @return array<string, string>|\WP_Error
	 */
	public function export_plugin_data_file( $master_password, $format ) {
		$payload = $this->export_plugin_data( $master_password );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		return $this->export_format_service->build_export_file( $payload, $format );
	}

	/**
	 * Reset plugin DB after strong confirmation and master-password verification.
	 *
	 * @param string $master_password Master password input.
	 * @param string $confirm_phrase  Typed confirm phrase.
	 * @param bool   $confirm_checked Explicit checkbox confirmation.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reset_plugin_data( $master_password, $confirm_phrase, $confirm_checked ) {
		$verified = $this->verify_master_password( $master_password );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		if ( ! $confirm_checked ) {
			return new \WP_Error( 'sm_db_reset_confirm_missing', __( 'You must explicitly confirm the reset operation.', 'super-mechanic' ) );
		}

		if ( 'RESET DB' !== strtoupper( trim( (string) $confirm_phrase ) ) ) {
			return new \WP_Error( 'sm_db_reset_confirm_phrase_invalid', __( 'The reset confirmation phrase is invalid.', 'super-mechanic' ) );
		}

		$result = $this->reset_engine_service->reset_operational_data();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Import plugin DB backup from uploaded JSON file after strict validations.
	 *
	 * @param string               $master_password Master password input.
	 * @param array<string, mixed> $uploaded_file   Uploaded file entry from $_FILES.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function import_plugin_data_from_uploaded_json( $master_password, array $uploaded_file ) {
		$verified = $this->verify_master_password( $master_password );
		if ( is_wp_error( $verified ) ) {
			return $verified;
		}

		$schema_tables = Schema::get_tables();
		$validated     = $this->import_validator->validate_uploaded_json_backup( $uploaded_file, Schema::get_schema_version(), $schema_tables );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$table_rows = isset( $validated['tables'] ) && is_array( $validated['tables'] ) ? $validated['tables'] : array();
		$shape_ok   = $this->repository->validate_import_table_rows( $table_rows );
		if ( is_wp_error( $shape_ok ) ) {
			return $shape_ok;
		}

		$default_name = (string) $this->settings_service->get_setting( 'business', 'business_name', 'Super Mechanic' );

		return $this->repository->import_plugin_data( $table_rows, $default_name );
	}

	/**
	 * Check if a master password hash exists.
	 *
	 * @return bool
	 */
	public function has_master_password() {
		$hash = (string) $this->settings_service->get_setting( self::SECURITY_GROUP, 'master_password_hash', '' );

		return '' !== $hash;
	}

	/**
	 * Get stored timestamp metadata.
	 *
	 * @return string
	 */
	public function get_master_password_generated_at() {
		return (string) $this->settings_service->get_setting( self::SECURITY_GROUP, 'master_password_generated_at', '' );
	}

	/**
	 * Create a plain random master password.
	 *
	 * @return string
	 */
	protected function create_plain_master_password() {
		if ( function_exists( 'random_bytes' ) ) {
			try {
				return strtoupper( bin2hex( random_bytes( 16 ) ) );
			} catch ( \Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			}
		}

		return wp_generate_password( 24, true, true );
	}

	/**
	 * Persist master password hash and metadata.
	 *
	 * @param string $plain_password Plain password.
	 * @return bool
	 */
	protected function store_master_password_hash( $plain_password ) {
		$hash = wp_hash_password( (string) $plain_password );

		$ok_hash = $this->settings_service->set_setting( self::SECURITY_GROUP, 'master_password_hash', $hash );
		$ok_time = $this->settings_service->set_setting( self::SECURITY_GROUP, 'master_password_generated_at', current_time( 'mysql', true ) );

		return (bool) ( $ok_hash && $ok_time );
	}

	/**
	 * Store one-time plain password in transient and return token.
	 *
	 * @param string $plain_password Plain password.
	 * @return string
	 */
	protected function store_one_time_master_password( $plain_password ) {
		$token = sanitize_key( wp_generate_password( 20, false, false ) );

		if ( '' === $token ) {
			return '';
		}

		set_transient( self::MASTER_PASSWORD_TRANSIENT_PREFIX . $token, (string) $plain_password, 15 * MINUTE_IN_SECONDS );

		return $token;
	}

	/**
	 * Optionally email master password to site admin email.
	 *
	 * @param string $plain_password Plain password.
	 * @return void
	 */
	protected function send_master_password_email( $plain_password ) {
		$admin_email = get_option( 'admin_email', '' );
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$subject = __( 'Super Mechanic master password', 'super-mechanic' );
		$message = sprintf(
			/* translators: %s: generated master password */
			__( 'Your new Super Mechanic master password is: %s', 'super-mechanic' ),
			(string) $plain_password
		);

		wp_mail( $admin_email, $subject, $message );
	}
}
