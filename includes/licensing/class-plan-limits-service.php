<?php
/**
 * Plan limits service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Licensing;

use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Processes\Process_Repository;
use Super_Mechanic\Users\Business_Membership_Service;
use Super_Mechanic\Webhooks\Webhook_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized plan limits and usage status.
 */
class Plan_Limits_Service {
	/**
	 * Plan limits catalog.
	 *
	 * @var array<string,array<string,int|null>>
	 */
	const PLAN_LIMITS = array(
		'starter'    => array(
			'max_businesses'       => 1,
			'max_users'            => 5,
			'max_active_processes' => 25,
			'max_webhooks'         => 1,
		),
		'pro'        => array(
			'max_businesses'       => 3,
			'max_users'            => 25,
			'max_active_processes' => 250,
			'max_webhooks'         => 10,
		),
		'enterprise' => array(
			'max_businesses'       => null,
			'max_users'            => null,
			'max_active_processes' => null,
			'max_webhooks'         => null,
		),
	);

	/**
	 * Resource map.
	 *
	 * @var array<string,array<string,string>>
	 */
	const RESOURCE_MAP = array(
		'businesses'       => array(
			'limit_key' => 'max_businesses',
			'label'     => 'Businesses',
		),
		'users'            => array(
			'limit_key' => 'max_users',
			'label'     => 'Internal users',
		),
		'active_processes' => array(
			'limit_key' => 'max_active_processes',
			'label'     => 'Active processes',
		),
		'active_webhooks'  => array(
			'limit_key' => 'max_webhooks',
			'label'     => 'Active webhooks',
		),
	);

	/**
	 * License service.
	 *
	 * @var License_Service
	 */
	protected $license_service;

	/**
	 * Business repository.
	 *
	 * @var Business_Repository
	 */
	protected $business_repository;

	/**
	 * Membership service.
	 *
	 * @var Business_Membership_Service
	 */
	protected $membership_service;

	/**
	 * Process repository.
	 *
	 * @var Process_Repository
	 */
	protected $process_repository;

	/**
	 * Webhook repository.
	 *
	 * @var Webhook_Repository
	 */
	protected $webhook_repository;

	/**
	 * Constructor.
	 *
	 * @param License_Service|null             $license_service License service.
	 * @param Business_Repository|null         $business_repository Business repository.
	 * @param Business_Membership_Service|null $membership_service Membership service.
	 * @param Process_Repository|null          $process_repository Process repository.
	 * @param Webhook_Repository|null          $webhook_repository Webhook repository.
	 */
	public function __construct(
		License_Service $license_service = null,
		Business_Repository $business_repository = null,
		Business_Membership_Service $membership_service = null,
		Process_Repository $process_repository = null,
		Webhook_Repository $webhook_repository = null
	) {
		$this->license_service     = $license_service ? $license_service : new License_Service();
		$this->business_repository = $business_repository ? $business_repository : new Business_Repository();
		$this->membership_service  = $membership_service ? $membership_service : new Business_Membership_Service();
		$this->process_repository  = $process_repository ? $process_repository : new Process_Repository();
		$this->webhook_repository  = $webhook_repository ? $webhook_repository : new Webhook_Repository();
	}

	/**
	 * Get limits for one plan.
	 *
	 * @param string $plan_type Plan key.
	 * @return array<string,int|null>
	 */
	public function get_plan_limits( $plan_type ) {
		$plan_type = sanitize_key( (string) $plan_type );
		if ( ! isset( self::PLAN_LIMITS[ $plan_type ] ) ) {
			$plan_type = 'starter';
		}

		return self::PLAN_LIMITS[ $plan_type ];
	}

	/**
	 * Resolve current plan with starter fallback if inactive/invalid.
	 *
	 * @return string
	 */
	public function get_current_plan_type() {
		$license = $this->license_service->get_license();
		$status  = isset( $license['license_status'] ) ? sanitize_key( (string) $license['license_status'] ) : 'inactive';
		$plan    = isset( $license['plan_type'] ) ? sanitize_key( (string) $license['plan_type'] ) : 'starter';

		if ( 'active' !== $status || ! isset( self::PLAN_LIMITS[ $plan ] ) ) {
			return 'starter';
		}

		return $plan;
	}

