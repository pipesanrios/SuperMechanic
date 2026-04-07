<?php
/**
 * Audit service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Audit;

defined( 'ABSPATH' ) || exit;

/**
 * Centralized deep audit service.
 */
class Audit_Service {
	/**
	 * Repository dependency.
	 *
	 * @var Audit_Repository
	 */
	protected $repository;

	/**
	 * Constructor.
	 *
	 * @param Audit_Repository|null $repository Repository dependency.
	 */
	public function __construct( Audit_Repository $repository = null ) {
		$this->repository = $repository ? $repository : new Audit_Repository();
	}

	/**
	 * Create one audit row.
	 *
	 * @param string               $audit_type Audit type.
	 * @param string               $entity_type Entity type.
	 * @param int                  $entity_id Entity ID.
	 * @param string               $action Action.
	 * @param array<string,mixed>  $before Before snapshot.
	 * @param array<string,mixed>  $after After snapshot.
	 * @param array<string,mixed>  $context Context payload.
	 * @param int                  $actor_user_id Actor user ID.
	 * @param int                  $business_id Business ID.
	 * @return int
	 */
	public function create_audit( $audit_type, $entity_type, $entity_id, $action, array $before = array(), array $after = array(), array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		return $this->repository->insert_audit(
			array(
				'audit_type'    => sanitize_key( (string) $audit_type ),
				'entity_type'   => sanitize_key( (string) $entity_type ),
				'entity_id'     => absint( $entity_id ),
				'action'        => sanitize_key( (string) $action ),
				'actor_user_id' => absint( $actor_user_id ),
				'business_id'   => absint( $business_id ),
				'before_json'   => $this->sanitize_payload( $before ),
				'after_json'    => $this->sanitize_payload( $after ),
				'context_json'  => $this->sanitize_payload( $context ),
			)
		);
	}

	/**
	 * Membership shortcut.
	 *
	 * @param string               $action Action.
	 * @param int                  $membership_id Membership ID.
	 * @param array<string,mixed>  $before Before snapshot.
	 * @param array<string,mixed>  $after After snapshot.
	 * @param array<string,mixed>  $context Context payload.
	 * @param int                  $actor_user_id Actor user ID.
	 * @param int                  $business_id Business ID.
	 * @return int
	 */
	public function audit_membership_change( $action, $membership_id, array $before = array(), array $after = array(), array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		return $this->create_audit( 'membership', 'business_membership', $membership_id, $action, $before, $after, $context, $actor_user_id, $business_id );
	}

	/**
	 * License shortcut.
	 *
	 * @param string               $action Action.
	 * @param int                  $license_id License ID.
	 * @param array<string,mixed>  $before Before snapshot.
	 * @param array<string,mixed>  $after After snapshot.
	 * @param array<string,mixed>  $context Context payload.
	 * @param int                  $actor_user_id Actor user ID.
	 * @param int                  $business_id Business ID.
	 * @return int
	 */
	public function audit_license_change( $action, $license_id, array $before = array(), array $after = array(), array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		return $this->create_audit( 'licensing', 'license', $license_id, $action, $before, $after, $context, $actor_user_id, $business_id );
	}

	/**
	 * Branding shortcut.
	 *
	 * @param string               $action Action.
	 * @param array<string,mixed>  $before Before snapshot.
	 * @param array<string,mixed>  $after After snapshot.
	 * @param array<string,mixed>  $context Context payload.
	 * @param int                  $actor_user_id Actor user ID.
	 * @param int                  $business_id Business ID.
	 * @return int
	 */
	public function audit_branding_change( $action, array $before = array(), array $after = array(), array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		return $this->create_audit( 'branding', 'branding_settings', 1, $action, $before, $after, $context, $actor_user_id, $business_id );
	}

	/**
	 * Webhook shortcut.
	 *
	 * @param string               $action Action.
	 * @param int                  $webhook_id Webhook ID.
	 * @param array<string,mixed>  $before Before snapshot.
	 * @param array<string,mixed>  $after After snapshot.
	 * @param array<string,mixed>  $context Context payload.
	 * @param int                  $actor_user_id Actor user ID.
	 * @param int                  $business_id Business ID.
	 * @return int
	 */
	public function audit_webhook_change( $action, $webhook_id, array $before = array(), array $after = array(), array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		return $this->create_audit( 'webhook', 'webhook', $webhook_id, $action, $before, $after, $context, $actor_user_id, $business_id );
	}

	/**
	 * Recursively sanitize payload for JSON storage.
	 *
	 * @param mixed $value Input value.
	 * @return mixed
	 */
	protected function sanitize_payload( $value ) {
		if ( is_array( $value ) ) {
			$clean = array();
			foreach ( $value as $key => $item ) {
				$clean_key          = is_string( $key ) ? sanitize_key( $key ) : $key;
				$clean[ $clean_key ] = $this->sanitize_payload( $item );
			}
			return $clean;
		}

		if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) ) {
			return $value;
		}

		if ( null === $value ) {
			return null;
		}

		return sanitize_text_field( (string) $value );
	}
}

