<?php
/**
 * Notification service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use WP_Error;

defined( 'ABSPATH' ) || exit;

/**
 * Handles notification business rules.
 */
class Notification_Service {
	protected $repository;
	protected $dashboard_service;
	protected $process_service;
	protected $quote_service;
	protected $invoice_service;
	protected $attachment_service;
	protected $access_control_service;
	protected $appointment_service;
	protected $settings_service;
	protected $event_catalog;
	protected $channels;
	protected $business_context_service;

	public function __construct( Notification_Repository $repository = null, Dashboard_Service $dashboard_service = null, Process_Service $process_service = null, Quote_Service $quote_service = null, Invoice_Service $invoice_service = null, Attachment_Service $attachment_service = null, Access_Control_Service $access_control_service = null, Appointment_Service $appointment_service = null, Settings_Service $settings_service = null, Notification_Event_Catalog $event_catalog = null, array $channels = null, Business_Context_Service $business_context_service = null ) {
		$this->repository         = $repository ? $repository : new Notification_Repository();
		$this->dashboard_service  = $dashboard_service ? $dashboard_service : new Dashboard_Service();
		$this->process_service    = $process_service ? $process_service : new Process_Service();
		$this->quote_service      = $quote_service ? $quote_service : new Quote_Service();
		$this->invoice_service    = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->attachment_service = $attachment_service ? $attachment_service : new Attachment_Service();
		$this->access_control_service = $access_control_service ? $access_control_service : new Access_Control_Service();
		$this->appointment_service = $appointment_service ? $appointment_service : new Appointment_Service();
		$this->settings_service    = $settings_service ? $settings_service : new Settings_Service();
		$this->event_catalog       = $event_catalog ? $event_catalog : new Notification_Event_Catalog();
		$this->business_context_service = $business_context_service ? $business_context_service : new Business_Context_Service();
		$this->channels            = is_array( $channels ) ? $channels : array(
			new Email_Notification_Channel( $this->settings_service ),
		);
	}

	public function create_notification( array $data ) {
		$data  = $this->prepare_notification_data( $data );
		$valid = $this->validate_notification_data( $data );

		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		if ( $this->should_skip_client_notification( $data ) ) {
			return 0;
		}

		$inserted = $this->repository->insert( $data );

		if ( false === $inserted ) {
			return new WP_Error( 'sm_notification_create_failed', __( 'No fue posible crear la notificacion.', 'super-mechanic' ) );
		}

		$this->deliver_external_channels(
			array_merge(
				$data,
				array(
					'id' => (int) $inserted,
				)
			)
		);

		return $inserted;
	}

	public function get_notifications( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->get_all( $args );
	}

	public function count_notifications( array $args = array() ) {
		if ( empty( $args['business_id'] ) ) {
			$args['business_id'] = $this->resolve_business_id();
		}

		return $this->repository->count_all( $args );
	}

	public function get_user_notifications( $user_id, array $args = array() ) {
		return $this->repository->get_by_recipient( 'user', $user_id, $args );
	}

	public function get_client_notifications( $client_id, array $args = array() ) {
		return $this->repository->get_by_recipient( 'client', $client_id, $args );
	}

	public function mark_notification_read( $notification_id, $user_id = 0 ) {
		$notification = $this->repository->get_by_id( $notification_id );

		if ( ! $notification ) {
			return new WP_Error( 'sm_notification_not_found', __( 'La notificacion no existe.', 'super-mechanic' ) );
		}

		if ( $user_id && ! $this->user_can_access_notification( $user_id, $notification ) ) {
			return new WP_Error( 'sm_notification_access_denied', __( 'No tienes acceso a esta notificacion.', 'super-mechanic' ) );
		}

		return $this->repository->mark_as_read( $notification_id );
	}

	public function mark_all_read( $recipient_type, $recipient_id ) {
		return $this->repository->mark_all_as_read( $recipient_type, $recipient_id );
	}

	public function delete_notification( $notification_id ) {
		return $this->repository->delete( $notification_id );
	}

