<?php
/**
 * Client service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Clients;

use Super_Mechanic\Helpers\Business_Context_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles client business rules.
 */
class Client_Service {
	/**
	 * Client repository.
	 *
	 * @var Client_Repository
	 */
	protected $repository;
	/**
	 * Business context service.
	 *
	 * @var Business_Context_Service
	 */
	protected $business_context_service;

	/**
	 * Constructor.
	 *
	 * @param Client_Repository|null $repository Repository instance.
	 */
	public function __construct( Client_Repository $repository = null, Business_Context_Service $business_context_service = null ) {
		$this->repository               = $repository ? $repository : new Client_Repository();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	/**
	 * Create a client.
	 *
	 * @param array<string, mixed> $data Client data.
	 * @return int|WP_Error
	 */
	public function create_client( array $data ) {
		$data = $this->normalize_client_data( $data );
		$valid = $this->validate_client_data( $data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_client_insert_failed', __( 'No fue posible crear el cliente.', 'super-mechanic' ) );
		}

		return $inserted;
	}

	/**
	 * Update a client.
	 *
	 * @param int                 $id   Client ID.
	 * @param array<string, mixed> $data Client data.
	 * @return bool|WP_Error
	 */
	public function update_client( $id, array $data ) {
		$id = absint( $id );
		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_client_not_found', __( 'El cliente no existe.', 'super-mechanic' ) );
		}

		$data = $this->normalize_client_data( $data );
		$valid = $this->validate_client_data( $data, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$updated = $this->repository->update( $id, $data );

		if ( ! $updated ) {
			return new WP_Error( 'sm_client_update_failed', __( 'No fue posible actualizar el cliente.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Delete a client.
	 *
	 * @param int $id Client ID.
	 * @return bool|WP_Error
	 */
	public function delete_client( $id ) {
		$id = absint( $id );

		if ( ! $id || ! $this->repository->get_by_id( $id ) ) {
			return new WP_Error( 'sm_client_not_found', __( 'El cliente no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $id ) ) {
			return new WP_Error( 'sm_client_delete_failed', __( 'No fue posible eliminar el cliente.', 'super-mechanic' ) );
		}

		return true;
	}

	/**
	 * Get a client.
	 *
	 * @param int $id Client ID.
	 * @return array<string, mixed>|null
	 */
	public function get_client( $id ) {
		return $this->repository->get_by_id( $id );
	}

	/**
	 * Get clients.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, array<string, mixed>>
	 */
	public function get_clients( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	/**
	 * Count clients.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_clients( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->count_all( $args );
	}

	/**
	 * Validate client data.
	 *
	 * @param array<string, mixed> $data      Client data.
	 * @param bool                $is_update Whether this is an update.
	 * @param int                 $client_id Client ID.
	 * @return true|WP_Error
	 */
	public function validate_client_data( array $data, $is_update = false, $client_id = 0 ) {
		$errors = new WP_Error();

		if ( empty( $data['first_name'] ) ) {
			$errors->add( 'first_name_required', __( 'El nombre es obligatorio.', 'super-mechanic' ) );
		}

		if ( empty( $data['email'] ) ) {
			$errors->add( 'email_required', __( 'El correo electrónico es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['email'] ) && ! is_email( $data['email'] ) ) {
			$errors->add( 'invalid_email', __( 'El correo electrónico no es válido.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['email'] ) && $this->is_duplicate_email( $data['email'], $client_id ) ) {
			$errors->add( 'duplicate_email', __( 'Ya existe un cliente con este correo electrónico.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['document_id'] ) && $this->is_duplicate_document_id( $data['document_id'], $client_id ) ) {
			$errors->add( 'duplicate_document_id', __( 'Ya existe un cliente con este documento.', 'super-mechanic' ) );
		}

		if ( empty( $data['phone'] ) ) {
			$errors->add( 'phone_required', __( 'El teléfono es obligatorio.', 'super-mechanic' ) );
		}

		if ( empty( $data['document_id'] ) ) {
			$errors->add( 'document_id_required', __( 'El documento es obligatorio.', 'super-mechanic' ) );
		}

		if ( $is_update && $client_id <= 0 ) {
			$errors->add( 'invalid_client_id', __( 'El identificador del cliente no es válido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	/**
	 * Normalize client input.
	 *
	 * @param array<string, mixed> $data Raw client data.
	 * @return array<string, mixed>
	 */
	protected function normalize_client_data( array $data ) {
		return array(
			'business_id' => isset( $data['business_id'] ) ? max( 1, absint( $data['business_id'] ) ) : $this->resolve_business_id(),
			'first_name'  => isset( $data['first_name'] ) ? sanitize_text_field( $data['first_name'] ) : '',
			'last_name'   => isset( $data['last_name'] ) ? sanitize_text_field( $data['last_name'] ) : '',
			'email'       => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'phone'       => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'document_id' => isset( $data['document_id'] ) ? sanitize_text_field( $data['document_id'] ) : '',
			'notes'       => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'status'      => 'active',
		);
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}

	/**
	 * Check duplicate email.
	 *
	 * @param string $email     Email.
	 * @param int    $client_id Client ID.
	 * @return bool
	 */
	protected function is_duplicate_email( $email, $client_id ) {
		$matches = $this->repository->get_all(
			array(
				'exact_email' => $email,
				'exclude_id'  => absint( $client_id ),
				'per_page'    => 1,
			)
		);

		return ! empty( $matches );
	}

	/**
	 * Check duplicate document ID.
	 *
	 * @param string $document_id Document ID.
	 * @param int    $client_id   Client ID.
	 * @return bool
	 */
	protected function is_duplicate_document_id( $document_id, $client_id ) {
		$matches = $this->repository->get_all(
			array(
				'exact_document_id' => $document_id,
				'exclude_id'        => absint( $client_id ),
				'per_page'          => 1,
			)
		);

		return ! empty( $matches );
	}
}
