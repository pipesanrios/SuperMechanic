<?php
/**
 * Seed a reproducible QA dataset for the pre-API baseline.
 *
 * @package Super_Mechanic
 */

declare(strict_types=1);

use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Invoices\Invoice_Repository;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Repository;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Repository;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Relations\Client_Vehicle_Repository;
use Super_Mechanic\Relations\Client_Vehicle_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;

if ( 'cli' !== PHP_SAPI ) {
	fwrite( STDERR, "This script must run from CLI.\n" );
	exit( 1 );
}

$wp_load = dirname( __DIR__, 4 ) . DIRECTORY_SEPARATOR . 'wp-load.php';

if ( ! file_exists( $wp_load ) ) {
	fwrite( STDERR, "wp-load.php not found.\n" );
	exit( 1 );
}

require_once $wp_load;

function sm_seed_out( string $message ): void {
	fwrite( STDOUT, $message . PHP_EOL );
}

function sm_seed_fail( string $message ): void {
	fwrite( STDERR, $message . PHP_EOL );
	exit( 1 );
}

function sm_seed_is_ok( $value ): bool {
	return ! is_wp_error( $value ) && false !== $value && null !== $value;
}

function sm_seed_require_admin_user(): int {
	$admin_ids = get_users(
		array(
			'role'   => 'administrator',
			'fields' => 'ids',
			'number' => 1,
		)
	);

	$admin_id = ! empty( $admin_ids ) ? absint( $admin_ids[0] ) : 0;

	if ( ! $admin_id ) {
		sm_seed_fail( 'No administrator user was found for dataset seeding.' );
	}

	wp_set_current_user( $admin_id );

	return $admin_id;
}

function sm_seed_ensure_user( string $login, string $email, string $role, array $caps = array() ): int {
	$user = get_user_by( 'login', $login );

	if ( ! $user ) {
		$user_id = wp_create_user( $login, wp_generate_password( 24, true, true ), $email );

		if ( is_wp_error( $user_id ) ) {
			sm_seed_fail( 'Failed creating QA user ' . $login . ': ' . $user_id->get_error_message() );
		}

		$user = get_user_by( 'id', (int) $user_id );
	}

	if ( ! $user instanceof WP_User ) {
		sm_seed_fail( 'Failed resolving QA user ' . $login . '.' );
	}

	if ( get_role( $role ) ) {
		$user->set_role( $role );
	} elseif ( empty( $user->roles ) ) {
		$user->set_role( 'subscriber' );
	}

	foreach ( $caps as $cap ) {
		$user->add_cap( $cap );
	}

	return (int) $user->ID;
}

function sm_seed_find_first( array $rows ): ?array {
	return ! empty( $rows ) && is_array( $rows[0] ) ? $rows[0] : null;
}

function sm_seed_ensure_client( Client_Service $service, array $data ): array {
	$current = sm_seed_find_first(
		$service->get_clients(
			array(
				'exact_email' => $data['email'],
				'per_page'    => 1,
			)
		)
	);

	if ( $current ) {
		$result = $service->update_client( (int) $current['id'], $data );

		if ( is_wp_error( $result ) ) {
			sm_seed_fail( 'Failed updating client ' . $data['email'] . ': ' . $result->get_error_message() );
		}

		return (array) $service->get_client( (int) $current['id'] );
	}

	$client_id = $service->create_client( $data );

	if ( is_wp_error( $client_id ) ) {
		sm_seed_fail( 'Failed creating client ' . $data['email'] . ': ' . $client_id->get_error_message() );
	}

	return (array) $service->get_client( (int) $client_id );
}

