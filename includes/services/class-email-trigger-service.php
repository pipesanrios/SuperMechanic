<?php
/**
 * Centralized email trigger service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Services;

use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Logs\Log_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds and dispatches email notification intents from domain events.
 *
 * Trigger intents are templated, dispatched through the delivery service,
 * and logged with structured delivery outcomes.
 */
class Email_Trigger_Service {
	/**
	 * Structured logger.
	 *
	 * @var Log_Service
	 */
	protected $log_service;

	/**
	 * Invoice service dependency for collection-status resolution.
	 *
	 * @var Invoice_Service
	 */
	protected $invoice_service;

	/**
	 * Email template builder.
	 *
	 * @var Email_Template_Service
	 */
	protected $template_service;
	/**
	 * Email delivery service.
	 *
	 * @var Email_Delivery_Service
	 */
	protected $delivery_service;
	/**
	 * Process service dependency for process recipient resolution.
	 *
	 * @var Process_Service
	 */
	protected $process_service;
	/**
	 * Quote service dependency for quote recipient resolution.
	 *
	 * @var Quote_Service
	 */
	protected $quote_service;
	/**
	 * Client service dependency for recipient resolution.
	 *
	 * @var Client_Service
	 */
	protected $client_service;

	/**
	 * Constructor.
	 *
	 * @param Log_Service|null            $log_service      Log service.
	 * @param Invoice_Service|null        $invoice_service  Invoice service.
	 * @param Email_Template_Service|null $template_service Email template service.
	 * @param Email_Delivery_Service|null $delivery_service Email delivery service.
	 * @param Process_Service|null        $process_service  Process service.
	 * @param Quote_Service|null          $quote_service    Quote service.
	 * @param Client_Service|null         $client_service   Client service.
	 */
	public function __construct( Log_Service $log_service = null, Invoice_Service $invoice_service = null, Email_Template_Service $template_service = null, Email_Delivery_Service $delivery_service = null, Process_Service $process_service = null, Quote_Service $quote_service = null, Client_Service $client_service = null ) {
		$this->log_service      = $log_service ? $log_service : new Log_Service();
		$this->invoice_service  = $invoice_service ? $invoice_service : new Invoice_Service();
		$this->template_service = $template_service ? $template_service : new Email_Template_Service();
		$this->delivery_service = $delivery_service ? $delivery_service : new Email_Delivery_Service();
		$this->process_service  = $process_service ? $process_service : new Process_Service();
		$this->quote_service    = $quote_service ? $quote_service : new Quote_Service();
		$this->client_service   = $client_service ? $client_service : new Client_Service();
	}

	/**
	 * Register event listeners for trigger sources.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'sm_event_process_status_changed', array( $this, 'handle_process_status_changed' ), 10, 1 );
		add_action( 'sm_event_process_finalized', array( $this, 'handle_process_status_changed' ), 10, 1 );
		add_action( 'sm_event_quote_approved', array( $this, 'handle_quote_approved' ), 10, 1 );
		add_action( 'sm_event_quote_rejected', array( $this, 'handle_quote_rejected' ), 10, 1 );
		add_action( 'sm_event_payment_registered', array( $this, 'handle_payment_registered' ), 10, 1 );
		add_action( 'sm_event_invoice_paid', array( $this, 'handle_invoice_paid' ), 10, 1 );
	}

	/**
	 * Trigger on process status change.
	 *
	 * @param int                  $process_id Process ID.
	 * @param string               $old_status Previous status.
	 * @param string               $new_status New status.
	 * @param array<string, mixed> $context    Extra context.
	 * @return array<string, mixed>
	 */
	public function trigger_process_status_change( $process_id, $old_status, $new_status, array $context = array() ) {
		return $this->dispatch_notification_intent(
			'process_status_change',
			'process',
			$process_id,
			$old_status,
			$new_status,
			$context
		);
	}

	/**
	 * Trigger on quote status change.
	 *
	 * @param int                  $quote_id    Quote ID.
	 * @param string               $old_status  Previous status.
	 * @param string               $new_status  New status.
	 * @param array<string, mixed> $context     Extra context.
	 * @return array<string, mixed>
	 */
	public function trigger_quote_status_change( $quote_id, $old_status, $new_status, array $context = array() ) {
		return $this->dispatch_notification_intent(
			'quote_status_change',
			'quote',
			$quote_id,
			$old_status,
			$new_status,
			$context
		);
	}

