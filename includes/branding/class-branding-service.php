<?php
/**
 * Branding service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Branding;

use Super_Mechanic\Audit\Audit_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized white-label settings access.
 */
class Branding_Service {
	/**
	 * Option key.
	 */
	const OPTION_KEY = 'sm_branding_settings';

	/**
	 * Audit service dependency.
	 *
	 * @var Audit_Service|null
	 */
	protected $audit_service;

	/**
	 * Constructor.
	 *
	 * @param Audit_Service|null $audit_service Audit service dependency.
	 */
	public function __construct( Audit_Service $audit_service = null ) {
		$this->audit_service = $audit_service;
	}

	/**
	 * Get full settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return wp_parse_args( $stored, $this->get_defaults() );
	}

	/**
	 * Get one setting by key.
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default fallback.
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		$key      = sanitize_key( (string) $key );
		$settings = $this->get_settings();

		if ( '' === $key ) {
			return $default;
		}

		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $default;
	}

	/**
	 * Save settings with strict sanitization.
	 *
	 * @param array<string,mixed> $data Raw payload.
	 * @return bool
	 */
	public function save_settings( array $data ) {
		$current = $this->get_settings();
		$merged  = array_merge( $current, $data );

		$defaults = $this->get_defaults();
		$sanitized = array(
			'system_name'         => $this->sanitize_system_name( isset( $merged['system_name'] ) ? (string) $merged['system_name'] : '' ),
			'logo_url'            => esc_url_raw( isset( $merged['logo_url'] ) ? (string) $merged['logo_url'] : '' ),
			'logo_attachment_id'  => absint( isset( $merged['logo_attachment_id'] ) ? $merged['logo_attachment_id'] : 0 ),
			'primary_color'       => $this->sanitize_color( isset( $merged['primary_color'] ) ? (string) $merged['primary_color'] : '', (string) $defaults['primary_color'] ),
			'secondary_color'     => $this->sanitize_color( isset( $merged['secondary_color'] ) ? (string) $merged['secondary_color'] : '', (string) $defaults['secondary_color'] ),
			'admin_footer_text'   => sanitize_text_field( isset( $merged['admin_footer_text'] ) ? (string) $merged['admin_footer_text'] : '' ),
		);

		$changed = $this->get_changed_fields( $current, $sanitized );
		if ( empty( $changed ) ) {
			return true;
		}

		$saved = (bool) update_option( self::OPTION_KEY, $sanitized, false );
		if ( ! $saved ) {
			return false;
		}

		$this->audit_branding_change(
			'update',
			$this->get_branding_audit_snapshot( $current ),
			$this->get_branding_audit_snapshot( $sanitized ),
			array(
				'changed_fields' => $changed,
			)
		);

		return true;
	}

	/**
	 * Get configured system name.
	 *
	 * @return string
	 */
	public function get_system_name() {
		return (string) $this->get( 'system_name', $this->get_defaults()['system_name'] );
	}

	/**
	 * Get primary color.
	 *
	 * @return string
	 */
	public function get_primary_color() {
		return (string) $this->get( 'primary_color', $this->get_defaults()['primary_color'] );
	}

	/**
	 * Get logo URL resolved from attachment id or direct URL.
	 *
	 * @return string
	 */
	public function get_logo_url() {
		$attachment_id = absint( $this->get( 'logo_attachment_id', 0 ) );
		if ( $attachment_id > 0 ) {
			$attachment_url = wp_get_attachment_url( $attachment_id );
			if ( is_string( $attachment_url ) && '' !== $attachment_url ) {
				return esc_url_raw( $attachment_url );
			}
		}

		return esc_url_raw( (string) $this->get( 'logo_url', '' ) );
	}

	/**
	 * Detect if basic branding has been configured.
	 *
	 * @return bool
	 */
	public function has_basic_branding() {
		$raw = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return false;
		}

		$settings = $this->get_settings();
		$defaults = $this->get_defaults();

