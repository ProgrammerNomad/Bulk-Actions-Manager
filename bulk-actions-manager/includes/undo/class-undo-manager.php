<?php
/**
 * Undo manager.
 *
 * @package BulkActionsManager
 */

namespace BAM\Undo;

use BAM\Actions\Action_Registry;
use BAM\Database\Repositories\Job_Item_Repository;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Log_Repository;
use BAM\Database\Repositories\Snapshot_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Jobs\Job_Queue;
use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Undo_Manager
 */
class Undo_Manager {

	/**
	 * Create undo job from log.
	 *
	 * @param int $log_id Log ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_undo_job( $log_id ) {
		if ( ! Settings::get( 'enable_undo', true ) ) {
			return new \WP_Error( 'bam_undo_disabled', __( 'Undo is disabled in settings.', 'bulk-actions-manager' ) );
		}

		$log = Log_Repository::find( $log_id );
		if ( ! $log ) {
			return new \WP_Error( 'bam_log_not_found', __( 'Log not found.', 'bulk-actions-manager' ) );
		}

		if ( 'available' !== $log->undo_status ) {
			return new \WP_Error( 'bam_undo_unavailable', __( 'Undo is not available for this log.', 'bulk-actions-manager' ) );
		}

		$original_job = Job_Repository::find( (int) $log->job_id );
		if ( ! $original_job ) {
			return new \WP_Error( 'bam_job_not_found', __( 'Original job not found.', 'bulk-actions-manager' ) );
		}

		$snapshots = new Snapshot_Service();
		$valid     = $snapshots->validate_for_undo( (int) $log->job_id );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$registry = new Action_Registry();
		$action   = $registry->get( $log->action_type );
		if ( ! $action || ! $action->supports_undo() ) {
			return new \WP_Error( 'bam_action_not_undoable', __( 'This action cannot be undone.', 'bulk-actions-manager' ) );
		}

		$snapshot_rows = Snapshot_Repository::get_by_job( (int) $log->job_id );
		$object_ids    = array_map(
			function ( $row ) {
				return (int) $row->object_id;
			},
			$snapshot_rows
		);

		$undo_job_id = Job_Repository::create(
			array(
				'name'           => sprintf(
					/* translators: %d: job ID */
					__( 'Undo Job #%d', 'bulk-actions-manager' ),
					$log->job_id
				),
				'action_type'    => $log->action_type,
				'action_payload' => $log->action_payload,
				'filter_payload' => $log->filter_payload,
				'status'         => 'queued',
				'parent_job_id'  => (int) $log->job_id,
				'total_items'    => count( $object_ids ),
				'started_at'     => current_time( 'mysql' ),
			)
		);

		if ( ! $undo_job_id ) {
			return new \WP_Error( 'bam_undo_failed', __( 'Failed to create undo job.', 'bulk-actions-manager' ) );
		}

		Job_Item_Repository::bulk_insert( $undo_job_id, $object_ids );
		Job_Repository::update( $undo_job_id, array( 'status' => 'running' ) );

		Log_Repository::update(
			(int) $log->id,
			array(
				'undo_status' => 'used',
				'undo_job_id' => $undo_job_id,
			)
		);

		Job_Repository::update( (int) $log->job_id, array( 'undo_available' => 0 ) );

		return array(
			'job_id'  => $undo_job_id,
			'message' => sprintf(
				/* translators: %d: job ID */
				__( 'Undo job #%d created.', 'bulk-actions-manager' ),
				$undo_job_id
			),
		);
	}
}
