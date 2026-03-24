<?php
/**
 * Client vehicle transaction repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Relations;

use Throwable;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates transactional writes for client-vehicle ownership changes.
 */
class Client_Vehicle_Transaction_Repository {
	/**
	 * Execute a callback inside a database transaction.
	 *
	 * @param callable $callback Transaction callback.
	 * @return mixed|WP_Error
	 */
	public function run_in_transaction( callable $callback ) {
		global $wpdb;

		$started = $wpdb->query( 'START TRANSACTION' );

		if ( false === $started ) {
			return new WP_Error( 'sm_client_vehicle_transaction_start_failed', __( 'No fue posible iniciar la transaccion de relacion cliente-vehiculo.', 'super-mechanic' ) );
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

				return new WP_Error( 'sm_client_vehicle_transaction_commit_failed', __( 'No fue posible confirmar la transaccion de relacion cliente-vehiculo.', 'super-mechanic' ) );
			}

			return $result;
		} catch ( Throwable $throwable ) {
			$wpdb->query( 'ROLLBACK' );

			return new WP_Error( 'sm_client_vehicle_transaction_exception', $throwable->getMessage() );
		}
	}
}
