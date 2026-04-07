<?php
/**
 * Business membership service.
 *
 * @package Super_Mechanic
 */

namespace Super_Mechanic\Users;

use Super_Mechanic\Audit\Audit_Service;
use Super_Mechanic\Businesses\Business_Repository;
use Super_Mechanic\Notifications\Notification_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Read layer for business memberships.
 */
class Business_Membership_Service {
	/**
	 * Allowed operational roles.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_ROLES = array( 'admin', 'mechanic', 'client' );

	/**
	 * Allowed status values.
	 *
	 * @var array<int,string>
	 */
	const ALLOWED_STATUS = array( 'active', 'inactive' );

	/**
	 * Repository dependency.
	 *
	 * @var Business_Membership_Repository
	 */
	protected $repository;

	/**
	 * Business repository.
	 *
	 * @var Business_Repository
	 */
	protected $business_repository;

	/**
	 * Audit service dependency.
	 *
	 * @var Audit_Service|null
	 */
	protected $audit_service;

	/**
	 * Constructor.
	 *
	 * @param Business_Membership_Repository|null $repository Repository dependency.
	 * @param Business_Repository|null            $business_repository Business repository.
	 * @param Audit_Service|null                  $audit_service Audit service dependency.
	 */
	public function __construct( Business_Membership_Repository $repository = null, Business_Repository $business_repository = null, Audit_Service $audit_service = null ) {
		$this->repository          = $repository ? $repository : new Business_Membership_Repository();
		$this->business_repository = $business_repository ? $business_repository : new Business_Repository();
		$this->audit_service       = $audit_service;
	}

	/**
	 * Get user memberships.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_user_memberships( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		return $this->sanitize_membership_rows( $this->repository->get_user_memberships( $user_id ) );
	}

	/**
	 * Get active user memberships only.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_active_user_memberships( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array();
		}

		return $this->sanitize_membership_rows( $this->repository->get_user_memberships( $user_id, 'active' ) );
	}

	/**
	 * Get one user primary membership.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>|null
	 */
	public function get_user_primary_membership( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return null;
		}

		$primary = $this->repository->get_user_primary_membership( $user_id );
		if ( is_array( $primary ) ) {
			return $this->sanitize_membership_row( $primary );
		}

		$active_memberships = $this->repository->get_user_memberships( $user_id, 'active' );
		if ( ! empty( $active_memberships ) ) {
			return $this->sanitize_membership_row( $active_memberships[0] );
		}

		$memberships = $this->repository->get_user_memberships( $user_id );
		if ( ! empty( $memberships ) ) {
			return $this->sanitize_membership_row( $memberships[0] );
		}