function sm_seed_ensure_vehicle( Vehicle_Service $service, array $data ): array {
	$current = sm_seed_find_first(
		$service->get_vehicles(
			array(
				'exact_plate' => $data['plate'],
				'per_page'    => 1,
			)
		)
	);

	if ( $current ) {
		$result = $service->update_vehicle( (int) $current['id'], $data );

		if ( is_wp_error( $result ) ) {
			sm_seed_fail( 'Failed updating vehicle ' . $data['plate'] . ': ' . $result->get_error_message() );
		}

		return (array) $service->get_vehicle( (int) $current['id'] );
	}

	$vehicle_id = $service->create_vehicle( $data );

	if ( is_wp_error( $vehicle_id ) ) {
		sm_seed_fail( 'Failed creating vehicle ' . $data['plate'] . ': ' . $vehicle_id->get_error_message() );
	}

	return (array) $service->get_vehicle( (int) $vehicle_id );
}

function sm_seed_find_process( Process_Service $service, int $client_id, int $vehicle_id, string $title ): ?array {
	$rows = $service->get_processes(
		array(
			'client_id'  => $client_id,
			'vehicle_id' => $vehicle_id,
			'per_page'   => 100,
			'orderby'    => 'created_at',
			'order'      => 'DESC',
		)
	);

	foreach ( $rows as $row ) {
		if ( isset( $row['title'] ) && $row['title'] === $title ) {
			return $row;
		}
	}

	return null;
}

function sm_seed_ensure_process( Process_Service $service, array $data ): array {
	$current = sm_seed_find_process( $service, (int) $data['client_id'], (int) $data['vehicle_id'], (string) $data['title'] );

	if ( $current ) {
		$result = $service->update_process(
			(int) $current['id'],
			array(
				'status'         => $data['status'],
				'title'          => $data['title'],
				'internal_notes' => $data['internal_notes'],
				'opened_at'      => $data['opened_at'],
				'due_date'       => $data['due_date'],
			)
		);

		if ( is_wp_error( $result ) ) {
			sm_seed_fail( 'Failed updating process ' . $data['title'] . ': ' . $result->get_error_message() );
		}

		return (array) $service->get_process( (int) $current['id'] );
	}

	$process_id = $service->create_process( $data );

	if ( is_wp_error( $process_id ) ) {
		sm_seed_fail( 'Failed creating process ' . $data['title'] . ': ' . $process_id->get_error_message() );
	}

	return (array) $service->get_process( (int) $process_id );
}

function sm_seed_find_payment( Invoice_Service $service, int $invoice_id, string $reference ): ?array {
	foreach ( $service->get_payments( $invoice_id ) as $payment ) {
		if ( isset( $payment['reference'] ) && $payment['reference'] === $reference ) {
			return $payment;
		}
	}

	return null;
}

function sm_seed_find_attachment( Attachment_Service $service, int $process_id, string $title ): ?array {
	foreach ( $service->get_process_attachments( $process_id, array( 'per_page' => 200 ) ) as $attachment ) {
		if ( isset( $attachment['title'] ) && $attachment['title'] === $title ) {
			return $attachment;
		}
	}

	return null;
}

function sm_seed_find_comment( Comment_Service $service, int $process_id, string $content ): ?array {
	foreach ( $service->get_comments( array( 'process_id' => $process_id, 'per_page' => 200 ) ) as $comment ) {
		if ( isset( $comment['content'] ) && $comment['content'] === $content ) {
			return $comment;
		}
	}

	return null;
}

function sm_seed_find_notification( Notification_Service $service, int $client_id, string $title, int $object_id ): ?array {
	foreach ( $service->get_client_notifications( $client_id, array( 'per_page' => 200 ) ) as $notification ) {
		if (
			isset( $notification['title'], $notification['object_id'] ) &&
			$title === $notification['title'] &&
			$object_id === (int) $notification['object_id']
		) {
			return $notification;
		}
	}

	return null;
}

$admin_id = sm_seed_require_admin_user();

$settings_screen = new Super_Mechanic\Settings();
$settings_values = $settings_screen->sanitize_settings(
	array(
		'company_name'                 => 'SM QA Workshop',
		'business_context_key'         => 'default-workshop',
		'language_locale'              => 'en_US',
		'default_currency'             => 'USD',
		'timezone'                     => 'Europe/Rome',
		'date_format'                  => 'Y-m-d',
		'enabled_process_types'        => array( 'maintenance', 'pre_delivery', 'paperwork' ),
		'allow_step_back'              => 1,
		'auto_complete_on_final_step'  => 1,
		'default_tax_rate'             => 21,
		'allow_partial_payments'       => 1,
		'enable_client_notifications'  => 1,
		'client_panel_enabled'         => 1,
	)
);

