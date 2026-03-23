<?php
/**
 * Settings manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

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
			__( 'Configuración general', 'super-mechanic' ),
			array( $this, 'render_general_section' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			'company_name',
			__( 'Nombre de la empresa', 'super-mechanic' ),
			array( $this, 'render_field_company_name' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'default_currency',
			__( 'Moneda por defecto', 'super-mechanic' ),
			array( $this, 'render_field_default_currency' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'date_format',
			__( 'Formato de fecha', 'super-mechanic' ),
			array( $this, 'render_field_date_format' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'enabled_process_types',
			__( 'Tipos de proceso habilitados', 'super-mechanic' ),
			array( $this, 'render_field_enabled_process_types' ),
			self::PAGE_SLUG,
			'sm_general_settings'
		);

		add_settings_field(
			'client_panel_enabled',
			__( 'Panel de cliente habilitado', 'super-mechanic' ),
			array( $this, 'render_field_client_panel_enabled' ),
			self::PAGE_SLUG,
			'sm_general_settings'
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
			'default_currency'      => 'USD',
			'date_format'           => 'd/m/Y',
			'enabled_process_types' => array( 'maintenance', 'pre_delivery', 'paperwork' ),
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

		return wp_parse_args( $settings, $this->get_default_settings() );
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

		$sanitized = array(
			'company_name'          => isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : $defaults['company_name'],
			'default_currency'      => isset( $input['default_currency'] ) && in_array( $input['default_currency'], $currencies, true ) ? $input['default_currency'] : $defaults['default_currency'],
			'date_format'           => isset( $input['date_format'] ) && in_array( $input['date_format'], $formats, true ) ? $input['date_format'] : $defaults['date_format'],
			'enabled_process_types' => array_values( array_intersect( $types, isset( $input['enabled_process_types'] ) && is_array( $input['enabled_process_types'] ) ? array_map( 'sanitize_text_field', $input['enabled_process_types'] ) : array() ) ),
			'client_panel_enabled'  => ! empty( $input['client_panel_enabled'] ) ? 1 : 0,
		);

		if ( empty( $sanitized['enabled_process_types'] ) ) {
			$sanitized['enabled_process_types'] = $defaults['enabled_process_types'];
		}

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

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'Ajustes de Super Mechanic', 'super-mechanic' ) . '</h1>';
		echo '<p>' . esc_html__( 'Configura los parámetros base del taller, formatos y tipos de proceso disponibles.', 'super-mechanic' ) . '</p>';
		echo '<form method="post" action="options.php">';
		settings_fields( self::OPTION_GROUP );
		do_settings_sections( self::PAGE_SLUG );
		submit_button();
		echo '</form>';
		echo '</div>';
	}

	/**
	 * Render section introduction.
	 *
	 * @return void
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Estos ajustes preparan la configuración base para la operación del plugin.', 'super-mechanic' ) . '</p>';
	}

	/**
	 * Render company name field.
	 *
	 * @return void
	 */
	public function render_field_company_name() {
		$settings = $this->get_settings();

		echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPTION_NAME ) . '[company_name]" value="' . esc_attr( $settings['company_name'] ) . '" />';
		echo '<p class="description">' . esc_html__( 'Nombre comercial del taller o concesionario.', 'super-mechanic' ) . '</p>';
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
		echo esc_html__( 'Permitir acceso al panel de cliente cuando el módulo esté disponible.', 'super-mechanic' );
		echo '</label>';
	}
}
