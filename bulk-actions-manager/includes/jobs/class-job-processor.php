<?php
/**
 * Job batch processor.
 *
 * @package BulkActionsManager
 */

namespace BAM\Jobs;

use BAM\Admin\Export_Download;
use BAM\Actions\Action_Registry;
use BAM\Actions\Types\Export_Action;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Job_Item_Repository;
use BAM\Logging\Logger;
use BAM\Settings;
use BAM\Undo\Snapshot_Service;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Processor
 */
class Job_Processor {

	/**
	 * Action registry.
	 *
	 * @var Action_Registry
	 */
	private $actions;

	/**
	 * Snapshot service.
	 *
	 * @var Snapshot_Service
	 */
	private $snapshots;

	/**
	 * Collected export IDs per job (in-memory for current request).
	 *
	 * @var array<int, array<int, int>>
	 */
	private static $export_ids = array();

	/**
	 * Constructor.
	 *
	 * @param Action_Registry|null  $actions   Actions.
	 * @param Snapshot_Service|null $snapshots Snapshots.
	 */
	public function __construct( Action_Registry $actions = null, Snapshot_Service $snapshots = null ) {
		$this->actions   = $actions ?: new Action_Registry();
		$this->snapshots = $snapshots ?: new Snapshot_Service();
	}

