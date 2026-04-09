<?php
/**
 * Connector repository.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Connectors;

defined( 'ABSPATH' ) || exit;

/**
 * Persists outbound connectors in a structured WP option.
 */
class Connector_Repository {
	/**
	 * Option key for connectors.
	 */
	const OPTION_CONNECTORS = 'sm_connectors';

	/**
	 * Get all connectors.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public function get_all() {
		$rows = get_option( self::OPTION_CONNECTORS, array() );

		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * Get connector by id.
	 *
	 * @param int $connector_id Connector id.
	 * @return array<string,mixed>|null
	 */
	public function get_by_id( $connector_id ) {
		$connector_id = absint( $connector_id );
		if ( $connector_id <= 0 ) {
			return null;
		}

		foreach ( $this->get_all() as $row ) {
			if ( isset( $row['id'] ) && absint( $row['id'] ) === $connector_id ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Get connectors by event name.
	 *
	 * @param string $event_name Canonical event key.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_by_event( $event_name ) {
		$event_name = (string) $event_name;
		if ( '' === $event_name ) {
			return array();
		}

		$matched = array();
		foreach ( $this->get_all() as $row ) {
			$row_event = isset( $row['event_name'] ) ? (string) $row['event_name'] : '';
			if ( $row_event === $event_name ) {
				$matched[] = $row;
			}
		}

		return $matched;
	}

	/**
	 * Create one connector.
	 *
	 * @param array<string,mixed> $data Sanitized data.
	 * @return int
	 */
	public function create( array $data ) {
		$rows = $this->get_all();
		$now  = current_time( 'mysql' );

		$data['id']         = $this->next_id( $rows );
		$data['created_at'] = $now;
		$data['updated_at'] = $now;
		$rows[]             = $data;

		update_option( self::OPTION_CONNECTORS, array_values( $rows ), false );

		return absint( $data['id'] );
	}

	/**
	 * Update one connector.
	 *
	 * @param int                 $connector_id Connector id.
	 * @param array<string,mixed> $data         Sanitized data.
	 * @return bool
	 */
	public function update( $connector_id, array $data ) {
		$connector_id = absint( $connector_id );
		if ( $connector_id <= 0 ) {
			return false;
		}

		$rows    = $this->get_all();
		$updated = false;

		foreach ( $rows as $index => $row ) {
			if ( ! isset( $row['id'] ) || absint( $row['id'] ) !== $connector_id ) {
				continue;
			}

			$data['id']         = $connector_id;
			$data['created_at'] = isset( $row['created_at'] ) ? (string) $row['created_at'] : current_time( 'mysql' );
			$data['updated_at'] = current_time( 'mysql' );
			$rows[ $index ]     = $data;
			$updated            = true;
			break;
		}

		if ( ! $updated ) {
			return false;
		}

		update_option( self::OPTION_CONNECTORS, array_values( $rows ), false );
		return true;
	}

	/**
	 * Delete one connector.
	 *
	 * @param int $connector_id Connector id.
	 * @return bool
	 */
	public function delete( $connector_id ) {
		$connector_id = absint( $connector_id );
		if ( $connector_id <= 0 ) {
			return false;
		}

		$rows    = $this->get_all();
		$initial = count( $rows );
		$rows    = array_values(
			array_filter(
				$rows,
				static function ( $row ) use ( $connector_id ) {
					return ! isset( $row['id'] ) || absint( $row['id'] ) !== $connector_id;
				}
			)
		);

		if ( count( $rows ) === $initial ) {
			return false;
		}

		update_option( self::OPTION_CONNECTORS, $rows, false );
		return true;
	}

	/**
	 * Toggle connector status.
	 *
	 * @param int    $connector_id Connector id.
	 * @param string $status       New status.
	 * @return bool
	 */
	public function set_status( $connector_id, $status ) {
		$connector = $this->get_by_id( $connector_id );
		if ( ! is_array( $connector ) ) {
			return false;
		}

		$connector['status'] = (string) $status;

		return $this->update( $connector_id, $connector );
	}

	/**
	 * Resolve next connector id.
	 *
	 * @param array<int,array<string,mixed>> $rows Existing rows.
	 * @return int
	 */
	protected function next_id( array $rows ) {
		$max = 0;
		foreach ( $rows as $row ) {
			$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
			if ( $id > $max ) {
				$max = $id;
			}
		}

		return $max + 1;
	}
}

