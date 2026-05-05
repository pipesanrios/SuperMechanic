<?php
/**
 * Main plugin class.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Automation\Automation_Rule_Engine;
use Super_Mechanic\Automation\Automation_Service;
use Super_Mechanic\API\API_Loader;
use Super_Mechanic\Admin\Notifications_Admin_Controller;
use Super_Mechanic\Admin\License_Admin_Controller;
use Super_Mechanic\Admin\Branding_Admin_Controller;
use Super_Mechanic\Admin\Onboarding_Admin_Controller;
use Super_Mechanic\Admin\Webhooks_Admin_Controller;
use Super_Mechanic\Admin\Connectors_Admin_Controller;
use Super_Mechanic\Admin\Export_Admin_Controller;
use Super_Mechanic\Admin\Dashboard_Admin_Controller;
use Super_Mechanic\Admin\Reporting_Admin_Controller;
use Super_Mechanic\Admin\Demo_Admin_Controller;
use Super_Mechanic\Appointments\Appointment_Admin_Controller;
use Super_Mechanic\Appointments\Appointment_Ical_Feed_Controller;
use Super_Mechanic\Appointments\Appointment_Ical_Feed_Service;
use Super_Mechanic\Appointments\Appointment_Reminder_Scheduler;
use Super_Mechanic\Appointments\Appointment_Service;
use Super_Mechanic\Attachments\Attachment_Admin_Controller;
use Super_Mechanic\Attachments\Attachment_Service;
use Super_Mechanic\Attachments\Client_Attachment_Shortcodes;
use Super_Mechanic\Attachments\Process_Timeline_Service;
use Super_Mechanic\Businesses\Business_Admin_Controller;
use Super_Mechanic\Businesses\Business_Service;
use Super_Mechanic\Businesses\Business_User_Assignment_Controller;
use Super_Mechanic\Clients\Client_Admin_Controller;
use Super_Mechanic\CRM\Crm_Pipeline_Admin_Controller;
use Super_Mechanic\CRM\Crm_Scheduler_Service;
use Super_Mechanic\Communication\Client_Comment_Shortcodes;
use Super_Mechanic\Communication\Comment_Service;
use Super_Mechanic\Communication\Event_Dispatcher;
use Super_Mechanic\Communication\Notification_Service;
use Super_Mechanic\Dashboard\Admin_Dashboard_Controller;
use Super_Mechanic\Dashboard\Admin_REST_Controller;
use Super_Mechanic\Dashboard\Client_Dashboard_Controller;
use Super_Mechanic\Dashboard\Client_Dashboard_Shortcodes;
use Super_Mechanic\Dashboard\Client_REST_Controller;
use Super_Mechanic\Dashboard\Client_Process_View_Service;
use Super_Mechanic\Dashboard\Dashboard_Service;
use Super_Mechanic\Dashboard\Mechanic_Dashboard_Shortcodes;
use Super_Mechanic\Dashboard\Mechanic_Dashboard_Controller;
use Super_Mechanic\Database\Migrator;
use Super_Mechanic\Database\Schema;
use Super_Mechanic\Helpers\Document_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Flows\Flow_Admin_Controller;
use Super_Mechanic\Helpers\Download_Service;
use Super_Mechanic\Helpers\Feed_Token_Service;
use Super_Mechanic\Helpers\PDF_Service;
use Super_Mechanic\Helpers\Settings_Service;
use Super_Mechanic\Helpers\Update_Service;
use Super_Mechanic\Invoices\Client_Invoice_Shortcodes;
use Super_Mechanic\Invoices\Invoice_Admin_Controller;
use Super_Mechanic\Invoices\Invoice_Finance_Admin_Controller;
use Super_Mechanic\Invoices\Payment_Finance_Admin_Controller;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Auth_Controller;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Client;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Sync_Repository;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Sync_Service;
use Super_Mechanic\Integrations\Google_Calendar\Google_Calendar_Webhook_Controller;
use Super_Mechanic\Integrations\Public_API\Public_API_Auth_Service;
use Super_Mechanic\Integrations\Public_API\Public_API_Service;
use Super_Mechanic\Integrations\Public_API\Public_Webhook_Delivery_Service;
use Super_Mechanic\Integrations\Public_API\Public_Webhook_Event_Catalog;
use Super_Mechanic\Integrations\Public_API\Public_Webhook_Repository;
use Super_Mechanic\Integrations\Public_API\Public_Webhook_Service;
use Super_Mechanic\Integrations\Public_API\Public_REST_Controller;
use Super_Mechanic\Integrations\Elementor\Elementor_Loader;
use Super_Mechanic\Integrations\Connectors\Connector_Service;
use Super_Mechanic\Licensing\License_Service;
use Super_Mechanic\Maintenance\Maintenance_Admin_Controller;
use Super_Mechanic\Maintenance\Maintenance_Service;
use Super_Mechanic\Paperwork\Paperwork_Admin_Controller;
use Super_Mechanic\Paperwork\Paperwork_Service;
use Super_Mechanic\Pre_Delivery\Pre_Delivery_Admin_Controller;
use Super_Mechanic\Pre_Delivery\Pre_Delivery_Service;
use Super_Mechanic\Processes\Process_Admin_Controller;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Client_Quote_Shortcodes;
use Super_Mechanic\Quotes\Quote_Admin_Controller;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Queue\Queue_Service;
use Super_Mechanic\Relations\Client_Vehicle_Service;
use Super_Mechanic\Reports\Report_Admin_Controller;
use Super_Mechanic\Reports\Report_Service;
use Super_Mechanic\Reporting\Reporting_Service;
use Super_Mechanic\Demo\Demo_Service;
use Super_Mechanic\Services\Email_Trigger_Service;
use Super_Mechanic\Users\Superadmin_Bootstrap_Service;
use Super_Mechanic\Vehicles\Vehicle_Admin_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin class.
 */
