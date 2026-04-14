<?php
/**
 * Reset engine service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

use Super_Mechanic\Database\Reset_Engine_Repository;
use Super_Mechanic\Users\Reset_User_Handling_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized reset orchestration for Mekvort runtime/operational data.
 */
class Reset_Engine_Service {
	/**
	 * Settings service.
	 *
	 * @var Settings_Service
	 */
	protected $settings_service;

	/**
	 * Reset engine repository.
	 *
	 * @var Reset_Engine_Repository
	 */
	protected $repository;
	/**
	 * Reset user handling service.
	 *
	 * @var Reset_User_Handling_Service
	 */
	protected $reset_user_handling_service;

	/**
	 * Constructor.
	 *
	 * @param Settings_Service|null        $settings_service Settings service.
	 * @param Reset_Engine_Repository|null $repository       Reset repository.
	 */
	public function __construct( Settings_Service $settings_service = null, Reset_Engine_Repository $repository = null ) {
		$this->settings_service            = $settings_service ? $settings_service : new Settings_Service();
		$this->repository                  = $repository ? $repository : new Reset_Engine_Repository();
		$this->reset_user_handling_service = new Reset_User_Handling_Service();
	}

	/**
	 * Execute centralized reset engine operation.
	 *
	 * @return array<string, mixed>|\WP_Error
	 */
	public function reset_operational_data() {
		$default_name = (string) $this->settings_service->get_setting( 'business', 'business_name', 'Mekvort' );
		$db_reset     = $this->repository->reset_operational_data( $default_name );
		if ( is_wp_error( $db_reset ) ) {
			return $db_reset;
		}

		$user_cleanup = $this->reset_user_handling_service->cleanup_non_protected_runtime_users();
		if ( ! is_array( $user_cleanup ) ) {
			$user_cleanup = array(
				'protected_superadmin_ids' => array(),
				'deleted_user_ids'         => array(),
				'preserved_user_ids'       => array(),
				'preserved_superadmin_ids' => array(),
				'failed_user_ids'          => array(),
				'memberships_deleted'      => 0,
			);
		}

		$db_reset['user_cleanup'] = $user_cleanup;

		return $db_reset;
	}
}
