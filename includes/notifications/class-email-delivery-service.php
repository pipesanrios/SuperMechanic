<?php
/**
 * Email delivery service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Sends notifications through wp_mail.
 */
class Email_Delivery_Service {
	/**
	 * Send email using wp_mail.
	 *
	 * @param string $to Recipient.
	 * @param string $subject Subject.
	 * @param string $body Body.
	 * @return bool
	 */
	public function send_email( $to, $subject, $body ) {
		$to      = sanitize_email( (string) $to );
		$subject = sanitize_text_field( (string) $subject );
		$body    = wp_kses_post( (string) $body );

		if ( '' === $to || '' === $subject || '' === $body ) {
			error_log( sprintf( '[SM_EMAIL][ERROR] invalid payload to=%s subject=%s', '' !== $to ? $to : 'n/a', $subject ) );
			return false;
		}

		error_log( sprintf( '[SM_EMAIL] to=%s subject=%s', $to, $subject ) );
		$result = (bool) wp_mail( $to, $subject, $body );
		error_log( sprintf( '[SM_EMAIL] wp_mail result=%s', $result ? 'success' : 'fail' ) );

		return $result;
	}
}