class Plugin {
	protected $assets;
	protected $settings;
	protected $settings_service;
	protected $business_context_service;
	protected $business_service;
	protected $business_admin_controller;
	protected $business_user_assignment_controller;
	protected $admin_menu;
	protected $client_admin_controller;
	protected $vehicle_admin_controller;
	protected $process_admin_controller;
	protected $flow_admin_controller;
	protected $maintenance_service;
	protected $maintenance_admin_controller;
	protected $pre_delivery_service;
	protected $pre_delivery_admin_controller;
	protected $paperwork_service;
	protected $paperwork_admin_controller;
	protected $client_vehicle_service;
	protected $dashboard_service;
	protected $admin_dashboard_controller;
	protected $mechanic_dashboard_controller;
	protected $mechanic_dashboard_shortcodes;
	protected $client_dashboard_controller;
	protected $client_dashboard_shortcodes;
	protected $quote_service;
	protected $quote_admin_controller;
	protected $client_quote_shortcodes;
	protected $invoice_service;
	protected $pdf_service;
	protected $document_service;
	protected $download_service;
	protected $invoice_admin_controller;
	protected $client_invoice_shortcodes;
	protected $attachment_service;
	protected $process_timeline_service;
	protected $attachment_admin_controller;
	protected $client_attachment_shortcodes;
	protected $comment_service;
	protected $notification_service;
	protected $event_dispatcher;
	protected $client_comment_shortcodes;
	protected $process_service;
	protected $report_service;
	protected $report_admin_controller;
	protected $reporting_service;
	protected $reporting_admin_controller;
	protected $shortcode_admin_controller;
	protected $invoice_finance_admin_controller;
	protected $payment_finance_admin_controller;
	protected $client_process_view_service;
	protected $client_rest_controller;
	protected $admin_rest_controller;
	protected $public_api_auth_service;
	protected $public_api_service;
	protected $public_rest_controller;
	protected $public_webhook_repository;
	protected $public_webhook_event_catalog;
	protected $public_webhook_delivery_service;
	protected $public_webhook_service;
	protected $update_service;
	protected $appointment_service;
	protected $appointment_admin_controller;
	protected $crm_pipeline_admin_controller;
	protected $crm_scheduler_service;
	protected $appointment_ical_feed_service;
	protected $feed_token_service;
	protected $appointment_ical_feed_controller;
	protected $google_calendar_client;
	protected $google_calendar_service;
	protected $google_calendar_sync_repository;
	protected $google_calendar_sync_service;
	protected $google_calendar_auth_controller;
	protected $google_calendar_webhook_controller;
	protected $appointment_reminder_scheduler;
	protected $automation_rule_engine;
	protected $automation_service;
	protected $notifications_admin_controller;
	protected $license_admin_controller;
	protected $license_service;
	protected $branding_admin_controller;
	protected $onboarding_admin_controller;
	protected $webhooks_admin_controller;
	protected $connector_service;
	protected $connectors_admin_controller;
	protected $export_admin_controller;
	protected $dashboard_admin_controller;
	protected $demo_service;
	protected $demo_admin_controller;
	protected $queue_service;
	protected $elementor_loader;
	protected $api_loader;
	protected $superadmin_bootstrap_service;
	protected $email_trigger_service;

