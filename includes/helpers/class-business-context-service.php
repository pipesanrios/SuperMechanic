<?php
/**
 * Business context service (tenancy base preparation).
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the active business context contract without enabling multi-tenant runtime.
 */
class Business_Context_Service {
	/**
	 * Runtime mode for the current architecture.
	 */
	const MODE_SINGLE_BUSINESS = 'single_business';

	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null $settings_service Settings service.
	 */
	public function __construct( Settings_Service $settings_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
	}

	/**
	 * Resolve the stable business context key.
	 *
	 * @return string
	 */
	public function get_business_context_key() {
		$key = (string) $this->settings_service->get_setting( 'business', 'business_context_key', 'default' );
		$key = sanitize_key( $key );

		return '' === $key ? 'default' : $key;
	}

	/**
	 * Resolve the active runtime business context contract.
	 *
	 * This contract is intentionally preparatory:
	 * - single-business mode only
	 * - no business_id persistence
	 * - no cross-table tenancy filtering
	 *
	 * @return array<string, mixed>
	 */
	public function get_runtime_context() {
		return array(
			'mode'                => self::MODE_SINGLE_BUSINESS,
			'business_context_key' => $this->get_business_context_key(),
			'business_id'         => null,
			'is_tenancy_active'   => false,
			'data_source'         => Settings_Service::OPTION_NAME . '.business.business_context_key',
		);
	}

	/**
	 * Resolve a future business identifier placeholder.
	 *
	 * Reserved for future tenancy phases. It is intentionally inactive now.
	 *
	 * @return null
	 */
	public function resolve_business_id() {
		return null;
	}
}
