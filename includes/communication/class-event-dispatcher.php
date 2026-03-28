<?php
/**
 * Event dispatcher.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

use Super_Mechanic\Helpers\Document_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Central event dispatcher for internal plugin events.
 */
class Event_Dispatcher {
	protected static $instance = null;
	protected $notification_service;
	protected $document_service;

	public static function get_instance( Notification_Service $notification_service = null, Document_Service $document_service = null ) {
		if ( null === self::$instance ) {
			self::$instance = new self( $notification_service, $document_service );
		} elseif ( $notification_service ) {
			self::$instance->notification_service = $notification_service;
		}

		if ( null !== self::$instance && $document_service ) {
			self::$instance->document_service = $document_service;
		}

		return self::$instance;
	}

	protected function __construct( Notification_Service $notification_service = null, Document_Service $document_service = null ) {
		$this->notification_service = $notification_service;
		$this->document_service     = $document_service;
	}

	public function register_hooks() {
		add_action( 'sm_event_process_created', array( $this, 'handle_process_created' ), 10, 1 );
		add_action( 'sm_event_process_step_changed', array( $this, 'handle_process_step_changed' ), 10, 1 );
		add_action( 'sm_event_process_status_changed', array( $this, 'handle_process_status_changed' ), 10, 1 );
		add_action( 'sm_event_process_finalized', array( $this, 'handle_process_finalized' ), 10, 1 );
		add_action( 'sm_event_process_updated', array( $this, 'handle_process_updated' ), 10, 1 );
		add_action( 'sm_event_quote_created_from_maintenance', array( $this, 'handle_quote_created_from_maintenance' ), 10, 1 );
		add_action( 'sm_event_quote_sent', array( $this, 'handle_quote_sent' ), 10, 1 );
		add_action( 'sm_event_quote_approved', array( $this, 'handle_quote_approved' ), 10, 1 );
		add_action( 'sm_event_quote_rejected', array( $this, 'handle_quote_rejected' ), 10, 1 );
		add_action( 'sm_event_quote_cancelled', array( $this, 'handle_quote_cancelled' ), 10, 1 );
		add_action( 'sm_event_invoice_created_from_quote', array( $this, 'handle_invoice_created_from_quote' ), 10, 1 );
		add_action( 'sm_event_invoice_issued', array( $this, 'handle_invoice_issued' ), 10, 1 );
		add_action( 'sm_event_payment_registered', array( $this, 'handle_payment_registered' ), 10, 1 );
		add_action( 'sm_event_invoice_paid', array( $this, 'handle_invoice_paid' ), 10, 1 );
		add_action( 'sm_event_document_uploaded', array( $this, 'handle_document_uploaded' ), 10, 1 );
		add_action( 'sm_event_comment_added', array( $this, 'handle_comment_added' ), 10, 1 );
		add_action( 'sm_event_appointment_created', array( $this, 'handle_appointment_created' ), 10, 1 );
		add_action( 'sm_event_appointment_updated', array( $this, 'handle_appointment_updated' ), 10, 1 );
		add_action( 'sm_event_appointment_status_changed', array( $this, 'handle_appointment_status_changed' ), 10, 1 );
		add_action( 'sm_event_appointment_cancelled', array( $this, 'handle_appointment_cancelled' ), 10, 1 );
		add_action( 'sm_event_appointment_reminder', array( $this, 'handle_appointment_reminder' ), 10, 1 );
	}

	public function dispatch( $event_name, $payload = array() ) {
		do_action( 'sm_event_' . sanitize_key( $event_name ), is_array( $payload ) ? $payload : array() );
	}

	public function handle_process_created( $payload ) {
		$this->get_notification_service()->notify_process_created( is_array( $payload ) ? $payload : array() );
	}

	public function handle_process_step_changed( $payload ) {
		$this->get_notification_service()->notify_process_step_changed( is_array( $payload ) ? $payload : array() );
	}

	public function handle_process_status_changed( $payload ) {
		$this->get_notification_service()->notify_process_status_changed( is_array( $payload ) ? $payload : array() );
	}

	public function handle_process_finalized( $payload ) {
		$this->get_notification_service()->notify_process_finalized( is_array( $payload ) ? $payload : array() );
	}

