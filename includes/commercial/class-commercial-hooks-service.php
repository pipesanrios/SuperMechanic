<?php
/**
 * Commercial hooks service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Commercial;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized dispatcher for commercial extension hooks.
 */
class Commercial_Hooks_Service {
	/**
	 * Supported hooks.
	 *
	 * @var array<int,string>
	 */
	protected $supported_hooks = array(
		'sm_quote_created',
		'sm_quote_approved',
		'sm_invoice_created',
		'sm_invoice_paid',
		'sm_payment_created',
		'sm_process_completed',
	);

	/**
	 * Dispatch one commercial hook with standardized payload.
	 *
	 * @param string              $hook_name Hook name.
	 * @param array<string,mixed> $payload   Hook payload.
	 * @return bool
	 */
	public function dispatch( $hook_name, $payload ) {
		$hook_name = sanitize_key( (string) $hook_name );
		if ( ! in_array( $hook_name, $this->supported_hooks, true ) ) {
			return false;
		}

		$payload = $this->normalize_payload( is_array( $payload ) ? $payload : array() );
		do_action( $hook_name, $payload );

		return true;
	}

	/**
	 * Get supported hook names.
	 *
	 * @return array<int,string>
	 */
	public function get_supported_hooks() {
		return $this->supported_hooks;
	}

	/**
	 * Normalize commercial hook payload.
	 *
	 * @param array<string,mixed> $payload Raw payload.
	 * @return array<string,mixed>
	 */
	protected function normalize_payload( array $payload ) {
		$data = isset( $payload['data'] ) && is_array( $payload['data'] ) ? $payload['data'] : $payload;

		return array(
			'business_id' => isset( $payload['business_id'] ) ? absint( $payload['business_id'] ) : 0,
			'entity_id'   => isset( $payload['entity_id'] ) ? absint( $payload['entity_id'] ) : 0,
			'entity_type' => isset( $payload['entity_type'] ) ? sanitize_key( (string) $payload['entity_type'] ) : '',
			'data'        => $data,
		);
	}
}