update_option( Super_Mechanic\Settings::OPTION_NAME, $settings_values );

$settings_service = new Settings_Service();
$settings_saved   = sm_seed_is_ok( get_option( Super_Mechanic\Settings::OPTION_NAME, array() ) );
$settings_loaded  = 'SM QA Workshop' === $settings_service->get_setting( 'business', 'business_name', '' )
	&& 'default-workshop' === $settings_service->get_setting( 'business', 'business_context_key', '' )
	&& 'en_US' === $settings_service->get_setting( 'business', 'locale', '' )
	&& 'Europe/Rome' === $settings_service->get_setting( 'business', 'timezone', '' )
	&& true === $settings_service->get_setting( 'financial', 'allow_partial_payments', false );

$client_service       = new Client_Service();
$vehicle_service      = new Vehicle_Service();
$relation_service     = new Client_Vehicle_Service();
$process_service      = new Process_Service();
$process_repository   = new Process_Repository();
$quote_service        = new Quote_Service();
$quote_repository     = new Quote_Repository();
$invoice_service      = new Invoice_Service();
$invoice_repository   = new Invoice_Repository();
$attachment_service   = new Attachment_Service();
$comment_service      = new Comment_Service();
$notification_service = new Notification_Service();

$client_user_id   = sm_seed_ensure_user( 'sm_qa_client', 'qa.client@supermechanic.local', 'sm_client', array( 'sm_view_own_processes', 'sm_view_own_vehicles' ) );
$mechanic_user_id = sm_seed_ensure_user( 'sm_qa_mechanic', 'qa.mechanic@supermechanic.local', 'sm_mechanic', array( 'sm_manage_processes', 'sm_manage_vehicles', 'sm_view_own_processes' ) );

$client_alpha = sm_seed_ensure_client(
	$client_service,
	array(
		'first_name'  => 'Alex',
		'last_name'   => 'Driver',
		'email'       => 'alex.driver.qa@supermechanic.local',
		'phone'       => '+1-555-100-0001',
		'document_id' => 'SM-QA-CLIENT-001',
		'notes'       => 'Primary QA customer for pre-API validation.',
	)
);

$client_beta = sm_seed_ensure_client(
	$client_service,
	array(
		'first_name'  => 'Taylor',
		'last_name'   => 'Delivery',
		'email'       => 'taylor.delivery.qa@supermechanic.local',
		'phone'       => '+1-555-100-0002',
		'document_id' => 'SM-QA-CLIENT-002',
		'notes'       => 'Secondary QA customer for cross-checking ownership.',
	)
);

update_user_meta( $client_user_id, 'sm_client_id', (int) $client_alpha['id'] );

$vehicle_alpha = sm_seed_ensure_vehicle(
	$vehicle_service,
	array(
		'client_id' => (int) $client_alpha['id'],
		'brand'     => 'Toyota',
		'model'     => 'Corolla QA',
		'year'      => 2020,
		'vin'       => 'SMQATESTVIN0000001',
		'plate'     => 'SMQA001',
		'color'     => 'Blue',
		'notes'     => 'Primary QA vehicle.',
	)
);

$vehicle_beta = sm_seed_ensure_vehicle(
	$vehicle_service,
	array(
		'client_id' => (int) $client_beta['id'],
		'brand'     => 'Ford',
		'model'     => 'Transit QA',
		'year'      => 2021,
		'vin'       => 'SMQATESTVIN0000002',
		'plate'     => 'SMQA002',
		'color'     => 'White',
		'notes'     => 'Secondary QA vehicle.',
	)
);

$alpha_relations = $relation_service->get_client_vehicles( (int) $client_alpha['id'], array( 'per_page' => 50 ) );
$beta_relations  = $relation_service->get_client_vehicles( (int) $client_beta['id'], array( 'per_page' => 50 ) );