	public function handle_process_updated( $payload ) {
		$this->get_notification_service()->notify_process_updated( is_array( $payload ) ? $payload : array() );
	}

	public function handle_quote_created_from_maintenance( $payload ) {
		$this->get_notification_service()->notify_quote_created_from_maintenance( is_array( $payload ) ? $payload : array() );
	}

	public function handle_quote_sent( $payload ) {
		$this->get_notification_service()->notify_quote_sent( is_array( $payload ) ? $payload : array() );
	}

	public function handle_quote_approved( $payload ) {
		$this->get_notification_service()->notify_quote_approved( is_array( $payload ) ? $payload : array() );
		$this->prepare_automated_document( 'quote_approved', is_array( $payload ) ? $payload : array() );
	}

	public function handle_quote_rejected( $payload ) {
		$this->get_notification_service()->notify_quote_rejected( is_array( $payload ) ? $payload : array() );
	}

	public function handle_quote_cancelled( $payload ) {
		$this->get_notification_service()->notify_quote_cancelled( is_array( $payload ) ? $payload : array() );
	}

	public function handle_invoice_created_from_quote( $payload ) {
		$this->get_notification_service()->notify_invoice_created_from_quote( is_array( $payload ) ? $payload : array() );
	}

	public function handle_invoice_issued( $payload ) {
		$this->get_notification_service()->notify_invoice_issued( is_array( $payload ) ? $payload : array() );
		$this->prepare_automated_document( 'invoice_issued', is_array( $payload ) ? $payload : array() );
	}

	public function handle_payment_registered( $payload ) {
		$this->get_notification_service()->notify_payment_registered( is_array( $payload ) ? $payload : array() );
		$this->prepare_automated_document( 'payment_registered', is_array( $payload ) ? $payload : array() );
	}

	public function handle_invoice_paid( $payload ) {
		$this->get_notification_service()->notify_invoice_paid( is_array( $payload ) ? $payload : array() );
		$this->prepare_automated_document( 'invoice_paid', is_array( $payload ) ? $payload : array() );
	}

	public function handle_document_uploaded( $payload ) {
		$this->get_notification_service()->notify_document_uploaded( is_array( $payload ) ? $payload : array() );
	}

	public function handle_comment_added( $payload ) {
		$this->get_notification_service()->notify_comment_added( is_array( $payload ) ? $payload : array() );
	}

	public function handle_appointment_created( $payload ) {
		$this->get_notification_service()->notify_appointment_created( is_array( $payload ) ? $payload : array() );
	}

	public function handle_appointment_updated( $payload ) {
		$this->get_notification_service()->notify_appointment_updated( is_array( $payload ) ? $payload : array() );
	}

	public function handle_appointment_status_changed( $payload ) {
		$this->get_notification_service()->notify_appointment_status_changed( is_array( $payload ) ? $payload : array() );
	}

	public function handle_appointment_cancelled( $payload ) {
		$this->get_notification_service()->notify_appointment_cancelled( is_array( $payload ) ? $payload : array() );
	}

	public function handle_appointment_reminder( $payload ) {
		$this->get_notification_service()->notify_appointment_reminder( is_array( $payload ) ? $payload : array() );
	}

	/**
	 * Lazily resolve notification service to avoid circular bootstrap recursion.
	 *
	 * @return Notification_Service
	 */
	protected function get_notification_service() {
		if ( null === $this->notification_service ) {
			$this->notification_service = new Notification_Service();
		}

		return $this->notification_service;
	}

	/**
	 * Prepare a logical automated document without persisting artifacts.
	 *
	 * @param string               $event_name Event name.
	 * @param array<string, mixed> $payload    Event payload.
	 * @return void
	 */
	protected function prepare_automated_document( $event_name, array $payload ) {
		$document_service = $this->get_document_service();
		$result           = $document_service->prepare_automated_document_for_event( $event_name, $payload );

		if ( is_wp_error( $result ) ) {
			return;
		}
	}

	/**
	 * Lazily resolve document service.
	 *
	 * @return Document_Service
	 */
	protected function get_document_service() {
		if ( null === $this->document_service ) {
			$this->document_service = new Document_Service();
		}

		return $this->document_service;
	}
}