	public function delete_all_for_recipient( $recipient_type, $recipient_id ) {
		$notifications = $this->repository->get_by_recipient( $recipient_type, $recipient_id, array( 'per_page' => 500 ) );

		foreach ( $notifications as $notification ) {
			$this->repository->delete( absint( $notification['id'] ) );
		}

		return true;
	}

	public function notify_process_status_changed( array $payload ) {
		$process_id = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;

		if ( ! $process ) {
			return false;
		}

		$old_status = ! empty( $payload['old_status'] ) ? sanitize_key( $payload['old_status'] ) : '';
		$new_status = ! empty( $payload['new_status'] ) ? sanitize_key( $payload['new_status'] ) : sanitize_key( $process['status'] );

		$this->notify_client(
			absint( $process['client_id'] ),
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_status_changed',
				'title'             => __( 'Actualizacion del proceso', 'super-mechanic' ),
				'message'           => sprintf( __( 'El proceso "%s" cambio de %s a %s.', 'super-mechanic' ), $process['title'], $old_status ? $old_status : __( 'sin estado', 'super-mechanic' ), $new_status ),
				'data_json'         => array(
					'old_status' => $old_status,
					'new_status' => $new_status,
				),
				'is_system'         => 1,
			)
		);

		$this->notify_staff_group(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_status_changed',
				'title'             => __( 'Cambio de estado de proceso', 'super-mechanic' ),
				'message'           => sprintf( __( 'El proceso #%d ahora esta en estado %s.', 'super-mechanic' ), $process_id, $new_status ),
				'data_json'         => array(
					'old_status' => $old_status,
					'new_status' => $new_status,
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);

		return true;
	}

