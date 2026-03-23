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
			SM_PLUGIN_VERSION
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

		if ( ! $this->is_super_mechanic_admin_page() ) {
			return;
		}

		wp_enqueue_style( self::ADMIN_STYLE );
		wp_enqueue_script( self::ADMIN_SCRIPT );

		if ( $this->is_mechanic_page() ) {
			wp_enqueue_style( self::MECHANIC_STYLE );
			wp_enqueue_script( self::MECHANIC_SCRIPT );
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
}
