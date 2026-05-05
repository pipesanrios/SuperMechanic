<?php
/**
 * Centralized email delivery service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Services;

defined( 'ABSPATH' ) || exit;

/**
 * Sends email notification payloads through WordPress mail.
 */
class Email_Delivery_Service {
	/**
	 * Send one email.
	 *
	 * @param string $to      Recipient email.
	 * @param string $subject Email subject.
	 * @param string $body    Email body.
	 * @return bool
	 */
	public function send_email( $to, $subject, $body ) {
		$to      = sanitize_email( (string) $to );
		$subject = sanitize_text_field( (string) $subject );
		$body    = wp_kses_post( (string) $body );

		if ( ! is_email( $to ) || '' === $subject || '' === $body ) {
			return false;
		}

		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		return (bool) wp_mail( $to, $subject, $body, $headers );
	}
}
