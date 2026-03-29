<?php
/**
 * Woo product read service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\WooCommerce;

defined( 'ABSPATH' ) || exit;

/**
 * Reads WooCommerce products for operational linking.
 */
class Woo_Product_Service {
	/**
	 * Whether WooCommerce product APIs are available.
	 *
	 * @return bool
	 */
	public function is_available() {
		return class_exists( 'WooCommerce' ) && function_exists( 'wc_get_products' ) && function_exists( 'wc_get_product' );
	}

	/**
	 * List available Woo products as lightweight options.
	 *
	 * @param int    $limit  Max rows.
	 * @param string $search Optional search text.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_product_options( $limit = 100, $search = '' ) {
		if ( ! $this->is_available() ) {
			return array();
		}

		$limit = max( 1, min( 200, absint( $limit ) ) );
		$args  = array(
			'status'  => 'publish',
			'limit'   => $limit,
			'orderby' => 'title',
			'order'   => 'ASC',
			'return'  => 'objects',
		);

		$search = sanitize_text_field( $search );
		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$products = wc_get_products( $args );
		if ( ! is_array( $products ) ) {
			return array();
		}

		$options = array();
		foreach ( $products as $product ) {
			if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
				continue;
			}

			$unit_price = $this->normalize_product_price( $product );
			$options[]  = array(
				'id'         => absint( $product->get_id() ),
				'name'       => sanitize_text_field( $product->get_name() ),
				'unit_price' => $unit_price,
				'label'      => sprintf(
					'%s (%s)',
					sanitize_text_field( $product->get_name() ),
					number_format_i18n( $unit_price, 2 )
				),
			);
		}

		return $options;
	}

	/**
	 * Resolve a Woo product snapshot for persistence in plugin items.
	 *
	 * @param int $product_id Woo product ID.
	 * @return array<string, mixed>|null
	 */
	public function get_product_snapshot( $product_id ) {
		$product_id = absint( $product_id );

		if ( $product_id <= 0 || ! $this->is_available() ) {
			return null;
		}

		$product = wc_get_product( $product_id );
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_id' ) ) {
			return null;
		}

		return array(
			'id'         => absint( $product->get_id() ),
			'name'       => sanitize_text_field( $product->get_name() ),
			'unit_price' => $this->normalize_product_price( $product ),
		);
	}

	/**
	 * Normalize Woo price as decimal float.
	 *
	 * @param object $product Woo product.
	 * @return float
	 */
	protected function normalize_product_price( $product ) {
		$raw_price = method_exists( $product, 'get_price' ) ? $product->get_price() : '';

		if ( '' === (string) $raw_price && method_exists( $product, 'get_regular_price' ) ) {
			$raw_price = $product->get_regular_price();
		}

		return round( (float) str_replace( ',', '.', (string) $raw_price ), 2 );
	}
}

