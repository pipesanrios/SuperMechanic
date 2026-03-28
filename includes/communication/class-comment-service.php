<?php
/**
 * Comment service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles comment business rules.
 */
class Comment_Service {
	protected $repository;
	protected $dashboard_service;
	protected $process_service;
	protected $quote_service;
	protected $invoice_service;
	protected $attachment_service;
	protected $event_dispatcher;
	protected $access_control_service;
	protected $business_context_service;

	public function __construct( Comment_Repository $repository = null, Dashboard_Service $dashboard_service = null, Process_Service $process_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Attachment_Service $attachment_service = null, Event_Dispatcher $event_dispatcher = null, Access_Control_Service $access_control_service = null, Business_Context_Service $business_context_service = null ) {
		$this->repository         = $repository ? $repository : new Comment_Repository();
		$this->dashboard_service  = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->process_service    = $process_service ? $process_service : new Process_Service();
		$this->quote_service      = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service    = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->attachment_service = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->event_dispatcher   = $event_dispatcher ? $event_dispatcher : Event_Dispatcher::get_instance();
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
	}

	public function create_comment( array $data ) {
		$data  = $this->prepare_comment_data( $data );
		$valid = $this->validate_comment_data( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_comment_create_failed', __( 'No fue posible registrar el comentario.', 'super-mechanic' ) );
		}

		$comment = $this->get_comment( $inserted );
		if ( $comment ) {
			$this->event_dispatcher->dispatch(
				'comment_added',
				array(
					'comment_id'        => $inserted,
					'object_type'       => $comment['object_type'],
					'object_id'         => absint( $comment['object_id'] ),
					'process_id'        => absint( $comment['process_id'] ),
					'client_id'         => absint( $comment['client_id'] ),
					'comment_type'      => $comment['comment_type'],
					'content'           => $comment['content'],
					'is_client_visible' => ! empty( $comment['is_client_visible'] ),
					'triggered_by'      => ! empty( $comment['author_user_id'] ) ? absint( $comment['author_user_id'] ) : get_current_user_id(),
				)
			);
		}

		return $inserted;
	}

