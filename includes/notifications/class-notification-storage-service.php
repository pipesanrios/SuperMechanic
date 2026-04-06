<?php
/**
 * Notification storage service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Notifications;

defined( 'ABSPATH' ) || exit;

/**
 * Business logic for persistent internal notifications.
 */
class Notification_Storage_Service {
	/**
	 * Repository dependency.
	 *
	 * @var Notification_Storage_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Notification_Storage_Repository|null $repository Repository.
	 */
	public function __construct( Notification_Storage_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Notification_Storage_Repository();
	}

	/**
	 * Create one internal notification row.
	 *
	 * @param int                 $user_id User ID.
	 * @param string              $type Notification type.
	 * @param string              $title Notification title.
	 * @param string              $message Notification message.
	 * @param array<string,mixed> $data Optional metadata.
	 * @return int|false
	 */
	public function create_notification( $user_id, $type, $title, $message, array $data = array() ) {
		$user_id = absint( $user_id );
		$type    = sanitize_key( (string) $type );
		$title   = sanitize_text_field( (string) $title );
		$message = wp_kses_post( (string) $message );

		if ( $user_id <= 0 || '' === $type || '' === $title || '' === $message ) {
			return false;
		}

		return $this->repository->insert_notification(
			array(
				'user_id' => $user_id,
				'type'    => $type,
				'title'   => $title,
				'message' => $message,
				'data'    => $data,
			)
		);
	}

	/**
	 * Get notifications for one user.
	 *
	 * @param int                 $user_id User ID.
	 * @param array<string,mixed> $filters Optional filters.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_user_notifications( $user_id, array $filters = array() ) {
		return $this->repository->get_user_notifications( $user_id, $filters );
	}

	/**
	 * Mark notification as read.
	 *
	 * @param int $notification_id Notification ID.
	 * @return bool
	 */
	public function mark_as_read( $notification_id ) {
		$notification_id = absint( $notification_id );
		if ( $notification_id <= 0 ) {
			return false;
		}

		return $this->repository->mark_as_read( $notification_id );
	}

	/**
	 * Get notifications for admin center table.
	 *
	 * @param array<string,mixed> $filters Optional filters.
	 * @param int                 $page Page.
	 * @param int                 $per_page Per page.
	 * @return array{items:array<int,array<string,mixed>>,total:int,page:int,per_page:int}
	 */
	public function get_admin_notifications( array $filters = array(), $page = 1, $per_page = 50 ) {
		$page     = max( 1, absint( $page ) );
		$per_page = max( 1, min( 200, absint( $per_page ) ) );

		$items = $this->repository->get_admin_notifications( $filters, $page, $per_page );
		$total = $this->repository->count_admin_notifications( $filters );

		return array(
			'items'    => is_array( $items ) ? $items : array(),
			'total'    => absint( $total ),
			'page'     => $page,
			'per_page' => $per_page,
		);
	}
}