	public function __construct() {
		$this->assets                        = new Assets();
		$this->settings                      = new Settings();
		$this->settings_service              = new Settings_Service();
		$this->update_service                = new Update_Service( $this->settings_service );
		$this->business_service              = new Business_Service();
		$this->business_context_service      = new Business_Context_Service( $this->settings_service, $this->business_service );
		$this->business_admin_controller     = new Business_Admin_Controller( $this->business_service, $this->business_context_service, $this->settings_service );
		$this->business_user_assignment_controller = new Business_User_Assignment_Controller( $this->business_service, $this->business_context_service );
		$this->appointment_ical_feed_service = new Appointment_Ical_Feed_Service();
		$this->feed_token_service            = new Feed_Token_Service();
		$this->google_calendar_client        = new Google_Calendar_Client();
		$this->google_calendar_service       = new Google_Calendar_Service( $this->settings_service, $this->google_calendar_client );
		$this->google_calendar_sync_repository = new Google_Calendar_Sync_Repository();
		$this->google_calendar_sync_service  = new Google_Calendar_Sync_Service( $this->google_calendar_service, $this->google_calendar_sync_repository );
		$this->google_calendar_service->set_sync_service( $this->google_calendar_sync_service );
		$this->google_calendar_auth_controller = new Google_Calendar_Auth_Controller( $this->google_calendar_service );
		$this->google_calendar_webhook_controller = new Google_Calendar_Webhook_Controller( $this->google_calendar_service );
		$this->appointment_service           = new Appointment_Service( null, null, null, null, $this->google_calendar_sync_service );
		$this->google_calendar_sync_service->set_appointment_service( $this->appointment_service );
		$this->appointment_admin_controller  = new Appointment_Admin_Controller( $this->appointment_service );
		$this->appointment_ical_feed_controller = new Appointment_Ical_Feed_Controller( $this->appointment_service, $this->appointment_ical_feed_service, $this->feed_token_service );
		$this->client_admin_controller       = new Client_Admin_Controller();
		$this->crm_pipeline_admin_controller = new Crm_Pipeline_Admin_Controller();
		$this->crm_scheduler_service         = new Crm_Scheduler_Service();
		$this->vehicle_admin_controller      = new Vehicle_Admin_Controller();
		$this->process_service               = new Process_Service( null, null, null, null, null, null, null, null, null, null, $this->settings_service );
		$this->maintenance_service           = new Maintenance_Service();
		$this->pre_delivery_service          = new Pre_Delivery_Service();
		$this->paperwork_service             = new Paperwork_Service();
		$this->quote_service                 = new Quote_Service( null, null, $this->process_service, $this->maintenance_service, null, null, null, null, $this->settings_service );
		$this->invoice_service               = new Invoice_Service( null, null, null, $this->quote_service, null, null, null, $this->settings_service );
		$this->pdf_service                   = new PDF_Service( $this->invoice_service, $this->quote_service );
		$this->dashboard_service             = new Dashboard_Service( null, null, $this->process_service );
		$this->report_service                = new Report_Service( null, $this->process_service );
		$this->reporting_service             = new Reporting_Service();
		$this->shortcode_admin_controller    = new Shortcode_Admin_Controller();
		$this->attachment_service            = new Attachment_Service( null, $this->process_service, $this->dashboard_service, $this->quote_service, $this->invoice_service );
		$this->document_service              = new Document_Service( $this->pdf_service, $this->attachment_service, $this->invoice_service, $this->quote_service );
		$this->download_service              = new Download_Service( $this->document_service );
		$this->notification_service          = new Notification_Service( null, $this->dashboard_service, $this->process_service, $this->quote_service, $this->invoice_service, $this->attachment_service );
		$this->email_trigger_service         = new Email_Trigger_Service( null, $this->invoice_service );
		$this->public_webhook_repository     = new Public_Webhook_Repository();
		$this->public_webhook_event_catalog  = new Public_Webhook_Event_Catalog();
		$this->public_webhook_delivery_service = new Public_Webhook_Delivery_Service();
		$this->public_webhook_service        = new Public_Webhook_Service( $this->settings_service, $this->public_webhook_repository, $this->public_webhook_delivery_service, $this->public_webhook_event_catalog );
		$this->event_dispatcher              = Event_Dispatcher::get_instance( $this->notification_service, $this->document_service, $this->public_webhook_service );
		$this->appointment_service->set_event_dispatcher( $this->event_dispatcher );
		$this->appointment_reminder_scheduler = new Appointment_Reminder_Scheduler( $this->appointment_service, $this->notification_service, $this->event_dispatcher, $this->settings_service );
		$this->automation_rule_engine       = new Automation_Rule_Engine( $this->settings_service );
		$this->automation_service           = new Automation_Service( $this->automation_rule_engine, $this->appointment_reminder_scheduler, $this->settings_service );
		$this->queue_service               = new Queue_Service();
		$this->comment_service               = new Comment_Service( null, $this->dashboard_service, $this->process_service, $this->quote_service, $this->invoice_service, $this->attachment_service, $this->event_dispatcher );
		$this->client_process_view_service   = new Client_Process_View_Service( $this->dashboard_service, $this->quote_service, $this->invoice_service, $this->comment_service );
		$this->client_rest_controller        = new Client_REST_Controller( $this->dashboard_service, $this->client_process_view_service, null, $this->quote_service, $this->process_service, $this->invoice_service );
		$this->admin_rest_controller         = new Admin_REST_Controller( $this->process_service, null, null, $this->quote_service, $this->invoice_service, $this->comment_service );
		$this->public_api_auth_service       = new Public_API_Auth_Service( $this->settings_service, $this->business_service );
		$this->public_api_service            = new Public_API_Service( $this->business_service, $this->process_service, $this->appointment_service );
		$this->public_rest_controller        = new Public_REST_Controller( $this->public_api_auth_service, $this->public_api_service );
		$this->process_timeline_service      = new Process_Timeline_Service( $this->process_service, $this->attachment_service, $this->quote_service, $this->invoice_service, $this->comment_service, $this->notification_service );
		$this->maintenance_admin_controller  = new Maintenance_Admin_Controller( $this->maintenance_service );
		$this->pre_delivery_admin_controller = new Pre_Delivery_Admin_Controller( $this->pre_delivery_service );
		$this->paperwork_admin_controller    = new Paperwork_Admin_Controller( $this->paperwork_service );
		$this->attachment_admin_controller   = new Attachment_Admin_Controller( $this->attachment_service, $this->process_timeline_service, $this->process_service, $this->download_service );
		$this->quote_admin_controller        = new Quote_Admin_Controller( $this->quote_service, $this->invoice_service, $this->pdf_service );
		$this->invoice_admin_controller      = new Invoice_Admin_Controller( $this->invoice_service, $this->pdf_service );
		$this->process_admin_controller      = new Process_Admin_Controller(
			$this->process_service,
			$this->maintenance_admin_controller,
			$this->pre_delivery_admin_controller,
			$this->paperwork_admin_controller,
			$this->quote_admin_controller,
			$this->invoice_admin_controller,
			$this->attachment_admin_controller,
			$this->comment_service,
			$this->notification_service
		);
		$this->flow_admin_controller         = new Flow_Admin_Controller();
		$this->client_vehicle_service        = new Client_Vehicle_Service();
		$this->admin_dashboard_controller    = new Admin_Dashboard_Controller( $this->dashboard_service );
		$this->report_admin_controller       = new Report_Admin_Controller( $this->report_service );
		$this->invoice_finance_admin_controller = new Invoice_Finance_Admin_Controller( $this->invoice_service, $this->pdf_service, $this->download_service );
		$this->payment_finance_admin_controller = new Payment_Finance_Admin_Controller( $this->invoice_service, null, $this->download_service );
		$this->mechanic_dashboard_controller = new Mechanic_Dashboard_Controller( $this->dashboard_service, $this->process_service, $this->process_timeline_service, $this->comment_service, $this->attachment_service, $this->maintenance_service, null, $this->download_service );
		$this->mechanic_dashboard_shortcodes = new Mechanic_Dashboard_Shortcodes( $this->mechanic_dashboard_controller );
		$this->client_dashboard_controller   = new Client_Dashboard_Controller( $this->dashboard_service, $this->quote_service, $this->invoice_service, $this->attachment_service, $this->process_timeline_service, $this->comment_service, $this->notification_service, $this->download_service, $this->client_process_view_service );
		$this->client_dashboard_shortcodes   = new Client_Dashboard_Shortcodes( $this->client_dashboard_controller, $this->dashboard_service );
		$this->client_attachment_shortcodes  = new Client_Attachment_Shortcodes( $this->client_dashboard_controller, $this->dashboard_service, $this->attachment_service, $this->download_service );
		$this->client_quote_shortcodes       = new Client_Quote_Shortcodes( $this->quote_service, $this->dashboard_service, $this->download_service );
		$this->client_invoice_shortcodes     = new Client_Invoice_Shortcodes( $this->invoice_service, $this->dashboard_service, $this->download_service );
		$this->client_comment_shortcodes     = new Client_Comment_Shortcodes( $this->comment_service, $this->notification_service, $this->dashboard_service, $this->client_dashboard_controller );
		$this->notifications_admin_controller = new Notifications_Admin_Controller();
		$this->license_service                = new License_Service();
		$this->license_admin_controller       = new License_Admin_Controller();
		$this->branding_admin_controller      = new Branding_Admin_Controller();
		$this->onboarding_admin_controller    = new Onboarding_Admin_Controller();
		$this->webhooks_admin_controller      = new Webhooks_Admin_Controller();
		$this->connector_service              = new Connector_Service();
		$this->connectors_admin_controller    = new Connectors_Admin_Controller( $this->connector_service );
		$this->export_admin_controller        = new Export_Admin_Controller();
		$this->dashboard_admin_controller     = new Dashboard_Admin_Controller( $this->dashboard_service );
		$this->reporting_admin_controller     = new Reporting_Admin_Controller( $this->reporting_service );
		$this->demo_service                   = new Demo_Service();
		$this->demo_admin_controller          = new Demo_Admin_Controller( $this->demo_service );
		$this->elementor_loader               = did_action( 'elementor/loaded' ) ? new Elementor_Loader() : null;
		$this->api_loader                     = new API_Loader();
		$this->superadmin_bootstrap_service   = new Superadmin_Bootstrap_Service();
		$this->admin_menu                    = new Admin_Menu(
			$this->settings,
			$this->client_admin_controller,
			$this->vehicle_admin_controller,
			$this->process_admin_controller,
			$this->flow_admin_controller,
			$this->admin_dashboard_controller,
			$this->mechanic_dashboard_controller,
			$this->report_admin_controller,
			$this->shortcode_admin_controller,
			$this->invoice_finance_admin_controller,
			$this->payment_finance_admin_controller,
			$this->appointment_admin_controller,
			$this->crm_pipeline_admin_controller
		);
	}

