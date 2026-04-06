<?php
/**
 * Notifications admin controller.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Notifications\Notification_Storage_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Renders Notification Center page in wp-admin.
 */
class Notifications_Admin_Controller {
	/**
	 * Storage service dependency.
	 *
	 * @var Notification_Storage_Service
	 */
	protected $storage_service;

	/**
	 * Constructor.
	 *
	 * @param Notification_Storage_Service|null $storage_service Storage service.
	 */
	public function __construct( Notification_Storage_Service $storage_service = null ) {
		$this->storage_service = $storage_service ? $storage_service : new Notification_Storage_Service();
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_submenu' ), 105 );
		add_action( 'admin_post_sm_mark_notification_read', array( $this, 'handle_mark_as_read' ) );
	}

	/**
	 * Register submenu.
	 *
	 * @return void
	 */
	public function register_submenu() {
		add_submenu_page(
			'super-mechanic',
			__( 'Notifications', 'super-mechanic' ),
			__( 'Notifications', 'super-mechanic' ),
			'sm_manage_plugin',
			'super-mechanic-notifications',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Render notifications center page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'super-mechanic' ) );
		}

		$status   = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( $_GET['status'] ) ) : '';
		$type     = isset( $_GET['type'] ) ? sanitize_key( (string) wp_unslash( $_GET['type'] ) ) : '';
		$user_id  = isset( $_GET['user_id'] ) ? absint( wp_unslash( $_GET['user_id'] ) ) : 0;
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( wp_unslash( $_GET['paged'] ) ) ) : 1;
		$per_page = 30;

		$filters = array(
			'status'  => in_array( $status, array( 'unread', 'read' ), true ) ? $status : '',
			'type'    => $type,
			'user_id' => $user_id,
		);

		$payload     = $this->storage_service->get_admin_notifications( $filters, $page, $per_page );
		$items       = isset( $payload['items'] ) && is_array( $payload['items'] ) ? $payload['items'] : array();
		$total       = isset( $payload['total'] ) ? absint( $payload['total'] ) : 0;
		$total_pages = $per_page > 0 ? (int) ceil( $total / $per_page ) : 1;

		echo '<div class="wrap sm-admin-shell">';
		echo '<h1>' . esc_html__( 'Notification Center', 'super-mechanic' ) . '</h1>';
		echo '<p class="sm-admin-subtitle">' . esc_html__( 'Persistent internal notifications stored in database.', 'super-mechanic' ) . '</p>';

		$this->render_notice();
		$this->render_filters( $filters );

		echo '<div class="sm-table-wrap">';
		echo '<table class="sm-table">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Date', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'User', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Type', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Message', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'super-mechanic' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'super-mechanic' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $items ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No notifications found.', 'super-mechanic' ) . '</td></tr>';
		} else {
			foreach ( $items as $item ) {
				$id         = isset( $item['id'] ) ? absint( $item['id'] ) : 0;
				$row_user   = isset( $item['user_id'] ) ? absint( $item['user_id'] ) : 0;
				$type_value = isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '';
				$title      = isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '';
				$message    = isset( $item['message'] ) ? wp_kses_post( (string) $item['message'] ) : '';
				$status_val = isset( $item['status'] ) ? sanitize_key( (string) $item['status'] ) : 'unread';
				$date       = isset( $item['created_at'] ) ? sanitize_text_field( (string) $item['created_at'] ) : '';

				$user_label = $this->resolve_user_label( $row_user );
				echo '<tr>';
				echo '<td>' . esc_html( $date ) . '</td>';
				echo '<td>' . esc_html( $user_label ) . '</td>';
				echo '<td>' . esc_html( $type_value ) . '</td>';
				echo '<td><strong>' . esc_html( $title ) . '</strong><br />' . wp_kses_post( $message ) . '</td>';
				echo '<td>' . esc_html( $status_val ) . '</td>';
				echo '<td>';
				if ( 'read' !== $status_val && $id > 0 ) {
					$url = wp_nonce_url(
						add_query_arg(
							array(
								'action'          => 'sm_mark_notification_read',
								'notification_id' => $id,
							),
							admin_url( 'admin-post.php' )
						),
						'sm_mark_notification_read_' . $id
					);
					echo '<a class="button button-small" href="' . esc_url( $url ) . '">' . esc_html__( 'Mark as read', 'super-mechanic' ) . '</a>';
				} else {
					echo '—';
				}
				echo '</td>';
				echo '</tr>';
			}
		}

		echo '</tbody></table>';
		echo '</div>';

		if ( $total_pages > 1 ) {
			echo '<div class="tablenav"><div class="tablenav-pages">';
			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%' ),
						'format'    => '',
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
						'total'     => max( 1, $total_pages ),
						'current'   => $page,
					)
				)
			);
			echo '</div></div>';
		}

		echo '</div>';
	}

	/**
	 * Handle read action.
	 *
	 * @return void
	 */
	public function handle_mark_as_read() {
		if ( ! current_user_can( 'sm_manage_plugin' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to perform this action.', 'super-mechanic' ) );
		}

		$notification_id = isset( $_GET['notification_id'] ) ? absint( wp_unslash( $_GET['notification_id'] ) ) : 0;
		if ( $notification_id <= 0 ) {
			$this->redirect_with_notice( 'error', 'invalid_payload' );
		}

		check_admin_referer( 'sm_mark_notification_read_' . $notification_id );
		$result = $this->storage_service->mark_as_read( $notification_id );
		if ( ! $result ) {
			$this->redirect_with_notice( 'error', 'mark_failed' );
		}

		$this->redirect_with_notice( 'success', 'marked_read' );
	}

	/**
	 * Render top notices.
	 *
	 * @return void
	 */
	protected function render_notice() {
		$notice_type = isset( $_GET['sm_notice_type'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_type'] ) ) : '';
		$notice_code = isset( $_GET['sm_notice_code'] ) ? sanitize_key( (string) wp_unslash( $_GET['sm_notice_code'] ) ) : '';
		if ( '' === $notice_type || '' === $notice_code ) {
			return;
		}

		$messages = array(
			'marked_read'     => __( 'Notification marked as read.', 'super-mechanic' ),
			'invalid_payload' => __( 'Invalid notification payload.', 'super-mechanic' ),
			'mark_failed'     => __( 'Could not mark notification as read.', 'super-mechanic' ),
		);

		if ( ! isset( $messages[ $notice_code ] ) ) {
			return;
		}

		echo '<div class="notice notice-' . esc_attr( 'success' === $notice_type ? 'success' : 'error' ) . ' is-dismissible"><p>' . esc_html( $messages[ $notice_code ] ) . '</p></div>';
	}

	/**
	 * Render table filters.
	 *
	 * @param array<string,mixed> $filters Active filters.
	 * @return void
	 */
	protected function render_filters( array $filters ) {
		echo '<form method="get" class="sm-notification-filters" style="margin:12px 0;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">';
		echo '<input type="hidden" name="page" value="super-mechanic-notifications" />';
		echo '<input type="number" min="0" name="user_id" placeholder="' . esc_attr__( 'User ID', 'super-mechanic' ) . '" value="' . esc_attr( (string) ( isset( $filters['user_id'] ) ? absint( $filters['user_id'] ) : 0 ) ) . '" />';
		echo '<select name="status">';
		echo '<option value="">' . esc_html__( 'All statuses', 'super-mechanic' ) . '</option>';
		echo '<option value="unread" ' . selected( isset( $filters['status'] ) ? $filters['status'] : '', 'unread', false ) . '>' . esc_html__( 'Unread', 'super-mechanic' ) . '</option>';
		echo '<option value="read" ' . selected( isset( $filters['status'] ) ? $filters['status'] : '', 'read', false ) . '>' . esc_html__( 'Read', 'super-mechanic' ) . '</option>';
		echo '</select>';
		echo '<input type="text" name="type" placeholder="' . esc_attr__( 'Type', 'super-mechanic' ) . '" value="' . esc_attr( isset( $filters['type'] ) ? (string) $filters['type'] : '' ) . '" />';
		echo '<button type="submit" class="button">' . esc_html__( 'Filter', 'super-mechanic' ) . '</button>';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=super-mechanic-notifications' ) ) . '" class="button button-secondary">' . esc_html__( 'Reset', 'super-mechanic' ) . '</a>';
		echo '</form>';
	}

	/**
	 * Resolve user label.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	protected function resolve_user_label( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return __( 'Unknown user', 'super-mechanic' );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof \WP_User ) {
			return sprintf( __( 'User #%d', 'super-mechanic' ), $user_id );
		}

		return sprintf( '%s (%d)', sanitize_text_field( (string) $user->display_name ), $user_id );
	}

	/**
	 * Redirect with page notice.
	 *
	 * @param string $type Notice type.
	 * @param string $code Notice code.
	 * @return void
	 */
	protected function redirect_with_notice( $type, $code ) {
		wp_safe_redirect(
			add_query_arg(
				array(
					'page'           => 'super-mechanic-notifications',
					'sm_notice_type' => sanitize_key( (string) $type ),
					'sm_notice_code' => sanitize_key( (string) $code ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
