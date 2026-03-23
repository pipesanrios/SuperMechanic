<?php
/**
 * Process transaction repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Processes;

defined( 'ABSPATH' ) || exit;

/**
 * Encapsulates atomic process persistence operations.
 */
class Process_Transaction_Repository extends Process_Repository {
	/**
	 * Create a process and its initial log atomically.
	 *
	 * @param array<string, mixed>      $process_data Process data.
	 * @param array<string, mixed>|null $log_data     Initial log data.
	 * @return int|false
	 */
	public function create_process_with_initial_log( array $process_data, array $log_data = null ) {
		if ( ! $this->begin_transaction() ) {
			return false;
		}

		try {
			$process_id = $this->insert( $process_data );

			if ( false === $process_id ) {
				$this->rollback_transaction();
				return false;
			}

			if ( ! empty( $log_data ) ) {
				$log_data['process_id'] = $process_id;
				$logged                 = $this->insert_process_step_log( $log_data );

				if ( false === $logged ) {
					$this->rollback_transaction();
					return false;
				}
			}

			if ( ! $this->commit_transaction() ) {
				$this->rollback_transaction();
				return false;
			}

			return $process_id;
		} catch ( \Throwable $throwable ) {
			$this->rollback_transaction();
			return false;
		}
	}

	/**
	 * Update a process and related logs atomically.
	 *
	 * @param int                        $process_id   Process ID.
	 * @param array<string, mixed>       $process_data Process data.
	 * @param array<int, array<string, mixed>> $logs   Log payloads.
	 * @return bool
	 */
	public function update_process_with_logs( $process_id, array $process_data, array $logs = array() ) {
		$process_id = absint( $process_id );

		if ( ! $this->begin_transaction() ) {
			return false;
		}

		try {
			$updated = $this->update( $process_id, $process_data );

			if ( ! $updated ) {
				$this->rollback_transaction();
				return false;
			}

			foreach ( $logs as $log_data ) {
				$log_data['process_id'] = $process_id;
				$logged                 = $this->insert_process_step_log( $log_data );

				if ( false === $logged ) {
					$this->rollback_transaction();
					return false;
				}
			}

			if ( ! $this->commit_transaction() ) {
				$this->rollback_transaction();
				return false;
			}

			return true;
		} catch ( \Throwable $throwable ) {
			$this->rollback_transaction();
			return false;
		}
	}

	/**
	 * Start a database transaction.
	 *
	 * @return void
	 */
	protected function begin_transaction() {
		global $wpdb;

		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	/**
	 * Commit a database transaction.
	 *
	 * @return void
	 */
	protected function commit_transaction() {
		global $wpdb;

		return false !== $wpdb->query( 'COMMIT' );
	}

	/**
	 * Roll back a database transaction.
	 *
	 * @return void
	 */
	protected function rollback_transaction() {
		global $wpdb;

		try {
			return false !== $wpdb->query( 'ROLLBACK' );
		} catch ( \Throwable $throwable ) {
			return false;
		}
	}
}