	public function init() {
		$this->maybe_upgrade_schema();
		$this->superadmin_bootstrap_service->ensure_bootstrap_superadmin();
		$this->register_hooks();
	}

	public function register_hooks() {
		$this->assets->register_hooks();
		$this->event_dispatcher->register_hooks();
		$this->email_trigger_service->register_hooks();
		$this->public_webhook_service->register_hooks();
		$this->download_service->register_hooks();
		$this->update_service->register_hooks();
		$this->google_calendar_service->register_hooks();
		$this->appointment_reminder_scheduler->register_hooks();
		$this->automation_service->register_hooks();
		$this->queue_service->register_hooks();
		$this->crm_scheduler_service->register_hooks();
		$this->connector_service->register_hooks();

		if ( is_admin() ) {
			add_action( 'admin_post_sm_business_save', array( $this, 'guard_business_creation_limit' ), 1 );
			add_action( 'admin_post_sm_save_webhook', array( $this, 'guard_webhook_creation_limit' ), 1 );
			add_action( 'admin_init', array( $this, 'guard_process_creation_limit' ), 1 );
			add_action( 'wp_ajax_sm_roles_membership_action', array( $this, 'guard_membership_creation_limit' ), 1 );
			add_action( 'admin_menu', array( $this->admin_menu, 'register_menu' ) );
			add_action( 'admin_init', array( $this->settings, 'register_settings' ) );
			$this->settings->register_hooks();
			$this->client_admin_controller->register_hooks();
			$this->vehicle_admin_controller->register_hooks();
			$this->maintenance_admin_controller->register_hooks();
			$this->pre_delivery_admin_controller->register_hooks();
			$this->paperwork_admin_controller->register_hooks();
			$this->quote_admin_controller->register_hooks();
			$this->invoice_admin_controller->register_hooks();
			$this->attachment_admin_controller->register_hooks();
			$this->process_admin_controller->register_hooks();
			$this->flow_admin_controller->register_hooks();
			$this->report_admin_controller->register_hooks();
			$this->invoice_finance_admin_controller->register_hooks();
			$this->payment_finance_admin_controller->register_hooks();
			$this->appointment_admin_controller->register_hooks();
			$this->crm_pipeline_admin_controller->register_hooks();
			$this->google_calendar_auth_controller->register_hooks();
			$this->mechanic_dashboard_controller->register_hooks();
			$this->business_admin_controller->register_hooks();
			$this->business_user_assignment_controller->register_hooks();
			$this->notifications_admin_controller->register_hooks();
			$this->license_admin_controller->register_hooks();
			$this->branding_admin_controller->register_hooks();
			$this->onboarding_admin_controller->register_hooks();
			$this->webhooks_admin_controller->register_hooks();
			$this->connectors_admin_controller->register_hooks();
			$this->export_admin_controller->register_hooks();
			$this->dashboard_admin_controller->register_hooks();
			$this->reporting_admin_controller->register_hooks();
			$this->demo_admin_controller->register_hooks();
		}

		$this->client_dashboard_shortcodes->register_hooks();
		$this->mechanic_dashboard_shortcodes->register_hooks();
		$this->client_attachment_shortcodes->register_hooks();
		$this->client_quote_shortcodes->register_hooks();
		$this->client_invoice_shortcodes->register_hooks();
		$this->client_comment_shortcodes->register_hooks();
		$this->appointment_admin_controller->register_rest_hooks();
		$this->client_rest_controller->register_hooks();
		$this->admin_rest_controller->register_hooks();
		$this->public_rest_controller->register_hooks();
		$this->api_loader->register_hooks();
		$this->appointment_ical_feed_controller->register_hooks();
		$this->google_calendar_webhook_controller->register_hooks();

		if ( $this->elementor_loader instanceof Elementor_Loader ) {
			$this->elementor_loader->register_hooks();
		}
	}

