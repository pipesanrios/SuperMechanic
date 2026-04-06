<?php
/**
 * Notification orchestration service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Webhooks\Webhook_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Decoupled notification orchestrator.
 */
class Notification_Service {
	/**
	 * Template service.
	 *
	 * @var Notification_Template_Service
	 */
	protected $template_service;

	/**
	 * Email delivery service.
	 *
	 * @var Email_Delivery_Service
	 */
	protected $email_delivery_service;

	/**
	 * Business repository.
	 *
	 * @var Business_Repository
	 */
	protected $business_repository;

	/**
	 * Internal storage service.
	 *
	 * @var Notification_Storage_Service
	 */
	protected $storage_service;

	/**
	 * Webhook dispatcher service.
	 *
	 * @var Webhook_Service
	 */
	protected $webhook_service;

	/**
	 * Constructor.
	 *
	 * @param Notification_Template_Service|null $template_service Template service.
	 * @param Email_Delivery_Service|null        $email_delivery_service Email service.
	 * @param Business_Repository|null           $business_repository Business repository.
	 * @param Notification_Storage_Service|null  $storage_service Storage service.
	 * @param Webhook_Service|null               $webhook_service Webhook service.
	 */
	public function __construct( Notification_Template_Service $template_service = null, Email_Delivery_Service $email_delivery_service = null, Business_Repository $business_repository = null, Notification_Storage_Service $storage_service = null, Webhook_Service $webhook_service = null ) {
		$this->template_service       = $template_service ? $template_service : new Notification_Template_Service();
		$this->email_delivery_service = $email_delivery_service ? $email_delivery_service : new Email_Delivery_Service();
		$this->business_repository    = $business_repository ? $business_repository : new Business_Repository();
		$this->storage_service        = $storage_service ? $storage_service : new Notification_Storage_Service();
		$this->webhook_service        = $webhook_service ? $webhook_service : new Webhook_Service();
	}

	/**
	 * Send one notification by type and user.
	 *
	 * @param string              $type Notification type.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $data Dynamic data.
	 * @return array<string,mixed>
	 */
	public function send_notification( $type, $user_id, array $data = array() ) {
		$type    = sanitize_key( (string) $type );
		$user_id = absint( $user_id );

		if ( '' === $type || $user_id <= 0 ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] invalid payload type=%s user_id=%d', $type, $user_id ) );
			return array(
				'success' => false,
				'message' => __( 'Invalid notification payload.', 'super-mechanic' ),
			);
		}

		if ( ! $this->acquire_dedupe_lock( $type, $user_id, $data ) ) {
			error_log( sprintf( '[SM_NOTIFY] duplicate skipped type=%s user_id=%d', $type, $user_id ) );
			return array(
				'success' => true,
				'message' => __( 'Duplicate notification skipped.', 'super-mechanic' ),
			);
		}

