<?php
/**
 * Elementor integration loader.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Integrations\Elementor;

use Super_Mechanic\Integrations\Elementor\Widgets\Client_Dashboard_Widget;
use Super_Mechanic\Integrations\Elementor\Widgets\Client_Invoices_Widget;
use Super_Mechanic\Integrations\Elementor\Widgets\Client_Processes_Widget;
use Super_Mechanic\Integrations\Elementor\Widgets\Client_Quotes_Widget;
use Super_Mechanic\Integrations\Elementor\Widgets\Client_Vehicles_Widget;
use Super_Mechanic\Integrations\Elementor\Widgets\Mechanic_Dashboard_Widget;

defined( 'ABSPATH' ) || exit;

/**
 * Registers Super Mechanic widgets in Elementor.
 */
class Elementor_Loader {
	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
	}

	/**
	 * Register plugin widgets.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 * @return void
	 */
	public function register_widgets( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$widgets_manager->register( new Client_Dashboard_Widget() );
		$widgets_manager->register( new Client_Vehicles_Widget() );
		$widgets_manager->register( new Client_Processes_Widget() );
		$widgets_manager->register( new Client_Quotes_Widget() );
		$widgets_manager->register( new Client_Invoices_Widget() );
		$widgets_manager->register( new Mechanic_Dashboard_Widget() );
	}
}