		$custom_name      = isset( $settings['system_name'] ) && (string) $settings['system_name'] !== (string) $defaults['system_name'];
		$custom_primary   = isset( $settings['primary_color'] ) && (string) $settings['primary_color'] !== (string) $defaults['primary_color'];
		$custom_secondary = isset( $settings['secondary_color'] ) && (string) $settings['secondary_color'] !== (string) $defaults['secondary_color'];
		$has_logo         = '' !== trim( (string) $this->get_logo_url() ) || absint( isset( $settings['logo_attachment_id'] ) ? $settings['logo_attachment_id'] : 0 ) > 0;
		$has_footer       = '' !== trim( sanitize_text_field( isset( $settings['admin_footer_text'] ) ? (string) $settings['admin_footer_text'] : '' ) );

		return $custom_name || $custom_primary || $custom_secondary || $has_logo || $has_footer;
	}

	/**
	 * Get defaults.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_defaults() {
		return array(
			'system_name'        => 'Mekvort',
			'logo_url'           => '',
			'logo_attachment_id' => 0,
			'primary_color'      => '#2271b1',
			'secondary_color'    => '#135e96',
			'admin_footer_text'  => '',
		);
	}

	/**
	 * Sanitize system name.
	 *
	 * @param string $value Name value.
	 * @return string
	 */
	protected function sanitize_system_name( $value ) {
		$value = sanitize_text_field( (string) $value );
		if ( '' === $value ) {
			return (string) $this->get_defaults()['system_name'];
		}

		return $value;
	}

	/**
	 * Sanitize hex color with default fallback.
	 *
	 * @param string $value Color.
	 * @param string $default Default fallback.
	 * @return string
	 */
	protected function sanitize_color( $value, $default ) {
		$value = sanitize_text_field( (string) $value );
		$color = sanitize_hex_color( $value );
		if ( '' === $color || null === $color ) {
			return sanitize_hex_color( (string) $default ) ? (string) $default : '#2271b1';
		}

		return $color;
	}

	/**
	 * Resolve audit service lazily.
	 *
	 * @return Audit_Service|null
	 */
	protected function get_audit_service() {
		if ( $this->audit_service instanceof Audit_Service ) {
			return $this->audit_service;
		}

		try {
			$this->audit_service = new Audit_Service();
			return $this->audit_service;
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Write branding audit row.
	 *
	 * @param string              $action Action.
	 * @param array<string,mixed> $before Before payload.
	 * @param array<string,mixed> $after After payload.
	 * @param array<string,mixed> $context Context.
	 * @return void
	 */
	protected function audit_branding_change( $action, array $before, array $after, array $context = array() ) {
		$audit = $this->get_audit_service();
		if ( ! $audit instanceof Audit_Service ) {
			return;
		}

		$audit->audit_branding_change(
			sanitize_key( (string) $action ),
			$before,
			$after,
			$context,
			get_current_user_id(),
			0
		);
	}

	/**
	 * Build compact audit payload.
	 *
	 * @param array<string,mixed> $settings Settings payload.
	 * @return array<string,mixed>
	 */
	protected function get_branding_audit_snapshot( array $settings ) {
		return array(
			'system_name'       => isset( $settings['system_name'] ) ? sanitize_text_field( (string) $settings['system_name'] ) : '',
			'primary_color'     => isset( $settings['primary_color'] ) ? sanitize_text_field( (string) $settings['primary_color'] ) : '',
			'secondary_color'   => isset( $settings['secondary_color'] ) ? sanitize_text_field( (string) $settings['secondary_color'] ) : '',
			'logo_set'          => ( ! empty( $settings['logo_attachment_id'] ) || ! empty( $settings['logo_url'] ) ),
			'admin_footer_text' => isset( $settings['admin_footer_text'] ) ? sanitize_text_field( (string) $settings['admin_footer_text'] ) : '',
		);
	}

	/**
	 * Detect changed keys for audit context.
	 *
	 * @param array<string,mixed> $before Before.
	 * @param array<string,mixed> $after After.
	 * @return array<int,string>
	 */
	protected function get_changed_fields( array $before, array $after ) {
		$changed = array();

		foreach ( $after as $key => $value ) {
			$before_value = isset( $before[ $key ] ) ? $before[ $key ] : null;
			if ( (string) $before_value !== (string) $value ) {
				$changed[] = sanitize_key( (string) $key );
			}
		}

		return array_values( array_unique( $changed ) );
	}
}
