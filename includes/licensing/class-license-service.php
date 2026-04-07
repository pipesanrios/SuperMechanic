<?php
/**
 * License service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Licensing;

use Super_Mechanic\Audit\Audit_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Local license business rules.
 */
class License_Service {
	/**
	 * Supported statuses.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_STATUSES = array( 'active', 'inactive', 'expired', 'revoked' );

	/**
	 * Supported plans.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_PLANS = array( 'starter', 'pro', 'enterprise' );

	/**
	 * Repository dependency.
	 *
	 * @var License_Repository
	 */
	protected $repository;

	/**
	 * Audit service dependency.
	 *
	 * @var Audit_Service|null
	 */
	protected $audit_service;

	/**
	 * Constructor.
	 *
	 * @param License_Repository|null $repository Repository dependency.
	 * @param Audit_Service|null      $audit_service Audit service dependency.
	 */
	public function __construct( License_Repository $repository = null, Audit_Service $audit_service = null ) {
		$this->repository    = $repository ? $repository : new License_Repository();
		$this->audit_service = $audit_service;
	}

	/**
	 * Get normalized license row.
	 *
	 * @return array<string,mixed>
	 */
	public function get_license() {
		$row = $this->repository->get_license();
		if ( ! is_array( $row ) ) {
			return $this->get_default_license();
		}

		$status = sanitize_key( isset( $row['license_status'] ) ? (string) $row['license_status'] : 'inactive' );
		if ( ! in_array( $status, self::ALLOWED_STATUSES, true ) ) {
			$status = 'inactive';
		}

		$plan = sanitize_key( isset( $row['plan_type'] ) ? (string) $row['plan_type'] : 'starter' );
		if ( ! in_array( $plan, self::ALLOWED_PLANS, true ) ) {
			$plan = 'starter';
		}

		return array(
			'id'              => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'license_key'     => isset( $row['license_key'] ) ? sanitize_text_field( (string) $row['license_key'] ) : '',
			'license_status'  => $status,
			'domain'          => isset( $row['domain'] ) ? sanitize_text_field( (string) $row['domain'] ) : '',
			'plan_type'       => $plan,
			'expires_at'      => isset( $row['expires_at'] ) ? sanitize_text_field( (string) $row['expires_at'] ) : '',
			'activated_at'    => isset( $row['activated_at'] ) ? sanitize_text_field( (string) $row['activated_at'] ) : '',
			'last_checked_at' => isset( $row['last_checked_at'] ) ? sanitize_text_field( (string) $row['last_checked_at'] ) : '',
			'created_at'      => isset( $row['created_at'] ) ? sanitize_text_field( (string) $row['created_at'] ) : '',
			'updated_at'      => isset( $row['updated_at'] ) ? sanitize_text_field( (string) $row['updated_at'] ) : '',
		);
	}

