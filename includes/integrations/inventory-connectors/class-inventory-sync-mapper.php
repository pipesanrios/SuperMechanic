<?php
/**
 * Inventory sync mapper.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Inventory_Connectors;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes and validates provider inventory payloads.
 */
class Inventory_Sync_Mapper {
	/**
	 * Stock statuses accepted by the generic connector contract.
	 *
	 * @var array<int,string>
	 */
	protected $stock_statuses = array(
		'available',
		'reserved',
		'sold',
		'inactive',
		'unknown',
	);

	/**
	 * Normalize one provider item to the generic inventory payload.
	 *
	 * @param array<string,mixed> $item          Raw provider item.
	 * @param int                 $business_id   Business ID.
	 * @param string              $connector_key Connector key.
	 * @return array<string,mixed>
	 */
	public function normalize_item( array $item, $business_id, $connector_key ) {
		$business_id   = absint( $business_id );
		$connector_key = sanitize_key( (string) $connector_key );
		$stock_status  = isset( $item['stock_status'] ) ? sanitize_key( (string) $item['stock_status'] ) : 'available';

		if ( ! in_array( $stock_status, $this->stock_statuses, true ) ) {
			$stock_status = 'unknown';
		}

		return array(
			'external_id'   => isset( $item['external_id'] ) ? sanitize_text_field( (string) $item['external_id'] ) : '',
			'business_id'   => $business_id,
			'make'          => isset( $item['make'] ) ? sanitize_text_field( (string) $item['make'] ) : '',
			'model'         => isset( $item['model'] ) ? sanitize_text_field( (string) $item['model'] ) : '',
			'year'          => isset( $item['year'] ) ? absint( $item['year'] ) : 0,
			'trim_version'  => isset( $item['trim_version'] ) ? sanitize_text_field( (string) $item['trim_version'] ) : '',
			'body_type'     => isset( $item['body_type'] ) ? sanitize_text_field( (string) $item['body_type'] ) : '',
			'fuel_type'     => isset( $item['fuel_type'] ) ? sanitize_text_field( (string) $item['fuel_type'] ) : '',
			'transmission'  => isset( $item['transmission'] ) ? sanitize_text_field( (string) $item['transmission'] ) : '',
			'engine'        => isset( $item['engine'] ) ? sanitize_text_field( (string) $item['engine'] ) : '',
			'vin'           => isset( $item['vin'] ) ? sanitize_text_field( (string) $item['vin'] ) : '',
			'plate'         => isset( $item['plate'] ) ? sanitize_text_field( (string) $item['plate'] ) : '',
			'color'         => isset( $item['color'] ) ? sanitize_text_field( (string) $item['color'] ) : '',
			'mileage'       => isset( $item['mileage'] ) && is_numeric( $item['mileage'] ) ? (float) $item['mileage'] : null,
			'price'         => isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : null,
			'currency'      => isset( $item['currency'] ) ? strtoupper( sanitize_text_field( (string) $item['currency'] ) ) : '',
			'stock_status'  => $stock_status,
			'media'         => isset( $item['media'] ) && is_array( $item['media'] ) ? $this->sanitize_media( $item['media'] ) : array(),
			'notes'         => isset( $item['notes'] ) ? sanitize_textarea_field( (string) $item['notes'] ) : '',
			'raw_payload'   => $this->sanitize_raw_payload( $item ),
			'connector_key' => $connector_key,
		);
	}

	/**
	 * Validate normalized payload.
	 *
	 * @param array<string,mixed> $payload              Normalized payload.
	 * @param int                 $expected_business_id Expected business ID.
	 * @return array<int,array<string,string>>
	 */
	public function validate_payload( array $payload, $expected_business_id ) {
		$errors               = array();
		$expected_business_id = absint( $expected_business_id );
		$current_year         = (int) gmdate( 'Y' ) + 1;

		foreach ( array( 'external_id', 'business_id', 'make', 'model', 'year' ) as $field ) {
			if ( ! isset( $payload[ $field ] ) || '' === (string) $payload[ $field ] || 0 === absint( $payload[ $field ] ) && in_array( $field, array( 'business_id', 'year' ), true ) ) {
				$errors[] = $this->build_error( 'missing_required_field', $field, 'Required field is missing.' );
			}
		}

		$year = isset( $payload['year'] ) ? absint( $payload['year'] ) : 0;
		if ( $year < 1900 || $year > $current_year ) {
			$errors[] = $this->build_error( 'invalid_payload', 'year', 'Year must be a valid four-digit value.' );
		}

		if ( isset( $payload['mileage'] ) && null !== $payload['mileage'] && ( ! is_numeric( $payload['mileage'] ) || (float) $payload['mileage'] < 0 ) ) {
			$errors[] = $this->build_error( 'invalid_payload', 'mileage', 'Mileage must be numeric and non-negative.' );
		}

		if ( isset( $payload['price'] ) && null !== $payload['price'] && ( ! is_numeric( $payload['price'] ) || (float) $payload['price'] < 0 ) ) {
			$errors[] = $this->build_error( 'invalid_payload', 'price', 'Price must be numeric and non-negative.' );
		}

		$currency = isset( $payload['currency'] ) ? (string) $payload['currency'] : '';
		if ( '' !== $currency && ! preg_match( '/^[A-Z]{3}$/', $currency ) ) {
			$errors[] = $this->build_error( 'invalid_payload', 'currency', 'Currency must be a three-letter uppercase code.' );
		}

		$stock_status = isset( $payload['stock_status'] ) ? (string) $payload['stock_status'] : '';
		if ( '' !== $stock_status && ! in_array( $stock_status, $this->stock_statuses, true ) ) {
			$errors[] = $this->build_error( 'invalid_payload', 'stock_status', 'Stock status is not supported.' );
		}

		$business_id = isset( $payload['business_id'] ) ? absint( $payload['business_id'] ) : 0;
		if ( $expected_business_id <= 0 || $business_id !== $expected_business_id ) {
			$errors[] = $this->build_error( 'business_scope_violation', 'business_id', 'Payload business scope does not match connector scope.' );
		}

		return $errors;
	}

	/**
	 * Build a standard validation error.
	 *
	 * @param string $code    Error code.
	 * @param string $field   Field.
	 * @param string $message Message.
	 * @return array<string,string>
	 */
	protected function build_error( $code, $field, $message ) {
		return array(
			'error_code' => sanitize_key( (string) $code ),
			'field'      => sanitize_key( (string) $field ),
			'message'    => sanitize_text_field( (string) $message ),
		);
	}

	/**
	 * Sanitize media list.
	 *
	 * @param array<int|string,mixed> $media Raw media.
	 * @return array<int,string>
	 */
	protected function sanitize_media( array $media ) {
		$sanitized = array();

		foreach ( $media as $item ) {
			$value = esc_url_raw( (string) $item );
			if ( '' !== $value ) {
				$sanitized[] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize raw payload snapshot.
	 *
	 * @param array<string,mixed> $item Raw item.
	 * @return array<string,mixed>
	 */
	protected function sanitize_raw_payload( array $item ) {
		$payload = array();

		foreach ( $item as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || in_array( $key, array( 'token', 'access_token', 'secret', 'password' ), true ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$payload[ $key ] = $this->sanitize_raw_payload( $value );
			} elseif ( is_numeric( $value ) ) {
				$payload[ $key ] = $value;
			} else {
				$payload[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $payload;
	}
}
