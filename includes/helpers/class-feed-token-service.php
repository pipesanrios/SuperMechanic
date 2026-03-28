<?php
/**
 * Feed token service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Helpers;

defined( 'ABSPATH' ) || exit;

/**
 * Creates and validates signed feed tokens for read-only exports.
 */
class Feed_Token_Service {
	/**
	 * Secret option key.
	 */
	const SECRET_OPTION = 'sm_feed_token_secret';

	/**
	 * Build a signed token payload.
	 *
	 * @param array<string,mixed> $filters Allowed feed filters.
	 * @param int                 $expires Unix timestamp.
	 * @return array<string,string|int>
	 */
	public function build_signed_token( array $filters, $expires ) {
		$expires = absint( $expires );
		$filters = $this->normalize_filters( $filters );
		$sig     = $this->generate_signature( $filters, $expires );

		return array(
			'expires' => $expires,
			'sig'     => $sig,
		);
	}

	/**
	 * Verify a token against filters + expiration.
	 *
	 * @param array<string,mixed> $filters Allowed feed filters.
	 * @param int                 $expires Unix timestamp.
	 * @param string              $sig Signature.
	 * @return bool
	 */
	public function is_valid_token( array $filters, $expires, $sig ) {
		$expires = absint( $expires );
		$sig     = sanitize_text_field( (string) $sig );

		if ( $expires <= time() || '' === $sig ) {
			return false;
		}

		$expected = $this->generate_signature( $this->normalize_filters( $filters ), $expires );

		return hash_equals( $expected, $sig );
	}

	/**
	 * Normalize allowed filters for signature.
	 *
	 * @param array<string,mixed> $filters Raw filters.
	 * @return array<string,mixed>
	 */
	public function normalize_filters( array $filters ) {
		return array(
			'assigned_to' => isset( $filters['assigned_to'] ) ? absint( $filters['assigned_to'] ) : 0,
			'status'      => isset( $filters['status'] ) ? sanitize_key( (string) $filters['status'] ) : '',
			'date_from'   => isset( $filters['date_from'] ) ? $this->normalize_date( $filters['date_from'] ) : '',
			'date_to'     => isset( $filters['date_to'] ) ? $this->normalize_date( $filters['date_to'] ) : '',
		);
	}

	/**
	 * Generate HMAC signature.
	 *
	 * @param array<string,mixed> $filters Normalized filters.
	 * @param int                 $expires Unix timestamp.
	 * @return string
	 */
	protected function generate_signature( array $filters, $expires ) {
		$payload = wp_json_encode(
			array(
				'assigned_to' => absint( $filters['assigned_to'] ),
				'status'      => sanitize_key( (string) $filters['status'] ),
				'date_from'   => sanitize_text_field( (string) $filters['date_from'] ),
				'date_to'     => sanitize_text_field( (string) $filters['date_to'] ),
				'expires'     => absint( $expires ),
			)
		);

		return hash_hmac( 'sha256', (string) $payload, $this->get_or_create_secret() );
	}

	/**
	 * Get or create secret key.
	 *
	 * @return string
	 */
	protected function get_or_create_secret() {
		$secret = get_option( self::SECRET_OPTION, '' );

		if ( is_string( $secret ) && '' !== $secret ) {
			return $secret;
		}

		try {
			$secret = bin2hex( random_bytes( 32 ) );
		} catch ( \Exception $exception ) {
			$secret = wp_generate_password( 64, true, true );
		}

		update_option( self::SECRET_OPTION, $secret, false );

		return (string) $secret;
	}

	/**
	 * Normalize a date value.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	protected function normalize_date( $value ) {
		$raw = sanitize_text_field( (string) $value );

		if ( '' === $raw ) {
			return '';
		}

		$timestamp = strtotime( $raw );

		return false === $timestamp ? '' : gmdate( 'Y-m-d', $timestamp );
	}
}