		$template = $this->template_service->get_template( $type );
		if ( ! is_array( $template ) ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] invalid template type=%s user_id=%d', $type, $user_id ) );
			return array(
				'success' => false,
				'message' => __( 'Unknown notification type.', 'super-mechanic' ),
			);
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] user not found type=%s user_id=%d', $type, $user_id ) );
			return array(
				'success' => false,
				'message' => __( 'Notification user not found.', 'super-mechanic' ),
			);
		}

		$to = sanitize_email( (string) $user->user_email );
		error_log( sprintf( '[SM_NOTIFY] type=%s user_id=%d to=%s', $type, $user_id, '' !== $to ? $to : 'n/a' ) );
		if ( '' === $to ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] missing destination email type=%s user_id=%d', $type, $user_id ) );
			return array(
				'success' => false,
				'message' => __( 'Notification user has no valid email.', 'super-mechanic' ),
			);
		}

		$context = $this->build_template_context( $user, $data );
		$subject = $this->template_service->render_template( (string) $template['subject'], $context );
		$body    = $this->template_service->render_template( (string) $template['body'], $context );
		error_log( sprintf( '[SM_NOTIFY] rendered subject=%s', $subject ) );

		$this->persist_internal_notification( $type, $user_id, $subject, $body, $data );
		$this->dispatch_webhook_event( $type, $user_id, $data );

		$sent = $this->email_delivery_service->send_email( $to, $subject, $body );
		if ( ! $sent ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] email delivery failed type=%s user_id=%d to=%s', $type, $user_id, $to ) );
			return array(
				'success' => false,
				'message' => __( 'Email delivery failed.', 'super-mechanic' ),
			);
		}

		return array(
			'success' => true,
			'message' => __( 'Notification sent.', 'super-mechanic' ),
		);
	}

	/**
	 * Try to acquire one short-lived dedupe lock for the same notification payload.
	 *
	 * @param string              $type Notification type.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $data Notification payload.
	 * @return bool
	 */
	protected function acquire_dedupe_lock( $type, $user_id, array $data ) {
		if ( ! isset( $data['dedupe_key'] ) ) {
			return true;
		}

		$dedupe_key = sanitize_key( (string) $data['dedupe_key'] );
		if ( '' === $dedupe_key ) {
			return true;
		}

		$ttl = isset( $data['dedupe_ttl'] ) ? absint( $data['dedupe_ttl'] ) : 90;
		if ( $ttl <= 0 ) {
			$ttl = 90;
		}

		$transient_key = $this->build_dedupe_transient_key( $type, $user_id, $dedupe_key );
		if ( false !== get_transient( $transient_key ) ) {
			return false;
		}

		set_transient( $transient_key, 1, $ttl );
		return true;
	}

	/**
	 * Build a stable transient key for dedupe checks.
	 *
	 * @param string $type Notification type.
	 * @param int    $user_id User ID.
	 * @param string $dedupe_key Dedupe key.
	 * @return string
	 */
	protected function build_dedupe_transient_key( $type, $user_id, $dedupe_key ) {
		$base = sanitize_key( (string) $type ) . ':' . absint( $user_id ) . ':' . sanitize_key( (string) $dedupe_key );
		return 'sm_notify_lock_' . md5( $base );
	}

	/**
	 * Persist internal notification in DB (non-blocking).
	 *
	 * @param string              $type Notification type.
	 * @param int                 $user_id User ID.
	 * @param string              $title Notification title.
	 * @param string              $message Notification message.
	 * @param array<string,mixed> $data Payload context.
	 * @return void
	 */
	protected function persist_internal_notification( $type, $user_id, $title, $message, array $data = array() ) {
		$stored = $this->storage_service->create_notification( $user_id, $type, $title, $message, $data );
		if ( false === $stored ) {
			error_log( sprintf( '[SM_NOTIFY][ERROR] storage failed type=%s user_id=%d', sanitize_key( (string) $type ), absint( $user_id ) ) );
		}
	}

	/**
	 * Dispatch webhook event in non-blocking mode.
	 *
	 * @param string              $type Notification type.
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $data Payload context.
	 * @return void
	 */
	protected function dispatch_webhook_event( $type, $user_id, array $data = array() ) {
		if ( ! empty( $data['skip_webhook_dispatch'] ) ) {
			return;
		}

		$result = $this->webhook_service->dispatch_from_engine(
			(string) $type,
			array(
				'user_id' => absint( $user_id ),
				'data'    => $data,
			)
		);

		if ( ! is_array( $result ) ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] invalid dispatch result event=%s', sanitize_key( (string) $type ) ) );
			return;
		}

		$failed = isset( $result['failed'] ) ? absint( $result['failed'] ) : 0;
		if ( $failed > 0 ) {
			error_log( sprintf( '[SM_WEBHOOK][ERROR] dispatch partial failure event=%s failed=%d', sanitize_key( (string) $type ), $failed ) );
		}
	}

	/**
	 * Build template context using user + payload.
	 *
	 * @param \WP_User            $user User object.
	 * @param array<string,mixed> $data Data payload.
	 * @return array<string,mixed>
	 */
	protected function build_template_context( \WP_User $user, array $data ) {
		$business_id   = isset( $data['business_id'] ) ? absint( $data['business_id'] ) : 0;
		$business_name = '';
		if ( $business_id > 0 ) {
			$business = $this->business_repository->get_by_id( $business_id );
			if ( is_array( $business ) && isset( $business['name'] ) ) {
				$business_name = sanitize_text_field( (string) $business['name'] );
			}
		}

		if ( '' === $business_name ) {
			$business_name = $business_id > 0 ? sprintf( 'Business #%d', $business_id ) : __( 'Unassigned business', 'super-mechanic' );
		}

		$context = array(
			'user_name'     => sanitize_text_field( (string) $user->display_name ),
			'user_email'    => sanitize_email( (string) $user->user_email ),
			'business_id'   => $business_id,
			'business_name' => $business_name,
			'role'          => isset( $data['role'] ) ? sanitize_key( (string) $data['role'] ) : '',
			'status'        => isset( $data['status'] ) ? sanitize_key( (string) $data['status'] ) : '',
			'mode'          => isset( $data['mode'] ) ? sanitize_key( (string) $data['mode'] ) : '',
		);

		foreach ( $data as $key => $value ) {
			$key = sanitize_key( (string) $key );
			if ( '' === $key || isset( $context[ $key ] ) ) {
				continue;
			}
			if ( is_scalar( $value ) || null === $value ) {
				$context[ $key ] = sanitize_text_field( (string) $value );
			}
		}

		return $context;
	}
}