	public function notify_process_created( array $payload ) {
		$process_id = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;

		if ( ! $process ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_created',
				'title'             => __( 'Proceso creado', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se creo el proceso "%s".', 'super-mechanic' ), $process['title'] ),
				'data_json'         => array(
					'status'       => $process['status'],
					'process_type' => $process['process_type'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_process_step_changed( array $payload ) {
		$process_id = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;

		if ( ! $process ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_step_changed',
				'title'             => __( 'Paso del proceso actualizado', 'super-mechanic' ),
				'message'           => sprintf( __( 'El proceso "%s" cambio de paso.', 'super-mechanic' ), $process['title'] ),
				'data_json'         => array(
					'from_step_id' => ! empty( $payload['from_step_id'] ) ? absint( $payload['from_step_id'] ) : 0,
					'to_step_id'   => ! empty( $payload['to_step_id'] ) ? absint( $payload['to_step_id'] ) : 0,
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_process_updated( array $payload ) {
		$process_id = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;

		if ( ! $process ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_updated',
				'title'             => __( 'Proceso actualizado', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se actualizo el proceso "%s".', 'super-mechanic' ), $process['title'] ),
				'data_json'         => array(
					'status' => $process['status'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_process_finalized( array $payload ) {
		$process_id = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$process    = $process_id ? $this->process_service->get_process( $process_id ) : null;

		if ( ! $process ) {
			return false;
		}

		$final_status = ! empty( $payload['new_status'] ) ? sanitize_key( $payload['new_status'] ) : sanitize_key( $process['status'] );

		$this->notify_client(
			absint( $process['client_id'] ),
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_finalized',
				'title'             => __( 'Proceso actualizado', 'super-mechanic' ),
				'message'           => sprintf( __( 'El proceso "%s" quedo en estado %s.', 'super-mechanic' ), $process['title'], $final_status ),
				'data_json'         => array(
					'final_status' => $final_status,
				),
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'process',
				'object_id'         => $process_id,
				'process_id'        => $process_id,
				'notification_type' => 'process_finalized',
				'title'             => __( 'Proceso en estado final', 'super-mechanic' ),
				'message'           => sprintf( __( 'El proceso "%s" quedo en estado final %s.', 'super-mechanic' ), $process['title'], $final_status ),
				'data_json'         => array(
					'final_status' => $final_status,
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_quote_created_from_maintenance( array $payload ) {
		$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_created_from_maintenance',
				'title'             => __( 'Cotizacion generada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se genero la cotizacion %s desde mantenimiento.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_quote_sent( array $payload ) {
		$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			return false;
		}

		return $this->notify_client(
			absint( $quote['client_id'] ),
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_sent',
				'title'             => __( 'Cotizacion disponible', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cotizacion %s fue enviada para revision.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
				),
				'is_system'         => 1,
			)
		);
	}

	public function notify_quote_approved( array $payload ) {
		$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_approved',
				'title'             => __( 'Cotizacion aprobada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cotizacion %s fue aprobada por el cliente.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_quote_rejected( array $payload ) {
		$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_rejected',
				'title'             => __( 'Cotizacion rechazada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cotizacion %s fue rechazada por el cliente.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
					'reason'       => ! empty( $payload['reason'] ) ? sanitize_textarea_field( $payload['reason'] ) : '',
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_quote_cancelled( array $payload ) {
		$quote_id = ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0;
		$quote    = $quote_id ? $this->quote_service->get_quote( $quote_id ) : null;

		if ( ! $quote ) {
			return false;
		}

		$this->notify_client(
			absint( $quote['client_id'] ),
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_cancelled',
				'title'             => __( 'Cotizacion cancelada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cotizacion %s fue cancelada.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
				),
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'quote',
				'object_id'         => $quote_id,
				'process_id'        => absint( $quote['process_id'] ),
				'notification_type' => 'quote_cancelled',
				'title'             => __( 'Cotizacion cancelada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cotizacion %s fue cancelada.', 'super-mechanic' ), $quote['quote_number'] ),
				'data_json'         => array(
					'quote_number' => $quote['quote_number'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_invoice_created_from_quote( array $payload ) {
		$invoice_id = ! empty( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;
		$invoice    = $invoice_id ? $this->invoice_service->get_invoice( $invoice_id ) : null;

		if ( ! $invoice ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'invoice',
				'object_id'         => $invoice_id,
				'process_id'        => absint( $invoice['process_id'] ),
				'notification_type' => 'invoice_created_from_quote',
				'title'             => __( 'Factura creada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se creo la factura %s desde una cotizacion aprobada.', 'super-mechanic' ), $invoice['invoice_number'] ),
				'data_json'         => array(
					'invoice_number' => $invoice['invoice_number'],
					'quote_id'       => ! empty( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : absint( $invoice['quote_id'] ),
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_invoice_issued( array $payload ) {
		$invoice_id = ! empty( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;
		$invoice    = $invoice_id ? $this->invoice_service->get_invoice( $invoice_id ) : null;

		if ( ! $invoice ) {
			return false;
		}

		return $this->notify_client(
			absint( $invoice['client_id'] ),
			array(
				'object_type'       => 'invoice',
				'object_id'         => $invoice_id,
				'process_id'        => absint( $invoice['process_id'] ),
				'notification_type' => 'invoice_issued',
				'title'             => __( 'Factura emitida', 'super-mechanic' ),
				'message'           => sprintf( __( 'La factura %s fue emitida.', 'super-mechanic' ), $invoice['invoice_number'] ),
				'data_json'         => array(
					'invoice_number' => $invoice['invoice_number'],
				),
				'is_system'         => 1,
			)
		);
	}

	public function notify_payment_registered( array $payload ) {
		$invoice_id = ! empty( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;
		$invoice    = $invoice_id ? $this->invoice_service->get_invoice( $invoice_id ) : null;

		if ( ! $invoice ) {
			return false;
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'invoice',
				'object_id'         => $invoice_id,
				'process_id'        => absint( $invoice['process_id'] ),
				'notification_type' => 'payment_registered',
				'title'             => __( 'Pago registrado', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se registro un pago en la factura %s.', 'super-mechanic' ), $invoice['invoice_number'] ),
				'data_json'         => array(
					'amount' => ! empty( $payload['amount'] ) ? (float) $payload['amount'] : 0,
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_invoice_paid( array $payload ) {
		$invoice_id = ! empty( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;
		$invoice    = $invoice_id ? $this->invoice_service->get_invoice( $invoice_id ) : null;

		if ( ! $invoice ) {
			return false;
		}

		$this->notify_client(
			absint( $invoice['client_id'] ),
			array(
				'object_type'       => 'invoice',
				'object_id'         => $invoice_id,
				'process_id'        => absint( $invoice['process_id'] ),
				'notification_type' => 'invoice_paid',
				'title'             => __( 'Factura pagada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La factura %s quedo pagada.', 'super-mechanic' ), $invoice['invoice_number'] ),
				'data_json'         => array(
					'invoice_number' => $invoice['invoice_number'],
				),
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'invoice',
				'object_id'         => $invoice_id,
				'process_id'        => absint( $invoice['process_id'] ),
				'notification_type' => 'invoice_paid',
				'title'             => __( 'Factura pagada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La factura %s quedo completamente pagada.', 'super-mechanic' ), $invoice['invoice_number'] ),
				'data_json'         => array(
					'invoice_number' => $invoice['invoice_number'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_document_uploaded( array $payload ) {
		$attachment_id = ! empty( $payload['attachment_id'] ) ? absint( $payload['attachment_id'] ) : 0;
		$attachment    = $attachment_id ? $this->attachment_service->get_attachment( $attachment_id ) : null;

		if ( ! $attachment ) {
			return false;
		}

		if ( ! empty( $attachment['is_client_visible'] ) && empty( $attachment['is_internal'] ) ) {
			$this->notify_client(
				absint( $attachment['client_id'] ),
				array(
					'object_type'       => 'attachment',
					'object_id'         => $attachment_id,
					'process_id'        => absint( $attachment['process_id'] ),
					'notification_type' => 'document_uploaded',
					'title'             => __( 'Nuevo documento disponible', 'super-mechanic' ),
					'message'           => sprintf( __( 'Se agrego el documento "%s" a tu proceso.', 'super-mechanic' ), $attachment['title'] ),
					'data_json'         => array(
						'attachment_type' => $attachment['attachment_type'],
					),
					'is_system'         => 1,
				)
			);
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => 'attachment',
				'object_id'         => $attachment_id,
				'process_id'        => absint( $attachment['process_id'] ),
				'notification_type' => 'document_uploaded',
				'title'             => __( 'Documento cargado', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se cargo el documento "%s".', 'super-mechanic' ), $attachment['title'] ),
				'data_json'         => array(
					'attachment_type' => $attachment['attachment_type'],
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_comment_added( array $payload ) {
		$comment_type      = ! empty( $payload['comment_type'] ) ? sanitize_key( $payload['comment_type'] ) : 'internal_note';
		$is_client_visible = ! empty( $payload['is_client_visible'] );
		$client_id         = ! empty( $payload['client_id'] ) ? absint( $payload['client_id'] ) : 0;
		$process_id        = ! empty( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0;
		$object_type       = ! empty( $payload['object_type'] ) ? sanitize_key( $payload['object_type'] ) : 'process';
		$object_id         = ! empty( $payload['object_id'] ) ? absint( $payload['object_id'] ) : $process_id;
		$content           = ! empty( $payload['content'] ) ? wp_trim_words( wp_strip_all_tags( (string) $payload['content'] ), 18, '...' ) : '';

		if ( $is_client_visible && $client_id ) {
			$this->notify_client(
				$client_id,
				array(
					'object_type'       => $object_type,
					'object_id'         => $object_id,
					'process_id'        => $process_id,
					'notification_type' => 'comment_added',
					'title'             => __( 'Nuevo mensaje en tu proceso', 'super-mechanic' ),
					'message'           => $content,
					'data_json'         => array(
						'comment_type' => $comment_type,
					),
					'is_system'         => 1,
				)
			);
		}

		return $this->notify_staff_group(
			array(
				'object_type'       => $object_type,
				'object_id'         => $object_id,
				'process_id'        => $process_id,
				'notification_type' => 'comment_added',
				'title'             => __( 'Nuevo comentario registrado', 'super-mechanic' ),
				'message'           => $content,
				'data_json'         => array(
					'comment_type' => $comment_type,
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_appointment_created( array $payload ) {
		$appointment = $this->get_appointment_from_payload( $payload );
		if ( ! $appointment ) {
			return false;
		}

		$context = $this->build_appointment_context( $appointment, $payload );

		$this->notify_client(
			absint( $appointment['client_id'] ),
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_created',
				'title'             => __( 'Cita programada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Tu cita fue programada para %1$s %2$s.', 'super-mechanic' ), $context['appointment_date'], $context['appointment_time'] ),
				'data_json'         => $context,
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_created',
				'title'             => __( 'Nueva cita registrada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se registro una cita para el cliente #%d.', 'super-mechanic' ), absint( $appointment['client_id'] ) ),
				'data_json'         => $context,
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_appointment_updated( array $payload ) {
		$appointment = $this->get_appointment_from_payload( $payload );
		if ( ! $appointment ) {
			return false;
		}

		$context = $this->build_appointment_context( $appointment, $payload );

		$this->notify_client(
			absint( $appointment['client_id'] ),
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_updated',
				'title'             => __( 'Cita actualizada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Tu cita fue actualizada para %1$s %2$s.', 'super-mechanic' ), $context['appointment_date'], $context['appointment_time'] ),
				'data_json'         => $context,
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_updated',
				'title'             => __( 'Cita actualizada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Se actualizo la cita #%d.', 'super-mechanic' ), absint( $appointment['id'] ) ),
				'data_json'         => $context,
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_appointment_status_changed( array $payload ) {
		$appointment = $this->get_appointment_from_payload( $payload );
		if ( ! $appointment ) {
			return false;
		}

		$context = $this->build_appointment_context( $appointment, $payload );
		$old     = ! empty( $payload['old_status'] ) ? sanitize_key( $payload['old_status'] ) : '';
		$new     = ! empty( $payload['new_status'] ) ? sanitize_key( $payload['new_status'] ) : sanitize_key( $appointment['appointment_status'] );

		$this->notify_client(
			absint( $appointment['client_id'] ),
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_status_changed',
				'title'             => __( 'Cambio de estado de cita', 'super-mechanic' ),
				'message'           => sprintf( __( 'Tu cita cambio de estado: %1$s → %2$s.', 'super-mechanic' ), $old ? $old : __( 'sin estado', 'super-mechanic' ), $new ),
				'data_json'         => array_merge(
					$context,
					array(
						'old_status' => $old,
						'new_status' => $new,
					)
				),
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_status_changed',
				'title'             => __( 'Estado de cita actualizado', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cita #%1$d cambio de %2$s a %3$s.', 'super-mechanic' ), absint( $appointment['id'] ), $old ? $old : __( 'sin estado', 'super-mechanic' ), $new ),
				'data_json'         => array_merge(
					$context,
					array(
						'old_status' => $old,
						'new_status' => $new,
					)
				),
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_appointment_cancelled( array $payload ) {
		$appointment = $this->get_appointment_from_payload( $payload );
		if ( ! $appointment ) {
			return false;
		}

		$context = $this->build_appointment_context( $appointment, $payload );

		$this->notify_client(
			absint( $appointment['client_id'] ),
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_cancelled',
				'title'             => __( 'Cita cancelada', 'super-mechanic' ),
				'message'           => sprintf( __( 'Tu cita del %1$s %2$s fue cancelada.', 'super-mechanic' ), $context['appointment_date'], $context['appointment_time'] ),
				'data_json'         => $context,
				'is_system'         => 1,
			)
		);

		return $this->notify_staff_group(
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_cancelled',
				'title'             => __( 'Cita cancelada', 'super-mechanic' ),
				'message'           => sprintf( __( 'La cita #%d fue cancelada.', 'super-mechanic' ), absint( $appointment['id'] ) ),
				'data_json'         => $context,
				'is_system'         => 1,
			),
			! empty( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0
		);
	}

	public function notify_appointment_reminder( array $payload ) {
		$appointment = $this->get_appointment_from_payload( $payload );
		if ( ! $appointment ) {
			return false;
		}

		$context = $this->build_appointment_context( $appointment, $payload );

		$this->notify_client(
			absint( $appointment['client_id'] ),
			array(
				'object_type'       => 'appointment',
				'object_id'         => absint( $appointment['id'] ),
				'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
				'notification_type' => 'appointment_reminder',
				'title'             => __( 'Recordatorio de cita', 'super-mechanic' ),
				'message'           => sprintf( __( 'Recordatorio: tienes una cita el %1$s a las %2$s.', 'super-mechanic' ), $context['appointment_date'], $context['appointment_time'] ),
				'data_json'         => $context,
				'is_system'         => 1,
			)
		);

		$assigned_to = ! empty( $appointment['assigned_to'] ) ? absint( $appointment['assigned_to'] ) : 0;
		if ( $assigned_to ) {
			$this->notify_user(
				$assigned_to,
				array(
					'object_type'       => 'appointment',
					'object_id'         => absint( $appointment['id'] ),
					'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
					'notification_type' => 'appointment_reminder',
					'title'             => __( 'Recordatorio de cita asignada', 'super-mechanic' ),
					'message'           => sprintf( __( 'Recordatorio: cita #%1$d para %2$s %3$s.', 'super-mechanic' ), absint( $appointment['id'] ), $context['appointment_date'], $context['appointment_time'] ),
					'data_json'         => $context,
					'is_system'         => 1,
				)
			);
		}

		return true;
	}

	public function validate_notification_data( array $data ) {
		$errors                     = new WP_Error();
		$allowed_recipient_types    = array( 'user', 'client' );
		$allowed_notification_types = $this->event_catalog->get_supported_notification_types();

		if ( ! in_array( $data['recipient_type'], $allowed_recipient_types, true ) ) {
			$errors->add( 'invalid_recipient_type', __( 'El tipo de destinatario no es valido.', 'super-mechanic' ) );
		}

		if ( empty( $data['recipient_id'] ) ) {
			$errors->add( 'invalid_recipient_id', __( 'El destinatario de la notificacion es obligatorio.', 'super-mechanic' ) );
		}

		if ( ! in_array( $data['notification_type'], $allowed_notification_types, true ) ) {
			$errors->add( 'invalid_notification_type', __( 'El tipo de notificacion no es valido.', 'super-mechanic' ) );
		}

		if ( '' === $data['title'] ) {
			$errors->add( 'missing_title', __( 'La notificacion requiere un titulo.', 'super-mechanic' ) );
		}

		$parent_business_id = $this->resolve_structural_business_id( $data );
		if ( $parent_business_id > 0 && absint( $data['business_id'] ) !== $parent_business_id ) {
			$errors->add( 'invalid_business_context', __( 'La notificación debe pertenecer al mismo negocio que su entidad padre.', 'super-mechanic' ) );
		}

		return $errors->has_errors() ? $errors : true;
	}

	protected function prepare_notification_data( array $data ) {
		$data_json = isset( $data['data_json'] ) ? $data['data_json'] : '';

		if ( is_array( $data_json ) ) {
			$data_json = wp_json_encode( $data_json );
		}

		return array(
			'business_id'       => isset( $data['business_id'] ) && absint( $data['business_id'] ) > 0
				? absint( $data['business_id'] )
				: $this->resolve_structural_business_id( $data ),
			'recipient_type'    => isset( $data['recipient_type'] ) ? sanitize_key( $data['recipient_type'] ) : '',
			'recipient_id'      => isset( $data['recipient_id'] ) ? absint( $data['recipient_id'] ) : 0,
			'object_type'       => isset( $data['object_type'] ) ? sanitize_key( $data['object_type'] ) : '',
			'object_id'         => isset( $data['object_id'] ) ? absint( $data['object_id'] ) : 0,
			'process_id'        => isset( $data['process_id'] ) ? absint( $data['process_id'] ) : 0,
			'notification_type' => isset( $data['notification_type'] ) ? sanitize_key( $data['notification_type'] ) : '',
			'title'             => isset( $data['title'] ) ? sanitize_text_field( $data['title'] ) : '',
			'message'           => isset( $data['message'] ) ? sanitize_textarea_field( $data['message'] ) : '',
			'data_json'         => is_string( $data_json ) ? $this->normalize_data_json( $data_json ) : '',
			'is_read'           => ! empty( $data['is_read'] ) ? 1 : 0,
			'read_at'           => ! empty( $data['read_at'] ) ? sanitize_text_field( $data['read_at'] ) : null,
			'is_system'         => ! empty( $data['is_system'] ) ? 1 : 0,
		);
	}

	/**
	 * Resolve business ID from structural parent, with fallback.
	 *
	 * @param array<string,mixed> $data Notification payload.
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

		if ( 'appointment' === $object_type && $object_id > 0 ) {
			$appointment = $this->appointment_service->get_appointment( $object_id );
			if ( is_array( $appointment ) && ! empty( $appointment['business_id'] ) ) {
				return max( 1, absint( $appointment['business_id'] ) );
			}
		}

		if ( 'client' === ( isset( $data['recipient_type'] ) ? sanitize_key( (string) $data['recipient_type'] ) : '' ) ) {
			$client_service = new \Super_Mechanic\Clients\Client_Service();
			$client         = ! empty( $data['recipient_id'] ) ? $client_service->get_client( absint( $data['recipient_id'] ) ) : null;
			if ( is_array( $client ) && ! empty( $client['business_id'] ) ) {
				return max( 1, absint( $client['business_id'] ) );
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

	protected function normalize_data_json( $data_json ) {
		$decoded = json_decode( $data_json, true );

		if ( is_array( $decoded ) ) {
			return wp_json_encode( $decoded );
		}

		return sanitize_textarea_field( $data_json );
	}

	/**
	 * Skip client notifications when toggle is disabled.
	 *
	 * @param array<string, mixed> $data Notification payload.
	 * @return bool
	 */
	protected function should_skip_client_notification( array $data ) {
		if ( 'client' !== $data['recipient_type'] ) {
			return false;
		}

		return ! empty( $this->settings_service->get_setting( 'notifications', 'enable_client_notifications', true ) ) ? false : true;
	}

	/**
	 * Deliver notification through enabled external channels.
	 *
	 * @param array<string, mixed> $notification Notification payload.
	 * @return void
	 */
	protected function deliver_external_channels( array $notification ) {
		$type       = isset( $notification['notification_type'] ) ? sanitize_key( (string) $notification['notification_type'] ) : '';
		$event_meta = $this->event_catalog->get_event_definition( $type );

		if ( empty( $event_meta ) || empty( $event_meta['email_enabled'] ) ) {
			return;
		}

		foreach ( $this->channels as $channel ) {
			if ( ! $channel instanceof Notification_Channel_Interface ) {
				continue;
			}

			if ( ! $channel->is_enabled() ) {
				continue;
			}

			$channel->send( $notification, $event_meta );
		}
	}

	/**
	 * Resolve appointment data from event payload.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return array<string, mixed>|null
	 */
	protected function get_appointment_from_payload( array $payload ) {
		if ( ! empty( $payload['appointment'] ) && is_array( $payload['appointment'] ) ) {
			return $payload['appointment'];
		}

		$appointment_id = ! empty( $payload['appointment_id'] ) ? absint( $payload['appointment_id'] ) : 0;
		if ( ! $appointment_id ) {
			return null;
		}

		$appointment = $this->appointment_service->get_appointment( $appointment_id );

		return is_array( $appointment ) ? $appointment : null;
	}

	/**
	 * Build normalized appointment context for notification data_json.
	 *
	 * @param array<string, mixed> $appointment Appointment data.
	 * @param array<string, mixed> $payload     Event payload.
	 * @return array<string, mixed>
	 */
	protected function build_appointment_context( array $appointment, array $payload ) {
		$timestamp = ! empty( $appointment['start_at'] ) ? strtotime( (string) $appointment['start_at'] . ' UTC' ) : false;
		$date      = '';
		$time      = '';

		if ( $timestamp ) {
			if ( function_exists( 'wp_timezone' ) ) {
				$date = wp_date( 'Y-m-d', $timestamp, wp_timezone() );
				$time = wp_date( 'H:i', $timestamp, wp_timezone() );
			} else {
				$date = gmdate( 'Y-m-d', $timestamp );
				$time = gmdate( 'H:i', $timestamp );
			}
		}

		return array(
			'appointment_id'    => absint( $appointment['id'] ),
			'client_id'         => absint( $appointment['client_id'] ),
			'vehicle_id'        => absint( $appointment['vehicle_id'] ),
			'process_id'        => ! empty( $appointment['process_id'] ) ? absint( $appointment['process_id'] ) : 0,
			'assigned_to'       => absint( $appointment['assigned_to'] ),
			'appointment_status' => isset( $appointment['appointment_status'] ) ? sanitize_key( (string) $appointment['appointment_status'] ) : '',
			'appointment_date'  => '' !== $date ? $date : ( ! empty( $appointment['appointment_date'] ) ? sanitize_text_field( (string) $appointment['appointment_date'] ) : '' ),
			'appointment_time'  => $time,
			'source'            => ! empty( $payload['source'] ) ? sanitize_key( (string) $payload['source'] ) : 'runtime',
		);
	}

	protected function notify_client( $client_id, array $data ) {
		if ( ! $client_id ) {
			return false;
		}

		$data['recipient_type'] = 'client';
		$data['recipient_id']   = $client_id;

		return $this->create_notification( $data );
	}

	/**
	 * Notify a specific user recipient.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string, mixed> $data   Notification payload.
	 * @return int|false|WP_Error
	 */
	protected function notify_user( $user_id, array $data ) {
		$user_id = absint( $user_id );
		if ( ! $user_id ) {
			return false;
		}

		$data['recipient_type'] = 'user';
		$data['recipient_id']   = $user_id;

		return $this->create_notification( $data );
	}

	protected function notify_staff_group( array $data, $exclude_user_id = 0 ) {
		$recipient_ids = $this->get_staff_recipient_user_ids( $exclude_user_id );

		foreach ( $recipient_ids as $user_id ) {
			$this->create_notification(
				array_merge(
					$data,
					array(
						'recipient_type' => 'user',
						'recipient_id'   => $user_id,
					)
				)
			);
		}

		return true;
	}

	protected function get_staff_recipient_user_ids( $exclude_user_id = 0 ) {
		$users = get_users(
			array(
				'fields' => array( 'ID' ),
			)
		);
		$ids   = array();

		foreach ( $users as $user ) {
			$user_id = isset( $user->ID ) ? absint( $user->ID ) : 0;

			if ( ! $user_id || $user_id === absint( $exclude_user_id ) ) {
				continue;
			}

			if ( user_can( $user_id, 'sm_manage_processes' ) || user_can( $user_id, 'sm_manage_plugin' ) ) {
				$ids[] = $user_id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	protected function user_can_access_notification( $user_id, array $notification ) {
		$user_id = absint( $user_id );

		if ( 'user' === $notification['recipient_type'] ) {
			if ( ! $this->access_control_service->user_has_full_access( $user_id ) ) {
				return false;
			}

			return absint( $notification['recipient_id'] ) === $user_id;
		}

		if ( 'client' !== $notification['recipient_type'] ) {
			return false;
		}

		if ( absint( $notification['recipient_id'] ) !== $this->access_control_service->get_client_id_by_user_id( $user_id ) ) {
			return false;
		}

		if ( ! empty( $notification['process_id'] ) ) {
			return $this->access_control_service->user_can_access_process( $user_id, absint( $notification['process_id'] ) );
		}

		if ( 'quote' === $notification['object_type'] && ! empty( $notification['object_id'] ) ) {
			return $this->access_control_service->user_can_access_quote( $user_id, absint( $notification['object_id'] ) );
		}

		if ( 'invoice' === $notification['object_type'] && ! empty( $notification['object_id'] ) ) {
			return $this->access_control_service->user_can_access_invoice( $user_id, absint( $notification['object_id'] ) );
		}

		if ( 'attachment' === $notification['object_type'] && ! empty( $notification['object_id'] ) ) {
			return $this->access_control_service->user_can_access_attachment( $user_id, absint( $notification['object_id'] ), true );
		}

		return true;
	}
}
