<?php
/**
 * Centralized email template service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Services;

use Super_Mechanic\Notifications\Notification_Template_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Builds reusable email templates from trigger intents.
 */
class Email_Template_Service {
	/**
	 * Optional shared renderer.
	 *
	 * @var Notification_Template_Service
	 */
	protected $renderer;

	/**
	 * Template map.
	 *
	 * @var array<string,array<string,string>>
	 */
	protected $templates = array(
		'process_status_change' => array(
			'subject' => 'Process #{{entity_id}} status updated: {{old_status_human}} -> {{new_status_human}}',
			'body'    => "Process #{{entity_id}} changed status.\n\nFrom: {{old_status_human}}\nTo: {{new_status_human}}\nTriggered by: {{triggered_by}}\nTimestamp: {{timestamp}}\n",
		),
		'quote_approved'        => array(
			'subject' => 'Quote #{{entity_id}} approved',
			'body'    => "Quote #{{entity_id}} was approved.\n\nProcess: {{process_id}}\nClient: {{client_id}}\nTriggered by: {{triggered_by}}\nTimestamp: {{timestamp}}\n",
		),
		'quote_rejected'        => array(
			'subject' => 'Quote #{{entity_id}} rejected',
			'body'    => "Quote #{{entity_id}} was rejected.\n\nReason: {{reason}}\nProcess: {{process_id}}\nClient: {{client_id}}\nTriggered by: {{triggered_by}}\nTimestamp: {{timestamp}}\n",
		),
		'invoice_paid'          => array(
			'subject' => 'Invoice #{{entity_id}} paid',
			'body'    => "Invoice #{{entity_id}} reached paid status.\n\nPayment: {{payment_id}}\nTriggered by: {{triggered_by}}\nTimestamp: {{timestamp}}\n",
		),
		'invoice_partial'       => array(
			'subject' => 'Invoice #{{entity_id}} partially paid',
			'body'    => "Invoice #{{entity_id}} has a partial payment.\n\nPayment: {{payment_id}}\nAmount: {{amount}}\nTriggered by: {{triggered_by}}\nTimestamp: {{timestamp}}\n",
		),
	);

	/**
	 * Constructor.
	 *
	 * @param Notification_Template_Service|null $renderer Renderer dependency.
	 */
	public function __construct( Notification_Template_Service $renderer = null ) {
		$this->renderer = $renderer ? $renderer : new Notification_Template_Service();
	}

	/**
	 * Build one email template payload from an email-trigger intent.
	 *
	 * @param array<string,mixed> $intent Intent payload.
	 * @return array<string,mixed>
	 */
	public function build_template_for_intent( array $intent ) {
		$template_key = $this->resolve_template_key( $intent );
		$context      = $this->build_context( $intent );
		$template     = isset( $this->templates[ $template_key ] ) ? $this->templates[ $template_key ] : null;

		if ( ! is_array( $template ) ) {
			return array(
				'template_key' => '',
				'subject'      => '',
				'body'         => '',
				'metadata'     => array(
					'delivery_channel' => 'email',
					'template_version' => '1.0',
					'ready_for_send'   => false,
				),
			);
		}

		return array(
			'template_key' => $template_key,
			'subject'      => $this->render( (string) $template['subject'], $context ),
			'body'         => $this->render( (string) $template['body'], $context ),
			'metadata'     => array(
				'delivery_channel' => 'email',
				'template_version' => '1.0',
				'ready_for_send'   => true,
			),
		);
	}

	/**
	 * Resolve template key based on intent + statuses.
	 *
	 * @param array<string,mixed> $intent Intent payload.
	 * @return string
	 */
	protected function resolve_template_key( array $intent ) {
		$intent_key = isset( $intent['intent'] ) ? sanitize_key( (string) $intent['intent'] ) : '';
		$new_status = isset( $intent['new_status'] ) ? sanitize_key( (string) $intent['new_status'] ) : '';

		if ( 'process_status_change' === $intent_key ) {
			return 'process_status_change';
		}

		if ( 'quote_status_change' === $intent_key ) {
			if ( 'approved' === $new_status ) {
				return 'quote_approved';
			}
			if ( 'rejected' === $new_status ) {
				return 'quote_rejected';
			}
		}

		if ( 'invoice_status_change' === $intent_key ) {
			if ( 'paid' === $new_status ) {
				return 'invoice_paid';
			}
			if ( 'partial' === $new_status ) {
				return 'invoice_partial';
			}
		}

		return '';
	}

	/**
	 * Build template context from intent payload.
	 *
	 * @param array<string,mixed> $intent Intent payload.
	 * @return array<string,mixed>
	 */
	protected function build_context( array $intent ) {
		$context = isset( $intent['context'] ) && is_array( $intent['context'] ) ? $intent['context'] : array();

		return array(
			'entity_id'        => isset( $intent['entity_id'] ) ? absint( $intent['entity_id'] ) : 0,
			'old_status'       => isset( $intent['old_status'] ) ? sanitize_key( (string) $intent['old_status'] ) : '',
			'new_status'       => isset( $intent['new_status'] ) ? sanitize_key( (string) $intent['new_status'] ) : '',
			'old_status_human' => isset( $intent['old_status'] ) ? $this->humanize_key( (string) $intent['old_status'] ) : '',
			'new_status_human' => isset( $intent['new_status'] ) ? $this->humanize_key( (string) $intent['new_status'] ) : '',
			'timestamp'        => isset( $intent['timestamp'] ) ? sanitize_text_field( (string) $intent['timestamp'] ) : gmdate( 'c' ),
			'process_id'       => isset( $context['process_id'] ) ? absint( $context['process_id'] ) : 0,
			'client_id'        => isset( $context['client_id'] ) ? absint( $context['client_id'] ) : 0,
			'payment_id'       => isset( $context['payment_id'] ) ? absint( $context['payment_id'] ) : 0,
			'amount'           => isset( $context['amount'] ) ? sanitize_text_field( (string) $context['amount'] ) : '0',
			'reason'           => isset( $context['reason'] ) ? sanitize_text_field( (string) $context['reason'] ) : '-',
			'triggered_by'     => isset( $context['triggered_by'] ) ? absint( $context['triggered_by'] ) : 0,
		);
	}

	/**
	 * Render template placeholders with sanitized context.
	 *
	 * @param string              $template Template string.
	 * @param array<string,mixed> $context Context map.
	 * @return string
	 */
	protected function render( $template, array $context ) {
		return $this->renderer->render_template( (string) $template, $context );
	}

	/**
	 * Convert machine key to readable text.
	 *
	 * @param string $value Key value.
	 * @return string
	 */
	protected function humanize_key( $value ) {
		$value = sanitize_key( (string) $value );
		if ( '' === $value ) {
			return '';
		}

		return ucwords( str_replace( '_', ' ', $value ) );
	}
}

