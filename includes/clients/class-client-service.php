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
	 * Allowed CRM statuses.
	 *
	 * @var array<int, string>
	 */
	const CRM_STATUSES = array( 'lead', 'prospect', 'active', 'inactive' );

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
		$this->business_context_service = $business_context_service;
	}

	/**
	 * Create a client.
	 *
	 * @param array<string, mixed> $data Client data.
	 * @return int|WP_Error
	 */
	public function create_client( array $data ) {
		$client_data = $this->normalize_client_data( $data );
		$crm_data    = $this->normalize_crm_data( $data );
		$valid       = $this->validate_client_data( $client_data, false );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$crm_valid = $this->validate_crm_data( $crm_data );

		if ( is_wp_error( $crm_valid ) ) {
			return $crm_valid;
		}

		$inserted = $this->repository->insert( $client_data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_client_insert_failed', __( 'No fue posible crear el cliente.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->upsert_crm_meta( $inserted, $crm_data ) ) {
			return new WP_Error( 'sm_client_crm_insert_failed', __( 'Client created, but CRM data could not be saved.', 'super-mechanic' ) );
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

		$client_data = $this->normalize_client_data( $data );
		$crm_data    = $this->normalize_crm_data( $data );
		$valid       = $this->validate_client_data( $client_data, true, $id );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$crm_valid = $this->validate_crm_data( $crm_data );

		if ( is_wp_error( $crm_valid ) ) {
			return $crm_valid;
		}

		$updated = $this->repository->update( $id, $client_data );

		if ( ! $updated ) {
			return new WP_Error( 'sm_client_update_failed', __( 'No fue posible actualizar el cliente.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->upsert_crm_meta( $id, $crm_data ) ) {
			return new WP_Error( 'sm_client_crm_update_failed', __( 'Client updated, but CRM data could not be saved.', 'super-mechanic' ) );
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

		$this->repository->delete_crm_meta( $id );

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
		$args = $this->normalize_list_business_scope( $args );

		return $this->repository->get_all( $args );
	}

	/**
	 * Count clients.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return int
	 */
	public function count_clients( array $args = array() ) {
		$args = $this->normalize_list_business_scope( $args );

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
	 * Validate CRM data.
	 *
	 * @param array<string, mixed> $data CRM data.
	 * @return true|WP_Error
	 */
	public function validate_crm_data( array $data ) {
		$errors = new WP_Error();

		if ( empty( $data['crm_status'] ) || ! in_array( $data['crm_status'], self::CRM_STATUSES, true ) ) {
			$errors->add( 'invalid_crm_status', __( 'CRM status is not valid.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['last_contact_at'] ) && ! $this->is_valid_datetime( $data['last_contact_at'] ) ) {
			$errors->add( 'invalid_last_contact_at', __( 'Last contact date is not valid.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['next_follow_up_at'] ) && ! $this->is_valid_datetime( $data['next_follow_up_at'] ) ) {
			$errors->add( 'invalid_next_follow_up_at', __( 'Next follow-up date is not valid.', 'super-mechanic' ) );
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
		$candidate_business_id = isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0;

		return array(
			'business_id' => $candidate_business_id > 0 ? $this->normalize_business_id( $candidate_business_id ) : $this->resolve_business_id(),
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
	 * Normalize CRM input.
	 *
	 * @param array<string, mixed> $data Raw CRM data.
	 * @return array<string, mixed>
	 */
	protected function normalize_crm_data( array $data ) {
		$crm_status = isset( $data['crm_status'] ) ? sanitize_key( (string) $data['crm_status'] ) : 'lead';

		if ( ! in_array( $crm_status, self::CRM_STATUSES, true ) ) {
			$crm_status = 'lead';
		}

		return array(
			'crm_status'        => $crm_status,
			'assigned_user_id'  => isset( $data['assigned_user_id'] ) ? absint( $data['assigned_user_id'] ) : 0,
			'last_contact_at'   => $this->normalize_datetime_value( isset( $data['last_contact_at'] ) ? (string) $data['last_contact_at'] : '' ),
			'next_follow_up_at' => $this->normalize_datetime_value( isset( $data['next_follow_up_at'] ) ? (string) $data['next_follow_up_at'] : '' ),
			'commercial_notes'  => isset( $data['commercial_notes'] ) ? sanitize_textarea_field( $data['commercial_notes'] ) : '',
		);
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->get_business_context_service()->resolve_business_id() );
	}

	/**
	 * Normalize explicit business filter by user tenancy scope.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<string, mixed>
	 */
	protected function normalize_list_business_scope( array $args ) {
		$candidate_business_id = isset( $args['business_id'] ) ? absint( $args['business_id'] ) : 0;
		$args['business_id']   = $candidate_business_id > 0 ? $this->normalize_business_id( $candidate_business_id ) : $this->resolve_business_id();

		return $args;
	}

	/**
	 * Normalize business ID against allowed businesses for current user.
	 *
	 * @param int $business_id Candidate business ID.
	 * @return int
	 */
	protected function normalize_business_id( $business_id ) {
		return absint( $this->get_business_context_service()->normalize_business_id( $business_id ) );
	}

	/**
	 * Lazily resolve business context service to avoid bootstrap cascades.
	 *
	 * @return Business_Context_Service
	 */
	protected function get_business_context_service() {
		if ( null === $this->business_context_service ) {
			$this->business_context_service = new Business_Context_Service();
		}

		return $this->business_context_service;
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

	/**
	 * Normalize datetime values coming from forms.
	 *
	 * @param string $value Datetime value.
	 * @return string
	 */
	protected function normalize_datetime_value( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return '';
		}

		$value = str_replace( 'T', ' ', $value );

		if ( 16 === strlen( $value ) ) {
			$value .= ':00';
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Check datetime format.
	 *
	 * @param string $datetime Datetime value.
	 * @return bool
	 */
	protected function is_valid_datetime( $datetime ) {
		$datetime = trim( (string) $datetime );

		if ( '' === $datetime ) {
			return true;
		}

		$parsed = \DateTime::createFromFormat( 'Y-m-d H:i:s', $datetime );

		return $parsed && $parsed->format( 'Y-m-d H:i:s' ) === $datetime;
	}
}