	public function update_comment( $comment_id, array $data ) {
		$current = $this->repository->get_by_id( $comment_id );

		if ( ! $current ) {
			return new WP_Error( 'sm_comment_not_found', __( 'El comentario no existe.', 'super-mechanic' ) );
		}

		$data  = $this->prepare_comment_data( array_merge( $current, $data ) );
		$valid = $this->validate_comment_data( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( ! $this->repository->update( $comment_id, $data ) ) {
			return new WP_Error( 'sm_comment_update_failed', __( 'No fue posible actualizar el comentario.', 'super-mechanic' ) );
		}

		return true;
	}

	public function delete_comment( $comment_id ) {
		$comment = $this->repository->get_by_id( $comment_id );

		if ( ! $comment ) {
			return new WP_Error( 'sm_comment_not_found', __( 'El comentario no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->repository->delete( $comment_id ) ) {
			return new WP_Error( 'sm_comment_delete_failed', __( 'No fue posible archivar el comentario.', 'super-mechanic' ) );
		}

		return true;
	}

	public function get_comment( $comment_id ) {
		return $this->repository->get_by_id( $comment_id );
	}

	public function get_comments( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	public function count_comments( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->count_all( $args );
	}

	public function get_process_comments( $process_id, array $args = array() ) {
		return $this->repository->get_by_process_id(
			$process_id,
			array_merge(
				array(
					'status'   => 'published',
					'per_page' => 200,
				),
				$args
			)
		);
	}

	public function get_client_visible_process_comments( $process_id ) {
		return $this->repository->get_by_process_id(
			$process_id,
			array(
				'status'            => 'published',
				'is_internal'       => 0,
				'is_client_visible' => 1,
				'per_page'          => 200,
				'orderby'           => 'created_at',
				'order'             => 'DESC',
			)
		);
	}

	public function user_can_access_comment( $user_id, $comment_id ) {
		$comment = $this->get_comment( $comment_id );

		if ( ! $comment ) {
			return false;
		}

		if ( $this->access_control_service->user_has_full_access( $user_id ) ) {
			return true;
		}

		if ( ! empty( $comment['is_internal'] ) || empty( $comment['is_client_visible'] ) ) {
			return false;
		}

		if ( ! empty( $comment['process_id'] ) ) {
			return $this->access_control_service->user_can_access_process( $user_id, absint( $comment['process_id'] ) );
		}

		if ( 'quote' === $comment['object_type'] ) {
			return $this->access_control_service->user_can_access_quote( $user_id, absint( $comment['object_id'] ) );
		}

		if ( 'invoice' === $comment['object_type'] ) {
			return $this->access_control_service->user_can_access_invoice( $user_id, absint( $comment['object_id'] ) );
		}

		if ( 'attachment' === $comment['object_type'] ) {
			return $this->access_control_service->user_can_access_attachment( $user_id, absint( $comment['object_id'] ), true );
		}

		return false;
	}

	public function validate_comment_data( array $data ) {
		$errors                = new WP_Error();
		$allowed_object_types  = array( 'process', 'quote', 'invoice', 'attachment', 'maintenance', 'paperwork' );
		$allowed_comment_types = array( 'internal_note', 'client_message', 'staff_reply', 'system_note' );
		$allowed_statuses      = array( 'published', 'hidden', 'archived' );

		if ( ! in_array( $data['object_type'], $allowed_object_types, true ) ) {
			$errors->add( 'invalid_object_type', __( 'El tipo de objeto del comentario no es valido.', 'super-mechanic' ) );
		}

		if ( '' === $data['content'] ) {
			$errors->add( 'missing_content', __( 'El contenido del comentario es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['comment_type'], $allowed_comment_types, true ) ) {
			$errors->add( 'invalid_comment_type', __( 'El tipo de comentario no es valido.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['status'], $allowed_statuses, true ) ) {
			$errors->add( 'invalid_status', __( 'El estado del comentario no es valido.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['is_internal'] ) && ! empty( $data['is_client_visible'] ) ) {
			$errors->add( 'invalid_visibility', __( 'Un comentario interno no puede ser visible para el cliente.', 'super-mechanic' ) );
		}

		if ( empty( $data['process_id'] ) && empty( $data['object_id'] ) ) {
			$errors->add( 'missing_target', __( 'El comentario debe estar vinculado a un objeto o proceso.', 'super-mechanic' ) );
		}

		if ( ! empty( $data['process_id'] ) && ! $this->process_service->get_process( $data['process_id'] ) ) {
			$errors->add( 'invalid_process', __( 'El proceso asociado al comentario no existe.', 'super-mechanic' ) );
		}

		if ( ! $this->is_valid_target( $data ) ) {
			$errors->add( 'invalid_target', __( 'El objeto asociado al comentario no existe.', 'super-mechanic' ) );
		}

		$parent_business_id = $this->resolve_structural_business_id( $data );
		if ( $parent_business_id > 0 && absint( $data['business_id'] ) !== $parent_business_id ) {
			$errors->add( 'invalid_business_context', __( 'El comentario debe pertenecer al mismo negocio que su entidad padre.', 'super-mechanic' ) );
		}

		if ( 'client_message' === $data['comment_type'] && empty( $data['author_client_id'] ) ) {
			$errors->add( 'invalid_client_author', __( 'Los mensajes del cliente requieren un cliente autor valido.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	protected function prepare_comment_data( array $data ) {
		$process_id = isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0;

		if ( ! $process_id && 'process' === ( isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : '' ) ) {
			$process_id = isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0;
		}

		$client_id = isset( $data['client_id'] ) ? absint( $data['client_id'] ) : 0;
		if ( ! $client_id && $process_id ) {
			$process   = $this->process_service->get_process( $process_id );
			$client_id = ! empty( $process['client_id'] ) ? absint( $process['client_id'] ) : 0;
		}

		$comment_type = isset( $data['comment_type'] ) ? sanitize_key( $data['comment_type'] ) : 'internal_note';
		$is_internal  = isset( $data['is_internal'] ) ? (int) ! empty( $data['is_internal'] ) : ( 'internal_note' === $comment_type ? 1 : 0 );

		return array(
			'business_id'       => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_structural_business_id(
					array(
						'object_type' => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : 'process',
						'object_id'   => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : $process_id,
						'process_id'  => $process_id,
					)
				),
			'object_type'       => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : 'process',
			'object_id'         => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : $process_id,
			'process_id'        => $process_id,
			'client_id'         => $client_id,
			'vehicle_id'        => isset( $data['vehicle_id'] ) ? absint( $data['vehicle_id'] ) : 0,
			'parent_id'         => isset( $data['parent_id'] ) ? absint( $data['parent_id'] ) : 0,
			'author_user_id'    => isset( $data['author_user_id'] ) ? absint( $data['author_user_id'] ) : get_current_user_id(),
			'author_client_id'  => isset( $data['author_client_id'] ) ? absint( $data['author_client_id'] ) : 0,
			'comment_type'      => $comment_type,
			'content'           => isset( $data['content'] ) ? sanitize_textarea_field( $data['content'] ) : '',
			'is_internal'       => $is_internal,
			'is_client_visible' => ! empty( $data['is_client_visible'] ) ? 1 : 0,
			'status'            => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'published',
		);
	}

	protected function is_valid_target( array $data ) {
		$object_id = absint( $data['object_id'] );

		if ( ! $object_id ) {
			return ! empty( $data['process_id'] );
		}

		switch ( $data['object_type'] ) {
			case 'process':
			case 'maintenance':
			case 'paperwork':
				return (bool) $this->process_service->get_process( $data['process_id'] ? $data['process_id'] : $object_id );
			case 'quote':
				return (bool) $this->quote_service->get_quote( $object_id );
			case 'invoice':
				return (bool) $this->invoice_service->get_invoice( $object_id );
			case 'attachment':
				return (bool) $this->attachment_service->get_attachment( $object_id );
		}

		return false;
	}

	/**
	 * Resolve business ID from structural parent, with context fallback.
	 *
	 * @param array<string,mixed> $data Comment payload.
	 * @return int
	 */
	protected function resolve_structural_business_id( array $data ) {
		$process_id = ! empty( $data['process_id'] ) ? absint( $data['process_id'] ) : 0;
		$object_id  = ! empty( $data['object_id'] ) ? absint( $data['object_id'] ) : 0;
		$object_type = ! empty( $data['object_type'] ) ? sanitize_key( (string) $data['object_type'] ) : '';

		if ( $process_id > 0 ) {
			$process = $this->process_service->get_process( $process_id );
			if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
				return max( 1, absint( $process['business_id'] ) );
			}
		}

		if ( 'process' === $object_type && $object_id > 0 ) {
			$process = $this->process_service->get_process( $object_id );
			if ( is_array( $process ) && ! empty( $process['business_id'] ) ) {
				return max( 1, absint( $process['business_id'] ) );
			}
		}

		if ( 'quote' === $object_type && $object_id > 0 ) {
			$quote = $this->quote_service->get_quote( $object_id );
			if ( is_array( $quote ) && ! empty( $quote['business_id'] ) ) {
				return max( 1, absint( $quote['business_id'] ) );
			}
		}

		if ( 'invoice' === $object_type && $object_id > 0 ) {
			$invoice = $this->invoice_service->get_invoice( $object_id );
			if ( is_array( $invoice ) && ! empty( $invoice['business_id'] ) ) {
				return max( 1, absint( $invoice['business_id'] ) );
			}
		}

		if ( 'attachment' === $object_type && $object_id > 0 ) {
			$attachment = $this->attachment_service->get_attachment( $object_id );
			if ( is_array( $attachment ) && ! empty( $attachment['business_id'] ) ) {
				return max( 1, absint( $attachment['business_id'] ) );
			}
		}

		return $this->resolve_business_id();
	}

	/**
	 * Resolve active business ID.
	 *
	 * @return int
	 */
	protected function resolve_business_id() {
		return absint( $this->business_context_service->resolve_business_id() );
	}
}