$alpha_has_relation = false;
foreach ( is_array( $alpha_relations ) ? $alpha_relations : array() as $relation ) {
	if ( (int) $relation['vehicle_id'] === (int) $vehicle_alpha['id'] ) {
		$alpha_has_relation = true;
		break;
	}
}

if ( ! $alpha_has_relation ) {
	$relation_result = $relation_service->assign_vehicle_to_client( (int) $client_alpha['id'], (int) $vehicle_alpha['id'] );

	if ( is_wp_error( $relation_result ) ) {
		sm_seed_fail( 'Failed creating client/vehicle relation for client alpha: ' . $relation_result->get_error_message() );
	}
}

$beta_has_relation = false;
foreach ( is_array( $beta_relations ) ? $beta_relations : array() as $relation ) {
	if ( (int) $relation['vehicle_id'] === (int) $vehicle_beta['id'] ) {
		$beta_has_relation = true;
		break;
	}
}

if ( ! $beta_has_relation ) {
	$relation_result = $relation_service->assign_vehicle_to_client( (int) $client_beta['id'], (int) $vehicle_beta['id'] );

	if ( is_wp_error( $relation_result ) ) {
		sm_seed_fail( 'Failed creating client/vehicle relation for client beta: ' . $relation_result->get_error_message() );
	}
}

$process_alpha = sm_seed_ensure_process(
	$process_service,
	array(
		'vehicle_id'     => (int) $vehicle_alpha['id'],
		'client_id'      => (int) $client_alpha['id'],
		'process_type'   => 'maintenance',
		'status'         => 'in_progress',
		'title'          => 'SM QA Maintenance Intake',
		'internal_notes' => 'Pre-API QA baseline maintenance flow.',
		'opened_at'      => '2026-03-20 09:00:00',
		'due_date'       => '2026-03-30 18:00:00',
	)
);

$process_beta = sm_seed_ensure_process(
	$process_service,
	array(
		'vehicle_id'     => (int) $vehicle_beta['id'],
		'client_id'      => (int) $client_beta['id'],
		'process_type'   => 'maintenance',
		'status'         => 'pending',
		'title'          => 'SM QA Secondary Maintenance Flow',
		'internal_notes' => 'Secondary QA process for ownership and list filtering checks.',
		'opened_at'      => '2026-03-22 10:30:00',
		'due_date'       => '2026-04-02 17:00:00',
	)
);

$process_repository->update(
	(int) $process_alpha['id'],
	array(
		'assigned_to' => $mechanic_user_id,
	)
);

$quote = $quote_repository->get_by_quote_number( 'SMQ-QA-001' );

if ( ! $quote ) {
	$quote_id = $quote_service->create_quote(
		array(
			'process_id'   => (int) $process_alpha['id'],
			'client_id'    => (int) $client_alpha['id'],
			'quote_number' => 'SMQ-QA-001',
			'status'       => 'draft',
			'currency'     => 'USD',
			'notes'        => 'QA quote baseline.',
		)
	);

	if ( is_wp_error( $quote_id ) ) {
		sm_seed_fail( 'Failed creating QA quote: ' . $quote_id->get_error_message() );
	}

	$quote_service->add_quote_item(
		(int) $quote_id,
		array(
			'item_type'   => 'custom',
			'label'       => 'Diagnostic labor',
			'description' => 'Initial QA diagnostic block.',
			'quantity'    => 1,
			'unit_price'  => 120,
			'sort_order'  => 1,
		)
	);

	$quote_service->add_quote_item(
		(int) $quote_id,
		array(
			'item_type'   => 'custom',
			'label'       => 'Brake pad kit',
			'description' => 'QA replacement item.',
			'quantity'    => 1,
			'unit_price'  => 80,
			'sort_order'  => 2,
		)
	);

	$quote = $quote_service->get_quote( (int) $quote_id );
}

if ( ! $quote || ! is_array( $quote ) ) {
	sm_seed_fail( 'QA quote could not be resolved after creation.' );
}