	/**
	 * Process one batch for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function process_batch( $job_id ) {
		$job_id = (int) $job_id;
		$lock_key = 'bam_job_lock_' . $job_id;

		if ( get_transient( $lock_key ) ) {
			$job = Job_Repository::find( $job_id );
			if ( ! $job ) {
				return new \WP_Error( 'bam_job_not_found', __( 'Job not found.', 'bulk-actions-manager' ) );
			}
			return array(
				'status'    => $job->status,
				'processed' => (int) $job->processed_items,
				'total'     => (int) $job->total_items,
				'percent'   => $job->total_items > 0 ? round( ( $job->processed_items / $job->total_items ) * 100, 1 ) : 100,
				'errors'    => array(),
				'message'   => __( 'Batch already in progress.', 'bulk-actions-manager' ),
			);
		}

		set_transient( $lock_key, 1, 2 * MINUTE_IN_SECONDS );

		try {
			return $this->process_batch_unlocked( $job_id );
		} finally {
			delete_transient( $lock_key );
		}
	}

	/**
	 * Process one batch for a job (internal, assumes lock is held).
	 *
	 * @param int $job_id Job ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function process_batch_unlocked( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			return new \WP_Error( 'bam_job_not_found', __( 'Job not found.', 'bulk-actions-manager' ) );
		}

		if ( in_array( $job->status, array( 'paused', 'cancelled', 'completed', 'failed' ), true ) ) {
			return array(
				'status'    => $job->status,
				'processed' => (int) $job->processed_items,
				'total'     => (int) $job->total_items,
				'percent'   => $job->total_items > 0 ? round( ( $job->processed_items / $job->total_items ) * 100, 1 ) : 100,
				'errors'    => array(),
				'message'   => __( 'Job is not running.', 'bulk-actions-manager' ),
			);
		}

		if ( 'queued' === $job->status ) {
			Job_Repository::update( $job_id, array( 'status' => 'running', 'started_at' => current_time( 'mysql' ) ) );
		}

		$action = $this->actions->get( $job->action_type );
		if ( ! $action ) {
			Job_Repository::update(
				$job_id,
				array(
					'status'        => 'failed',
					'error_message' => __( 'Unknown action.', 'bulk-actions-manager' ),
					'finished_at'   => current_time( 'mysql' ),
				)
			);
			return new \WP_Error( 'bam_unknown_action', __( 'Unknown action.', 'bulk-actions-manager' ) );
		}

		$payload  = Sanitizer::json_decode( $job->action_payload );
		$dry_run  = (bool) $job->is_dry_run;
		$is_undo  = ! empty( $job->parent_job_id );
		$batch    = Job_Item_Repository::claim_pending_batch( $job_id, (int) $job->batch_size );
		$errors   = array();
		$processed = 0;
		$failed    = 0;

		// Dry run without queue items - simulate count only.
		if ( $dry_run && empty( $batch ) ) {
			Job_Repository::update(
				$job_id,
				array(
					'status'          => 'completed',
					'processed_items' => (int) $job->total_items,
					'finished_at'     => current_time( 'mysql' ),
				)
			);
			return array(
				'status'    => 'completed',
				'processed' => (int) $job->total_items,
				'total'     => (int) $job->total_items,
				'percent'   => 100,
				'errors'    => array(),
				'message'   => sprintf(
					/* translators: %d: record count */
					__( 'This action would affect %d records.', 'bulk-actions-manager' ),
					(int) $job->total_items
				),
			);
		}

		foreach ( $batch as $item ) {
			$object_id = (int) $item->object_id;

			if ( $is_undo ) {
				$snapshot_row = $this->snapshots->get_for_object( (int) $job->parent_job_id, $object_id );
				if ( ! $snapshot_row ) {
					Job_Item_Repository::update_status( $item->id, 'skipped', __( 'No snapshot.', 'bulk-actions-manager' ) );
					continue;
				}
				$snapshot_data = Sanitizer::json_decode( $snapshot_row->snapshot_data );
				$result = $action->undo( $object_id, $snapshot_data );
			} else {
				if ( ! $dry_run && Settings::get( 'enable_undo', true ) && $action->supports_undo() ) {
					$this->snapshots->save( $job_id, $object_id, $job->action_type, $action->snapshot( $object_id, $payload ) );
				}
				$result = $action->execute( $object_id, $payload, $dry_run );
			}

			if ( $result->success ) {
				Job_Item_Repository::update_status( $item->id, 'done' );
				$processed++;

				if ( 0 === strpos( $job->action_type, 'export.' ) ) {
					if ( ! isset( self::$export_ids[ $job_id ] ) ) {
						self::$export_ids[ $job_id ] = array();
					}
					self::$export_ids[ $job_id ][] = $object_id;
				}
			} else {
				Job_Item_Repository::update_status( $item->id, 'failed', $result->message );
				$failed++;
				$errors[] = array(
					'object_id' => $object_id,
					'message'   => $result->message,
				);
			}
		}

		$new_processed = (int) $job->processed_items + $processed;
		$new_failed    = (int) $job->failed_items + $failed;

		Job_Repository::update(
			$job_id,
			array(
				'processed_items' => $new_processed,
				'failed_items'    => $new_failed,
			)
		);

		$max_errors = (int) Settings::get( 'max_errors_before_pause', 10 );
		if ( $new_failed >= $max_errors ) {
			Job_Repository::update(
				$job_id,
				array(
					'status'        => 'paused',
					'error_message' => __( 'Paused due to too many errors.', 'bulk-actions-manager' ),
				)
			);
		}

		$pending = Job_Item_Repository::count_pending( $job_id );
		$job     = Job_Repository::find( $job_id );

		if ( 0 === $pending && 'paused' !== $job->status ) {
			$this->complete_job( $job );
			$job = Job_Repository::find( $job_id );
		}

		$percent = $job->total_items > 0 ? round( ( $job->processed_items / $job->total_items ) * 100, 1 ) : 100;
		$remaining = max( 0, (int) $job->total_items - (int) $job->processed_items );

		return array(
			'status'              => $job->status,
			'processed'           => (int) $job->processed_items,
			'total'               => (int) $job->total_items,
			'remaining'           => $remaining,
			'percent'             => $percent,
			'errors'              => $errors,
			'eta_seconds'         => Job_Estimator::estimate( $job ),
			'export_download_url' => Export_Download::get_url( $job_id ),
		);
	}

	/**
	 * Mark job completed and finalize logging/undo/export.
	 *
	 * @param object $job Job row.
	 */
	private function complete_job( $job ) {
		$undo_available = false;
		$undo_expires   = null;

		$action = $this->actions->get( $job->action_type );
		if ( $action && $action->supports_undo() && Settings::get( 'enable_undo', true ) && ! $job->is_dry_run && empty( $job->parent_job_id ) ) {
			$undo_available = true;
			$retention = (int) Settings::get( 'snapshot_retention_days', 30 );
			if ( $retention > 0 ) {
				$undo_expires = gmdate( 'Y-m-d H:i:s', strtotime( '+' . $retention . ' days' ) );
			}
		}

		$export_file = null;
		if ( 0 === strpos( $job->action_type, 'export.' ) && $action instanceof Export_Action ) {
			$ids = self::$export_ids[ (int) $job->id ] ?? array();
			$export_file = $action->write_export_file( $ids, (int) $job->id );
			unset( self::$export_ids[ (int) $job->id ] );
		}

		Job_Repository::update(
			(int) $job->id,
			array(
				'status'          => 'completed',
				'finished_at'     => current_time( 'mysql' ),
				'undo_available'  => $undo_available ? 1 : 0,
				'undo_expires_at' => $undo_expires,
			)
		);

		if ( Settings::get( 'enable_logs', true ) ) {
			Logger::complete_job(
				(int) $job->id,
				(int) $job->processed_items,
				(int) $job->failed_items,
				$undo_available,
				$export_file
			);
		}

		/**
		 * Fires when a job completes.
		 *
		 * @param int    $job_id Job ID.
		 * @param object $job    Job object.
		 */
		do_action( 'bam_job_completed', (int) $job->id, $job );
	}
}
