<?php
/**
 * Settings manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Helpers\Settings_Service;

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
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_service = new Settings_Service();
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
			'language_locale',
			__( 'Default locale', 'super-mechanic' ),
			array( $this, 'render_field_language_locale' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'default_currency',
			__( 'Default currency', 'super-mechanic' ),
			array( $this, 'render_field_default_currency' ),
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
			'language_locale'       => function_exists( 'determine_locale' ) ? determine_locale() : get_locale(),
			'default_currency'      => 'USD',
			'timezone'              => function_exists( 'wp_timezone_string' ) && wp_timezone_string() ? wp_timezone_string() : 'UTC',
			'date_format'           => 'd/m/Y',
			'enabled_process_types' => array( 'maintenance', 'pre_delivery', 'paperwork' ),
			'allow_step_back'       => 1,
			'auto_complete_on_final_step' => 1,
			'default_tax_rate'      => 0,
			'allow_partial_payments' => 1,
			'enable_client_notifications' => 1,
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
		$mapped           = array(
			'company_name'               => isset( $service_settings['business']['business_name'] ) ? $service_settings['business']['business_name'] : $defaults['company_name'],
			'business_context_key'       => isset( $service_settings['business']['business_context_key'] ) ? $service_settings['business']['business_context_key'] : $defaults['business_context_key'],
			'language_locale'            => isset( $service_settings['business']['locale'] ) ? $service_settings['business']['locale'] : $defaults['language_locale'],
			'default_currency'           => isset( $service_settings['business']['currency'] ) ? $service_settings['business']['currency'] : $defaults['default_currency'],
			'timezone'                   => isset( $service_settings['business']['timezone'] ) ? $service_settings['business']['timezone'] : $defaults['timezone'],
			'date_format'                => isset( $service_settings['business']['date_format'] ) ? $service_settings['business']['date_format'] : $defaults['date_format'],
			'enabled_process_types'      => isset( $service_settings['process']['enabled_process_types'] ) ? $service_settings['process']['enabled_process_types'] : $defaults['enabled_process_types'],
			'allow_step_back'            => ! empty( $service_settings['process']['allow_step_back'] ) ? 1 : 0,
			'auto_complete_on_final_step' => ! empty( $service_settings['process']['auto_complete_on_final_step'] ) ? 1 : 0,
			'default_tax_rate'           => isset( $service_settings['financial']['default_tax_rate'] ) ? $service_settings['financial']['default_tax_rate'] : $defaults['default_tax_rate'],
			'allow_partial_payments'     => ! empty( $service_settings['financial']['allow_partial_payments'] ) ? 1 : 0,
			'enable_client_notifications' => ! empty( $service_settings['notifications']['enable_client_notifications'] ) ? 1 : 0,
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

		$currencies = array( 'USD', 'EUR', 'COP', 'PAB' );
		$formats    = array( 'd/m/Y', 'm/d/Y', 'Y-m-d' );
		$types      = array( 'maintenance', 'pre_delivery', 'paperwork' );
		$locales    = array( 'en_US', 'es_ES', 'it_IT' );
		$timezones  = timezone_identifiers_list();

		$sanitized = array(
			'company_name'          => isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : $defaults['company_name'],
			'business_context_key'  => isset( $input['business_context_key'] ) ? sanitize_key( $input['business_context_key'] ) : $defaults['business_context_key'],
			'language_locale'       => isset( $input['language_locale'] ) && in_array( $input['language_locale'], $locales, true ) ? $input['language_locale'] : $defaults['language_locale'],
			'default_currency'      => isset( $input['default_currency'] ) && in_array( $input['default_currency'], $currencies, true ) ? $input['default_currency'] : $defaults['default_currency'],
			'timezone'              => isset( $input['timezone'] ) && in_array( $input['timezone'], $timezones, true ) ? $input['timezone'] : $defaults['timezone'],
			'date_format'           => isset( $input['date_format'] ) && in_array( $input['date_format'], $formats, true ) ? $input['date_format'] : $defaults['date_format'],
			'enabled_process_types' => array_values( array_intersect( $types, isset( $input['enabled_process_types'] ) && is_array( $input['enabled_process_types'] ) ? array_map( 'sanitize_text_field', $input['enabled_process_types'] ) : array() ) ),
			'allow_step_back'       => ! empty( $input['allow_step_back'] ) ? 1 : 0,
			'auto_complete_on_final_step' => ! empty( $input['auto_complete_on_final_step'] ) ? 1 : 0,
			'default_tax_rate'      => isset( $input['default_tax_rate'] ) ? round( (float) str_replace( ',', '.', (string) $input['default_tax_rate'] ), 2 ) : $defaults['default_tax_rate'],
			'allow_partial_payments' => ! empty( $input['allow_partial_payments'] ) ? 1 : 0,
			'enable_client_notifications' => ! empty( $input['enable_client_notifications'] ) ? 1 : 0,
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
			wp_die( esc_html__( 'No tienes permisos suficientes para acceder a esta página.', 'super-mechanic' ) );
		}

		echo '<div class="wrap sm-admin-shell">';
		echo '<div class="sm-admin-header">';
		echo '<div class="sm-admin-title">';
		echo '<h1>' . esc_html__( 'Super Mechanic Settings', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Centralize the business profile, operational defaults and pre-API baseline without opening multi-tenant behavior yet.', 'super-mechanic' ) . '</p>';
		echo '</div>';
		echo '</div>';
		echo '<div class="sm-card sm-form-card sm-settings-card">';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		echo '<div class="sm-form-actions">';
		submit_button( __( 'Guardar ajustes', 'super-mechanic' ), 'primary', 'submit', false );
		echo '</div>';
		echo '</form>';
		echo '</div>';
		echo '</div>';
	}

	/**
	 * Render section introduction.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'These values define the active workshop profile and the minimum i18n baseline used by runtime services.', 'super-mechanic' ) . '</p>';
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
	 * Render business context key field.
	 *
	 * @return void
	 */
	public function render_field_business_context_key() {
		$settings = $this->get_settings();

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[business_context_key]" value="' . esc_attr( $settings['business_context_key'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Stable internal key reserved for future `business_id` evolution. It does not enable multi-business runtime today.', 'super-mechanic' ) . '</p>';
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
	 * Render default currency field.
	 *
	 * @return void
	 */
	public function render_field_default_currency() {
		$settings   = $this->get_settings();
		$currencies = array( 'USD', 'EUR', 'COP', 'PAB' );

		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[default_currency]">';

		foreach ( $currencies as $currency ) {
			echo '<option value="' . esc_attr( $currency ) . '" ' . selected( $settings['default_currency'], $currency, false ) . '>' . esc_html( $currency ) . '</option>';
		}

		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Default currency used by quote and invoice services when a specific value is not provided.', 'super-mechanic' ) . '</p>';
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
	 * Sync the active grouped settings option consumed by services.
	 *
	 * @param array<string, mixed> $settings Sanitized legacy-shaped settings.
	 * @return void
	 */
	protected function sync_modern_settings( array $settings ) {
		update_option(
			Settings_Service::OPTION_NAME,
			array(
				'business'      => array(
					'business_name'        => $settings['company_name'],
					'business_context_key' => $settings['business_context_key'],
					'currency'             => $settings['default_currency'],
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
				),
				'portal'        => array(
					'client_panel_enabled' => ! empty( $settings['client_panel_enabled'] ),
				),
			)
		);
	}
}
