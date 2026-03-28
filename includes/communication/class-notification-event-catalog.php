<?php
/**
 * Notification event catalog.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

defined( 'ABSPATH' ) || exit;

/**
 * Central catalog for notification events/triggers and base templates.
 */
class Notification_Event_Catalog {
	/**
	 * Get all event definitions.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_event_definitions() {
		return array(
			'process_created' => array(
				'label'         => __( 'Process created', 'super-mechanic' ),
				'default_title' => __( 'Proceso creado', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'process_step_changed' => array(
				'label'         => __( 'Process step changed', 'super-mechanic' ),
				'default_title' => __( 'Paso del proceso actualizado', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'process_status_changed' => array(
				'label'         => __( 'Process status changed', 'super-mechanic' ),
				'default_title' => __( 'Actualizacion del proceso', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'process_finalized' => array(
				'label'         => __( 'Process finalized', 'super-mechanic' ),
				'default_title' => __( 'Proceso finalizado', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'process_updated' => array(
				'label'         => __( 'Process updated', 'super-mechanic' ),
				'default_title' => __( 'Proceso actualizado', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'quote_created_from_maintenance' => array(
				'label'         => __( 'Quote created from maintenance', 'super-mechanic' ),
				'default_title' => __( 'Cotizacion generada', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'quote_sent' => array(
				'label'         => __( 'Quote sent', 'super-mechanic' ),
				'default_title' => __( 'Cotizacion disponible', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'quote_approved' => array(
				'label'         => __( 'Quote approved', 'super-mechanic' ),
				'default_title' => __( 'Cotizacion aprobada', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'quote_rejected' => array(
				'label'         => __( 'Quote rejected', 'super-mechanic' ),
				'default_title' => __( 'Cotizacion rechazada', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'quote_cancelled' => array(
				'label'         => __( 'Quote cancelled', 'super-mechanic' ),
				'default_title' => __( 'Cotizacion cancelada', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'invoice_created_from_quote' => array(
				'label'         => __( 'Invoice created from quote', 'super-mechanic' ),
				'default_title' => __( 'Factura creada', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'invoice_issued' => array(
				'label'         => __( 'Invoice issued', 'super-mechanic' ),
				'default_title' => __( 'Factura emitida', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'payment_registered' => array(
				'label'         => __( 'Payment registered', 'super-mechanic' ),
				'default_title' => __( 'Pago registrado', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'invoice_paid' => array(
				'label'         => __( 'Invoice paid', 'super-mechanic' ),
				'default_title' => __( 'Factura pagada', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'document_uploaded' => array(
				'label'         => __( 'Document uploaded', 'super-mechanic' ),
				'default_title' => __( 'Nuevo documento disponible', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'comment_added' => array(
				'label'         => __( 'Comment added', 'super-mechanic' ),
				'default_title' => __( 'Nuevo comentario registrado', 'super-mechanic' ),
				'email_enabled' => false,
			),
			'appointment_created' => array(
				'label'         => __( 'Appointment created', 'super-mechanic' ),
				'default_title' => __( 'Cita programada', 'super-mechanic' ),
				'default_message' => __( 'Se programo una nueva cita.', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'appointment_updated' => array(
				'label'         => __( 'Appointment updated', 'super-mechanic' ),
				'default_title' => __( 'Cita actualizada', 'super-mechanic' ),
				'default_message' => __( 'Se actualizo la cita asignada.', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'appointment_status_changed' => array(
				'label'         => __( 'Appointment status changed', 'super-mechanic' ),
				'default_title' => __( 'Cambio de estado en cita', 'super-mechanic' ),
				'default_message' => __( 'La cita cambio de estado.', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'appointment_cancelled' => array(
				'label'         => __( 'Appointment cancelled', 'super-mechanic' ),
				'default_title' => __( 'Cita cancelada', 'super-mechanic' ),
				'default_message' => __( 'La cita fue cancelada.', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'appointment_reminder' => array(
				'label'         => __( 'Appointment reminder', 'super-mechanic' ),
				'default_title' => __( 'Recordatorio de cita', 'super-mechanic' ),
				'default_message' => __( 'Tienes una cita programada.', 'super-mechanic' ),
				'email_enabled' => true,
			),
			'reminder' => array(
				'label'         => __( 'Reminder', 'super-mechanic' ),
				'default_title' => __( 'Recordatorio', 'super-mechanic' ),
				'email_enabled' => true,
			),
		);
	}

	/**
	 * Get event definition by type.
	 *
	 * @param string $notification_type Notification type.
	 * @return array<string, mixed>
	 */
	public function get_event_definition( $notification_type ) {
		$notification_type = sanitize_key( (string) $notification_type );
		$definitions       = $this->get_event_definitions();

		return isset( $definitions[ $notification_type ] ) ? $definitions[ $notification_type ] : array();
	}

	/**
	 * Get supported notification types.
	 *
	 * @return array<int, string>
	 */
	public function get_supported_notification_types() {
		return array_keys( $this->get_event_definitions() );
	}
}
