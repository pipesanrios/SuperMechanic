<?php
/**
 * Quote transaction repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Quotes;

use Throwable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates low-level transactional persistence for quotes.
 */
class Quote_Transaction_Repository {
	/**
	 * Execute a callback inside a database transaction.
	 *
	 * @param callable $callback Transactional callback.
	 * @return mixed|WP_Error
	 */
	public function run_in_transaction( callable $callback ) {
		global $wpdb;

		$started = $wpdb->query( 'START TRANSACTION' );

		if ( false === $started ) {
			return new WP_Error( 'sm_quote_transaction_start_failed', __( 'No fue posible iniciar la transaccion de cotizacion.', 'super-mechanic' ) );
		}

		try {
			$result = $callback();

			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $result;
			}

			$committed = $wpdb->query( 'COMMIT' );

			if ( false === $committed ) {
				$wpdb->query( 'ROLLBACK' );

				return new WP_Error( 'sm_quote_transaction_commit_failed', __( 'No fue posible confirmar la transaccion de cotizacion.', 'super-mechanic' ) );
			}

			return $result;
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error( 'sm_quote_transaction_exception', $throwable->getMessage() );
		}
	}
}