if ( 'draft' === $quote['status'] ) {
	$quote_service->send_quote( (int) $quote['id'] );
	$quote = $quote_service->get_quote( (int) $quote['id'] );
}

if ( $quote && 'sent' === $quote['status'] ) {
	$approval_result = $quote_service->approve_quote( (int) $quote['id'], $client_user_id );

	if ( is_wp_error( $approval_result ) ) {
		sm_seed_fail( 'Failed approving QA quote: ' . $approval_result->get_error_message() );
	}

	$quote = $quote_service->get_quote( (int) $quote['id'] );
}

$invoice = $invoice_repository->get_by_invoice_number( 'SMI-QA-001' );

if ( ! $invoice ) {
	$invoice_id = $invoice_service->create_invoice(
		array(
			'process_id'     => (int) $process_alpha['id'],
			'client_id'      => (int) $client_alpha['id'],
			'invoice_number' => 'SMI-QA-001',
			'status'         => 'issued',
			'currency'       => 'USD',
			'issued_at'      => '2026-03-25 11:00:00',
			'due_date'       => '2026-04-05',
			'notes'          => 'QA invoice baseline.',
		)
	);

	if ( is_wp_error( $invoice_id ) ) {
		sm_seed_fail( 'Failed creating QA invoice: ' . $invoice_id->get_error_message() );
	}

	$invoice_service->add_invoice_item(
		(int) $invoice_id,
		array(
			'item_type'   => 'custom',
			'label'       => 'Diagnostic labor',
			'description' => 'Initial QA diagnostic block.',
			'quantity'    => 1,
			'unit_price'  => 120,
			'sort_order'  => 1,
		)
	);

	$invoice_service->add_invoice_item(
		(int) $invoice_id,
		array(
			'item_type'   => 'custom',
			'label'       => 'Brake pad kit',
			'description' => 'QA replacement item.',
			'quantity'    => 1,
			'unit_price'  => 80,
			'sort_order'  => 2,
		)
	);

	$invoice = $invoice_service->get_invoice( (int) $invoice_id );
}

if ( ! $invoice || ! is_array( $invoice ) ) {
	sm_seed_fail( 'QA invoice could not be resolved after creation.' );
}

$invoice_total      = isset( $invoice['grand_total'] ) ? round( (float) $invoice['grand_total'], 2 ) : 0.0;
$first_payment_sum  = round( $invoice_total * 0.6, 2 );
$second_payment_sum = round( $invoice_total - $first_payment_sum, 2 );

if ( $invoice_total > 0 && ! sm_seed_find_payment( $invoice_service, (int) $invoice['id'], 'SM-QA-PAY-001-A' ) ) {
	$payment_result = $invoice_service->add_payment(
		(int) $invoice['id'],
		array(
			'payment_date'   => '2026-03-26 10:00:00',
			'amount'         => $first_payment_sum,
			'payment_method' => 'card',
			'reference'      => 'SM-QA-PAY-001-A',
			'notes'          => 'QA partial payment A.',
			'received_by'    => $admin_id,
		)
	);

	if ( is_wp_error( $payment_result ) ) {
		sm_seed_fail( 'Failed creating first QA payment: ' . $payment_result->get_error_message() );
	}
}

if ( $invoice_total > 0 && $second_payment_sum > 0 && ! sm_seed_find_payment( $invoice_service, (int) $invoice['id'], 'SM-QA-PAY-001-B' ) ) {
	$payment_result = $invoice_service->add_payment(
		(int) $invoice['id'],
		array(
			'payment_date'   => '2026-03-27 10:00:00',
			'amount'         => $second_payment_sum,
			'payment_method' => 'transfer',
			'reference'      => 'SM-QA-PAY-001-B',
			'notes'          => 'QA partial payment B.',
			'received_by'    => $admin_id,
		)
	);

	if ( is_wp_error( $payment_result ) ) {
		sm_seed_fail( 'Failed creating second QA payment: ' . $payment_result->get_error_message() );
	}
}

