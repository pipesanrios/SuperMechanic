<?php
/**
 * Roles access controller compatibility wrapper.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Admin;

use Super_Mechanic\Users\Admin_Roles_Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Compatibility controller mapped to users admin controller.
 */
class Roles_Access_Controller extends Admin_Roles_Controller {
}

