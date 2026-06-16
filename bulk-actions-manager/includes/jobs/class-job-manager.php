<?php
/**
 * Job manager — create and control jobs.
 *
 * @package BulkActionsManager
 */

namespace BAM\Jobs;

use BAM\Actions\Action_Registry;
use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Job_Item_Repository;
use BAM\Filters\Filter_Compiler;
use BAM\Filters\Filter_Validator;
use BAM\Logging\Logger;
use BAM\Settings;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Manager
 */
class Job_Manager {

	/**
	 * Action registry.
	 *
	 * @var Action_Registry
	 */
	private $actions;

	/**
	 * Constructor.
	 *
	 * @param Action_Registry|null $actions Action registry.
	 */
	public function __construct( Action_Registry $actions = null ) {
		$this->actions = $actions ?: new Action_Registry();
	}

	/**
	 * Create a new job.
	 *
	 * @param array<string, mixed> $data Job data from REST.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create( array $data ) {
		$filter = isset( $data['filter'] ) ? (array) $data['filter'] : array();
		$valid  = Filter_Validator::validate( $filter );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$action_type = sanitize_text_field( $data['action_type'] ?? '' );
		$action      = $this->actions->get( $action_type );
		if ( ! $action ) {
			return new \WP_Error( 'bam_invalid_action', __( 'Invalid action type.', 'bulk-actions-manager' ) );
		}

		$payload = isset( $data['action_payload'] ) ? (array) $data['action_payload'] : array();
		$valid_payload = $action->validate_payload( $payload );
		if ( is_wp_error( $valid_payload ) ) {
			return $valid_payload;
		}

		$is_dry_run = ! empty( $data['is_dry_run'] );
		$batch_size = absint( $data['batch_size'] ?? Settings::get( 'default_batch_size', 25 ) );
		$mode       = sanitize_text_field( $data['processing_mode'] ?? Settings::get( 'default_processing_mode', 'ajax' ) );

		$query_result = Filter_Compiler::query( $filter, 0 );
		$total        = $query_result['total'];

		if ( 0 === $total && ! $is_dry_run ) {
			return new \WP_Error( 'bam_no_matches', __( 'No matching records found.', 'bulk-actions-manager' ) );
		}

		$name = sanitize_text_field( $data['name'] ?? '' );
		if ( ! $name ) {
			$name = sprintf(
				/* translators: 1: action label, 2: count */
				__( '%1$s (%2$d records)', 'bulk-actions-manager' ),
				$action->get_label(),
				$total
			);
		}

		$job_id = Job_Repository::create(
			array(
				'name'            => $name,
				'action_type'     => $action_type,
				'action_payload'  => $payload,
				'filter_payload'  => $filter,
				'status'          => $is_dry_run ? 'running' : 'queued',
				'processing_mode' => $mode,
				'batch_size'      => $batch_size,
				'is_dry_run'      => $is_dry_run ? 1 : 0,
				'total_items'     => $total,
				'started_at'      => current_time( 'mysql' ),
			)
		);

		if ( ! $job_id ) {
			return new \WP_Error( 'bam_job_create_failed', __( 'Failed to create job.', 'bulk-actions-manager' ) );
		}

		if ( ! $is_dry_run && ! empty( $query_result['ids'] ) ) {
			Job_Item_Repository::bulk_insert( $job_id, $query_result['ids'] );
		}

		if ( Settings::get( 'enable_logs', true ) ) {
			Logger::create_for_job( $job_id, $action_type, $filter, $payload );
		}

		if ( $is_dry_run ) {
			$processor = new Job_Processor( $this->actions );
			$result    = $processor->process_batch( $job_id );
			return array_merge(
				array( 'job_id' => $job_id, 'dry_run' => true ),
				$result
			);
		}

		if ( 'background' === $mode ) {
			Job_Repository::update( $job_id, array( 'status' => 'queued' ) );
			Job_Queue::mark_for_processing( $job_id );
		} else {
			Job_Repository::update( $job_id, array( 'status' => 'running' ) );
		}

		return array(
			'job_id' => $job_id,
			'total'  => $total,
			'status' => 'background' === $mode ? 'queued' : 'running',
		);
	}

	/**
	 * Pause a job.
	 *
	 * @param int $job_id Job ID.
	 * @return true|\WP_Error
	 */
	public function pause( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job || 'running' !== $job->status ) {
			return new \WP_Error( 'bam_cannot_pause', __( 'Job cannot be paused.', 'bulk-actions-manager' ) );
		}
		Job_Repository::update( $job_id, array( 'status' => 'paused' ) );
		return true;
	}

	/**
	 * Resume a job.
	 *
	 * @param int $job_id Job ID.
	 * @return true|\WP_Error
	 */
	public function resume( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job || 'paused' !== $job->status ) {
			return new \WP_Error( 'bam_cannot_resume', __( 'Job cannot be resumed.', 'bulk-actions-manager' ) );
		}
		Job_Repository::update( $job_id, array( 'status' => 'running' ) );
		return true;
	}

	/**
	 * Cancel a job.
	 *
	 * @param int $job_id Job ID.
	 * @return true|\WP_Error
	 */
	public function cancel( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job || in_array( $job->status, array( 'completed', 'failed', 'cancelled' ), true ) ) {
			return new \WP_Error( 'bam_cannot_cancel', __( 'Job cannot be cancelled.', 'bulk-actions-manager' ) );
		}
		Job_Repository::update(
			$job_id,
			array(
				'status'      => 'cancelled',
				'finished_at' => current_time( 'mysql' ),
			)
		);
		return true;
	}

	/**
	 * Format job for API response.
	 *
	 * @param object $job Job row.
	 * @return array<string, mixed>
	 */
	public static function format_job( $job ) {
		$percent = $job->total_items > 0
			? round( ( $job->processed_items / $job->total_items ) * 100, 1 )
			: 0;

		return array(
			'id'               => (int) $job->id,
			'name'             => $job->name,
			'action_type'      => $job->action_type,
			'status'           => $job->status,
			'processing_mode'  => $job->processing_mode,
			'batch_size'       => (int) $job->batch_size,
			'is_dry_run'       => (bool) $job->is_dry_run,
			'total_items'      => (int) $job->total_items,
			'processed_items'  => (int) $job->processed_items,
			'failed_items'     => (int) $job->failed_items,
			'percent'          => $percent,
			'undo_available'   => (bool) $job->undo_available,
			'undo_expires_at'  => $job->undo_expires_at,
			'parent_job_id'    => $job->parent_job_id ? (int) $job->parent_job_id : null,
			'created_at'       => $job->created_at,
			'finished_at'      => $job->finished_at,
			'filter_payload'   => Sanitizer::json_decode( $job->filter_payload ),
			'action_payload'   => Sanitizer::json_decode( $job->action_payload ),
			'error_message'    => $job->error_message,
		);
	}
}