$upload = wp_upload_bits(
	'sm-qa-intake.txt',
	null,
	"Super Mechanic QA dataset attachment.\nGenerated for Subfases 14-16.\n"
);

if ( ! empty( $upload['error'] ) ) {
	sm_seed_fail( 'Failed creating QA upload asset: ' . $upload['error'] );
}

$attachment = sm_seed_find_attachment( $attachment_service, (int) $process_alpha['id'], 'SM QA Intake Sheet' );

if ( ! $attachment ) {
	$attachment_id = $attachment_service->create_attachment(
		array(
			'object_type'       => 'process',
			'object_id'         => (int) $process_alpha['id'],
			'process_id'        => (int) $process_alpha['id'],
			'client_id'         => (int) $client_alpha['id'],
			'vehicle_id'        => (int) $vehicle_alpha['id'],
			'attachment_type'   => 'document',
			'title'             => 'SM QA Intake Sheet',
			'description'       => 'QA attachment baseline.',
			'file_url'          => $upload['url'],
			'file_path'         => $upload['file'],
			'mime_type'         => $upload['type'],
			'file_size'         => file_exists( $upload['file'] ) ? (int) filesize( $upload['file'] ) : 0,
			'is_internal'       => 0,
			'is_client_visible' => 1,
			'uploaded_by'       => $admin_id,
		)
	);

	if ( is_wp_error( $attachment_id ) ) {
		sm_seed_fail( 'Failed creating QA attachment: ' . $attachment_id->get_error_message() );
	}

	$attachment = $attachment_service->get_attachment( (int) $attachment_id );
}

$internal_comment_text = 'SM QA internal note for operational regression checks.';
$client_comment_text   = 'SM QA customer-visible status update.';

if ( ! sm_seed_find_comment( $comment_service, (int) $process_alpha['id'], $internal_comment_text ) ) {
	$comment_result = $comment_service->create_comment(
		array(
			'object_type'    => 'process',
			'object_id'      => (int) $process_alpha['id'],
			'process_id'     => (int) $process_alpha['id'],
			'client_id'      => (int) $client_alpha['id'],
			'vehicle_id'     => (int) $vehicle_alpha['id'],
			'author_user_id' => $admin_id,
			'comment_type'   => 'internal_note',
			'content'        => $internal_comment_text,
			'is_internal'    => 1,
			'status'         => 'published',
		)
	);

	if ( is_wp_error( $comment_result ) ) {
		sm_seed_fail( 'Failed creating internal QA comment: ' . $comment_result->get_error_message() );
	}
}

if ( ! sm_seed_find_comment( $comment_service, (int) $process_alpha['id'], $client_comment_text ) ) {
	$comment_result = $comment_service->create_comment(
		array(
			'object_type'       => 'process',
			'object_id'         => (int) $process_alpha['id'],
			'process_id'        => (int) $process_alpha['id'],
			'client_id'         => (int) $client_alpha['id'],
			'vehicle_id'        => (int) $vehicle_alpha['id'],
			'author_user_id'    => $admin_id,
			'comment_type'      => 'staff_reply',
			'content'           => $client_comment_text,
			'is_client_visible' => 1,
			'status'            => 'published',
		)
	);

	if ( is_wp_error( $comment_result ) ) {
		sm_seed_fail( 'Failed creating client-visible QA comment: ' . $comment_result->get_error_message() );
	}
}

if ( ! sm_seed_find_notification( $notification_service, (int) $client_alpha['id'], 'QA Reminder', (int) $process_alpha['id'] ) ) {
	$notification_result = $notification_service->create_notification(
		array(
			'recipient_type'    => 'client',
			'recipient_id'      => (int) $client_alpha['id'],
			'object_type'       => 'process',
			'object_id'         => (int) $process_alpha['id'],
			'process_id'        => (int) $process_alpha['id'],
			'notification_type' => 'reminder',
			'title'             => 'QA Reminder',
			'message'           => 'Review the active maintenance process in the pre-API QA dataset.',
			'data_json'         => array(
				'source' => 'subfases_14_16',
			),
			'is_system'         => 1,
		)
	);

	if ( is_wp_error( $notification_result ) ) {
		sm_seed_fail( 'Failed creating QA reminder notification: ' . $notification_result->get_error_message() );
	}
}

