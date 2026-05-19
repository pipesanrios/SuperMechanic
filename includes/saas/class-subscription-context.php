<?php
/**
 * SaaS subscription context.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

defined( 'ABSPATH' ) || exit;

/**
 * Passive subscription abstraction for future SaaS licensing.
 */
class Subscription_Context {
	const SOURCE_PASSIVE_CONTEXT = 'passive_context';
	const SOURCE_LOCAL_LICENSE   = 'local_license_service';

	/**
	 * Subscription status.
	 *
	 * @var string
	 */
	protected $status;

	/**
	 * Plan type.
	 *
	 * @var string
	 */
	protected $plan;

	/**
	 * Data source.
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
	 * @param array<string,mixed> $context Subscription context.
	 */
	public function __construct( array $context = array() ) {
		$this->status       = isset( $context['status'] ) ? sanitize_key( (string) $context['status'] ) : License_Context::STATUS_UNKNOWN;
		$this->plan         = isset( $context['plan'] ) ? sanitize_key( (string) $context['plan'] ) : License_Context::PLAN_UNKNOWN;
		$this->source       = isset( $context['source'] ) ? sanitize_key( (string) $context['source'] ) : self::SOURCE_PASSIVE_CONTEXT;
		$this->renewal_at   = $this->normalize_nullable_text( isset( $context['renewal_at'] ) ? $context['renewal_at'] : null );
		$this->expires_at   = $this->normalize_nullable_text( isset( $context['expires_at'] ) ? $context['expires_at'] : null );
		$this->entitlements = License_Context::normalize_entitlement_snapshot(
			isset( $context['entitlements'] ) && is_array( $context['entitlements'] ) ? $context['entitlements'] : array()
		);
	}

	/**
	 * Get status.
	 *
	 * @return string
	 */
	public function get_status() {
		return $this->status;
	}

	/**
	 * Get plan.
	 *
	 * @return string
	 */
	public function get_plan() {
		return $this->plan;
	}

	/**
	 * Get source.
	 *
	 * @return string
	 */
	public function get_source() {
		return $this->source;
	}

	/**
	 * Get renewal date.
	 *
	 * @return string|null
	 */
	public function get_renewal_at() {
		return $this->renewal_at;
	}

	/**
	 * Get expiration date.
	 *
	 * @return string|null
	 */
	public function get_expires_at() {
		return $this->expires_at;
	}

	/**
	 * Get entitlement snapshot.
	 *
	 * @return array<string,mixed>
	 */
	public function get_entitlements() {
		return $this->entitlements;
	}

	/**
	 * Export subscription context.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'status'       => $this->status,
			'plan'         => $this->plan,
			'source'       => $this->source,
			'renewal_at'   => $this->renewal_at,
			'expires_at'   => $this->expires_at,
			'entitlements' => $this->entitlements,
			'is_passive'   => true,
		);
	}

	/**
	 * Normalize nullable text fields.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null
	 */
	protected function normalize_nullable_text( $value ) {
		$value = sanitize_text_field( (string) $value );

		return '' === $value ? null : $value;
	}
}
