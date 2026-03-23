<?php
/**
 * Database seeder extension point.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Provides a future hook for default data seeding.
 */
class Seeder {
	/**
	 * Seed default plugin data.
	 *
	 * Reserved for future defaults such as sale_delivery,
	 * maintenance, and administrative_documents flows.
	 *
	 * @return void
	 */
	public static function seed_defaults() {
		// Intentionally empty until default flows are introduced.
	}
}
