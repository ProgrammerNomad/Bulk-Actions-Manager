<?php
/**
 * Job manager - create and control jobs.
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
use BAM\Admin\Export_Download;
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
		Job_Repository::update(
			$job_id,
			array(
				'status'        => 'running',
				'error_message' => '',
			)
		);

		if ( 'background' === $job->processing_mode ) {
			Job_Queue::mark_for_processing( $job_id );
		}

		return true;
	}

	/**
	 * Whether a job status allows permanent removal from the jobs list.
	 *
	 * @param string $status Job status.
	 * @return bool
	 */
	public static function is_terminal_status( $status ) {
		return in_array( $status, array( 'completed', 'failed', 'cancelled' ), true );
	}

	/**
	 * Delete a terminal job and its item rows.
	 *
	 * @param int $job_id Job ID.
	 * @return true|\WP_Error
	 */
	public function delete_job( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			return new \WP_Error( 'bam_job_not_found', __( 'Job not found.', 'bulk-actions-manager' ) );
		}

		if ( ! self::is_terminal_status( $job->status ) ) {
			return new \WP_Error( 'bam_cannot_delete', __( 'Only completed, failed, or cancelled jobs can be deleted.', 'bulk-actions-manager' ) );
		}

		Job_Queue::unmark( $job_id );
		Job_Item_Repository::delete_for_job( $job_id );
		Job_Repository::delete( $job_id );

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
	 * Update a queued or paused job with strict edit rules.
	 *
	 * Rules (enforced here, not just in UI):
	 * - `running`, `completed`, `failed`, `cancelled` → not editable.
	 * - `queued`/`paused` with `processed_items > 0` → only name, batch_size,
	 *   and (when safe) processing_mode may change.
	 * - `queued` with `processed_items = 0` → full edit including filter and action.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<string, mixed> $data   Fields to update.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function update( $job_id, array $data ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			return new \WP_Error( 'bam_not_found', __( 'Job not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}

		$non_editable = array( 'running', 'completed', 'failed', 'cancelled' );
		if ( in_array( $job->status, $non_editable, true ) ) {
			return new \WP_Error(
				'bam_not_editable',
				__( 'This job cannot be edited in its current state.', 'bulk-actions-manager' ),
				array( 'status' => 400 )
			);
		}

		$has_progress = (int) $job->processed_items > 0;

		// Fields that are always forbidden when progress has started.
		$forbidden_when_partial = array( 'filter', 'filter_payload', 'action_type', 'action_payload' );
		if ( $has_progress ) {
			foreach ( $forbidden_when_partial as $field ) {
				if ( array_key_exists( $field, $data ) ) {
					return new \WP_Error(
						'bam_partial_edit_forbidden',
						__( 'Filter, action type, and action payload cannot be changed after processing has started.', 'bulk-actions-manager' ),
						array( 'status' => 400 )
					);
				}
			}
		}

		$update = array();

		if ( isset( $data['name'] ) ) {
			$update['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['batch_size'] ) ) {
			$update['batch_size'] = absint( $data['batch_size'] );
		}

		if ( isset( $data['processing_mode'] ) && 'running' !== $job->status ) {
			$allowed_modes = array( 'ajax', 'background' );
			$mode          = sanitize_text_field( $data['processing_mode'] );
			if ( in_array( $mode, $allowed_modes, true ) ) {
				$update['processing_mode'] = $mode;
			}
		}

		if ( ! $has_progress ) {
			// Full edit: filter, action, payload may change.
			if ( isset( $data['filter'] ) || isset( $data['filter_payload'] ) ) {
				$filter = $data['filter'] ?? $data['filter_payload'];
				$valid  = \BAM\Filters\Filter_Validator::validate( (array) $filter );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				// Re-query items.
				$query_result = \BAM\Filters\Filter_Compiler::query( (array) $filter, 0 );
				$total        = $query_result['total'];

				\BAM\Database\Repositories\Job_Item_Repository::delete_for_job( $job_id );
				if ( ! empty( $query_result['ids'] ) ) {
					\BAM\Database\Repositories\Job_Item_Repository::bulk_insert( $job_id, $query_result['ids'] );
				}

				$update['filter_payload'] = (array) $filter;
				$update['total_items']    = $total;
			}

			if ( isset( $data['action_type'] ) ) {
				$action_type = sanitize_text_field( $data['action_type'] );
				$action      = $this->actions->get( $action_type );
				if ( ! $action ) {
					return new \WP_Error( 'bam_invalid_action', __( 'Invalid action type.', 'bulk-actions-manager' ) );
				}
				$update['action_type'] = $action_type;
			}

			if ( isset( $data['action_payload'] ) ) {
				$update['action_payload'] = (array) $data['action_payload'];
			}
		}

		if ( empty( $update ) ) {
			return array( 'success' => true, 'id' => $job_id, 'updated' => array() );
		}

		Job_Repository::update( $job_id, $update );
		return array( 'success' => true, 'id' => $job_id, 'updated' => array_keys( $update ) );
	}

	/**
	 * Create a tool-job - a normal queued job for a tool.* action type.
	 *
	 * @param string         $tool_slug Tool slug (e.g. empty_trash).
	 * @param array<int,int> $ids       Object IDs to process.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function create_tool_job( $tool_slug, array $ids ) {
		$action_type = 'tool.' . $tool_slug;
		$action      = $this->actions->get( $action_type );
		if ( ! $action ) {
			return new \WP_Error( 'bam_invalid_action', __( 'Unknown tool action.', 'bulk-actions-manager' ) );
		}

		$total      = count( $ids );
		$batch_size = (int) Settings::get( 'default_batch_size', 25 );
		$name       = sprintf(
			/* translators: %s: tool label */
			__( 'Tool: %s', 'bulk-actions-manager' ),
			$action->get_label()
		);

		$job_id = Job_Repository::create(
			array(
				'name'            => $name,
				'action_type'     => $action_type,
				'action_payload'  => array( 'tool_slug' => $tool_slug ),
				'filter_payload'  => array(),
				'status'          => 'queued',
				'processing_mode' => 'background',
				'batch_size'      => $batch_size,
				'is_dry_run'      => 0,
				'total_items'     => $total,
			)
		);

		if ( ! $job_id ) {
			return new \WP_Error( 'bam_job_create_failed', __( 'Failed to create tool job.', 'bulk-actions-manager' ) );
		}

		Job_Item_Repository::bulk_insert( $job_id, $ids );

		if ( Settings::get( 'enable_logs', true ) ) {
			Logger::create_for_job( $job_id, $action_type, array(), array( 'tool_slug' => $tool_slug ) );
		}

		Job_Queue::mark_for_processing( $job_id );

		return array(
			'job_id'  => $job_id,
			'total'   => $total,
			'status'  => 'queued',
			'message' => sprintf(
				/* translators: 1: tool label, 2: count */
				__( '%1$s: %2$d items queued for processing.', 'bulk-actions-manager' ),
				$action->get_label(),
				$total
			),
			'jobs_url' => admin_url( 'admin.php?page=bam-jobs&job_id=' . $job_id ),
		);
	}

	/**
	 * Clone a job - creates a new queued job with the same config but no progress.
	 *
	 * @param int $job_id Source job ID.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function clone_job( $job_id ) {
		$job = Job_Repository::find( $job_id );
		if ( ! $job ) {
			return new \WP_Error( 'bam_not_found', __( 'Job not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}

		$action_type = $job->action_type;
		$action      = $this->actions->get( $action_type );
		if ( ! $action ) {
			return new \WP_Error( 'bam_invalid_action', __( 'Invalid action type.', 'bulk-actions-manager' ) );
		}

		$filter       = Sanitizer::json_decode( $job->filter_payload );
		$query_result = Filter_Compiler::query( (array) $filter, 0 );
		$total        = $query_result['total'];

		$new_job_id = Job_Repository::create(
			array(
				'name'            => sprintf(
					/* translators: %s: original job name */
					__( 'Clone of %s', 'bulk-actions-manager' ),
					$job->name
				),
				'action_type'     => $action_type,
				'action_payload'  => Sanitizer::json_decode( $job->action_payload ),
				'filter_payload'  => $filter,
				'status'          => 'queued',
				'processing_mode' => $job->processing_mode,
				'batch_size'      => (int) $job->batch_size,
				'is_dry_run'      => 0,
				'total_items'     => $total,
			)
		);

		if ( ! $new_job_id ) {
			return new \WP_Error( 'bam_clone_failed', __( 'Failed to clone job.', 'bulk-actions-manager' ) );
		}

		if ( ! empty( $query_result['ids'] ) ) {
			\BAM\Database\Repositories\Job_Item_Repository::bulk_insert( $new_job_id, $query_result['ids'] );
		}

		if ( Settings::get( 'enable_logs', true ) ) {
			Logger::create_for_job( $new_job_id, $action_type, $filter, Sanitizer::json_decode( $job->action_payload ) );
		}

		Job_Queue::mark_for_processing( $new_job_id );

		return array(
			'job_id' => $new_job_id,
			'total'  => $total,
			'status' => 'queued',
		);
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
			'error_message'        => $job->error_message,
			'export_download_url'  => Export_Download::get_url( (int) $job->id ),
		);
	}
}
