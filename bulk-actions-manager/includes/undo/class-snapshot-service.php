<?php
/**
 * Snapshot service.
 *
 * @package BulkActionsManager
 */

namespace BAM\Undo;

use BAM\Database\Repositories\Snapshot_Repository;
use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Snapshot_Service
 */
class Snapshot_Service {

	/**
	 * Save snapshot for an object.
	 *
	 * @param int                  $job_id      Job ID.
	 * @param int                  $object_id   Object ID.
	 * @param string               $action_type Action type.
	 * @param array<string, mixed> $data        Snapshot data.
	 */
	public function save( $job_id, $object_id, $action_type, array $data ) {
		if ( empty( $data ) ) {
			return;
		}

		$retention  = (int) Settings::get( 'snapshot_retention_days', 30 );
		$expires_at = $retention > 0 ? gmdate( 'Y-m-d H:i:s', strtotime( '+' . $retention . ' days' ) ) : null;

		Snapshot_Repository::create(
			array(
				'job_id'        => $job_id,
				'object_type'   => 'post',
				'object_id'     => $object_id,
				'action_type'   => $action_type,
				'snapshot_data' => $data,
				'expires_at'    => $expires_at,
			)
		);
	}

	/**
	 * Get snapshot for object in job.
	 *
	 * @param int $job_id    Job ID.
	 * @param int $object_id Object ID.
	 * @return object|null
	 */
	public function get_for_object( $job_id, $object_id ) {
		return Snapshot_Repository::get_for_object( $job_id, $object_id );
	}

	/**
	 * Validate snapshots exist and are not expired for undo.
	 *
	 * @param int $job_id Original job ID.
	 * @return true|\WP_Error
	 */
	public function validate_for_undo( $job_id ) {
		$snapshots = Snapshot_Repository::get_by_job( $job_id );
		if ( empty( $snapshots ) ) {
			return new \WP_Error( 'bam_no_snapshots', __( 'No snapshots available for undo.', 'bulk-actions-manager' ) );
		}

		$now = current_time( 'mysql' );
		foreach ( $snapshots as $snapshot ) {
			if ( $snapshot->expires_at && $snapshot->expires_at < $now ) {
				return new \WP_Error( 'bam_snapshots_expired', __( 'Snapshots have expired. Undo is no longer available.', 'bulk-actions-manager' ) );
			}
		}

		return true;
	}
}
