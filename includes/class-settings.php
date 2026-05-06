<?php
/**
 * Settings manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Helpers\License_Service;
use Super_Mechanic\Helpers\Update_Service;
use Super_Mechanic\Helpers\Plan_Access_Service;
use Super_Mechanic\Helpers\Feature_Flags;
use Super_Mechanic\Helpers\DB_Security_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Client_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Sync_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders plugin settings.
 */
class Settings {
	/**
	 * Option name.
	 */
	const OPTION_NAME = 'super_mechanic_settings';

	/**
	 * Settings group name.
	 */
	const OPTION_GROUP = 'super_mechanic_settings_group';

	/**
	 * Settings page slug.
	 */
	const PAGE_SLUG = 'super-mechanic-settings';

	/**
	 * Modern settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;
	/**
	 * License service.
	 *
	 * @var License_Service
	 */
	protected $license_service;
	/**
	 * Update service.
	 *
	 * @var Update_Service
	 */
	protected $update_service;
	/**
	 * Plan access service.
	 *
	 * @var Plan_Access_Service
	 */
	protected $plan_access_service;
	/**
	 * Google Calendar service.
	 *
	 * @var Google_Calendar_Client_Service
	 */
	protected $google_calendar_service;
	/**
	 * Google Calendar sync service.
	 *
	 * @var Google_Calendar_Sync_Service
	 */
	protected $google_calendar_sync_service;
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;
	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;
	/**
	 * DB security service.
	 *
	 * @var DB_Security_Service
	 */
	protected $db_security_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_service = new Settings_Service();
		$this->license_service  = new License_Service( $this->settings_service );
		$this->update_service   = new Update_Service( $this->settings_service, null, $this->license_service );
		$this->plan_access_service = new Plan_Access_Service( $this->settings_service, $this->license_service );
		$this->google_calendar_service = new Google_Calendar_Client_Service( $this->settings_service );
		$this->google_calendar_sync_service = new Google_Calendar_Sync_Service( $this->google_calendar_service );
		$this->business_service = new Business_Service();
		$this->business_context_service = new Business_Context_Service( $this->settings_service, $this->business_service );
		$this->db_security_service = new DB_Security_Service( $this->settings_service );
		$this->google_calendar_service->set_sync_service( $this->google_calendar_sync_service );
		$appointment_service = new Appointment_Service( null, null, null, null, $this->google_calendar_sync_service );
		$this->google_calendar_sync_service->set_appointment_service( $appointment_service );
	}

	/**
	 * Register admin hooks for license actions.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_post_sm_license_activate', array( $this, 'handle_license_activate' ) );
		add_action( 'admin_post_sm_license_validate', array( $this, 'handle_license_validate' ) );
		add_action( 'admin_post_sm_license_deactivate', array( $this, 'handle_license_deactivate' ) );
		add_action( 'admin_post_sm_google_calendar_save_config', array( $this, 'handle_google_calendar_save_config' ) );
		add_action( 'admin_post_sm_google_calendar_reconcile_now', array( $this, 'handle_google_calendar_reconcile_now' ) );
		add_action( 'admin_post_sm_google_calendar_renew_watch', array( $this, 'handle_google_calendar_renew_watch' ) );
		add_action( 'admin_post_sm_db_security_generate_master_password', array( $this, 'handle_db_security_generate_master_password' ) );
		add_action( 'admin_post_sm_db_security_export', array( $this, 'handle_db_security_export' ) );
		add_action( 'admin_post_sm_db_security_reset', array( $this, 'handle_db_security_reset' ) );
		add_action( 'admin_post_sm_db_security_import_json', array( $this, 'handle_db_security_import_json' ) );
	}

	/**
	 * Register settings, sections and fields.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		add_settings_section(
			'sm_general_settings',
			__( 'Business Profile', 'super-mechanic' ),
			array( $this, 'render_general_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'company_name',
			__( 'Business name', 'super-mechanic' ),
			array( $this, 'render_field_company_name' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'business_context_key',
			__( 'Business context key', 'super-mechanic' ),
			array( $this, 'render_field_business_context_key' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'business_fallback_id',
			__( 'Fallback business ID', 'super-mechanic' ),
			array( $this, 'render_field_business_fallback_id' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_section(
			'sm_language_settings',
			__( 'Language Settings', 'super-mechanic' ),
			array( $this, 'render_language_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'language_locale',
			__( 'Default locale', 'super-mechanic' ),
			array( $this, 'render_field_language_locale' ),
			self::PAGE_SLUG,
			'sm_language_settings'
		);

		add_settings_field(
			'language_current_default',
			__( 'Current/default language', 'super-mechanic' ),
			array( $this, 'render_field_language_current_default' ),
			self::PAGE_SLUG,
			'sm_language_settings'
		);

		add_settings_field(
			'language_bundled_list',
			__( 'Bundled languages', 'super-mechanic' ),
			array( $this, 'render_field_language_bundled_list' ),
			self::PAGE_SLUG,
			'sm_language_settings'
		);

		add_settings_field(
			'language_future_placeholder',
			__( 'Future language expansion', 'super-mechanic' ),
			array( $this, 'render_field_language_future_placeholder' ),
			self::PAGE_SLUG,
			'sm_language_settings'
		);

		add_settings_field(
			'default_currency',
			__( 'Default currency', 'super-mechanic' ),
			array( $this, 'render_field_default_currency' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);
		add_settings_field(
			'supported_currencies',
			__( 'Supported currencies', 'super-mechanic' ),
			array( $this, 'render_field_supported_currencies' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'timezone',
			__( 'Business timezone', 'super-mechanic' ),
			array( $this, 'render_field_timezone' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'date_format',
			__( 'Date format', 'super-mechanic' ),
			array( $this, 'render_field_date_format' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_section(
			'sm_process_settings',
			__( 'Process Rules', 'super-mechanic' ),
			array( $this, 'render_process_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'enabled_process_types',
			__( 'Enabled process types', 'super-mechanic' ),
			array( $this, 'render_field_enabled_process_types' ),
			self::PAGE_SLUG,
			'sm_process_settings'
		);

		add_settings_field(
			'allow_step_back',
			__( 'Allow step back', 'super-mechanic' ),
			array( $this, 'render_field_allow_step_back' ),
			self::PAGE_SLUG,
			'sm_process_settings'
		);

		add_settings_field(
			'auto_complete_on_final_step',
			__( 'Auto-complete final step', 'super-mechanic' ),
			array( $this, 'render_field_auto_complete_on_final_step' ),
			self::PAGE_SLUG,
			'sm_process_settings'
		);

		add_settings_section(
			'sm_financial_settings',
			__( 'Financial Defaults', 'super-mechanic' ),
			array( $this, 'render_financial_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'default_tax_rate',
			__( 'Default tax rate (%)', 'super-mechanic' ),
			array( $this, 'render_field_default_tax_rate' ),
			self::PAGE_SLUG,
			'sm_financial_settings'
		);

		add_settings_field(
			'allow_partial_payments',
			__( 'Allow partial payments', 'super-mechanic' ),
			array( $this, 'render_field_allow_partial_payments' ),
			self::PAGE_SLUG,
			'sm_financial_settings'
		);

		add_settings_section(
			'sm_portal_settings',
			__( 'Portal And Notifications', 'super-mechanic' ),
			array( $this, 'render_portal_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'enable_client_notifications',
			__( 'Client notifications', 'super-mechanic' ),
			array( $this, 'render_field_enable_client_notifications' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);
		add_settings_field(
			'enable_email_notifications',
			__( 'Email notifications', 'super-mechanic' ),
			array( $this, 'render_field_enable_email_notifications' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);
		add_settings_field(
			'enable_automation_runtime',
			__( 'Automation runtime', 'super-mechanic' ),
			array( $this, 'render_field_enable_automation_runtime' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);
		add_settings_field(
			'enable_appointment_reminders',
			__( 'Appointment reminders', 'super-mechanic' ),
			array( $this, 'render_field_enable_appointment_reminders' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);
		add_settings_field(
			'appointment_reminder_minutes_before',
			__( 'Reminder lead time (minutes)', 'super-mechanic' ),
			array( $this, 'render_field_appointment_reminder_minutes_before' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);

		add_settings_field(
			'client_panel_enabled',
			__( 'Client portal enabled', 'super-mechanic' ),
			array( $this, 'render_field_client_panel_enabled' ),
			self::PAGE_SLUG,
			'sm_portal_settings'
		);

	}

	/**
	 * Get default settings.
	 *
	 * @return array<string, mixed>
	 */
	public function get_default_settings() {
		return array(
			'company_name'          => '',
			'business_context_key'  => 'default',
			'business_id'           => 1,
			'language_locale'       => 'en_US',
			'default_currency'      => 'USD',
			'supported_currencies'  => array( 'USD', 'EUR', 'COP', 'PAB' ),
			'timezone'              => function_exists( 'wp_timezone_string' ) && wp_timezone_string() ? wp_timezone_string() : 'UTC',
			'date_format'           => 'd/m/Y',
			'enabled_process_types' => array( 'maintenance', 'pre_delivery', 'paperwork' ),
			'allow_step_back'       => 1,
			'auto_complete_on_final_step' => 1,
			'default_tax_rate'      => 0,
			'allow_partial_payments' => 1,
			'enable_client_notifications' => 1,
			'enable_email_notifications' => 0,
			'enable_automation_runtime' => 1,
			'enable_appointment_reminders' => 1,
			'appointment_reminder_minutes_before' => 120,
			'client_panel_enabled'  => 1,
		);
	}

	/**
	 * Get current settings merged with defaults.
	 *
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$service_settings = $this->settings_service->get_all_settings();
		$defaults         = $this->get_default_settings();
		$allowed_locales  = array( 'en_US', 'es_ES', 'it_IT' );
		$mapped_locale    = isset( $service_settings['business']['locale'] ) ? (string) $service_settings['business']['locale'] : $defaults['language_locale'];
		if ( ! in_array( $mapped_locale, $allowed_locales, true ) ) {
			$mapped_locale = 'en_US';
		}

		$mapped           = array(
			'company_name'               => isset( $service_settings['business']['business_name'] ) ? $service_settings['business']['business_name'] : $defaults['company_name'],
			'business_context_key'       => isset( $service_settings['business']['business_context_key'] ) ? $service_settings['business']['business_context_key'] : $defaults['business_context_key'],
			'business_id'                => isset( $service_settings['business']['business_id'] ) ? absint( $service_settings['business']['business_id'] ) : $defaults['business_id'],
			'language_locale'            => $mapped_locale,
			'default_currency'           => isset( $service_settings['business']['currency'] ) ? $service_settings['business']['currency'] : $defaults['default_currency'],
			'supported_currencies'       => isset( $service_settings['business']['supported_currencies'] ) ? $service_settings['business']['supported_currencies'] : $defaults['supported_currencies'],
			'timezone'                   => isset( $service_settings['business']['timezone'] ) ? $service_settings['business']['timezone'] : $defaults['timezone'],
			'date_format'                => isset( $service_settings['business']['date_format'] ) ? $service_settings['business']['date_format'] : $defaults['date_format'],
			'enabled_process_types'      => isset( $service_settings['process']['enabled_process_types'] ) ? $service_settings['process']['enabled_process_types'] : $defaults['enabled_process_types'],
			'allow_step_back'            => ! empty( $service_settings['process']['allow_step_back'] ) ? 1 : 0,
			'auto_complete_on_final_step' => ! empty( $service_settings['process']['auto_complete_on_final_step'] ) ? 1 : 0,
			'default_tax_rate'           => isset( $service_settings['financial']['default_tax_rate'] ) ? $service_settings['financial']['default_tax_rate'] : $defaults['default_tax_rate'],
			'allow_partial_payments'     => ! empty( $service_settings['financial']['allow_partial_payments'] ) ? 1 : 0,
			'enable_client_notifications' => ! empty( $service_settings['notifications']['enable_client_notifications'] ) ? 1 : 0,
			'enable_email_notifications' => ! empty( $service_settings['notifications']['enable_email_notifications'] ) ? 1 : 0,
			'enable_automation_runtime'  => ! empty( $service_settings['automation']['enable_automation_runtime'] ) ? 1 : 0,
			'enable_appointment_reminders' => ! empty( $service_settings['automation']['enable_appointment_reminders'] ) ? 1 : 0,
			'appointment_reminder_minutes_before' => isset( $service_settings['automation']['appointment_reminder_minutes_before'] ) ? absint( $service_settings['automation']['appointment_reminder_minutes_before'] ) : $defaults['appointment_reminder_minutes_before'],
			'client_panel_enabled'       => ! empty( $service_settings['portal']['client_panel_enabled'] ) ? 1 : 0,
		);

		return wp_parse_args( $settings, wp_parse_args( $mapped, $defaults ) );
	}

	/**
	 * Sanitize settings input.
	 *
	 * @param mixed $input Raw settings input.
	 * @return array<string, mixed>
	 */
	public function sanitize_settings( $input ) {
		$defaults = $this->get_default_settings();
		$input    = is_array( $input ) ? $input : array();

		$currencies = array_keys( $this->settings_service->get_supported_currencies() );
		$supported_input = array();
		if ( isset( $input['supported_currencies'] ) ) {
			$supported_input = $this->parse_supported_currencies_input( $input['supported_currencies'] );
		}
		$supported_currencies = ! empty( $supported_input ) ? $supported_input : $currencies;
		if ( empty( $supported_currencies ) ) {
			$supported_currencies = isset( $defaults['supported_currencies'] ) && is_array( $defaults['supported_currencies'] ) ? $defaults['supported_currencies'] : array( 'USD', 'EUR', 'COP', 'PAB' );
		}
		$default_currency = isset( $input['default_currency'] ) ? strtoupper( sanitize_text_field( (string) $input['default_currency'] ) ) : $defaults['default_currency'];
		if ( ! in_array( $default_currency, $supported_currencies, true ) ) {
			$default_currency = $supported_currencies[0];
		}
		$formats    = array( 'd/m/Y', 'm/d/Y', 'Y-m-d' );
		$types      = array( 'maintenance', 'pre_delivery', 'paperwork' );
		$locales    = array( 'en_US', 'es_ES', 'it_IT' );
		$timezones  = timezone_identifiers_list();

		$sanitized = array(
			'company_name'          => isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : $defaults['company_name'],
			'business_context_key'  => isset( $input['business_context_key'] ) ? sanitize_key( $input['business_context_key'] ) : $defaults['business_context_key'],
			'business_id'           => isset( $input['business_id'] ) ? max( 1, absint( $input['business_id'] ) ) : $defaults['business_id'],
			'language_locale'       => isset( $input['language_locale'] ) && in_array( $input['language_locale'], $locales, true ) ? $input['language_locale'] : $defaults['language_locale'],
			'default_currency'      => $default_currency,
			'supported_currencies'  => $supported_currencies,
			'timezone'              => isset( $input['timezone'] ) && in_array( $input['timezone'], $timezones, true ) ? $input['timezone'] : $defaults['timezone'],
			'date_format'           => isset( $input['date_format'] ) && in_array( $input['date_format'], $formats, true ) ? $input['date_format'] : $defaults['date_format'],
			'enabled_process_types' => array_values( array_intersect( $types, isset( $input['enabled_process_types'] ) && is_array( $input['enabled_process_types'] ) ? array_map( 'sanitize_text_field', $input['enabled_process_types'] ) : array() ) ),
			'allow_step_back'       => ! empty( $input['allow_step_back'] ) ? 1 : 0,
			'auto_complete_on_final_step' => ! empty( $input['auto_complete_on_final_step'] ) ? 1 : 0,
			'default_tax_rate'      => isset( $input['default_tax_rate'] ) ? round( (float) str_replace( ',', '.', (string) $input['default_tax_rate'] ), 2 ) : $defaults['default_tax_rate'],
			'allow_partial_payments' => ! empty( $input['allow_partial_payments'] ) ? 1 : 0,
			'enable_client_notifications' => ! empty( $input['enable_client_notifications'] ) ? 1 : 0,
			'enable_email_notifications' => ! empty( $input['enable_email_notifications'] ) ? 1 : 0,
			'enable_automation_runtime' => ! empty( $input['enable_automation_runtime'] ) ? 1 : 0,
			'enable_appointment_reminders' => ! empty( $input['enable_appointment_reminders'] ) ? 1 : 0,
			'appointment_reminder_minutes_before' => isset( $input['appointment_reminder_minutes_before'] ) ? max( 5, min( 1440, absint( $input['appointment_reminder_minutes_before'] ) ) ) : $defaults['appointment_reminder_minutes_before'],
			'client_panel_enabled'  => ! empty( $input['client_panel_enabled'] ) ? 1 : 0,
		);

		if ( empty( $sanitized['enabled_process_types'] ) ) {
			$sanitized['enabled_process_types'] = $defaults['enabled_process_types'];
		}

		if ( '' === $sanitized['business_context_key'] ) {
			$sanitized['business_context_key'] = $defaults['business_context_key'];
		}

		$this->sync_modern_settings( $sanitized );

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		echo '<div class="wrap sm-admin-shell">';
		$this->render_license_notice();
		$this->render_google_calendar_notice();
		$this->render_db_security_notice();
		$auto_master_password = '';
		$auto_generation      = $this->db_security_service->ensure_master_password_exists( false );
		if ( ! empty( $auto_generation['generated'] ) && ! empty( $auto_generation['master_password'] ) ) {
			$auto_master_password = (string) $auto_generation['master_password'];
		}
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Super Mechanic Settings', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Centralize the business profile, operational defaults and pre-API baseline without opening multi-tenant behavior yet.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		$this->render_settings_sections(
			array(
				'sm_general_settings',
				'sm_language_settings',
				'sm_process_settings',
				'sm_financial_settings',
				'sm_portal_settings',
			)
		);
		echo '<div class="sm-form-actions">';
		submit_button( __( 'Guardar ajustes', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'License summary', 'super-mechanic' ) . '</h2>';
		$this->render_license_section();
		$this->render_field_license_status();
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Private updates', 'super-mechanic' ) . '</h2>';
		$this->render_updates_section();
		$this->render_field_updates_status();
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Plan and feature access', 'super-mechanic' ) . '</h2>';
		$this->render_plan_access_section();
		$this->render_field_plan_access_status();
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Google Calendar (1-way sync)', 'super-mechanic' ) . '</h2>';
		$this->render_google_calendar_section();
		$this->render_field_google_calendar_status();
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<h2>' . esc_html__( 'Database security (export/reset)', 'super-mechanic' ) . '</h2>';
		$this->render_db_security_section( $auto_master_password );
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render selected sections and fields from Settings API registry.
	 *
	 * @param array<int, string> $section_ids Section ids.
	 * @return void
	 */
	protected function render_settings_sections( array $section_ids ) {
		global $wp_settings_sections, $wp_settings_fields;

		if ( empty( $wp_settings_sections[ self::PAGE_SLUG ] ) || ! is_array( $wp_settings_sections[ self::PAGE_SLUG ] ) ) {
			return;
		}

		foreach ( $section_ids as $section_id ) {
			if ( empty( $wp_settings_sections[ self::PAGE_SLUG ][ $section_id ] ) ) {
				continue;
			}

			$section = $wp_settings_sections[ self::PAGE_SLUG ][ $section_id ];

			if ( ! empty( $section['title'] ) ) {
				echo '<h2>' . esc_html( $section['title'] ) . '</h2>';
			}

			if ( ! empty( $section['callback'] ) && is_callable( $section['callback'] ) ) {
				call_user_func( $section['callback'] );
			}

			if ( ! empty( $wp_settings_fields[ self::PAGE_SLUG ][ $section_id ] ) ) {
				echo '<table class="form-table" role="presentation">';
				do_settings_fields( self::PAGE_SLUG, $section_id );
				echo '</table>';
			}
		}
	}

	/**
	 * Render section introduction.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'These values define the fallback business profile and compatibility baseline. The active runtime context now resolves by user selection first.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render process section introduction.
	 *
	 * @return void
	 */
	public function render_process_section() {
		echo '<p>' . esc_html__( 'These controls stay inside the current single-business runtime and only expose process rules already supported by services.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render language section introduction.
	 *
	 * @return void
	 */
	public function render_language_section() {
		echo '<p>' . esc_html__( 'Visible language baseline for admin settings. Full translation coverage and advanced language management are intentionally deferred to a later subphase.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render financial section introduction.
	 *
	 * @return void
	 */
	public function render_financial_section() {
		echo '<p>' . esc_html__( 'Financial defaults remain schema-compatible and feed the active invoice runtime without introducing a new billing model.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render portal section introduction.
	 *
	 * @return void
	 */
	public function render_portal_section() {
		echo '<p>' . esc_html__( 'Portal and notification toggles stay as product-readiness controls for the current business context only.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render license section introduction.
	 *
	 * @return void
	 */
	public function render_license_section() {
		$license_url = admin_url( 'admin.php?page=super-mechanic-license' );
		echo '<p>' . esc_html__( 'This Settings page provides a read-only summary. License activation, trial controls and plan management are handled in the dedicated License page.', 'super-mechanic' ) . '</p>';
		echo '<p><a class="button button-secondary" href="' . esc_url( $license_url ) . '">' . esc_html__( 'Open License page', 'super-mechanic' ) . '</a></p>';
	}

	/**
	 * Render updates section introduction.
	 *
	 * @return void
	 */
	public function render_updates_section() {
		echo '<p>' . esc_html__( 'Private update checks run through a provider contract and WordPress native update hooks, ready for a future external provider.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render plan access section introduction.
	 *
	 * @return void
	 */
	public function render_plan_access_section() {
		echo '<p>' . esc_html__( 'Read-only plan and feature snapshot for diagnostics. Use the License page for lifecycle actions and plan-related management.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render Google Calendar section intro.
	 *
	 * @return void
	 */
	public function render_google_calendar_section() {
		echo '<p>' . esc_html__( 'Outbound sync remains one-way by default. Manual inbound reconciliation is controlled and local appointments remain source of truth.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render company name field.
	 *
	 * @return void
	 */
	public function render_field_company_name() {
		$settings = $this->get_settings();

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[company_name]" value="' . esc_attr( $settings['company_name'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Public business name used in printable documents and customer-facing outputs.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render fallback business ID field.
	 *
	 * @return void
	 */
	public function render_field_business_fallback_id() {
		$settings = $this->get_settings();
		$runtime  = $this->business_context_service->get_runtime_context();
		$current  = isset( $runtime['business_id'] ) ? absint( $runtime['business_id'] ) : 1;
		$source   = isset( $runtime['data_source'] ) ? (string) $runtime['data_source'] : '';

		echo '<input type="number" min="1" step="1" class="small-text" name="' . esc_attr( self::OPTION_NAME ) . '[business_id]" value="' . esc_attr( (string) max( 1, absint( $settings['business_id'] ) ) ) . '" />';
		echo '<p class="description">' . esc_html__( 'Fallback used when the current user has no explicit selector. Runtime now resolves by user -> setting -> default.', 'super-mechanic' ) . '<br />' . esc_html( '#' . $current . ' (' . $source . ')' ) . '</p>';
	}

	/**
	 * Render business context key field.
	 *
	 * @return void
	 */
	public function render_field_business_context_key() {
		$settings = $this->get_settings();

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[business_context_key]" value="' . esc_attr( $settings['business_context_key'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Stable key for compatibility and integrations. Active business context is handled by user selector with safe fallback.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render locale field.
	 *
	 * @return void
	 */
	public function render_field_language_locale() {
		$settings = $this->get_settings();
		$locales  = array(
			'en_US' => __( 'English (United States)', 'super-mechanic' ),
			'es_ES' => __( 'Spanish (Spain)', 'super-mechanic' ),
			'it_IT' => __( 'Italian (Italy)', 'super-mechanic' ),
		);

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[language_locale]">';

		foreach ( $locales as $locale => $label ) {
			echo '<option value="' . esc_attr( $locale ) . '" ' . selected( $settings['language_locale'], $locale, false ) . '>' . esc_html( $label ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Keeps the base locale explicit for translations and future API payload localization.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render current/default language field.
	 *
	 * @return void
	 */
	public function render_field_language_current_default() {
		$settings = $this->get_settings();
		$locales  = array(
			'en_US' => __( 'English', 'super-mechanic' ),
			'es_ES' => __( 'Español', 'super-mechanic' ),
			'it_IT' => __( 'Italiano', 'super-mechanic' ),
		);
		$locale   = isset( $settings['language_locale'] ) ? (string) $settings['language_locale'] : 'en_US';
		$label    = isset( $locales[ $locale ] ) ? $locales[ $locale ] : $locales['en_US'];

		echo '<p><strong>' . esc_html( $label ) . '</strong> <code>' . esc_html( $locale ) . '</code></p>';
		echo '<p class="description">' . esc_html__( 'Reflects the currently configured default language used by the settings baseline.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render bundled language list.
	 *
	 * @return void
	 */
	public function render_field_language_bundled_list() {
		echo '<ul style="margin:0;padding-left:18px;">';
		echo '<li><strong>English</strong> <code>en_US</code></li>';
		echo '<li><strong>Español</strong> <code>es_ES</code></li>';
		echo '<li><strong>Italiano</strong> <code>it_IT</code></li>';
		echo '</ul>';
		echo '<p class="description">' . esc_html__( 'Bundled languages currently available in this baseline settings selector.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render future language placeholder field.
	 *
	 * @return void
	 */
	public function render_field_language_future_placeholder() {
		echo '<div class="notice notice-info inline"><p><strong>' . esc_html__( 'Prepared for future languages', 'super-mechanic' ) . '</strong><br />' . esc_html__( 'Additional language onboarding and full translation coverage are planned for 56P1-C.', 'super-mechanic' ) . '</p></div>';
	}

	/**
	 * Render default currency field.
	 *
	 * @return void
	 */
	public function render_field_default_currency() {
		$settings   = $this->get_settings();
		$currencies = array_keys( $this->settings_service->get_supported_currencies() );
		if ( empty( $currencies ) && ! empty( $settings['supported_currencies'] ) && is_array( $settings['supported_currencies'] ) ) {
			$currencies = $settings['supported_currencies'];
		}
		if ( empty( $currencies ) ) {
			$currencies = array( 'USD', 'EUR', 'COP', 'PAB' );
		}

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[default_currency]">';

		foreach ( $currencies as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['default_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Default business currency used by quote and invoice services when a specific value is not provided.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render supported currencies field.
	 *
	 * @return void
	 */
	public function render_field_supported_currencies() {
		$settings   = $this->get_settings();
		$currencies = isset( $settings['supported_currencies'] ) && is_array( $settings['supported_currencies'] ) ? $settings['supported_currencies'] : array( 'USD', 'EUR', 'COP', 'PAB' );
		$value      = implode( ', ', array_map( 'sanitize_text_field', $currencies ) );

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[supported_currencies]" value="' . esc_attr( $value ) . '" />';
		echo '<p class="description">' . esc_html__( 'Comma-separated ISO currency codes available in forms and report filters. Example: USD, EUR, COP, PAB.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render timezone field.
	 *
	 * @return void
	 */
	public function render_field_timezone() {
		$settings  = $this->get_settings();
		$timezones = array(
			'UTC',
			'Europe/Rome',
			'America/New_York',
			'America/Bogota',
			'America/Panama',
		);

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[timezone]">';

		foreach ( $timezones as $timezone ) {
			echo '<option value="' . esc_attr( $timezone ) . '" ' . selected( $settings['timezone'], $timezone, false ) . '>' . esc_html( $timezone ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Business timezone stored for consistent operational defaults and future business-aware scheduling.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render date format field.
	 *
	 * @return void
	 */
	public function render_field_date_format() {
		$settings = $this->get_settings();
		$formats  = array( 'd/m/Y', 'm/d/Y', 'Y-m-d' );

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[date_format]">';

		foreach ( $formats as $format ) {
			echo '<option value="' . esc_attr( $format ) . '" ' . selected( $settings['date_format'], $format, false ) . '>' . esc_html( $format ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Display-oriented format stored as business preference. It does not rewrite existing historical timestamps.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render enabled process types field.
	 *
	 * @return void
	 */
	public function render_field_enabled_process_types() {
		$settings      = $this->get_settings();
		$process_types = array(
			'maintenance'  => __( 'Maintenance', 'super-mechanic' ),
			'pre_delivery' => __( 'Pre-delivery', 'super-mechanic' ),
			'paperwork'    => __( 'Paperwork', 'super-mechanic' ),
		);

		foreach ( $process_types as $key => $label ) {
			echo '<label>';
			echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enabled_process_types][]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $settings['enabled_process_types'], true ), true, false ) . ' /> ';
			echo esc_html( $label );
			echo '</label><br />';
		}

		echo '<p class="description">' . esc_html__( 'Stored as allowed business scope for the current single-business setup. Existing runtime flows remain unchanged unless the active services already consult this setting.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render allow step back field.
	 *
	 * @return void
	 */
	public function render_field_allow_step_back() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[allow_step_back]" value="1" ' . checked( ! empty( $settings['allow_step_back'] ), true, false ) . ' /> ';
		echo esc_html__( 'Allow controlled backward transitions between adjacent process steps.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render auto complete field.
	 *
	 * @return void
	 */
	public function render_field_auto_complete_on_final_step() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[auto_complete_on_final_step]" value="1" ' . checked( ! empty( $settings['auto_complete_on_final_step'] ), true, false ) . ' /> ';
		echo esc_html__( 'Mark a process as completed automatically when it reaches a final step.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render default tax rate field.
	 *
	 * @return void
	 */
	public function render_field_default_tax_rate() {
		$settings = $this->get_settings();

		echo '<input type="number" step="0.01" min="0" class="small-text" name="' . esc_attr( self::OPTION_NAME ) . '[default_tax_rate]" value="' . esc_attr( (string) $settings['default_tax_rate'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Stored default tax rate for operational consistency and future API clients. Existing rows are not rewritten.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render partial payments field.
	 *
	 * @return void
	 */
	public function render_field_allow_partial_payments() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[allow_partial_payments]" value="1" ' . checked( ! empty( $settings['allow_partial_payments'] ), true, false ) . ' /> ';
		echo esc_html__( 'Allow registering partial payments on invoices.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render client notifications field.
	 *
	 * @return void
	 */
	public function render_field_enable_client_notifications() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_client_notifications]" value="1" ' . checked( ! empty( $settings['enable_client_notifications'] ), true, false ) . ' /> ';
		echo esc_html__( 'Keep client notification delivery enabled for the active product baseline.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render email notifications field.
	 *
	 * @return void
	 */
	public function render_field_enable_email_notifications() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_email_notifications]" value="1" ' . checked( ! empty( $settings['enable_email_notifications'] ), true, false ) . ' /> ';
		echo esc_html__( 'Enable external email delivery via WordPress wp_mail using the centralized notification catalog.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render automation runtime field.
	 *
	 * @return void
	 */
	public function render_field_enable_automation_runtime() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_automation_runtime]" value="1" ' . checked( ! empty( $settings['enable_automation_runtime'] ), true, false ) . ' /> ';
		echo esc_html__( 'Enable controlled event-based automation runtime.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render appointment reminders field.
	 *
	 * @return void
	 */
	public function render_field_enable_appointment_reminders() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[enable_appointment_reminders]" value="1" ' . checked( ! empty( $settings['enable_appointment_reminders'] ), true, false ) . ' /> ';
		echo esc_html__( 'Send automated appointment reminders through the centralized notification pipeline.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render appointment reminder lead time field.
	 *
	 * @return void
	 */
	public function render_field_appointment_reminder_minutes_before() {
		$settings = $this->get_settings();

		echo '<input type="number" min="5" max="1440" class="small-text" name="' . esc_attr( self::OPTION_NAME ) . '[appointment_reminder_minutes_before]" value="' . esc_attr( (string) absint( $settings['appointment_reminder_minutes_before'] ) ) . '" />';
		echo '<p class="description">' . esc_html__( 'Minutes before appointment start when automatic reminders are dispatched.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render client panel checkbox field.
	 *
	 * @return void
	 */
	public function render_field_client_panel_enabled() {
		$settings = $this->get_settings();

		echo '<label>';
		echo '<input type="checkbox" name="' . esc_attr( self::OPTION_NAME ) . '[client_panel_enabled]" value="1" ' . checked( ! empty( $settings['client_panel_enabled'] ), true, false ) . ' /> ';
		echo esc_html__( 'Allow access to the client portal when the active runtime surface is available.', 'super-mechanic' );
		echo '</label>';
	}

	/**
	 * Render local license status and actions.
	 *
	 * @return void
	 */
	public function render_field_license_status() {
		$state      = $this->license_service->get_license_state();
		$plan_state = $this->plan_access_service->get_effective_plan();
		$label      = $this->get_license_status_label( $state['status'] );
		$license_url = admin_url( 'admin.php?page=super-mechanic-license' );

		echo '<div class="sm-license-panel">';
		echo '<p><strong>' . esc_html__( 'Current status:', 'super-mechanic' ) . '</strong> ' . esc_html( $label ) . '</p>';
		echo '<p><strong>' . esc_html__( 'License key:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->license_service->mask_license_key( $state['license_key'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Provider:', 'super-mechanic' ) . '</strong> ' . esc_html( $state['provider'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Effective plan:', 'super-mechanic' ) . '</strong> ' . esc_html( strtoupper( (string) $plan_state['plan_key'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Plan source:', 'super-mechanic' ) . '</strong> ' . esc_html( (string) $plan_state['source'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Activated at:', 'super-mechanic' ) . '</strong> ' . esc_html( $state['activated_at'] ? $state['activated_at'] : '-' ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Last validated at:', 'super-mechanic' ) . '</strong> ' . esc_html( $state['last_validated_at'] ? $state['last_validated_at'] : '-' ) . '</p>';

		if ( '' !== $state['message'] ) {
			echo '<p class="description">' . esc_html( $state['message'] ) . '</p>';
		}

		echo '<div class="sm-license-summary-callout">';
		echo '<p><strong>' . esc_html__( 'License management moved to dedicated page', 'super-mechanic' ) . '</strong><br />' . esc_html__( 'Use the License page for activation/deactivation, trial actions and detailed limits.', 'super-mechanic' ) . '</p>';
		echo '<p class="sm-settings-license-actions"><a class="button button-primary" href="' . esc_url( $license_url ) . '">' . esc_html__( 'Manage license', 'super-mechanic' ) . '</a></p>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render private updates status.
	 *
	 * @return void
	 */
	public function render_field_updates_status() {
		$state            = $this->update_service->check_for_updates();
		$current_version  = sanitize_text_field( (string) SM_PLUGIN_VERSION );
		$latest_version   = isset( $state['latest_version'] ) ? (string) $state['latest_version'] : $current_version;
		$has_update       = version_compare( $latest_version, $current_version, '>' );
		$status_label     = $has_update ? __( 'Update available', 'super-mechanic' ) : __( 'Up to date', 'super-mechanic' );
		$package_label    = ! empty( $state['package_available'] ) ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' );
		$provider_label   = isset( $state['provider'] ) ? (string) $state['provider'] : 'local';
		$last_result      = isset( $state['last_result'] ) ? (string) $state['last_result'] : 'no_update';
		$last_check_at    = isset( $state['last_check_at'] ) && '' !== (string) $state['last_check_at'] ? (string) $state['last_check_at'] : '-';
		$requires         = isset( $state['requires'] ) && '' !== (string) $state['requires'] ? (string) $state['requires'] : '-';
		$tested           = isset( $state['tested'] ) && '' !== (string) $state['tested'] ? (string) $state['tested'] : '-';
		$message          = isset( $state['message'] ) ? (string) $state['message'] : '';

		echo '<div class="sm-license-panel">';
		echo '<p><strong>' . esc_html__( 'Current plugin version:', 'super-mechanic' ) . '</strong> ' . esc_html( $current_version ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Latest version:', 'super-mechanic' ) . '</strong> ' . esc_html( $latest_version ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Status:', 'super-mechanic' ) . '</strong> ' . esc_html( $status_label ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Provider:', 'super-mechanic' ) . '</strong> ' . esc_html( $provider_label ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Package available:', 'super-mechanic' ) . '</strong> ' . esc_html( $package_label ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Last check:', 'super-mechanic' ) . '</strong> ' . esc_html( $last_check_at ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Last result:', 'super-mechanic' ) . '</strong> ' . esc_html( $last_result ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Requires WordPress:', 'super-mechanic' ) . '</strong> ' . esc_html( $requires ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Tested up to:', 'super-mechanic' ) . '</strong> ' . esc_html( $tested ) . '</p>';

		if ( '' !== $message ) {
			echo '<p class="description">' . esc_html( $message ) . '</p>';
		}

		echo '</div>';
	}

	/**
	 * Render effective plan and feature flags status.
	 *
	 * @return void
	 */
	public function render_field_plan_access_status() {
		$plan_state    = $this->plan_access_service->get_effective_plan();
		$feature_state = $this->plan_access_service->get_feature_flags_state();
		$features      = Feature_Flags::get_supported_features();

		echo '<div class="sm-license-panel">';
		echo '<p><strong>' . esc_html__( 'Effective plan:', 'super-mechanic' ) . '</strong> ' . esc_html( strtoupper( (string) $plan_state['plan_key'] ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Plan status:', 'super-mechanic' ) . '</strong> ' . esc_html( (string) $plan_state['status'] ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Source:', 'super-mechanic' ) . '</strong> ' . esc_html( (string) $plan_state['source'] ) . '</p>';

		if ( ! empty( $plan_state['message'] ) ) {
			echo '<p class="description">' . esc_html( (string) $plan_state['message'] ) . '</p>';
		}

		echo '<h3 style="margin-top:16px;">' . esc_html__( 'Feature flags', 'super-mechanic' ) . '</h3>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Feature', 'super-mechanic' ) . '</th><th>' . esc_html__( 'Enabled', 'super-mechanic' ) . '</th></tr></thead><tbody>';
		foreach ( $features as $feature_key => $feature_label ) {
			$is_enabled = ! empty( $feature_state['resolved'][ $feature_key ] );
			echo '<tr>';
			echo '<td>' . esc_html( $feature_label ) . ' <code>(' . esc_html( $feature_key ) . ')</code></td>';
			echo '<td>' . esc_html( $is_enabled ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Defaults preserve backward compatibility. Local overrides can be prepared in sm_settings.features.feature_flags for future provider integration.', 'super-mechanic' ) . '</p>';
		echo '</div>';
	}

	/**
	 * Render Google Calendar status and config form.
	 *
	 * @return void
	 */
	public function render_field_google_calendar_status() {
		$settings      = $this->google_calendar_service->get_settings();
		$is_configured = $this->google_calendar_service->is_configured();
		$is_connected  = $this->google_calendar_service->is_connected();
		$is_enabled    = ! empty( $settings['sync_enabled'] );
		$client_id     = isset( $settings['client_id'] ) ? (string) $settings['client_id'] : '';
		$calendar_id   = isset( $settings['calendar_id'] ) ? (string) $settings['calendar_id'] : 'primary';
		$has_secret    = ! empty( $settings['client_secret'] );
		$token_expires = isset( $settings['token_expires_at'] ) ? (string) $settings['token_expires_at'] : '';
		$last_result   = isset( $settings['last_sync_result'] ) ? (string) $settings['last_sync_result'] : '';
		$last_message  = isset( $settings['last_sync_message'] ) ? (string) $settings['last_sync_message'] : '';
		$watch_channel = isset( $settings['watch_channel_id'] ) ? (string) $settings['watch_channel_id'] : '';
		$watch_exp_raw = isset( $settings['watch_expiration'] ) ? absint( $settings['watch_expiration'] ) : 0;
		$watch_exp     = $watch_exp_raw > 0 ? gmdate( 'Y-m-d H:i:s', $watch_exp_raw ) . ' UTC' : '-';
		$watch_last    = isset( $settings['watch_last_webhook_at'] ) ? (string) $settings['watch_last_webhook_at'] : '';

		echo '<div class="sm-license-panel">';
		echo '<p><strong>' . esc_html__( 'Configured:', 'super-mechanic' ) . '</strong> ' . esc_html( $is_configured ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Connected:', 'super-mechanic' ) . '</strong> ' . esc_html( $is_connected ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Sync enabled:', 'super-mechanic' ) . '</strong> ' . esc_html( $is_enabled ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'OAuth callback:', 'super-mechanic' ) . '</strong> <code>' . esc_html( $this->google_calendar_service->get_callback_url() ) . '</code></p>';
		if ( '' !== $token_expires ) {
			echo '<p><strong>' . esc_html__( 'Token expires at:', 'super-mechanic' ) . '</strong> ' . esc_html( $token_expires ) . '</p>';
		}
		if ( '' !== $last_result ) {
			echo '<p><strong>' . esc_html__( 'Last result:', 'super-mechanic' ) . '</strong> ' . esc_html( $last_result ) . '</p>';
		}
		if ( '' !== $last_message ) {
			echo '<p class="description">' . esc_html( $last_message ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Watch channel active:', 'super-mechanic' ) . '</strong> ' . esc_html( '' !== $watch_channel ? __( 'Yes', 'super-mechanic' ) : __( 'No', 'super-mechanic' ) ) . '</p>';
		echo '<p><strong>' . esc_html__( 'Watch expiration:', 'super-mechanic' ) . '</strong> ' . esc_html( $watch_exp ) . '</p>';
		if ( '' !== $watch_last ) {
			echo '<p><strong>' . esc_html__( 'Last webhook at:', 'super-mechanic' ) . '</strong> ' . esc_html( $watch_last ) . '</p>';
		}
		echo '<p><strong>' . esc_html__( 'Webhook URL:', 'super-mechanic' ) . '</strong> <code>' . esc_html( $this->google_calendar_service->get_webhook_url() ) . '</code></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:12px;">';
		echo '<input type="hidden" name="action" value="sm_google_calendar_save_config" />';
		wp_nonce_field( 'sm_google_calendar_save_config', 'sm_google_calendar_nonce' );
		echo '<p><label><strong>' . esc_html__( 'Client ID', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="text" class="regular-text" name="sm_google_client_id" value="' . esc_attr( $client_id ) . '" /></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Client Secret', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="password" class="regular-text" name="sm_google_client_secret" value="" placeholder="' . esc_attr( $has_secret ? __( 'Stored (leave blank to keep)', 'super-mechanic' ) : '' ) . '" /></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Calendar ID', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="text" class="regular-text" name="sm_google_calendar_id" value="' . esc_attr( $calendar_id ) . '" /></label></p>';
		echo '<p><label><input type="checkbox" name="sm_google_sync_enabled" value="1" ' . checked( $is_enabled, true, false ) . ' /> ' . esc_html__( 'Enable one-way appointment sync to Google Calendar', 'super-mechanic' ) . '</label></p>';
		submit_button( __( 'Save Google Calendar settings', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:10px;">';
		echo '<input type="hidden" name="action" value="sm_google_calendar_oauth_connect" />';
		wp_nonce_field( 'sm_google_calendar_oauth_connect', 'sm_google_calendar_oauth_nonce' );
		submit_button( __( 'Connect Google account', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px;">';
		echo '<input type="hidden" name="action" value="sm_google_calendar_oauth_disconnect" />';
		wp_nonce_field( 'sm_google_calendar_oauth_disconnect', 'sm_google_calendar_oauth_nonce' );
		submit_button( __( 'Disconnect Google account', 'super-mechanic' ), 'delete', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px;">';
		echo '<input type="hidden" name="action" value="sm_google_calendar_reconcile_now" />';
		wp_nonce_field( 'sm_google_calendar_reconcile_now', 'sm_google_calendar_reconcile_nonce' );
		submit_button( __( 'Reconcile inbound now', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:8px;">';
		echo '<input type="hidden" name="action" value="sm_google_calendar_renew_watch" />';
		wp_nonce_field( 'sm_google_calendar_renew_watch', 'sm_google_calendar_renew_watch_nonce' );
		submit_button( __( 'Renew watch channel now', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Handle local license activation request.
	 *
	 * @return void
	 */
	public function handle_license_activate() {
		$this->assert_license_action_permissions();

		$license_key = isset( $_POST['sm_license_key'] ) ? wp_unslash( $_POST['sm_license_key'] ) : '';
		$result      = $this->license_service->activate_license( (string) $license_key );

		$this->redirect_after_license_action( $result );
	}

	/**
	 * Handle local license validation request.
	 *
	 * @return void
	 */
	public function handle_license_validate() {
		$this->assert_license_action_permissions();

		$result = $this->license_service->validate_license();
		$this->redirect_after_license_action( $result );
	}

	/**
	 * Handle local license deactivation request.
	 *
	 * @return void
	 */
	public function handle_license_deactivate() {
		$this->assert_license_action_permissions();

		$result = $this->license_service->deactivate_license();
		$this->redirect_after_license_action( $result );
	}

	/**
	 * Assert permissions and nonce for sensitive license actions.
	 *
	 * @return void
	 */
	protected function assert_license_action_permissions() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage the license.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_license_action', 'sm_license_nonce' );
	}

	/**
	 * Redirect after license action with safe message.
	 *
	 * @param array<string, mixed> $result License action result.
	 * @return void
	 */
	protected function redirect_after_license_action( array $result ) {
		$type    = ! empty( $result['success'] ) ? 'success' : 'error';
		$message = ! empty( $result['message'] ) ? sanitize_text_field( (string) $result['message'] ) : __( 'License action completed.', 'super-mechanic' );
		$target  = add_query_arg(
			array(
				'page'               => self::PAGE_SLUG,
				'sm_license_notice'  => $type,
				'sm_license_message' => $message,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Render a one-time notice for license actions.
	 *
	 * @return void
	 */
	protected function render_license_notice() {
		$type_raw    = isset( $_GET['sm_license_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_license_notice'] ) ) : '';
		$message_raw = isset( $_GET['sm_license_message'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_license_message'] ) ) : '';

		if ( '' === $type_raw || '' === $message_raw ) {
			return;
		}

		$type_class = ( 'success' === $type_raw ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $type_class ) . ' is-dismissible"><p>' . esc_html( $message_raw ) . '</p></div>';
	}

	/**
	 * Render one-time Google Calendar notice.
	 *
	 * @return void
	 */
	protected function render_google_calendar_notice() {
		$type_raw    = isset( $_GET['sm_google_gc_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_google_gc_notice'] ) ) : '';
		$message_raw = isset( $_GET['sm_google_gc_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_google_gc_msg'] ) ) : '';

		if ( '' === $type_raw || '' === $message_raw ) {
			return;
		}

		$type_class = ( 'success' === $type_raw ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $type_class ) . ' is-dismissible"><p>' . esc_html( $message_raw ) . '</p></div>';
	}

	/**
	 * Render one-time DB security notice.
	 *
	 * @return void
	 */
	protected function render_db_security_notice() {
		$type_raw    = isset( $_GET['sm_db_notice'] ) ? sanitize_key( wp_unslash( $_GET['sm_db_notice'] ) ) : '';
		$message_raw = isset( $_GET['sm_db_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['sm_db_msg'] ) ) : '';

		if ( '' === $type_raw || '' === $message_raw ) {
			return;
		}

		$type_class = ( 'success' === $type_raw ) ? 'notice-success' : 'notice-error';
		echo '<div class="notice ' . esc_attr( $type_class ) . ' is-dismissible"><p>' . esc_html( $message_raw ) . '</p></div>';
	}

	/**
	 * Render DB security admin actions section.
	 *
	 * @param string $auto_master_password Master password generated in this request.
	 * @return void
	 */
	protected function render_db_security_section( $auto_master_password = '' ) {
		$generated_at = $this->db_security_service->get_master_password_generated_at();
		$token        = isset( $_GET['sm_db_master_token'] ) ? sanitize_key( wp_unslash( $_GET['sm_db_master_token'] ) ) : '';
		$one_time     = '' !== $token ? $this->db_security_service->consume_one_time_master_password( $token ) : '';
		$master_plain = '';

		if ( '' !== (string) $auto_master_password ) {
			$master_plain = (string) $auto_master_password;
		} elseif ( '' !== $one_time ) {
			$master_plain = $one_time;
		}

		echo '<p>' . esc_html__( 'Sensitive DB operations require capability + nonce + master password. Reset only affects Super Mechanic plugin tables.', 'super-mechanic' ) . '</p>';

		if ( '' !== $master_plain ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Master password (showing once):', 'super-mechanic' ) . '</strong> <code>' . esc_html( $master_plain ) . '</code></p></div>';
		}

		echo '<p><strong>' . esc_html__( 'Master password status:', 'super-mechanic' ) . '</strong> ' . esc_html( $this->db_security_service->has_master_password() ? __( 'Configured', 'super-mechanic' ) : __( 'Missing', 'super-mechanic' ) ) . '</p>';
		if ( '' !== $generated_at ) {
			echo '<p><strong>' . esc_html__( 'Generated at:', 'super-mechanic' ) . '</strong> ' . esc_html( $generated_at ) . '</p>';
		}

		echo '<h3>' . esc_html__( 'Generate or rotate master password', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_db_security_generate_master_password" />';
		wp_nonce_field( 'sm_db_security_generate_master_password', 'sm_db_security_generate_nonce' );
		echo '<p><label><input type="checkbox" name="sm_db_send_email" value="1" /> ' . esc_html__( 'Send generated password to admin email', 'super-mechanic' ) . '</label></p>';
		submit_button( __( 'Generate new master password', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Export plugin database', 'super-mechanic' ) . '</h3>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_db_security_export" />';
		wp_nonce_field( 'sm_db_security_export', 'sm_db_security_export_nonce' );
		echo '<p><label><strong>' . esc_html__( 'Export format', 'super-mechanic' ) . '</strong><br />';
		echo '<select name="sm_db_export_format">';
		echo '<option value="json">' . esc_html__( 'JSON (canonical backup)', 'super-mechanic' ) . '</option>';
		echo '<option value="csv">' . esc_html__( 'CSV ZIP (operational)', 'super-mechanic' ) . '</option>';
		echo '<option value="excel">' . esc_html__( 'Excel XML (operational)', 'super-mechanic' ) . '</option>';
		echo '</select></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Master password', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="password" class="regular-text" name="sm_db_master_password" value="" required /></label></p>';
		submit_button( __( 'Export DB', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Reset plugin database', 'super-mechanic' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'This action deletes data from Super Mechanic tables and re-seeds only the default business baseline.', 'super-mechanic' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_db_security_reset" />';
		wp_nonce_field( 'sm_db_security_reset', 'sm_db_security_reset_nonce' );
		echo '<p><label><strong>' . esc_html__( 'Master password', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="password" class="regular-text" name="sm_db_master_password" value="" required /></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Type confirmation phrase', 'super-mechanic' ) . '</strong> <code>RESET DB</code><br />';
		echo '<input type="text" class="regular-text" name="sm_db_reset_confirm_phrase" value="" required /></label></p>';
		echo '<p><label><input type="checkbox" name="sm_db_reset_confirm_checked" value="1" /> ' . esc_html__( 'I understand this operation is destructive.', 'super-mechanic' ) . '</label></p>';
		submit_button( __( 'Reset plugin DB', 'super-mechanic' ), 'delete', 'submit', false );
		echo '</form>';

		echo '<h3>' . esc_html__( 'Import plugin database backup (JSON only)', 'super-mechanic' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Import accepts only canonical JSON backups generated by this module. CSV/Excel exports are not importable.', 'super-mechanic' ) . '</p>';
		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		echo '<input type="hidden" name="action" value="sm_db_security_import_json" />';
		wp_nonce_field( 'sm_db_security_import_json', 'sm_db_security_import_json_nonce' );
		echo '<p><label><strong>' . esc_html__( 'Master password', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="password" class="regular-text" name="sm_db_master_password" value="" required /></label></p>';
		echo '<p><label><strong>' . esc_html__( 'Backup file (.json)', 'super-mechanic' ) . '</strong><br />';
		echo '<input type="file" name="sm_db_import_file" accept=".json,application/json" required /></label></p>';
		submit_button( __( 'Import JSON backup', 'super-mechanic' ), 'secondary', 'submit', false );
		echo '</form>';
	}

	/**
	 * Handle master password generation/rotation.
	 *
	 * @return void
	 */
	public function handle_db_security_generate_master_password() {
		$this->assert_db_security_permissions();
		check_admin_referer( 'sm_db_security_generate_master_password', 'sm_db_security_generate_nonce' );

		$send_email = ! empty( $_POST['sm_db_send_email'] );
		$result     = $this->db_security_service->generate_master_password( $send_email );

		if ( empty( $result['success'] ) ) {
			$this->redirect_after_db_security_action(
				'error',
				! empty( $result['message'] ) ? (string) $result['message'] : __( 'Could not generate a master password.', 'super-mechanic' )
			);
		}

		$extra = array();
		if ( ! empty( $result['token'] ) ) {
			$extra['sm_db_master_token'] = sanitize_key( (string) $result['token'] );
		}

		$this->redirect_after_db_security_action(
			'success',
			! empty( $result['message'] ) ? (string) $result['message'] : __( 'Master password generated.', 'super-mechanic' ),
			$extra
		);
	}

	/**
	 * Handle plugin DB export action.
	 *
	 * @return void
	 */
	public function handle_db_security_export() {
		$this->assert_db_security_permissions();
		check_admin_referer( 'sm_db_security_export', 'sm_db_security_export_nonce' );

		$master_password = isset( $_POST['sm_db_master_password'] ) ? (string) wp_unslash( $_POST['sm_db_master_password'] ) : '';
		$format          = isset( $_POST['sm_db_export_format'] ) ? sanitize_key( wp_unslash( $_POST['sm_db_export_format'] ) ) : 'json';
		$payload         = $this->db_security_service->export_plugin_data_file( $master_password, $format );

		if ( is_wp_error( $payload ) ) {
			$this->redirect_after_db_security_action( 'error', $payload->get_error_message() );
		}

		$filename = isset( $payload['filename'] ) ? sanitize_file_name( (string) $payload['filename'] ) : '';
		$mime     = isset( $payload['mime'] ) ? (string) $payload['mime'] : 'application/octet-stream';
		$content  = isset( $payload['content'] ) ? (string) $payload['content'] : '';
		if ( '' === $filename || '' === $content ) {
			$this->redirect_after_db_security_action( 'error', __( 'Could not generate export payload.', 'super-mechanic' ) );
		}

		nocache_headers();
		header( 'Content-Type: ' . $mime );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		echo $content; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Download payload.
		exit;
	}

	/**
	 * Handle plugin DB reset action.
	 *
	 * @return void
	 */
	public function handle_db_security_reset() {
		$this->assert_db_security_permissions();
		check_admin_referer( 'sm_db_security_reset', 'sm_db_security_reset_nonce' );

		$master_password = isset( $_POST['sm_db_master_password'] ) ? (string) wp_unslash( $_POST['sm_db_master_password'] ) : '';
		$confirm_phrase  = isset( $_POST['sm_db_reset_confirm_phrase'] ) ? (string) wp_unslash( $_POST['sm_db_reset_confirm_phrase'] ) : '';
		$confirm_checked = ! empty( $_POST['sm_db_reset_confirm_checked'] );

		$result = $this->db_security_service->reset_plugin_data( $master_password, $confirm_phrase, $confirm_checked );
		if ( is_wp_error( $result ) ) {
			$this->redirect_after_db_security_action( 'error', $result->get_error_message() );
		}

		$this->redirect_after_db_security_action( 'success', __( 'Plugin database reset completed.', 'super-mechanic' ) );
	}

	/**
	 * Handle plugin DB import action (JSON only).
	 *
	 * @return void
	 */
	public function handle_db_security_import_json() {
		$this->assert_db_security_permissions();
		check_admin_referer( 'sm_db_security_import_json', 'sm_db_security_import_json_nonce' );

		$master_password = isset( $_POST['sm_db_master_password'] ) ? (string) wp_unslash( $_POST['sm_db_master_password'] ) : '';
		$file_payload    = isset( $_FILES['sm_db_import_file'] ) && is_array( $_FILES['sm_db_import_file'] ) ? $_FILES['sm_db_import_file'] : array();

		$result = $this->db_security_service->import_plugin_data_from_uploaded_json( $master_password, $file_payload );
		if ( is_wp_error( $result ) ) {
			$this->redirect_after_db_security_action( 'error', $result->get_error_message() );
		}

		$this->redirect_after_db_security_action( 'success', __( 'Plugin database import completed.', 'super-mechanic' ) );
	}

	/**
	 * Assert permissions for DB security actions.
	 *
	 * @return void
	 */
	protected function assert_db_security_permissions() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to manage DB security actions.', 'super-mechanic' ) );
		}
	}

	/**
	 * Redirect after DB security action.
	 *
	 * @param string               $type    Notice type.
	 * @param string               $message Notice message.
	 * @param array<string,string> $extra   Extra query args.
	 * @return void
	 */
	protected function redirect_after_db_security_action( $type, $message, array $extra = array() ) {
		$args = array_merge(
			array(
				'page'         => self::PAGE_SLUG,
				'sm_db_notice' => sanitize_key( (string) $type ),
				'sm_db_msg'    => sanitize_text_field( (string) $message ),
			),
			$extra
		);

		$target = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Get translatable status label.
	 *
	 * @param string $status Status key.
	 * @return string
	 */
	protected function get_license_status_label( $status ) {
		$labels = array(
			'inactive' => __( 'Inactive', 'super-mechanic' ),
			'active'   => __( 'Active', 'super-mechanic' ),
			'invalid'  => __( 'Invalid', 'super-mechanic' ),
			'unknown'  => __( 'Unknown', 'super-mechanic' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : $labels['unknown'];
	}

	/**
	 * Handle Google Calendar config save.
	 *
	 * @return void
	 */
	public function handle_google_calendar_save_config() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permissions to manage Google Calendar.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_google_calendar_save_config', 'sm_google_calendar_nonce' );

		$this->google_calendar_service->save_config(
			array(
				'client_id'     => isset( $_POST['sm_google_client_id'] ) ? wp_unslash( $_POST['sm_google_client_id'] ) : '',
				'client_secret' => isset( $_POST['sm_google_client_secret'] ) ? wp_unslash( $_POST['sm_google_client_secret'] ) : '',
				'calendar_id'   => isset( $_POST['sm_google_calendar_id'] ) ? wp_unslash( $_POST['sm_google_calendar_id'] ) : 'primary',
				'sync_enabled'  => ! empty( $_POST['sm_google_sync_enabled'] ),
			)
		);

		$target = add_query_arg(
			array(
				'page'                => self::PAGE_SLUG,
				'sm_google_gc_notice' => 'success',
				'sm_google_gc_msg'    => __( 'Configuracion de Google Calendar actualizada.', 'super-mechanic' ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Handle manual inbound reconcile action.
	 *
	 * @return void
	 */
	public function handle_google_calendar_reconcile_now() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permissions to manage Google Calendar.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_google_calendar_reconcile_now', 'sm_google_calendar_reconcile_nonce' );

		$result = $this->google_calendar_sync_service->reconcile_inbound_for_linked_appointments( 100 );
		if ( is_wp_error( $result ) ) {
			$target = add_query_arg(
				array(
					'page'                => self::PAGE_SLUG,
					'sm_google_gc_notice' => 'error',
					'sm_google_gc_msg'    => $result->get_error_message(),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $target );
			exit;
		}

		$message = sprintf(
			/* translators: 1: processed, 2: synced, 3: conflict, 4: rejected, 5: error */
			__( 'Reconciliacion inbound completada. Procesadas: %1$d, synced: %2$d, conflict: %3$d, rejected: %4$d, error: %5$d.', 'super-mechanic' ),
			isset( $result['processed'] ) ? absint( $result['processed'] ) : 0,
			isset( $result['synced'] ) ? absint( $result['synced'] ) : 0,
			isset( $result['conflict'] ) ? absint( $result['conflict'] ) : 0,
			isset( $result['rejected'] ) ? absint( $result['rejected'] ) : 0,
			isset( $result['error'] ) ? absint( $result['error'] ) : 0
		);

		$target = add_query_arg(
			array(
				'page'                => self::PAGE_SLUG,
				'sm_google_gc_notice' => 'success',
				'sm_google_gc_msg'    => $message,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Handle manual watch channel renewal.
	 *
	 * @return void
	 */
	public function handle_google_calendar_renew_watch() {
		if ( ! current_user_can( 'sm_manage_settings' ) ) {
			wp_die( esc_html__( 'You do not have permissions to manage Google Calendar.', 'super-mechanic' ) );
		}

		check_admin_referer( 'sm_google_calendar_renew_watch', 'sm_google_calendar_renew_watch_nonce' );

		$result = $this->google_calendar_service->renew_watch_channel();
		if ( is_wp_error( $result ) ) {
			$target = add_query_arg(
				array(
					'page'                => self::PAGE_SLUG,
					'sm_google_gc_notice' => 'error',
					'sm_google_gc_msg'    => $result->get_error_message(),
				),
				admin_url( 'admin.php' )
			);

			wp_safe_redirect( $target );
			exit;
		}

		$target = add_query_arg(
			array(
				'page'                => self::PAGE_SLUG,
				'sm_google_gc_notice' => 'success',
				'sm_google_gc_msg'    => __( 'Watch channel renovado correctamente.', 'super-mechanic' ),
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $target );
		exit;
	}

	/**
	 * Sync the active grouped settings option consumed by services.
	 *
	 * @param array<string, mixed> $settings Sanitized legacy-shaped settings.
	 * @return void
	 */
	protected function sync_modern_settings( array $settings ) {
		$license_settings = $this->settings_service->get_group( 'license' );
		$updates_settings = $this->settings_service->get_group( 'updates' );
		$plan_settings    = $this->settings_service->get_group( 'plan' );
		$feature_settings = $this->settings_service->get_group( 'features' );
		$google_calendar_settings = $this->settings_service->get_group( 'google_calendar' );
		$security_settings = $this->settings_service->get_group( 'security' );

		update_option(
			Settings_Service::OPTION_NAME,
			array(
				'business'      => array(
					'business_name'        => $settings['company_name'],
					'business_context_key' => $settings['business_context_key'],
					'business_id'          => max( 1, absint( $settings['business_id'] ) ),
					'currency'             => $settings['default_currency'],
					'supported_currencies' => isset( $settings['supported_currencies'] ) && is_array( $settings['supported_currencies'] ) ? array_values( array_map( 'sanitize_text_field', $settings['supported_currencies'] ) ) : array( 'USD', 'EUR', 'COP', 'PAB' ),
					'timezone'             => $settings['timezone'],
					'locale'               => $settings['language_locale'],
					'date_format'          => $settings['date_format'],
				),
				'process'       => array(
					'enabled_process_types'      => $settings['enabled_process_types'],
					'allow_step_back'            => ! empty( $settings['allow_step_back'] ),
					'auto_complete_on_final_step' => ! empty( $settings['auto_complete_on_final_step'] ),
				),
				'financial'     => array(
					'default_tax_rate'       => $settings['default_tax_rate'],
					'allow_partial_payments' => ! empty( $settings['allow_partial_payments'] ),
				),
				'notifications' => array(
					'enable_client_notifications' => ! empty( $settings['enable_client_notifications'] ),
					'enable_email_notifications'  => ! empty( $settings['enable_email_notifications'] ),
				),
				'automation'   => array(
					'enable_automation_runtime'         => ! empty( $settings['enable_automation_runtime'] ),
					'enable_appointment_reminders'      => ! empty( $settings['enable_appointment_reminders'] ),
					'appointment_reminder_minutes_before' => absint( $settings['appointment_reminder_minutes_before'] ),
					'appointment_reminder_window_minutes' => 15,
				),
				'portal'        => array(
					'client_panel_enabled' => ! empty( $settings['client_panel_enabled'] ),
				),
				'license'       => is_array( $license_settings ) ? $license_settings : array(),
				'updates'       => is_array( $updates_settings ) ? $updates_settings : array(),
				'plan'          => is_array( $plan_settings ) ? $plan_settings : array(),
				'features'      => is_array( $feature_settings ) ? $feature_settings : array(),
				'google_calendar' => is_array( $google_calendar_settings ) ? $google_calendar_settings : array(),
				'security'      => is_array( $security_settings ) ? $security_settings : array(),
			)
		);
	}

	/**
	 * Parse supported currencies input from settings payload.
	 *
	 * @param mixed $value Raw input.
	 * @return array<int, string>
	 */
	protected function parse_supported_currencies_input( $value ) {
		$items = array();

		if ( is_array( $value ) ) {
			$items = $value;
		} elseif ( is_string( $value ) ) {
			$items = preg_split( '/[\s,;]+/', $value );
		}

		if ( ! is_array( $items ) ) {
			return array();
		}

		$currencies = array();
		foreach ( $items as $item ) {
			$code = strtoupper( sanitize_text_field( (string) $item ) );
			$code = preg_replace( '/[^A-Z]/', '', $code );

			if ( ! is_string( $code ) || strlen( $code ) < 3 || strlen( $code ) > 5 ) {
				continue;
			}

			if ( ! in_array( $code, $currencies, true ) ) {
				$currencies[] = $code;
			}
		}

		return $currencies;
	}
}