	/**
	 * Block business create when monetization limits deny creation.
	 *
	 * @return void
	 */
	public function guard_business_creation_limit() {
		if ( ! is_admin() || ! current_user_can( 'sm_manage_settings' ) ) {
			return;
		}

		$business_id = isset( $_POST['business_id'] ) ? absint( wp_unslash( $_POST['business_id'] ) ) : 0;
		if ( $business_id > 0 ) {
			return;
		}

		$allowed = $this->license_service->assert_resource_creation_allowed( 'business' );
		if ( ! is_wp_error( $allowed ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                => 'super-mechanic-businesses',
					'sm_business_notice'  => 'error',
					'sm_business_message' => $allowed->get_error_message(),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Block webhook create when monetization limits deny creation.
	 *
	 * @return void
	 */
	public function guard_webhook_creation_limit() {
		if ( ! is_admin() || ! current_user_can( 'sm_manage_plugin' ) ) {
			return;
		}

		$webhook_id = isset( $_POST['webhook_id'] ) ? absint( wp_unslash( $_POST['webhook_id'] ) ) : 0;
		if ( $webhook_id > 0 ) {
			return;
		}

		$allowed = $this->license_service->assert_resource_creation_allowed( 'webhook' );
		if ( ! is_wp_error( $allowed ) ) {
			return;
		}

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'super-mechanic-webhooks',
					'sm_notice_type'    => 'error',
					'sm_notice_code'    => 'save_failed',
					'sm_notice_message' => $allowed->get_error_message(),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Block process create when monetization limits deny creation.
	 *
	 * @return void
	 */
	public function guard_process_creation_limit() {
		if ( ! is_admin() || ! current_user_can( 'sm_manage_processes' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'super-mechanic-processes' !== sanitize_key( (string) wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) ) {
			return;
		}

		$operation = isset( $_POST['sm_process_operation'] ) ? sanitize_key( (string) wp_unslash( $_POST['sm_process_operation'] ) ) : '';
		if ( 'create' !== $operation ) {
			return;
		}

		$allowed = $this->license_service->assert_resource_creation_allowed( 'process' );
		if ( ! is_wp_error( $allowed ) ) {
			return;
		}

		set_transient(
			'sm_process_errors_' . get_current_user_id(),
			array( $allowed->get_error_message() ),
			MINUTE_IN_SECONDS
		);

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'      => 'super-mechanic-processes',
					'action'    => 'new',
					'sm_notice' => 'error',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Block membership create when monetization limits deny creation.
	 *
	 * @return void
	 */
	public function guard_membership_creation_limit() {
		if ( ! is_admin() || ! current_user_can( 'sm_manage_plugin' ) ) {
			return;
		}

		$membership_action = isset( $_POST['membership_action'] ) ? sanitize_key( (string) wp_unslash( $_POST['membership_action'] ) ) : '';
		if ( 'create_membership' !== $membership_action ) {
			return;
		}

		$allowed = $this->license_service->assert_resource_creation_allowed( 'users' );
		if ( ! is_wp_error( $allowed ) ) {
			return;
		}

		wp_send_json_error(
			array(
				'message' => $allowed->get_error_message(),
			),
			400
		);
	}

	protected function maybe_upgrade_schema() {
		$results = Migrator::maybe_upgrade();

		if ( ! empty( $results ) ) {
			update_option( Installer::DB_VERSION_OPTION, Schema::get_schema_version() );
		}
	}
}







