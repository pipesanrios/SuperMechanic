<?php
/**
 * Business context service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Businesses\Business_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Resolves the active business context contract for tenancy-aware runtime.
 */
class Business_Context_Service {
	/**
	 * Runtime mode for single business.
	 */
	const MODE_SINGLE_BUSINESS = 'single_business';
	/**
	 * Runtime mode for active multi-business.
	 */
	const MODE_MULTI_BUSINESS = 'multi_business';
	/**
	 * Legacy default business identifier.
	 */
	const DEFAULT_BUSINESS_ID = 1;
	/**
	 * User meta key for active business selection.
	 */
	const USER_META_ACTIVE_BUSINESS_ID = 'sm_active_business_id';

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
	 */
	public function __construct( Settings_Service $settings_service = null, Business_Service $business_service = null ) {
		$this->settings_service = $settings_service ? $settings_service : new Settings_Service();
		$this->business_service = $business_service ? $business_service : new Business_Service();
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
	 * @return array<string, mixed>
	 */
	public function get_runtime_context() {
		$user_business_id    = $this->get_user_selected_business_id();
		$setting_business_id = $this->resolve_setting_business_id();
		$default_business_id = $this->get_default_business_id();
		$business_id         = $default_business_id;
		$source              = 'default_business';

		if ( $user_business_id > 0 ) {
			$business_id = $user_business_id;
			$source      = self::USER_META_ACTIVE_BUSINESS_ID;
		} elseif ( $setting_business_id > 0 ) {
			$business_id = $setting_business_id;
			$source      = Settings_Service::OPTION_NAME . '.business.business_id';
		}

		return array(
			'mode'                => self::MODE_MULTI_BUSINESS,
			'business_context_key' => $this->get_business_context_key(),
			'business_id'         => $business_id,
			'is_tenancy_active'   => $business_id > 0,
			'data_source'         => $source,
		);
	}

	/**
	 * Resolve the active business identifier with legacy-safe defaults.
	 *
	 * @return int
	 */
	public function resolve_business_id() {
		$user_business_id = $this->get_user_selected_business_id();
		if ( $user_business_id > 0 ) {
			return $user_business_id;
		}

		$setting_business_id = $this->resolve_setting_business_id();
		if ( $setting_business_id > 0 ) {
			return $setting_business_id;
		}

		return $this->get_default_business_id();
	}

	/**
	 * Get user-selected business id if valid and active.
	 *
	 * @param int $user_id User ID.
	 * @return int
	 */
	public function get_user_selected_business_id( $user_id = 0 ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}

		if ( $user_id <= 0 ) {
			return 0;
		}

		$business_id = absint( get_user_meta( $user_id, self::USER_META_ACTIVE_BUSINESS_ID, true ) );

		return $this->business_service->resolve_valid_business_id( $business_id );
	}

	/**
	 * Persist active business by user.
	 *
	 * @param int $business_id Business ID.
	 * @param int $user_id     User ID.
	 * @return bool
	 */
	public function set_user_selected_business_id( $business_id, $user_id = 0 ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			$user_id = get_current_user_id();
		}
		if ( $user_id <= 0 ) {
			return false;
		}

		$business_id = $this->business_service->resolve_valid_business_id( $business_id );
		if ( $business_id <= 0 ) {
			$business_id = $this->get_default_business_id();
		}

		return false !== update_user_meta( $user_id, self::USER_META_ACTIVE_BUSINESS_ID, $business_id );
	}

	/**
	 * Resolve configured setting business id.
	 *
	 * @return int
	 */
	protected function resolve_setting_business_id() {
		$business_id = absint( $this->settings_service->get_setting( 'business', 'business_id', self::DEFAULT_BUSINESS_ID ) );
		$business_id = $business_id > 0 ? $business_id : self::DEFAULT_BUSINESS_ID;

		return $this->business_service->resolve_valid_business_id( $business_id );
	}

	/**
	 * Get default business id from entity.
	 *
	 * @return int
	 */
	protected function get_default_business_id() {
		$business_id = absint( $this->business_service->get_default_business_id() );

		return $business_id > 0 ? $business_id : self::DEFAULT_BUSINESS_ID;
	}
}
