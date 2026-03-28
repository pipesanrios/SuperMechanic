<?php
/**
 * Main plugin class.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

use Super_Mechanic\Automation\Automation_Rule_Engine;
use Super_Mechanic\Automation\Automation_Service;
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
use Super_Mechanic\Clients\Client_Admin_Controller;
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
use Super_Mechanic\Relations\Client_Vehicle_Service;
use Super_Mechanic\Reports\Report_Admin_Controller;
use Super_Mechanic\Reports\Report_Service;
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
	protected $shortcode_admin_controller;
	protected $invoice_finance_admin_controller;
	protected $payment_finance_admin_controller;
	protected $client_process_view_service;
	protected $client_rest_controller;
	protected $admin_rest_controller;
	protected $update_service;
	protected $appointment_service;
	protected $appointment_admin_controller;
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

	public function __construct() {
		$this->assets                        = new Assets();
		$this->settings                      = new Settings();
		$this->settings_service              = new Settings_Service();
		$this->update_service                = new Update_Service( $this->settings_service );
		$this->business_service              = new Business_Service();
		$this->business_context_service      = new Business_Context_Service( $this->settings_service, $this->business_service );
		$this->business_admin_controller     = new Business_Admin_Controller( $this->business_service, $this->business_context_service, $this->settings_service );
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
		$this->shortcode_admin_controller    = new Shortcode_Admin_Controller();
		$this->attachment_service            = new Attachment_Service( null, $this->process_service, $this->dashboard_service, $this->quote_service, $this->invoice_service );
		$this->document_service              = new Document_Service( $this->pdf_service, $this->attachment_service, $this->invoice_service, $this->quote_service );
		$this->download_service              = new Download_Service( $this->document_service );
		$this->notification_service          = new Notification_Service( null, $this->dashboard_service, $this->process_service, $this->quote_service, $this->invoice_service, $this->attachment_service );
		$this->event_dispatcher              = Event_Dispatcher::get_instance( $this->notification_service, $this->document_service );
		$this->appointment_service->set_event_dispatcher( $this->event_dispatcher );
		$this->appointment_reminder_scheduler = new Appointment_Reminder_Scheduler( $this->appointment_service, $this->notification_service, $this->event_dispatcher, $this->settings_service );
		$this->automation_rule_engine       = new Automation_Rule_Engine( $this->settings_service );
		$this->automation_service           = new Automation_Service( $this->automation_rule_engine, $this->appointment_reminder_scheduler, $this->settings_service );
		$this->comment_service               = new Comment_Service( null, $this->dashboard_service, $this->process_service, $this->quote_service, $this->invoice_service, $this->attachment_service, $this->event_dispatcher );
		$this->client_process_view_service   = new Client_Process_View_Service( $this->dashboard_service, $this->quote_service, $this->invoice_service, $this->comment_service );
		$this->client_rest_controller        = new Client_REST_Controller( $this->dashboard_service, $this->client_process_view_service, null, $this->quote_service, $this->process_service, $this->invoice_service );
		$this->admin_rest_controller         = new Admin_REST_Controller( $this->process_service, null, null, $this->quote_service, $this->invoice_service, $this->comment_service );
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
			$this->appointment_admin_controller
		);
	}

	public function init() {
		$this->maybe_upgrade_schema();
		$this->register_hooks();
	}

	public function register_hooks() {
		$this->assets->register_hooks();
		$this->event_dispatcher->register_hooks();
		$this->download_service->register_hooks();
		$this->update_service->register_hooks();
		$this->google_calendar_service->register_hooks();
		$this->appointment_reminder_scheduler->register_hooks();
		$this->automation_service->register_hooks();

		if ( is_admin() ) {
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
			$this->google_calendar_auth_controller->register_hooks();
			$this->mechanic_dashboard_controller->register_hooks();
			$this->business_admin_controller->register_hooks();
		}

		$this->client_dashboard_shortcodes->register_hooks();
		$this->mechanic_dashboard_shortcodes->register_hooks();
		$this->client_attachment_shortcodes->register_hooks();
		$this->client_quote_shortcodes->register_hooks();
		$this->client_invoice_shortcodes->register_hooks();
		$this->client_comment_shortcodes->register_hooks();
		$this->client_rest_controller->register_hooks();
		$this->admin_rest_controller->register_hooks();
		$this->appointment_ical_feed_controller->register_hooks();
		$this->google_calendar_webhook_controller->register_hooks();
	}

	protected function maybe_upgrade_schema() {
		$results = Migrator::maybe_upgrade();

		if ( ! empty( $results ) ) {
			update_option( Installer::DB_VERSION_OPTION, Schema::get_schema_version() );
		}
	}
}
