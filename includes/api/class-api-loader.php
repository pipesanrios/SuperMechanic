<?php
/**
 * API loader.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\API;

use Super_Mechanic\API\Controllers\Public_API_Controller;
use Super_Mechanic\Clients\Client_Service;
use Super_Mechanic\Helpers\Access_Control_Service;
use Super_Mechanic\Helpers\Business_Context_Service;
use Super_Mechanic\Invoices\Invoice_Service;
use Super_Mechanic\Processes\Process_Service;
use Super_Mechanic\Quotes\Quote_Service;
use Super_Mechanic\Reporting\Reporting_Service;
use Super_Mechanic\Vehicles\Vehicle_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the formal public API layer.
 */
class API_Loader {
	/**
	 * API controller.
	 *
	 * @var Public_API_Controller
	 */
	protected $public_api_controller;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->public_api_controller = new Public_API_Controller(
			new Client_Service(),
			new Vehicle_Service(),
			new Process_Service(),
			new Invoice_Service(),
			new Reporting_Service(),
			new Quote_Service(),
			new Access_Control_Service(),
			new Business_Context_Service()
		);
	}

	/**
	 * Register API hooks.
	 *
	 * @return void
	 */
	public function register_hooks() {
		$this->public_api_controller->register_hooks();
	}
}

