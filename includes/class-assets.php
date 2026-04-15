<?php
/**
 * Assets manager.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and enqueues plugin UI assets.
 */
class Assets {
	const ADMIN_STYLE    = 'sm-admin-ui';
	const CLIENT_STYLE   = 'sm-client-ui';
	const MECHANIC_STYLE = 'sm-mechanic-ui';
	const ADMIN_SCRIPT   = 'sm-admin-ui';
	const CLIENT_SCRIPT  = 'sm-client-ui';
	const MECHANIC_SCRIPT = 'sm-mechanic-ui';
	const CALENDAR_VENDOR_SCRIPT = 'sm-fullcalendar-vendor';
	const CALENDAR_SCRIPT = 'sm-admin-calendar';

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'admin_enqueue_scripts', array( $this, 'register_admin_assets' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_frontend_assets' ) );
	}

	/**
	 * Register admin assets and enqueue them when needed.
	 *
	 * @return void
	 */
	public function register_admin_assets() {
		wp_register_style(
			self::ADMIN_STYLE,
			SM_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			file_exists( SM_PLUGIN_PATH . 'assets/css/admin.css' ) ? (string) filemtime( SM_PLUGIN_PATH . 'assets/css/admin.css' ) : SM_PLUGIN_VERSION
		);
		wp_register_style(
			self::MECHANIC_STYLE,
			SM_PLUGIN_URL . 'assets/css/mechanic.css',
			array( self::ADMIN_STYLE ),
			SM_PLUGIN_VERSION
		);
		wp_register_script(
			self::ADMIN_SCRIPT,
			SM_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			SM_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			self::MECHANIC_SCRIPT,
			SM_PLUGIN_URL . 'assets/js/mechanic.js',
			array(),
			SM_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			self::CALENDAR_VENDOR_SCRIPT,
			SM_PLUGIN_URL . 'assets/vendor/fullcalendar/fullcalendar-6.1.19.index.global.min.js',
			array(),
			SM_PLUGIN_VERSION,
			true
		);
		wp_register_script(
			self::CALENDAR_SCRIPT,
			SM_PLUGIN_URL . 'assets/js/admin-calendar.js',
			array( self::CALENDAR_VENDOR_SCRIPT ),
			file_exists( SM_PLUGIN_PATH . 'assets/js/admin-calendar.js' ) ? (string) filemtime( SM_PLUGIN_PATH . 'assets/js/admin-calendar.js' ) : SM_PLUGIN_VERSION,
			true
		);

		if ( ! $this->is_super_mechanic_admin_page() ) {
			return;
		}

		wp_enqueue_style( self::ADMIN_STYLE );
		wp_enqueue_script( self::ADMIN_SCRIPT );

		if ( $this->is_mechanic_page() ) {
			wp_enqueue_style( self::MECHANIC_STYLE );
			wp_enqueue_script( self::MECHANIC_SCRIPT );
		}

		if ( $this->is_calendar_page() ) {
			wp_enqueue_script( self::CALENDAR_SCRIPT );
			wp_localize_script(
				self::CALENDAR_SCRIPT,
				'smAdminCalendar',
				array(
					'restUrl'           => esc_url_raw( rest_url( 'super-mechanic/v1/admin/appointments/' ) ),
					'nonce'             => wp_create_nonce( 'wp_rest' ),
					'detailsBaseUrl'    => admin_url( 'admin.php?page=super-mechanic-appointments&action=edit&id=' ),
					'crmTaskDetailsBaseUrl' => admin_url( 'admin.php?page=super-mechanic-crm-pipeline&action=view&id=' ),
					'createBaseUrl'     => admin_url( 'admin.php?page=super-mechanic-appointments&action=new' ),
					'statusUpdateLabel' => __( 'Estado actualizado.', 'super-mechanic' ),
					'statusUpdateError' => __( 'No fue posible actualizar el estado.', 'super-mechanic' ),
					'moveUpdateLabel'   => __( 'Cita reprogramada.', 'super-mechanic' ),
					'moveUpdateError'   => __( 'No fue posible reprogramar la cita.', 'super-mechanic' ),
					'crmTaskMoveBlockedLabel' => __( 'Las tareas CRM no se reprograman desde el calendario en esta fase.', 'super-mechanic' ),
					'calendarLoadError' => __( 'No fue posible cargar el calendario.', 'super-mechanic' ),
					'statusOptions'     => array(
						'scheduled'   => __( 'Scheduled', 'super-mechanic' ),
						'confirmed'   => __( 'Confirmed', 'super-mechanic' ),
						'in_progress' => __( 'In progress', 'super-mechanic' ),
						'completed'   => __( 'Completed', 'super-mechanic' ),
						'cancelled'   => __( 'Cancelled', 'super-mechanic' ),
					),
				)
			);
		}
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_frontend_assets() {
		wp_register_style(
			self::CLIENT_STYLE,
			SM_PLUGIN_URL . 'assets/css/client.css',
			array(),
			SM_PLUGIN_VERSION
		);
		wp_register_script(
			self::CLIENT_SCRIPT,
			SM_PLUGIN_URL . 'assets/js/client.js',
			array(),
			SM_PLUGIN_VERSION,
			true
		);
	}

	/**
	 * Ensure client assets are loaded by shortcode renderers.
	 *
	 * @return void
	 */
	public static function enqueue_client_assets() {
		wp_enqueue_style( self::CLIENT_STYLE );
		wp_enqueue_script( self::CLIENT_SCRIPT );
	}

	/**
	 * Check whether the current admin page belongs to the plugin.
	 *
	 * @return bool
	 */
	protected function is_super_mechanic_admin_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		$page = sanitize_key( wp_unslash( $_GET['page'] ) );

		return 0 === strpos( $page, 'super-mechanic' );
	}

	/**
	 * Check whether the current admin page is the mechanic portal.
	 *
	 * @return bool
	 */
	protected function is_mechanic_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		return 'super-mechanic-mechanic-dashboard' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}

	/**
	 * Check whether the current admin page is the operational calendar page.
	 *
	 * @return bool
	 */
	protected function is_calendar_page() {
		if ( ! isset( $_GET['page'] ) ) {
			return false;
		}

		return 'super-mechanic-calendar' === sanitize_key( wp_unslash( $_GET['page'] ) );
	}
}