	/**
	 * Trigger on invoice collection/payment status change.
	 *
	 * @param int                  $invoice_id  Invoice ID.
	 * @param string               $old_status  Previous collection status.
	 * @param string               $new_status  New collection status.
	 * @param array<string, mixed> $context     Extra context.
	 * @return array<string, mixed>
	 */
	public function trigger_invoice_status_change( $invoice_id, $old_status, $new_status, array $context = array() ) {
		return $this->dispatch_notification_intent(
			'invoice_status_change',
			'invoice',
			$invoice_id,
			$old_status,
			$new_status,
			$context
		);
	}

	/**
	 * Listener: process status changed/finalized.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_process_status_changed( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();

		$this->trigger_process_status_change(
			isset( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0,
			isset( $payload['old_status'] ) ? sanitize_key( (string) $payload['old_status'] ) : '',
			isset( $payload['new_status'] ) ? sanitize_key( (string) $payload['new_status'] ) : '',
			array(
				'event_name'   => 'process_status_changed',
				'triggered_by' => isset( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0,
			)
		);
	}

	/**
	 * Listener: quote approved.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_quote_approved( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();

		$this->trigger_quote_status_change(
			isset( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0,
			'sent',
			'approved',
			array(
				'event_name'   => 'quote_approved',
				'process_id'   => isset( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0,
				'client_id'    => isset( $payload['client_id'] ) ? absint( $payload['client_id'] ) : 0,
				'triggered_by' => isset( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0,
			)
		);
	}

	/**
	 * Listener: quote rejected.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_quote_rejected( $payload ) {
		$payload = is_array( $payload ) ? $payload : array();

		$this->trigger_quote_status_change(
			isset( $payload['quote_id'] ) ? absint( $payload['quote_id'] ) : 0,
			'sent',
			'rejected',
			array(
				'event_name'   => 'quote_rejected',
				'process_id'   => isset( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0,
				'client_id'    => isset( $payload['client_id'] ) ? absint( $payload['client_id'] ) : 0,
				'reason'       => isset( $payload['reason'] ) ? sanitize_text_field( (string) $payload['reason'] ) : '',
				'triggered_by' => isset( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0,
			)
		);
	}

	/**
	 * Listener: payment registered (partial/pending collection intent).
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_payment_registered( $payload ) {
		$payload    = is_array( $payload ) ? $payload : array();
		$invoice_id = isset( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;

		if ( $invoice_id <= 0 ) {
			return;
		}

		$summary = $this->invoice_service->get_invoice_payment_summary( $invoice_id );
		if ( is_wp_error( $summary ) ) {
			return;
		}

		$current_status = isset( $summary['payment_status'] ) ? sanitize_key( (string) $summary['payment_status'] ) : '';

		// Paid transition is handled by sm_event_invoice_paid to avoid duplicate intents.
		if ( 'paid' === $current_status ) {
			return;
		}

		if ( '' === $current_status ) {
			$current_status = 'pending';
		}

		$this->trigger_invoice_status_change(
			$invoice_id,
			'pending',
			$current_status,
			array(
				'event_name'   => 'payment_registered',
				'payment_id'   => isset( $payload['payment_id'] ) ? absint( $payload['payment_id'] ) : 0,
				'process_id'   => isset( $payload['process_id'] ) ? absint( $payload['process_id'] ) : 0,
				'client_id'    => isset( $payload['client_id'] ) ? absint( $payload['client_id'] ) : 0,
				'amount'       => isset( $payload['amount'] ) ? (float) $payload['amount'] : 0.0,
				'triggered_by' => isset( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0,
			)
		);
	}

	/**
	 * Listener: invoice paid transition.
	 *
	 * @param array<string, mixed> $payload Event payload.
	 * @return void
	 */
	public function handle_invoice_paid( $payload ) {
		$payload    = is_array( $payload ) ? $payload : array();
		$invoice_id = isset( $payload['invoice_id'] ) ? absint( $payload['invoice_id'] ) : 0;

		$this->trigger_invoice_status_change(
			$invoice_id,
			'partial_or_pending',
			'paid',
			array(
				'event_name'   => 'invoice_paid',
				'payment_id'   => isset( $payload['payment_id'] ) ? absint( $payload['payment_id'] ) : 0,
				'triggered_by' => isset( $payload['triggered_by'] ) ? absint( $payload['triggered_by'] ) : 0,
			)
		);
	}

