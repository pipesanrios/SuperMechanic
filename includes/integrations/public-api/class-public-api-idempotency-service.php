<?php
/**
 * Public API idempotency service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Public_API;

defined( 'ABSPATH' ) || exit;

/**
 * Handles idempotent replay for public write actions using transients.
 */
class Public_API_Idempotency_Service {
	/**
	 * Default transient TTL (24h).
	 */
	const DEFAULT_TTL = DAY_IN_SECONDS;

	/**
	 * Build one idempotency key fingerprint.
	 *
	 * @param int    $business_id      Business ID.
	 * @param int    $appointment_id   Appointment ID.
	 * @param string $action           Action name.
	 * @param string $idempotency_key  Client idempotency key.
	 * @return string
	 */
	public function build_fingerprint( $business_id, $appointment_id, $action, $idempotency_key ) {
		$raw = implode(
			'|',
			array(
				max( 1, absint( $business_id ) ),
				absint( $appointment_id ),
				sanitize_key( (string) $action ),
				sanitize_text_field( (string) $idempotency_key ),
			)
		);

		return 'sm_pub_idem_' . hash( 'sha256', $raw );
	}

	/**
	 * Get one cached response.
	 *
	 * @param string $fingerprint Fingerprint key.
	 * @return array<string,mixed>|null
	 */
	public function get_cached_response( $fingerprint ) {
		$value = get_transient( sanitize_key( (string) $fingerprint ) );

		return is_array( $value ) ? $value : null;
	}

	/**
	 * Cache response payload.
	 *
	 * @param string               $fingerprint Fingerprint key.
	 * @param array<string,mixed>  $payload     Response payload.
	 * @param int                  $ttl         Optional TTL.
	 * @return void
	 */
	public function remember_response( $fingerprint, array $payload, $ttl = self::DEFAULT_TTL ) {
		set_transient(
			sanitize_key( (string) $fingerprint ),
			$payload,
			max( MINUTE_IN_SECONDS, absint( $ttl ) )
		);
	}
}

