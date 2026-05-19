<?php
/**
 * SaaS license context.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

use Super_Mechanic\Licensing\License_Service;
use Super_Mechanic\Licensing\Plan_Limits_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Passive license/subscription context for future SaaS evolution.
 */
class License_Context {
	const STATUS_UNKNOWN  = 'unknown';
	const STATUS_ACTIVE   = 'active';
	const STATUS_INACTIVE = 'inactive';
	const STATUS_TRIAL    = 'trial';
	const STATUS_EXPIRED  = 'expired';
	const STATUS_REVOKED  = 'revoked';

	const PLAN_UNKNOWN    = 'unknown';
	const PLAN_STARTER    = 'starter';
	const PLAN_PRO        = 'pro';
	const PLAN_ENTERPRISE = 'enterprise';

	const SOURCE_PASSIVE_CONTEXT = 'passive_context';
	const SOURCE_LOCAL_LICENSE   = 'local_license_service';

	/**
	 * License key.
	 *
	 * @var string
	 */
	protected $license_key;

	/**
	 * Subscription status.
	 *
	 * @var string
	 */
	protected $subscription_status;

	/**
	 * Plan type.
	 *
	 * @var string
	 */
	protected $plan_type;

	/**
	 * Instance identifier.
	 *
	 * @var string
	 */
	protected $instance_id;

	/**
	 * Subscription source.
	 *
	 * @var string
	 */
	protected $source;

	/**
	 * Renewal timestamp.
	 *
	 * @var string|null
	 */
	protected $renewal_at;

	/**
	 * Expiration timestamp.
	 *
	 * @var string|null
	 */
	protected $expires_at;

	/**
	 * Entitlement snapshot.
	 *
	 * @var array<string,mixed>
	 */
	protected $entitlements;

	/**
	 * Constructor.
	 *
	 * @param array<string,mixed> $context License context.
	 */
	public function __construct( array $context = array() ) {
		$normalized = $this->normalize_context( $context );

		$this->license_key         = $normalized['license_key'];
		$this->subscription_status = $normalized['subscription_status'];
		$this->plan_type           = $normalized['plan_type'];
		$this->instance_id         = $normalized['instance_id'];
		$this->source              = $normalized['source'];
		$this->renewal_at          = $normalized['renewal_at'];
		$this->expires_at          = $normalized['expires_at'];
		$this->entitlements        = $normalized['entitlements'];
	}

	/**
	 * Get supported subscription statuses.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_subscription_statuses() {
		return array(
			self::STATUS_UNKNOWN,
			self::STATUS_ACTIVE,
			self::STATUS_INACTIVE,
			self::STATUS_TRIAL,
			self::STATUS_EXPIRED,
			self::STATUS_REVOKED,
		);
	}

	/**
	 * Get supported plans.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_plan_types() {
		return array(
			self::PLAN_UNKNOWN,
			self::PLAN_STARTER,
			self::PLAN_PRO,
			self::PLAN_ENTERPRISE,
		);
	}

	/**
	 * Export license context without enforcing billing.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'license_key'                  => $this->license_key,
			'subscription_status'          => $this->subscription_status,
			'plan_type'                    => $this->plan_type,
			'instance_id'                  => $this->instance_id,
			'source'                       => $this->source,
			'renewal_at'                   => $this->renewal_at,
			'expires_at'                   => $this->expires_at,
			'entitlement_snapshot'         => $this->get_entitlement_snapshot(),
			'subscription'                 => $this->get_subscription_context()->to_array(),
			'is_billing_provider_enabled'  => false,
			'is_subscription_enforcement'  => false,
			'supported_subscription_state' => $this->get_supported_subscription_statuses(),
			'supported_plan_types'         => $this->get_supported_plan_types(),
		);
	}

	/**
	 * Get license key.
	 *
	 * @return string
	 */
	public function get_license_key() {
		return $this->license_key;
	}

	/**
	 * Get subscription status.
	 *
	 * @return string
	 */
	public function get_subscription_status() {
		return $this->subscription_status;
	}