	/**
	 * Build and store one notification intent.
	 *
	 * @param string               $intent_key  Intent key.
	 * @param string               $entity_type Entity type.
	 * @param int                  $entity_id   Entity ID.
	 * @param string               $old_status  Previous status.
	 * @param string               $new_status  New status.
	 * @param array<string, mixed> $context     Extra context.
	 * @return array<string, mixed>
	 */
	protected function dispatch_notification_intent( $intent_key, $entity_type, $entity_id, $old_status, $new_status, array $context = array() ) {
		$payload = array(
			'intent'      => sanitize_key( (string) $intent_key ),
			'entity_type' => sanitize_key( (string) $entity_type ),
			'entity_id'   => absint( $entity_id ),
			'old_status'  => sanitize_key( (string) $old_status ),
			'new_status'  => sanitize_key( (string) $new_status ),
			'timestamp'   => gmdate( 'c' ),
			'context'     => $this->sanitize_context( $context ),
		);
		$payload['template'] = $this->template_service->build_template_for_intent( $payload );

		$this->log_service->log_notification_event(
			'email_trigger',
			'info',
			'Email trigger intent dispatched.',
			$payload,
			absint( $entity_id )
		);
		$payload['delivery'] = $this->deliver_notification_intent( $payload );

		return $payload;
	}

	/**
	 * Resolve recipient and dispatch real email delivery.
	 *
	 * @param array<string,mixed> $payload Trigger payload.
	 * @return array<string,mixed>
	 */
	protected function deliver_notification_intent( array $payload ) {
		$template = isset( $payload['template'] ) && is_array( $payload['template'] ) ? $payload['template'] : array();
		$result   = array(
			'success'      => false,
			'recipient'    => '',
			'template_key' => isset( $template['template_key'] ) ? sanitize_key( (string) $template['template_key'] ) : '',
			'error'        => '',
		);

		if ( empty( $template['metadata']['ready_for_send'] ) ) {
			$result['error'] = 'template_not_ready';
			$this->log_delivery_result( $payload, $result );
			return $result;
		}

		$client_id = $this->resolve_client_id_for_intent( $payload );
		$recipient = $this->resolve_client_email_recipient( $client_id );

		$result['recipient'] = $recipient;

		if ( '' === $recipient ) {
			$result['error'] = 'recipient_not_resolved';
			$this->log_delivery_result( $payload, $result );
			return $result;
		}

		$subject = isset( $template['subject'] ) ? sanitize_text_field( (string) $template['subject'] ) : '';
		$body    = isset( $template['body'] ) ? wp_kses_post( (string) $template['body'] ) : '';
		if ( '' === $subject || '' === $body ) {
			$result['error'] = 'template_content_missing';
			$this->log_delivery_result( $payload, $result );
			return $result;
		}

		$wp_mail_error = '';
		$failed_hook   = function ( $wp_error ) use ( &$wp_mail_error ) {
			if ( is_wp_error( $wp_error ) ) {
				$wp_mail_error = sanitize_text_field( $wp_error->get_error_message() );
			}
		};

		add_action( 'wp_mail_failed', $failed_hook, 10, 1 );
		$sent = $this->delivery_service->send_email( $recipient, $subject, $body );
		remove_action( 'wp_mail_failed', $failed_hook, 10 );

		$result['success'] = (bool) $sent;
		if ( ! $result['success'] ) {
			$result['error'] = '' !== $wp_mail_error ? $wp_mail_error : 'wp_mail_failed';
		}

		$this->log_delivery_result( $payload, $result );

		return $result;
	}

