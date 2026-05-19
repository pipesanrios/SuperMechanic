<?php
/**
 * SaaS runtime context.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Describes the current runtime mode without changing plugin behavior.
 */
class Runtime_Context {
	const MODE_SELF_HOSTED       = 'self_hosted';
	const MODE_SAAS_FUTURE       = 'saas_future';
	const MODE_LOCAL_DEVELOPMENT = 'local_development';

	/**
	 * Runtime mode.
	 *
	 * @var string
	 */
	protected $mode;

	/**
	 * Tenant context.
	 *
	 * @var Tenant_Context|null
	 */
	protected $tenant_context;

	/**
	 * Constructor.
	 *
	 * @param string              $mode Runtime mode.
	 * @param Tenant_Context|null $tenant_context Tenant context.
	 */
	public function __construct( $mode = '', Tenant_Context $tenant_context = null ) {
		$this->mode           = $this->normalize_mode( $mode );
		$this->tenant_context = $tenant_context;
	}

	/**
	 * Get supported runtime modes.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_modes() {
		return array(
			self::MODE_SELF_HOSTED,
			self::MODE_SAAS_FUTURE,
			self::MODE_LOCAL_DEVELOPMENT,
		);
	}

	/**
	 * Get current runtime mode.
	 *
	 * @return string
	 */
	public function get_mode() {
		return $this->mode;
	}

	/**
	 * Check self-hosted mode.
	 *
	 * @return bool
	 */
	public function is_self_hosted() {
		return self::MODE_SELF_HOSTED === $this->mode;
	}

	/**
	 * Check future SaaS mode.
	 *
	 * @return bool
	 */
	public function is_saas_future() {
		return self::MODE_SAAS_FUTURE === $this->mode;
	}

	/**
	 * Check local development mode.
	 *
	 * @return bool
	 */
	public function is_local_development() {
		return self::MODE_LOCAL_DEVELOPMENT === $this->mode;
	}

	/**
	 * Get passive tenant context bridge.
	 *
	 * @return Tenant_Context
	 */
	public function get_tenant_context() {
		if ( $this->tenant_context instanceof Tenant_Context ) {
			return $this->tenant_context;
		}

		$this->tenant_context = Tenant_Context::from_runtime_context( $this );

		return $this->tenant_context;
	}

	/**
	 * Export context for diagnostics or future composition layers.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'mode'                 => $this->mode,
			'supported_modes'      => $this->get_supported_modes(),
			'is_self_hosted'       => $this->is_self_hosted(),
			'is_saas_future'       => $this->is_saas_future(),
			'is_local_development' => $this->is_local_development(),
			'is_runtime_active'    => false,
			'tenant_context'       => $this->get_tenant_context()->to_array(),
		);
	}

	/**
	 * Normalize mode with backward-compatible default.
	 *
	 * @param string $mode Runtime mode.
	 * @return string
	 */
	protected function normalize_mode( $mode ) {
		$mode = sanitize_key( (string) $mode );

		if ( in_array( $mode, $this->get_supported_modes(), true ) ) {
			return $mode;
		}

		return self::MODE_SELF_HOSTED;
	}
}
