<?php
/**
 * Onboarding service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Onboarding;

use Super_Mechanic\Branding\Branding_Service;
use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Licensing\License_Service;
use Super_Mechanic\Users\Business_Membership_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Orchestrates onboarding diagnostics and next-step recommendation.
 */
class Onboarding_Service {
	/**
	 * State option key.
	 */
	const OPTION_KEY = 'sm_onboarding_state';

	/**
	 * License service.
	 *
	 * @var License_Service
	 */
	protected $license_service;

	/**
	 * Branding service.
	 *
	 * @var Branding_Service
	 */
	protected $branding_service;

	/**
	 * Business service.
	 *
	 * @var Business_Service
	 */
	protected $business_service;

	/**
	 * Membership service.
	 *
	 * @var Business_Membership_Service
	 */
	protected $membership_service;

	/**
	 * Constructor.
	 *
	 * @param License_Service|null             $license_service License service.
	 * @param Branding_Service|null            $branding_service Branding service.
	 * @param Business_Service|null            $business_service Business service.
	 * @param Business_Membership_Service|null $membership_service Membership service.
	 */
	public function __construct(
		License_Service $license_service = null,
		Branding_Service $branding_service = null,
		Business_Service $business_service = null,
		Business_Membership_Service $membership_service = null
	) {
		$this->license_service    = $license_service ? $license_service : new License_Service();
		$this->branding_service   = $branding_service ? $branding_service : new Branding_Service();
		$this->business_service   = $business_service ? $business_service : new Business_Service();
		$this->membership_service = $membership_service ? $membership_service : new Business_Membership_Service();
	}

	/**
	 * Get normalized onboarding state.
	 *
	 * @return array<string,mixed>
	 */
	public function get_onboarding_state() {
		$stored_state = get_option( self::OPTION_KEY, array() );
		$stored_state = is_array( $stored_state ) ? $stored_state : array();

		$state = array(
			'has_license'            => $this->license_service->has_usable_license(),
			'has_branding_basic'     => $this->branding_service->has_basic_branding(),
			'has_business'           => $this->has_active_business(),
			'has_business_admin'     => $this->has_business_admin(),
			'manual_complete'        => ! empty( $stored_state['manual_complete'] ),
			'manual_completed_at'    => isset( $stored_state['manual_completed_at'] ) ? sanitize_text_field( (string) $stored_state['manual_completed_at'] ) : '',
			'is_onboarding_complete' => false,
		);

		$state['is_onboarding_complete'] = $this->is_onboarding_complete_from_state( $state );
		$state['next_step']              = $this->get_next_recommended_step_from_state( $state );

		return $state;
	}

	/**
	 * Check if onboarding is complete.
	 *
	 * @return bool
	 */
	public function is_onboarding_complete() {
		$state = $this->get_onboarding_state();

		return ! empty( $state['is_onboarding_complete'] );
	}

	/**
	 * Get next recommended step.
	 *
	 * @return array<string,string>
	 */
	public function get_next_recommended_step() {
		$state = $this->get_onboarding_state();

		return isset( $state['next_step'] ) && is_array( $state['next_step'] ) ? $state['next_step'] : $this->get_default_done_step();
	}

	/**
	 * Mark onboarding as complete manually.
	 *
	 * @return bool
	 */
	public function mark_onboarding_complete() {
		return (bool) update_option(
			self::OPTION_KEY,
			array(
				'manual_complete'     => true,
				'manual_completed_at' => current_time( 'mysql' ),
			),
			false
		);
	}

	/**
	 * Reset onboarding state.
	 *
	 * @return bool
	 */
	public function reset_onboarding_state() {
		return (bool) delete_option( self::OPTION_KEY );
	}

	/**
	 * Determine active business presence.
	 *
	 * @return bool
	 */
	protected function has_active_business() {
		return $this->business_service->count_businesses(
			array(
				'status' => 'active',
			)
		) > 0;
	}