	/**
	 * Resolve client ID from trigger payload context/entity.
	 *
	 * @param array<string,mixed> $payload Trigger payload.
	 * @return int
	 */
	protected function resolve_client_id_for_intent( array $payload ) {
		$context = isset( $payload['context'] ) && is_array( $payload['context'] ) ? $payload['context'] : array();
		if ( ! empty( $context['client_id'] ) ) {
			return absint( $context['client_id'] );
		}

		$entity_type = isset( $payload['entity_type'] ) ? sanitize_key( (string) $payload['entity_type'] ) : '';
		$entity_id   = isset( $payload['entity_id'] ) ? absint( $payload['entity_id'] ) : 0;

		if ( $entity_id <= 0 ) {
			return 0;
		}

		if ( 'process' === $entity_type ) {
			$process = $this->process_service->get_process( $entity_id );
			return is_array( $process ) && ! empty( $process['client_id'] ) ? absint( $process['client_id'] ) : 0;
		}

		if ( 'quote' === $entity_type ) {
			$quote = $this->quote_service->get_quote( $entity_id );
			return is_array( $quote ) && ! empty( $quote['client_id'] ) ? absint( $quote['client_id'] ) : 0;
		}

		if ( 'invoice' === $entity_type ) {
			$invoice = $this->invoice_service->get_invoice( $entity_id );
			return is_array( $invoice ) && ! empty( $invoice['client_id'] ) ? absint( $invoice['client_id'] ) : 0;
		}

		return 0;
	}

	/**
	 * Resolve one valid recipient email for a client.
	 *
	 * @param int $client_id Client ID.
	 * @return string
	 */
	protected function resolve_client_email_recipient( $client_id ) {
		$client_id = absint( $client_id );
		if ( $client_id <= 0 ) {
			return '';
		}

		$client = $this->client_service->get_client( $client_id );
		if ( is_array( $client ) ) {
			$client_email = sanitize_email( isset( $client['email'] ) ? (string) $client['email'] : '' );
			if ( is_email( $client_email ) ) {
				return $client_email;
			}
		}

		$linked_users = get_users(
			array(
				'number'     => 2,
				'fields'     => array( 'ID', 'user_email' ),
				'meta_key'   => Access_Control_Service::USER_META_CLIENT_ID,
				'meta_value' => (string) $client_id,
			)
		);

		if ( 1 !== count( $linked_users ) ) {
			return '';
		}

		$user_email = sanitize_email( (string) $linked_users[0]->user_email );

		return is_email( $user_email ) ? $user_email : '';
	}

	/**
	 * Persist structured delivery result log.
	 *
	 * @param array<string,mixed> $payload Trigger payload.
	 * @param array<string,mixed> $result Delivery result.
	 * @return void
	 */
	protected function log_delivery_result( array $payload, array $result ) {
		$status  = ! empty( $result['success'] ) ? 'success' : 'error';
		$message = ! empty( $result['success'] ) ? 'Email delivery executed.' : 'Email delivery failed.';

		$this->log_service->log_notification_event(
			'email_delivery',
			$status,
			$message,
			array(
				'intent'       => isset( $payload['intent'] ) ? sanitize_key( (string) $payload['intent'] ) : '',
				'entity_type'  => isset( $payload['entity_type'] ) ? sanitize_key( (string) $payload['entity_type'] ) : '',
				'entity_id'    => isset( $payload['entity_id'] ) ? absint( $payload['entity_id'] ) : 0,
				'recipient'    => isset( $result['recipient'] ) ? sanitize_email( (string) $result['recipient'] ) : '',
				'template_key' => isset( $result['template_key'] ) ? sanitize_key( (string) $result['template_key'] ) : '',
				'success'      => ! empty( $result['success'] ) ? 1 : 0,
				'error'        => isset( $result['error'] ) ? sanitize_text_field( (string) $result['error'] ) : '',
			),
			isset( $payload['entity_id'] ) ? absint( $payload['entity_id'] ) : 0
		);
	}

	/**
	 * Sanitize context map.
	 *
	 * @param array<string, mixed> $context Raw context.
	 * @return array<string, mixed>
	 */
	protected function sanitize_context( array $context ) {
		$clean = array();

		foreach ( $context as $key => $value ) {
			$safe_key = sanitize_key( (string) $key );
			if ( '' === $safe_key ) {
				continue;
			}

			if ( is_scalar( $value ) || null === $value ) {
				$clean[ $safe_key ] = sanitize_text_field( (string) $value );
			}
		}

		return $clean;
	}
}
