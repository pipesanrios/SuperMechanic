<?php
/**
 * Notification channel contract.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Communication;

defined( 'ABSPATH' ) || exit;

/**
 * Defines an external notification delivery channel.
 */
interface Notification_Channel_Interface {
	/**
	 * Get stable channel key.
	 *
	 * @return string
	 */
	public function get_channel_key();

	/**
	 * Whether channel is globally enabled and ready.
	 *
	 * @return bool
	 */
	public function is_enabled();

	/**
	 * Send notification payload through this channel.
	 *
	 * @param array<string, mixed> $notification     Notification payload.
	 * @param array<string, mixed> $event_definition Event definition metadata.
	 * @return bool
	 */
	public function send( array $notification, array $event_definition = array() );
}