$invoice       = $invoice_service->get_invoice( (int) $invoice['id'] );
$payments      = $invoice_service->get_payments( (int) $invoice['id'] );
$comments      = $comment_service->get_process_comments( (int) $process_alpha['id'], array( 'per_page' => 200 ) );
$notifications = $notification_service->get_client_notifications( (int) $client_alpha['id'], array( 'per_page' => 200 ) );
$current_owner = ( new Client_Vehicle_Repository() )->get_current_owner( (int) $vehicle_alpha['id'] );

$relationships_consistent = $current_owner
	&& (int) $current_owner['client_id'] === (int) $client_alpha['id']
	&& (int) $process_alpha['client_id'] === (int) $client_alpha['id']
	&& (int) $process_alpha['vehicle_id'] === (int) $vehicle_alpha['id']
	&& $quote
	&& (int) $quote['process_id'] === (int) $process_alpha['id']
	&& $invoice
	&& (int) $invoice['process_id'] === (int) $process_alpha['id']
	&& $attachment
	&& (int) $attachment['process_id'] === (int) $process_alpha['id'];

$runtime_impact_ok = $settings_loaded
	&& (int) $process_alpha['id'] > 0
	&& is_array( $payments )
	&& count( $payments ) >= 2
	&& isset( $invoice['status'] )
	&& in_array( $invoice['status'], array( 'paid', 'partially_paid' ), true );

$summary = array(
	'settings_guardan'        => $settings_saved,
	'settings_cargan'         => $settings_loaded,
	'impacto_runtime_actual'  => $runtime_impact_ok,
	'clientes_creados'        => (int) $client_alpha['id'] > 0 && (int) $client_beta['id'] > 0,
	'vehiculos_creados'       => (int) $vehicle_alpha['id'] > 0 && (int) $vehicle_beta['id'] > 0,
	'procesos_creados'        => (int) $process_alpha['id'] > 0 && (int) $process_beta['id'] > 0,
	'quotes_creadas'          => ! empty( $quote['id'] ),
	'invoices_creadas'        => ! empty( $invoice['id'] ),
	'payments_creados'        => is_array( $payments ) && count( $payments ) >= 1,
	'adjuntos_creados'        => ! empty( $attachment['id'] ),
	'comments_creados'        => is_array( $comments ) && count( $comments ) >= 2,
	'notifications_creadas'   => is_array( $notifications ) && count( $notifications ) >= 1,
	'relaciones_consistentes' => $relationships_consistent,
);

$failed = array_filter(
	$summary,
	static function ( $value ) {
		return true !== $value;
	}
);

sm_seed_out( 'Subfases 14-16 QA dataset summary' );

foreach ( $summary as $label => $result ) {
	sm_seed_out( '- ' . $label . ': ' . ( $result ? 'OK' : 'FAIL' ) );
}

sm_seed_out( '- client_alpha_id: ' . (int) $client_alpha['id'] );
sm_seed_out( '- client_beta_id: ' . (int) $client_beta['id'] );
sm_seed_out( '- vehicle_alpha_id: ' . (int) $vehicle_alpha['id'] );
sm_seed_out( '- vehicle_beta_id: ' . (int) $vehicle_beta['id'] );
sm_seed_out( '- process_alpha_id: ' . (int) $process_alpha['id'] );
sm_seed_out( '- process_beta_id: ' . (int) $process_beta['id'] );
sm_seed_out( '- quote_id: ' . (int) $quote['id'] );
sm_seed_out( '- invoice_id: ' . (int) $invoice['id'] );
sm_seed_out( '- payment_count: ' . count( $payments ) );
sm_seed_out( '- attachment_id: ' . ( ! empty( $attachment['id'] ) ? (int) $attachment['id'] : 0 ) );

exit( empty( $failed ) ? 0 : 1 );

