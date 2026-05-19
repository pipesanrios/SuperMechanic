<?php
/**
 * SaaS tenant context.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Saas;

use Super_Mechanic\Helpers\Business_Context_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Future tenant abstraction that preserves current business scope.
 */
class Tenant_Context {
	const SOURCE_BUSINESS_SCOPE = 'business_scope';

	/**
	 * Business context dependency.
	 *
	 * @var Business_Context_Service|null
	 */
	protected $business_context_service;

	/**
	 * Future tenant identifier.
	 *
	 * @var string|null
	 */
	protected $tenant_id;

	/**
	 * Current business identifier.
	 *
	 * @var int
	 */
	protected $business_id;

	/**
	 * Constructor.
	 *
	 * @param Business_Context_Service|null $business_context_service Business scope resolver.
	 * @param string                        $tenant_id Future tenant identifier.
	 * @param int                           $business_id Current business identifier.
	 */
	public function __construct( Business_Context_Service $business_context_service = null, $tenant_id = '', $business_id = 0 ) {
		$this->business_context_service = $business_context_service;
		$this->tenant_id                = $this->normalize_tenant_id( $tenant_id );
		$this->business_id              = absint( $business_id );
	}

	/**
	 * Resolve tenant context for current or explicit business scope.
	 *
	 * @param int $business_id Requested business identifier.
	 * @param int $user_id User identifier.
	 * @return array<string,mixed>
	 */
	public function resolve( $business_id = 0, $user_id = 0 ) {
		$resolved_business_id = absint( $business_id );

		if ( $this->business_context_service instanceof Business_Context_Service ) {
			$resolved_business_id = $this->business_context_service->resolve_business_id_for_user( absint( $user_id ), $resolved_business_id );
		} elseif ( $resolved_business_id <= 0 ) {
			$resolved_business_id = $this->business_id;
		}

		return array(
			'tenant_id'                => $this->tenant_id,
			'business_id'              => $resolved_business_id,
			'source'                   => self::SOURCE_BUSINESS_SCOPE,
			'is_future_tenant_enabled' => false,
			'is_business_scope_active' => $resolved_business_id > 0,
			'has_tenant'               => null !== $this->tenant_id && '' !== $this->tenant_id,
			'has_business_scope'       => $resolved_business_id > 0,
		);
	}

	/**
	 * Get future tenant identifier.
	 *
	 * @return string|null
	 */
	public function get_tenant_id() {
		return $this->tenant_id;
	}

	/**
	 * Get current business identifier.
	 *
	 * @return int
	 */
	public function get_business_id() {
		return $this->business_id;
	}

	/**
	 * Check whether a future tenant identifier is present.
	 *
	 * @return bool
	 */
	public function has_tenant() {
		return null !== $this->tenant_id && '' !== $this->tenant_id;
	}

	/**
	 * Check whether current business scope is present.
	 *
	 * @return bool
	 */
	public function has_business_scope() {
		return $this->business_id > 0;
	}

	/**
	 * Export raw context without forcing a business lookup.
	 *
	 * @return array<string,mixed>
	 */
	public function to_array() {
		return array(
			'tenant_id'                => $this->tenant_id,
			'business_id'              => $this->business_id,
			'source'                   => self::SOURCE_BUSINESS_SCOPE,
			'is_future_tenant_enabled' => false,
			'has_tenant'               => $this->has_tenant(),
			'has_business_scope'       => $this->has_business_scope(),
			'bridge_strategy'          => 'business_id_canonical',
		);
	}

	/**
	 * Create a tenant context from the current business scope.
	 *
	 * @param int                           $business_id Business identifier.
	 * @param Business_Context_Service|null $business_context_service Business scope resolver.
	 * @return self
	 */
	public static function from_business_id( $business_id, Business_Context_Service $business_context_service = null ) {
		return new self( $business_context_service, null, absint( $business_id ) );
	}

	/**
	 * Create a tenant context from a runtime context without taking over runtime behavior.
	 *
	 * @param Runtime_Context|null          $runtime_context Runtime context.
	 * @param int                           $business_id Business identifier.
	 * @param Business_Context_Service|null $business_context_service Business scope resolver.
	 * @return self
	 */
	public static function from_runtime_context( Runtime_Context $runtime_context = null, $business_id = 0, Business_Context_Service $business_context_service = null ) {
		$tenant_id = null;

		if ( $runtime_context instanceof Runtime_Context && $runtime_context->is_saas_future() && absint( $business_id ) > 0 ) {
			$tenant_id = 'business-' . absint( $business_id );
		}

		return new self( $business_context_service, $tenant_id, absint( $business_id ) );
	}

	/**
	 * Normalize tenant identifier.
	 *
	 * @param string $tenant_id Tenant identifier.
	 * @return string|null
	 */
	protected function normalize_tenant_id( $tenant_id ) {
		$tenant_id = sanitize_key( (string) $tenant_id );

		return '' === $tenant_id ? null : $tenant_id;
	}
}