	/**
	 * Get plan type.
	 *
	 * @return string
	 */
	public function get_plan_type() {
		return $this->plan_type;
	}

	/**
	 * Get instance identifier.
	 *
	 * @return string
	 */
	public function get_instance_id() {
		return $this->instance_id;
	}

	/**
	 * Get entitlement snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function get_entitlement_snapshot() {
		return $this->entitlements;
	}

	/**
	 * Check active subscription state.
	 *
	 * @return bool
	 */
	public function is_active() {
		return self::STATUS_ACTIVE === $this->subscription_status;
	}

	/**
	 * Check trial subscription state.
	 *
	 * @return bool
	 */
	public function is_trial() {
		return self::STATUS_TRIAL === $this->subscription_status;
	}

	/**
	 * Check expired subscription state.
	 *
	 * @return bool
	 */
	public function is_expired() {
		return self::STATUS_EXPIRED === $this->subscription_status;
	}

	/**
	 * Get passive subscription context.
	 *
	 * @return Subscription_Context
	 */
	public function get_subscription_context() {
		return new Subscription_Context(
			array(
				'status'       => $this->subscription_status,
				'plan'         => $this->plan_type,
				'source'       => $this->source,
				'renewal_at'   => $this->renewal_at,
				'expires_at'   => $this->expires_at,
				'entitlements' => $this->entitlements,
			)
		);
	}

	/**
	 * Build a passive context from the current license service.
	 *
	 * @param License_Service $license_service License service.
	 * @return self
	 */
	public static function from_license_service( License_Service $license_service ) {
		$license = $license_service->get_license();
		$status  = method_exists( $license_service, 'get_effective_license_state' )
			? sanitize_key( (string) $license_service->get_effective_license_state() )
			: sanitize_key( isset( $license['license_status'] ) ? (string) $license['license_status'] : self::STATUS_INACTIVE );
		$plan    = isset( $license['plan_type'] ) ? sanitize_key( (string) $license['plan_type'] ) : self::PLAN_STARTER;

		return new self(
			array(
				'license_key'         => isset( $license['license_key'] ) ? (string) $license['license_key'] : '',
				'subscription_status' => $status,
				'plan_type'           => $plan,
				'instance_id'         => self::generate_instance_id(),
				'source'              => self::SOURCE_LOCAL_LICENSE,
				'renewal_at'          => null,
				'expires_at'          => isset( $license['expires_at'] ) ? (string) $license['expires_at'] : '',
				'entitlements'        => self::build_entitlement_snapshot_for_plan( $plan ),
			)
		);
	}

	/**
	 * Build normalized entitlement snapshot for one plan.
	 *
	 * @param string $plan_type Plan type.
	 * @return array<string,mixed>
	 */
	public static function build_entitlement_snapshot_for_plan( $plan_type ) {
		$plan_type = sanitize_key( (string) $plan_type );
		if ( ! in_array( $plan_type, array( self::PLAN_STARTER, self::PLAN_PRO, self::PLAN_ENTERPRISE ), true ) ) {
			$plan_type = self::PLAN_STARTER;
		}

		$limits = Plan_Limits_Service::PLAN_LIMITS[ $plan_type ];

		return self::normalize_entitlement_snapshot(
			array(
				'max_businesses' => isset( $limits['max_businesses'] ) ? $limits['max_businesses'] : null,
				'max_users'      => isset( $limits['max_users'] ) ? $limits['max_users'] : null,
				'max_vehicles'   => null,
				'max_webhooks'   => isset( $limits['max_webhooks'] ) ? $limits['max_webhooks'] : null,
				'feature_flags'  => array(
					'vehicle_catalog'      => true,
					'csv_catalog_import'   => true,
					'mock_connectors'      => true,
					'real_connectors'      => false,
					'scheduled_sync'       => false,
					'saas_billing_runtime' => false,
				),
			)
		);
	}