		return null;
	}

	/**
	 * Get business members.
	 *
	 * @param int $business_id Business ID.
	 * @return array<int,array<string,mixed>>
	 */
	public function get_business_members( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return array();
		}

		return $this->sanitize_membership_rows( $this->repository->get_business_members( $business_id ) );
	}

	/**
	 * Get user operational role in business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return string
	 */
	public function get_user_role_in_business( $user_id, $business_id ) {
		$membership = $this->get_user_membership_in_business( $user_id, $business_id );
		if ( ! is_array( $membership ) ) {
			return '';
		}

		return ( 'active' === $membership['status'] ) ? (string) $membership['operational_role'] : '';
	}

	/**
	 * Verify if user has active membership in business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	public function user_has_active_membership( $user_id, $business_id ) {
		$membership = $this->get_user_membership_in_business( $user_id, $business_id );
		if ( ! is_array( $membership ) ) {
			return false;
		}

		return 'active' === $membership['status'];
	}

	/**
	 * Get one user membership in one business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	public function get_user_membership_in_business( $user_id, $business_id ) {
		return $this->resolve_user_membership_in_business( $user_id, $business_id );
	}

	/**
	 * Create membership.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $business_id Business ID.
	 * @param string $role Role.
	 * @return array<string,mixed>
	 */
	public function create_membership( $user_id, $business_id, $role ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		$role        = sanitize_key( (string) $role );

		if ( $user_id <= 0 || $business_id <= 0 || ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid membership payload.', 'super-mechanic' ),
			);
		}

		if ( ! get_userdata( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

		if ( ! $this->business_exists( $business_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Business not found.', 'super-mechanic' ),
			);
		}

		$existing = $this->repository->get_user_membership_in_business( $user_id, $business_id );
		if ( is_array( $existing ) ) {
			$membership_id = isset( $existing['id'] ) ? absint( $existing['id'] ) : 0;
			if ( $membership_id <= 0 ) {
				return array(
					'success' => false,
					'message' => __( 'Existing membership is invalid.', 'super-mechanic' ),
				);
			}

			$before = $this->get_membership_audit_snapshot( $existing );
			$this->repository->update_membership_role( $membership_id, $role );
			$this->repository->update_membership_status( $membership_id, 'active' );
			$after = $this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( $membership_id ) );
			$this->audit_membership_change(
				'update',
				$membership_id,
				$before,
				$after,
				array(
					'operation' => 'reactivate_existing_membership',
				)
			);

			$this->dispatch_membership_notification( 'membership_updated', $user_id, $business_id, $role, 'active' );

			return array(
				'success'       => true,
				'membership_id' => $membership_id,
				'message'       => __( 'Membership already existed and was reactivated.', 'super-mechanic' ),
			);
		}

		$is_primary = empty( $this->get_active_user_memberships( $user_id ) );
		$created_id = $this->repository->create_membership( $user_id, $business_id, $role, 'active', $is_primary );
		if ( false === $created_id || $created_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Could not create membership.', 'super-mechanic' ),
			);
		}

		if ( $is_primary ) {
			$this->repository->set_primary_membership( (int) $created_id );
		}

		$this->audit_membership_change(
			'create',
			(int) $created_id,
			array(),
			$this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( (int) $created_id ) ),
			array(
				'operation' => 'create_membership',
			)
		);

		$this->dispatch_membership_notification( 'membership_created', $user_id, $business_id, $role, 'active' );
		$this->dispatch_membership_notification( 'user_assigned_to_business', $user_id, $business_id, $role, 'active' );

		return array(
			'success'       => true,
			'membership_id' => (int) $created_id,
			'message'       => __( 'Membership created.', 'super-mechanic' ),
		);
	}

	/**
	 * Update membership role.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $role Role.
	 * @return array<string,mixed>
	 */
	public function update_membership_role( $membership_id, $role ) {
		$membership_id = absint( $membership_id );
		$role          = sanitize_key( (string) $role );
		if ( $membership_id <= 0 || ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid membership role update payload.', 'super-mechanic' ),
			);
		}

		$before = $this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( $membership_id ) );
		$ok     = $this->repository->update_membership_role( $membership_id, $role );
		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update membership role.', 'super-mechanic' ),
			);
		}

		$after = $this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( $membership_id ) );
		$this->audit_membership_change(
			'update',
			$membership_id,
			$before,
			$after,
			array(
				'operation' => 'update_role',
			)
		);

		return array(
			'success'       => true,
			'membership_id' => $membership_id,
			'message'       => __( 'Membership role updated.', 'super-mechanic' ),
		);
	}

	/**
	 * Set membership status.
	 *
	 * @param int    $membership_id Membership ID.
	 * @param string $status Status.
	 * @return array<string,mixed>
	 */
	public function set_membership_status( $membership_id, $status ) {
		$membership_id = absint( $membership_id );
		$status        = sanitize_key( (string) $status );
		if ( $membership_id <= 0 || ! in_array( $status, self::ALLOWED_STATUS, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid membership status payload.', 'super-mechanic' ),
			);
		}

		$membership = $this->repository->get_membership_by_id( $membership_id );
		if ( ! is_array( $membership ) ) {
			return array(
				'success' => false,
				'message' => __( 'Membership not found.', 'super-mechanic' ),
			);
		}

		if ( ! empty( $membership['is_primary'] ) && 'inactive' === $status ) {
			return array(
				'success' => false,
				'message' => __( 'Primary membership cannot be inactive. Set another primary membership first.', 'super-mechanic' ),
			);
		}

		$before = $this->get_membership_audit_snapshot( $membership );
		$ok     = $this->repository->update_membership_status( $membership_id, $status );
		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'Could not update membership status.', 'super-mechanic' ),
			);
		}

		$after = $this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( $membership_id ) );
		$this->audit_membership_change(
			( 'active' === $status ) ? 'activate' : 'deactivate',
			$membership_id,
			$before,
			$after,
			array(
				'operation' => 'set_status',
			)
		);

		return array(
			'success'       => true,
			'membership_id' => $membership_id,
			'message'       => __( 'Membership status updated.', 'super-mechanic' ),
		);
	}

	/**
	 * Set membership as primary.
	 *
	 * @param int $membership_id Membership ID.
	 * @return array<string,mixed>
	 */
	public function set_primary_membership( $membership_id ) {
		$membership_id = absint( $membership_id );
		if ( $membership_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid membership ID.', 'super-mechanic' ),
			);
		}

		$membership = $this->repository->get_membership_by_id( $membership_id );
		if ( ! is_array( $membership ) ) {
			return array(
				'success' => false,
				'message' => __( 'Membership not found.', 'super-mechanic' ),
			);
		}

		$before = $this->get_membership_audit_snapshot( $membership );
		$ok     = $this->repository->set_primary_membership( $membership_id );
		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'Could not set primary membership.', 'super-mechanic' ),
			);
		}

		$after = $this->get_membership_audit_snapshot( $this->repository->get_membership_by_id( $membership_id ) );
		$this->audit_membership_change(
			'update',
			$membership_id,
			$before,
			$after,
			array(
				'operation' => 'set_primary_membership',
			)
		);

		return array(
			'success'       => true,
			'membership_id' => $membership_id,
			'message'       => __( 'Primary membership updated.', 'super-mechanic' ),
		);
	}

	/**
	 * Remove membership.
	 *
	 * @param int $membership_id Membership ID.
	 * @return array<string,mixed>
	 */
	public function remove_membership( $membership_id ) {
		$membership_id = absint( $membership_id );
		if ( $membership_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid membership ID.', 'super-mechanic' ),
			);
		}

		$membership = $this->repository->get_membership_by_id( $membership_id );
		if ( ! is_array( $membership ) ) {
			return array(
				'success' => false,
				'message' => __( 'Membership not found.', 'super-mechanic' ),
			);
		}

		if ( ! empty( $membership['is_primary'] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Primary membership cannot be removed. Set another primary membership first.', 'super-mechanic' ),
			);
		}

		$before = $this->get_membership_audit_snapshot( $membership );
		$ok     = $this->repository->delete_membership( $membership_id );
		if ( ! $ok ) {
			return array(
				'success' => false,
				'message' => __( 'Could not remove membership.', 'super-mechanic' ),
			);
		}

		$this->audit_membership_change(
			'delete',
			$membership_id,
			$before,
			array(),
			array(
				'operation' => 'remove_membership',
			)
		);

		return array(
			'success'       => true,
			'membership_id' => $membership_id,
			'message'       => __( 'Membership removed.', 'super-mechanic' ),
		);
	}

	/**
	 * Transfer user to another business membership scope.
	 *
	 * @param int    $user_id User ID.
	 * @param int    $target_business_id Target business ID.
	 * @param string $role Target operational role.
	 * @param string $mode Transfer mode: replace|add.
	 * @return array<string,mixed>
	 */
	public function transfer_user_to_business( $user_id, $target_business_id, $role, $mode ) {
		$user_id            = absint( $user_id );
		$target_business_id = absint( $target_business_id );
		$role               = sanitize_key( (string) $role );
		$mode               = sanitize_key( (string) $mode );

		if ( $user_id <= 0 || $target_business_id <= 0 || ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid transfer payload.', 'super-mechanic' ),
			);
		}

		if ( ! in_array( $mode, array( 'replace', 'add' ), true ) ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid transfer mode.', 'super-mechanic' ),
			);
		}

		if ( ! get_userdata( $user_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'User not found.', 'super-mechanic' ),
			);
		}

		if ( ! $this->business_exists( $target_business_id ) ) {
			return array(
				'success' => false,
				'message' => __( 'Target business not found.', 'super-mechanic' ),
			);
		}

		$before_memberships = $this->get_user_memberships( $user_id );
		$create_result      = $this->create_membership( $user_id, $target_business_id, $role );
		if ( empty( $create_result['success'] ) ) {
			return $create_result;
		}

		$target_membership_id = isset( $create_result['membership_id'] ) ? absint( $create_result['membership_id'] ) : 0;
		if ( $target_membership_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Target membership could not be resolved.', 'super-mechanic' ),
			);
		}

		if ( 'replace' === $mode ) {
			$this->repository->deactivate_active_memberships_by_user( $user_id, $target_membership_id );
			$this->repository->set_primary_membership( $target_membership_id );

			$this->dispatch_membership_notification( 'user_transferred', $user_id, $target_business_id, $role, 'active', array( 'mode' => 'replace' ) );
			$this->audit_membership_change(
				'transfer',
				$target_membership_id,
				array(
					'user_memberships' => $before_memberships,
				),
				array(
					'user_memberships' => $this->get_user_memberships( $user_id ),
				),
				array(
					'operation'  => 'transfer_user_to_business',
					'mode'       => 'replace',
					'user_id'    => $user_id,
					'business_id'=> $target_business_id,
				),
				get_current_user_id(),
				$target_business_id
			);

			return array(
				'success'       => true,
				'membership_id' => $target_membership_id,
				'mode'          => 'replace',
				'message'       => __( 'User transferred in replace mode.', 'super-mechanic' ),
			);
		}

		$this->dispatch_membership_notification( 'user_transferred', $user_id, $target_business_id, $role, 'active', array( 'mode' => 'add' ) );
		$this->audit_membership_change(
			'transfer',
			$target_membership_id,
			array(
				'user_memberships' => $before_memberships,
			),
			array(
				'user_memberships' => $this->get_user_memberships( $user_id ),
			),
			array(
				'operation'   => 'transfer_user_to_business',
				'mode'        => 'add',
				'user_id'     => $user_id,
				'business_id' => $target_business_id,
			),
			get_current_user_id(),
			$target_business_id
		);

		return array(
			'success'       => true,
			'membership_id' => $target_membership_id,
			'mode'          => 'add',
			'message'       => __( 'User transferred in add mode.', 'super-mechanic' ),
		);
	}

	/**
	/**
	 * Dispatch membership notification in non-blocking mode.
	 *
	 * @param string              $type Notification type.
	 * @param int                 $user_id User ID.
	 * @param int                 $business_id Business ID.
	 * @param string              $role Role.
	 * @param string              $status Status.
	 * @param array<string,mixed> $extra Extra payload.
	 * @return void
	 */
	protected function dispatch_membership_notification( $type, $user_id, $business_id, $role, $status, array $extra = array() ) {
		$type        = sanitize_key( (string) $type );
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		$role        = sanitize_key( (string) $role );
		$status      = sanitize_key( (string) $status );

		if ( '' === $type || $user_id <= 0 ) {
			return;
		}

		$service = new Notification_Service();
		$service->send_notification(
			$type,
			$user_id,
			array_merge(
				array(
					'business_id' => $business_id,
					'role'        => $role,
					'status'      => $status,
				),
				$extra
			)
		);
	}

	/**
	 * Validate membership consistency for one user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function validate_membership_consistency( $user_id ) {
		$user_id      = absint( $user_id );
		$memberships  = $this->get_user_memberships( $user_id );
		$warning_keys = $this->get_membership_consistency_warnings( $user_id );
		$active_count = 0;
		$primary_id   = 0;

		foreach ( $memberships as $membership ) {
			if ( ! is_array( $membership ) ) {
				continue;
			}
			if ( isset( $membership['status'] ) && 'active' === $membership['status'] ) {
				++$active_count;
			}
			if ( ! empty( $membership['is_primary'] ) && $primary_id <= 0 ) {
				$primary_id = isset( $membership['id'] ) ? absint( $membership['id'] ) : 0;
			}
		}

		return array(
			'user_id'        => $user_id,
			'valid'          => empty( $warning_keys ),
			'warning_keys'   => $warning_keys,
			'memberships'    => $memberships,
			'active_count'   => $active_count,
			'primary_id'     => $primary_id,
			'repairable'     => $this->has_repairable_membership_warnings( $warning_keys ),
		);
	}

	/**
	 * Get consistency warnings for one user's memberships.
	 *
	 * @param int $user_id User ID.
	 * @return array<int,string>
	 */
	public function get_membership_consistency_warnings( $user_id ) {
		$user_id     = absint( $user_id );
		$memberships = $this->get_user_memberships( $user_id );
		if ( $user_id <= 0 || empty( $memberships ) ) {
			return array();
		}

		$warnings          = array();
		$primary_rows      = array();
		$active_rows       = array();
		$active_by_business = array();

		foreach ( $memberships as $membership ) {
			if ( ! is_array( $membership ) ) {
				continue;
			}

			$membership_id = isset( $membership['id'] ) ? absint( $membership['id'] ) : 0;
			$business_id   = isset( $membership['business_id'] ) ? absint( $membership['business_id'] ) : 0;
			$status        = isset( $membership['status'] ) ? sanitize_key( (string) $membership['status'] ) : 'inactive';
			$is_primary    = ! empty( $membership['is_primary'] );

			if ( $is_primary ) {
				$primary_rows[] = $membership;
				if ( 'active' !== $status ) {
					$warnings[] = 'inactive_primary_membership';
				}
			}

			if ( 'active' === $status ) {
				$active_rows[] = $membership;
				if ( $business_id > 0 ) {
					if ( ! isset( $active_by_business[ $business_id ] ) ) {
						$active_by_business[ $business_id ] = array();
					}
					$active_by_business[ $business_id ][] = $membership_id;
				}
			}
		}

		if ( count( $primary_rows ) > 1 ) {
			$warnings[] = 'multiple_primary_memberships';
		}

		if ( empty( $primary_rows ) && ! empty( $active_rows ) ) {
			$warnings[] = 'missing_active_primary_membership';
		}

		foreach ( $active_by_business as $membership_ids ) {
			if ( is_array( $membership_ids ) && count( $membership_ids ) > 1 ) {
				$warnings[] = 'duplicate_active_membership_simple';
				break;
			}
		}

		return array_values( array_unique( array_map( 'sanitize_key', $warnings ) ) );
	}

	/**
	 * Repair safe membership inconsistencies for one user.
	 *
	 * @param int $user_id User ID.
	 * @return array<string,mixed>
	 */
	public function repair_membership_consistency( $user_id ) {
		$user_id = absint( $user_id );
		if ( $user_id <= 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Invalid user ID for consistency repair.', 'super-mechanic' ),
			);
		}

		$memberships = $this->get_user_memberships( $user_id );
		$before      = $memberships;
		if ( empty( $memberships ) ) {
			return array(
				'success'        => true,
				'repaired'       => false,
				'changes'        => array(),
				'warning_keys'   => array(),
				'message'        => __( 'No memberships found for this user.', 'super-mechanic' ),
			);
		}

		$changes                 = array();
		$active_memberships      = array();
		$active_primary_id       = 0;
		$primary_membership_ids  = array();
		$active_by_business      = array();

		foreach ( $memberships as $membership ) {
			if ( ! is_array( $membership ) ) {
				continue;
			}

			$membership_id = isset( $membership['id'] ) ? absint( $membership['id'] ) : 0;
			$business_id   = isset( $membership['business_id'] ) ? absint( $membership['business_id'] ) : 0;
			$status        = isset( $membership['status'] ) ? sanitize_key( (string) $membership['status'] ) : 'inactive';
			$is_primary    = ! empty( $membership['is_primary'] );

			if ( $membership_id <= 0 ) {
				continue;
			}

			if ( $is_primary ) {
				$primary_membership_ids[] = $membership_id;
				if ( 'active' === $status && $active_primary_id <= 0 ) {
					$active_primary_id = $membership_id;
				}
			}

			if ( 'active' === $status ) {
				$active_memberships[] = $membership;
				if ( $business_id > 0 ) {
					if ( ! isset( $active_by_business[ $business_id ] ) ) {
						$active_by_business[ $business_id ] = array();
					}
					$active_by_business[ $business_id ][] = $membership_id;
				}
			}
		}

		$target_primary_id = 0;
		if ( $active_primary_id > 0 ) {
			$target_primary_id = $active_primary_id;
		} elseif ( ! empty( $active_memberships ) && isset( $active_memberships[0]['id'] ) ) {
			$target_primary_id = absint( $active_memberships[0]['id'] );
		}

		if ( $target_primary_id > 0 ) {
			if ( count( $primary_membership_ids ) > 1 || ! in_array( $target_primary_id, $primary_membership_ids, true ) ) {
				if ( $this->repository->set_primary_membership( $target_primary_id ) ) {
					$changes[] = 'primary_membership_normalized';
				}
			}
		}

		foreach ( $active_by_business as $business_id => $membership_ids ) {
			if ( ! is_array( $membership_ids ) || count( $membership_ids ) <= 1 ) {
				continue;
			}

			$keep_id = 0;
			if ( $target_primary_id > 0 && in_array( $target_primary_id, $membership_ids, true ) ) {
				$keep_id = $target_primary_id;
			}
			if ( $keep_id <= 0 ) {
				$keep_id = absint( $membership_ids[0] );
			}

			foreach ( $membership_ids as $membership_id ) {
				$membership_id = absint( $membership_id );
				if ( $membership_id <= 0 || $membership_id === $keep_id ) {
					continue;
				}
				if ( $this->repository->update_membership_status( $membership_id, 'inactive' ) ) {
					$changes[] = 'deactivated_duplicate_active_membership_business_' . absint( $business_id );
				}
			}
		}

		if ( empty( $changes ) ) {
			return array(
				'success'      => true,
				'repaired'     => false,
				'changes'      => array(),
				'warning_keys' => $this->get_membership_consistency_warnings( $user_id ),
				'message'      => __( 'No safe repairs were required.', 'super-mechanic' ),
			);
		}

		$after_warnings = $this->get_membership_consistency_warnings( $user_id );
		$after_rows     = $this->get_user_memberships( $user_id );
		$this->audit_membership_change(
			'repair',
			0,
			array(
				'user_id'     => $user_id,
				'memberships' => $before,
			),
			array(
				'user_id'     => $user_id,
				'memberships' => $after_rows,
			),
			array(
				'changes'       => array_values( array_unique( $changes ) ),
				'warning_after' => $after_warnings,
				'operation'     => 'repair_membership_consistency',
			),
			get_current_user_id(),
			0
		);

		return array(
			'success'      => true,
			'repaired'     => true,
			'changes'      => array_values( array_unique( $changes ) ),
			'warning_keys' => $after_warnings,
			'message'      => __( 'Safe consistency repair completed.', 'super-mechanic' ),
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
	 * Write one membership audit event.
	 *
	 * @param string              $action Action.
	 * @param int                 $membership_id Membership ID.
	 * @param array<string,mixed> $before Before payload.
	 * @param array<string,mixed> $after After payload.
	 * @param array<string,mixed> $context Context payload.
	 * @param int                 $actor_user_id Actor user ID.
	 * @param int                 $business_id Business ID.
	 * @return void
	 */
	protected function audit_membership_change( $action, $membership_id, array $before, array $after, array $context = array(), $actor_user_id = 0, $business_id = 0 ) {
		$audit = $this->get_audit_service();
		if ( ! $audit instanceof Audit_Service ) {
			return;
		}

		$resolved_business_id = absint( $business_id );
		if ( $resolved_business_id <= 0 && isset( $after['business_id'] ) ) {
			$resolved_business_id = absint( $after['business_id'] );
		}
		if ( $resolved_business_id <= 0 && isset( $before['business_id'] ) ) {
			$resolved_business_id = absint( $before['business_id'] );
		}

		$audit->audit_membership_change(
			sanitize_key( (string) $action ),
			absint( $membership_id ),
			$before,
			$after,
			$context,
			absint( $actor_user_id ) > 0 ? absint( $actor_user_id ) : get_current_user_id(),
			$resolved_business_id
		);
	}

	/**
	 * Build compact membership snapshot for audit.
	 *
	 * @param array<string,mixed>|null $membership Membership row.
	 * @return array<string,mixed>
	 */
	protected function get_membership_audit_snapshot( $membership ) {
		if ( ! is_array( $membership ) ) {
			return array();
		}

		return array(
			'id'               => isset( $membership['id'] ) ? absint( $membership['id'] ) : 0,
			'business_id'      => isset( $membership['business_id'] ) ? absint( $membership['business_id'] ) : 0,
			'user_id'          => isset( $membership['user_id'] ) ? absint( $membership['user_id'] ) : 0,
			'operational_role' => isset( $membership['operational_role'] ) ? sanitize_key( (string) $membership['operational_role'] ) : '',
			'status'           => isset( $membership['status'] ) ? sanitize_key( (string) $membership['status'] ) : '',
			'is_primary'       => ! empty( $membership['is_primary'] ),
		);
	}

	/**
	 * Get one user membership in business.
	 *
	 * @param int $user_id User ID.
	 * @param int $business_id Business ID.
	 * @return array<string,mixed>|null
	 */
	protected function resolve_user_membership_in_business( $user_id, $business_id ) {
		$user_id     = absint( $user_id );
		$business_id = absint( $business_id );
		if ( $user_id <= 0 || $business_id <= 0 ) {
			return null;
		}

		$membership = $this->repository->get_user_membership_in_business( $user_id, $business_id );
		if ( ! is_array( $membership ) ) {
			return null;
		}

		return $this->sanitize_membership_row( $membership );
	}

	/**
	 * Normalize one membership row.
	 *
	 * @param array<string,mixed> $row Raw row.
	 * @return array<string,mixed>
	 */
	protected function sanitize_membership_row( array $row ) {
		$role = sanitize_key( isset( $row['operational_role'] ) ? (string) $row['operational_role'] : '' );
		if ( ! in_array( $role, self::ALLOWED_ROLES, true ) ) {
			$role = '';
		}

		$status = sanitize_key( isset( $row['status'] ) ? (string) $row['status'] : 'inactive' );
		if ( ! in_array( $status, self::ALLOWED_STATUS, true ) ) {
			$status = 'inactive';
		}

		return array(
			'id'               => isset( $row['id'] ) ? absint( $row['id'] ) : 0,
			'business_id'      => isset( $row['business_id'] ) ? absint( $row['business_id'] ) : 0,
			'user_id'          => isset( $row['user_id'] ) ? absint( $row['user_id'] ) : 0,
			'operational_role' => $role,
			'status'           => $status,
			'is_primary'       => ! empty( $row['is_primary'] ),
			'created_at'       => isset( $row['created_at'] ) ? sanitize_text_field( (string) $row['created_at'] ) : '',
			'updated_at'       => isset( $row['updated_at'] ) ? sanitize_text_field( (string) $row['updated_at'] ) : '',
		);
	}

	/**
	 * Normalize membership row list.
	 *
	 * @param array<int,mixed> $rows Raw rows.
	 * @return array<int,array<string,mixed>>
	 */
	protected function sanitize_membership_rows( array $rows ) {
		$clean = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$clean[] = $this->sanitize_membership_row( $row );
		}

		return $clean;
	}

	/**
	 * Validate business existence.
	 *
	 * @param int $business_id Business ID.
	 * @return bool
	 */
	protected function business_exists( $business_id ) {
		$business_id = absint( $business_id );
		if ( $business_id <= 0 ) {
			return false;
		}

		return is_array( $this->business_repository->get_by_id( $business_id ) );
	}

	/**
	 * Determine if warning set has at least one repairable issue.
	 *
	 * @param array<int,string> $warning_keys Warning keys.
	 * @return bool
	 */
	protected function has_repairable_membership_warnings( array $warning_keys ) {
		$repairable = array(
			'multiple_primary_memberships',
			'inactive_primary_membership',
			'missing_active_primary_membership',
			'duplicate_active_membership_simple',
		);

		foreach ( $warning_keys as $warning_key ) {
			$warning_key = sanitize_key( (string) $warning_key );
			if ( in_array( $warning_key, $repairable, true ) ) {
				return true;
			}
		}

		return false;
	}
}
