	/**
	 * Get current usage counters.
	 *
	 * @return array<string,int>
	 */
	public function get_current_usage() {
		return array(
			'businesses'       => $this->count_businesses(),
			'users'            => $this->count_internal_users(),
			'active_processes' => $this->count_active_processes(),
			'active_webhooks'  => $this->count_active_webhooks(),
		);
	}

	/**
	 * Get status for one resource.
	 *
	 * @param string $resource_key Resource key.
	 * @return array<string,mixed>
	 */
	public function get_limit_status( $resource_key ) {
		$resource_key = sanitize_key( (string) $resource_key );
		if ( ! isset( self::RESOURCE_MAP[ $resource_key ] ) ) {
			return array(
				'resource_key' => $resource_key,
				'label'        => $resource_key,
				'limit'        => null,
				'used'         => 0,
				'remaining'    => null,
				'is_unlimited' => true,
				'is_exceeded'  => false,
				'within_limit' => true,
			);
		}

		$plan_type  = $this->get_current_plan_type();
		$limits     = $this->get_plan_limits( $plan_type );
		$usage      = $this->get_current_usage();
		$limit_key  = self::RESOURCE_MAP[ $resource_key ]['limit_key'];
		$limit      = isset( $limits[ $limit_key ] ) ? $limits[ $limit_key ] : null;
		$used       = isset( $usage[ $resource_key ] ) ? absint( $usage[ $resource_key ] ) : 0;
		$unlimited  = null === $limit;
		$within     = $unlimited ? true : ( $used <= absint( $limit ) );
		$remaining  = $unlimited ? null : max( 0, absint( $limit ) - $used );

		return array(
			'resource_key' => $resource_key,
			'label'        => self::RESOURCE_MAP[ $resource_key ]['label'],
			'limit'        => $limit,
			'used'         => $used,
			'remaining'    => $remaining,
			'is_unlimited' => $unlimited,
			'is_exceeded'  => ! $within,
			'within_limit' => $within,
		);
	}

	/**
	 * Check if one resource is within plan limit.
	 *
	 * @param string $resource_key Resource key.
	 * @return bool
	 */
	public function is_within_limit( $resource_key ) {
		$status = $this->get_limit_status( $resource_key );

		return ! empty( $status['within_limit'] );
	}

	/**
	 * Get list of exceeded resources.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_exceeded_limits() {
		$exceeded = array();
		foreach ( array_keys( self::RESOURCE_MAP ) as $resource_key ) {
			$status = $this->get_limit_status( $resource_key );
			if ( ! empty( $status['is_exceeded'] ) ) {
				$exceeded[] = $status;
			}
		}

		return $exceeded;
	}

	/**
	 * Get resource keys and labels for UI.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_resource_map() {
		return self::RESOURCE_MAP;
	}

	/**
	 * Count businesses in site.
	 *
	 * @return int
	 */
	protected function count_businesses() {
		return absint( $this->business_repository->count_businesses() );
	}

	/**
	 * Count internal users (admin + mechanics roles).
	 *
	 * @return int
	 */
	protected function count_internal_users() {
		$user_ids = get_users(
			array(
				'fields'   => 'ids',
				'number'   => -1,
				'role__in' => array( 'administrator', 'sm_admin', 'sm_mechanic' ),
			)
		);

		if ( ! is_array( $user_ids ) ) {
			return 0;
		}

		$unique = array();
		foreach ( $user_ids as $user_id ) {
			$user_id = absint( $user_id );
			if ( $user_id > 0 ) {
				$unique[ $user_id ] = $user_id;
			}
		}

		return count( $unique );
	}

	/**
	 * Count active processes globally.
	 *
	 * @return int
	 */
	protected function count_active_processes() {
		$total = absint(
			$this->process_repository->count_all(
				array(
					'business_id' => 0,
				)
			)
		);

		$terminal_statuses = array( 'completed', 'delivered', 'cancelled' );
		$terminal_total    = 0;
		foreach ( $terminal_statuses as $status ) {
			$terminal_total += absint(
				$this->process_repository->count_all(
					array(
						'business_id' => 0,
						'status'      => $status,
					)
				)
			);
		}

		return max( 0, $total - $terminal_total );
	}

	/**
	 * Count active webhooks globally.
	 *
	 * @return int
	 */
	protected function count_active_webhooks() {
		return absint( $this->webhook_repository->count_active_webhooks() );
	}
}