	/**
	 * Normalize entitlement snapshot.
	 *
	 * @param array<string,mixed> $snapshot Entitlement snapshot.
	 * @return array<string,mixed>
	 */
	public static function normalize_entitlement_snapshot( array $snapshot ) {
		$feature_flags = array();
		if ( isset( $snapshot['feature_flags'] ) && is_array( $snapshot['feature_flags'] ) ) {
			foreach ( $snapshot['feature_flags'] as $flag => $enabled ) {
				$feature_flags[ sanitize_key( (string) $flag ) ] = (bool) $enabled;
			}
		}

		return array(
			'max_businesses' => self::normalize_nullable_limit( isset( $snapshot['max_businesses'] ) ? $snapshot['max_businesses'] : null ),
			'max_users'      => self::normalize_nullable_limit( isset( $snapshot['max_users'] ) ? $snapshot['max_users'] : null ),
			'max_vehicles'   => self::normalize_nullable_limit( isset( $snapshot['max_vehicles'] ) ? $snapshot['max_vehicles'] : null ),
			'max_webhooks'   => self::normalize_nullable_limit( isset( $snapshot['max_webhooks'] ) ? $snapshot['max_webhooks'] : null ),
			'feature_flags'  => $feature_flags,
		);
	}

	/**
	 * Generate stable local instance identity without external registration.
	 *
	 * @return string
	 */
	public static function generate_instance_id() {
		$home = function_exists( 'home_url' ) ? (string) home_url() : '';
		$site = function_exists( 'site_url' ) ? (string) site_url() : '';

		return 'sm_' . md5( $home . '|' . $site . '|super-mechanic' );
	}

	/**
	 * Normalize context payload.
	 *
	 * @param array<string,mixed> $context License context.
	 * @return array<string,mixed>
	 */
	protected function normalize_context( array $context ) {
		$status = isset( $context['subscription_status'] ) ? sanitize_key( (string) $context['subscription_status'] ) : self::STATUS_UNKNOWN;
		if ( ! in_array( $status, $this->get_supported_subscription_statuses(), true ) ) {
			$status = self::STATUS_UNKNOWN;
		}

		$plan = isset( $context['plan_type'] ) ? sanitize_key( (string) $context['plan_type'] ) : self::PLAN_UNKNOWN;
		if ( ! in_array( $plan, $this->get_supported_plan_types(), true ) ) {
			$plan = self::PLAN_UNKNOWN;
		}

		$instance_id = isset( $context['instance_id'] ) ? sanitize_key( (string) $context['instance_id'] ) : '';
		if ( '' === $instance_id ) {
			$instance_id = self::generate_instance_id();
		}

		return array(
			'license_key'         => isset( $context['license_key'] ) ? sanitize_text_field( (string) $context['license_key'] ) : '',
			'subscription_status' => $status,
			'plan_type'           => $plan,
			'instance_id'         => $instance_id,
			'source'              => isset( $context['source'] ) ? sanitize_key( (string) $context['source'] ) : self::SOURCE_PASSIVE_CONTEXT,
			'renewal_at'          => $this->normalize_nullable_text( isset( $context['renewal_at'] ) ? $context['renewal_at'] : null ),
			'expires_at'          => $this->normalize_nullable_text( isset( $context['expires_at'] ) ? $context['expires_at'] : null ),
			'entitlements'        => self::normalize_entitlement_snapshot(
				isset( $context['entitlements'] ) && is_array( $context['entitlements'] )
					? $context['entitlements']
					: self::build_entitlement_snapshot_for_plan( $plan )
			),
		);
	}

	/**
	 * Normalize nullable limit.
	 *
	 * @param mixed $limit Limit.
	 * @return int|null
	 */
	protected static function normalize_nullable_limit( $limit ) {
		if ( null === $limit || '' === $limit ) {
			return null;
		}

		return absint( $limit );
	}

	/**
	 * Normalize nullable text.
	 *
	 * @param mixed $value Value.
	 * @return string|null
	 */
	protected function normalize_nullable_text( $value ) {
		$value = sanitize_text_field( (string) $value );

		return '' === $value ? null : $value;
	}
}