	/**
	 * Determine business admin availability.
	 *
	 * @return bool
	 */
	protected function has_business_admin() {
		$businesses = $this->business_service->get_businesses(
			array(
				'status'   => 'active',
				'page'     => 1,
				'per_page' => 100,
				'orderby'  => 'id',
				'order'    => 'ASC',
			)
		);

		foreach ( $businesses as $business ) {
			if ( ! is_array( $business ) ) {
				continue;
			}
			$business_id = isset( $business['id'] ) ? absint( $business['id'] ) : 0;
			if ( $business_id <= 0 ) {
				continue;
			}

			$members = $this->membership_service->get_business_members( $business_id );
			foreach ( $members as $member ) {
				if ( ! is_array( $member ) ) {
					continue;
				}

				$role   = isset( $member['operational_role'] ) ? sanitize_key( (string) $member['operational_role'] ) : '';
				$status = isset( $member['status'] ) ? sanitize_key( (string) $member['status'] ) : '';
				if ( 'admin' === $role && 'active' === $status ) {
					return true;
				}
			}
		}

		$fallback_admin_users = get_users(
			array(
				'fields'   => 'ids',
				'number'   => 1,
				'role__in' => array( 'administrator', 'sm_admin' ),
			)
		);

		return is_array( $fallback_admin_users ) && ! empty( $fallback_admin_users );
	}

	/**
	 * Evaluate completeness from computed state.
	 *
	 * @param array<string,mixed> $state State payload.
	 * @return bool
	 */
	protected function is_onboarding_complete_from_state( array $state ) {
		$required_ok = ! empty( $state['has_license'] )
			&& ! empty( $state['has_branding_basic'] )
			&& ! empty( $state['has_business'] )
			&& ! empty( $state['has_business_admin'] );

		if ( $required_ok ) {
			return true;
		}

		return ! empty( $state['manual_complete'] );
	}

	/**
	 * Resolve next step from computed state.
	 *
	 * @param array<string,mixed> $state State payload.
	 * @return array<string,string>
	 */
	protected function get_next_recommended_step_from_state( array $state ) {
		if ( empty( $state['has_license'] ) ) {
			return array(
				'key'         => 'license',
				'label'       => __( 'Configure local license', 'super-mechanic' ),
				'description' => __( 'Activate a local license so the installation has a valid plan context.', 'super-mechanic' ),
				'url'         => admin_url( 'admin.php?page=super-mechanic-license' ),
			);
		}

		if ( empty( $state['has_branding_basic'] ) ) {
			return array(
				'key'         => 'branding',
				'label'       => __( 'Configure branding basics', 'super-mechanic' ),
				'description' => __( 'Set system name and basic colors to personalize this installation.', 'super-mechanic' ),
				'url'         => admin_url( 'admin.php?page=super-mechanic-branding' ),
			);
		}

		if ( empty( $state['has_business'] ) ) {
			return array(
				'key'         => 'businesses',
				'label'       => __( 'Create your first business', 'super-mechanic' ),
				'description' => __( 'Define at least one active business to scope operational data.', 'super-mechanic' ),
				'url'         => admin_url( 'admin.php?page=super-mechanic-businesses' ),
			);
		}

		if ( empty( $state['has_business_admin'] ) ) {
			return array(
				'key'         => 'roles_access',
				'label'       => __( 'Assign business admin access', 'super-mechanic' ),
				'description' => __( 'Ensure at least one active operational admin membership exists.', 'super-mechanic' ),
				'url'         => admin_url( 'admin.php?page=super-mechanic-roles' ),
			);
		}

		return $this->get_default_done_step();
	}

	/**
	 * Default done state.
	 *
	 * @return array<string,string>
	 */
	protected function get_default_done_step() {
		return array(
			'key'         => 'done',
			'label'       => __( 'Onboarding complete', 'super-mechanic' ),
			'description' => __( 'Base setup checks are complete. You can continue with normal operations.', 'super-mechanic' ),
			'url'         => admin_url( 'admin.php?page=super-mechanic' ),
		);
	}
}