	/**
	 * Activate local license.
	 *
	 * @param string $license_key License key.
	 * @param string $plan_type Plan key.
	 * @return array<string,mixed>
	 */
	public function activate_license( $license_key, $plan_type = 'starter' ) {
		$before      = $this->get_license_audit_snapshot( $this->get_license() );
		$license_key = trim( sanitize_text_field( (string) $license_key ) );
		$plan_type   = sanitize_key( (string) $plan_type );

		if ( '' === $license_key ) {
			return array(
				'success' => false,
				'message' => __( 'License key is required.', 'super-mechanic' ),
			);
		}
		if ( ! in_array( $plan_type, self::ALLOWED_PLANS, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid plan type.', 'super-mechanic' ),
			);
		}

		$now    = current_time( 'mysql' );
		$saved  = $this->repository->save_license(
			array(
				'license_key'     => $license_key,
				'license_status'  => 'active',
				'domain'          => $this->get_current_domain(),
				'plan_type'       => $plan_type,
				'expires_at'      => '',
				'activated_at'    => $now,
				'last_checked_at' => $now,
			)
		);

		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Could not activate license.', 'super-mechanic' ),
			);
		}

		$after = $this->get_license_audit_snapshot( $this->get_license() );
		$this->audit_license_change( 'activate', $before, $after, array( 'plan_type' => $plan_type ) );

		return array(
			'success' => true,
			'message' => __( 'License activated locally.', 'super-mechanic' ),
		);
	}

	/**
	 * Deactivate local license.
	 *
	 * @return array<string,mixed>
	 */
	public function deactivate_license() {
		$current = $this->get_license();
		$before  = $this->get_license_audit_snapshot( $current );
		$saved   = $this->repository->save_license(
			array(
				'license_key'     => '',
				'license_status'  => 'inactive',
				'domain'          => $current['domain'],
				'plan_type'       => $current['plan_type'],
				'expires_at'      => $current['expires_at'],
				'activated_at'    => '',
				'last_checked_at' => current_time( 'mysql' ),
			)
		);

		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Could not deactivate license.', 'super-mechanic' ),
			);
		}

		$after = $this->get_license_audit_snapshot( $this->get_license() );
		$this->audit_license_change( 'deactivate', $before, $after );

		return array(
			'success' => true,
			'message' => __( 'License deactivated.', 'super-mechanic' ),
		);
	}

	/**
	 * Get license status key.
	 *
	 * @return string
	 */
	public function get_license_status() {
		$license = $this->get_license();
		return isset( $license['license_status'] ) ? (string) $license['license_status'] : 'inactive';
	}

	/**
	 * Check active status.
	 *
	 * @return bool
	 */
	public function is_license_active() {
		return 'active' === $this->get_license_status();
	}

	/**
	 * Check usable local license state for onboarding/diagnostics.
	 *
	 * @return bool
	 */
	public function has_usable_license() {
		return $this->is_license_active();
	}

	/**
	 * Check if current site domain matches active license.
	 *
	 * @return bool
	 */
	public function is_license_valid_for_current_site() {
		$license = $this->get_license();
		if ( 'active' !== $license['license_status'] ) {
			return false;
		}
		if ( '' === $license['domain'] ) {
			return false;
		}
		if ( $license['domain'] !== $this->get_current_domain() ) {
			return false;
		}
		if ( in_array( $license['license_status'], array( 'expired', 'revoked' ), true ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Resolve current domain from WP site URL.
	 *
	 * @return string
	 */
	public function get_current_domain() {
		$domain = wp_parse_url( home_url(), PHP_URL_HOST );
		$domain = is_string( $domain ) ? strtolower( trim( $domain ) ) : '';
		return sanitize_text_field( $domain );
	}

	/**
	 * Get default license state.
	 *
	 * @return array<string,mixed>
	 */
	protected function get_default_license() {
		return array(
			'id'              => 0,
			'license_key'     => '',
			'license_status'  => 'inactive',
			'domain'          => '',
			'plan_type'       => 'starter',
			'expires_at'      => '',
			'activated_at'    => '',
			'last_checked_at' => '',
			'created_at'      => '',
			'updated_at'      => '',
		);
	}

	/**
	 * Resolve audit service lazily.
	 *
	 * @return Audit_Service|null
	 */
	protected function get_audit_service() {
		if ( $this->audit_service instanceof Audit_Service ) {
			return $this->audit_service;
		}

		try {
			$this->audit_service = new Audit_Service();
			return $this->audit_service;
		} catch ( \Throwable $throwable ) {
			return null;
		}
	}

	/**
	 * Audit one license change.
	 *
	 * @param string              $action Action.
	 * @param array<string,mixed> $before Before payload.
	 * @param array<string,mixed> $after After payload.
	 * @param array<string,mixed> $context Context payload.
	 * @return void
	 */
	protected function audit_license_change( $action, array $before, array $after, array $context = array() ) {
		$audit = $this->get_audit_service();
		if ( ! $audit instanceof Audit_Service ) {
			return;
		}

		$license_id = isset( $after['id'] ) ? absint( $after['id'] ) : 0;
		if ( $license_id <= 0 && isset( $before['id'] ) ) {
			$license_id = absint( $before['id'] );
		}

		$audit->audit_license_change(
			sanitize_key( (string) $action ),
			$license_id,
			$before,
			$after,
			$context,
			get_current_user_id(),
			0
		);
	}

	/**
	 * Normalize license payload for audit trail.
	 *
	 * @param array<string,mixed> $license License payload.
	 * @return array<string,mixed>
	 */
	protected function get_license_audit_snapshot( array $license ) {
		$license_key = isset( $license['license_key'] ) ? (string) $license['license_key'] : '';
		$key_tail    = '';
		if ( '' !== $license_key ) {
			$key_tail = substr( $license_key, -4 );
		}

		return array(
			'id'             => isset( $license['id'] ) ? absint( $license['id'] ) : 0,
			'license_status' => isset( $license['license_status'] ) ? sanitize_key( (string) $license['license_status'] ) : 'inactive',
			'plan_type'      => isset( $license['plan_type'] ) ? sanitize_key( (string) $license['plan_type'] ) : 'starter',
			'domain'         => isset( $license['domain'] ) ? sanitize_text_field( (string) $license['domain'] ) : '',
			'license_tail'   => $key_tail,
			'activated_at'   => isset( $license['activated_at'] ) ? sanitize_text_field( (string) $license['activated_at'] ) : '',
			'expires_at'     => isset( $license['expires_at'] ) ? sanitize_text_field( (string) $license['expires_at'] ) : '',
		);
	}
}
