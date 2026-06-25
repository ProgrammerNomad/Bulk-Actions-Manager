<?php
/**
 * Background job queue.
 *
 * @package BulkActionsManager
 */

namespace BAM\Jobs;

use BAM\Database\Repositories\Job_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Queue
 *
 * Sequential queue: one job is processed at a time. Each cron tick picks the
 * oldest queued (or currently running) job and advances it through batches
 * until it completes, pauses, fails, or hits the per-tick safety limit.
 */
class Job_Queue {

	const OPTION_KEY = 'bam_queue_jobs';

	const LOCK_KEY = 'bam_queue_processing';

	/**
	 * Max batches to run in a single cron tick (avoids PHP timeout on shared hosting).
	 */
	const MAX_BATCHES_PER_TICK = 10;

	/**
	 * Mark job for background processing (append to FIFO queue).
	 *
	 * @param int $job_id Job ID.
	 */
	public static function mark_for_processing( $job_id ) {
		$queue = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $queue ) ) {
			$queue = array();
		}
		if ( ! in_array( $job_id, $queue, true ) ) {
			$queue[] = $job_id;
			update_option( self::OPTION_KEY, $queue, false );
		}
	}

	/**
	 * Remove job from queue.
	 *
	 * @param int $job_id Job ID.
	 */
	public static function unmark( $job_id ) {
		$queue = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $queue ) ) {
			return;
		}
		$queue = array_values( array_diff( $queue, array( $job_id ) ) );
		update_option( self::OPTION_KEY, $queue, false );
	}

	/**
	 * Get queued job IDs (FIFO order).
	 *
	 * @return array<int, int>
	 */
	public static function get_queue() {
		$queue = get_option( self::OPTION_KEY, array() );
		return is_array( $queue ) ? array_map( 'intval', $queue ) : array();
	}

	/**
	 * Whether any job is currently active (queued or running).
	 *
	 * @return bool
	 */
	public static function has_active_work() {
		return Job_Repository::has_active_work();
	}

	/**
	 * Process one job per cron tick (sequential queue).
	 *
	 * Finds the currently running job, or promotes the oldest queued job.
	 * Runs batches on that single job until completion or the per-tick limit.
	 */
	public static function process_queue() {
		if ( get_transient( self::LOCK_KEY ) ) {
			return;
		}

		set_transient( self::LOCK_KEY, 1, 5 * MINUTE_IN_SECONDS );

		try {
			self::process_one_job();
		} finally {
			delete_transient( self::LOCK_KEY );
		}
	}

	/**
	 * Find and advance a single active job.
	 */
	private static function process_one_job() {
		$queue = self::get_queue();
		if ( empty( $queue ) ) {
			return;
		}

		// Prefer an already-running job so we don't skip over it.
		$active_id = Job_Repository::get_running_job_id();
		if ( ! $active_id ) {
			// Promote oldest queued job.
			$active_id = reset( $queue );
		}

		if ( ! $active_id || ! in_array( (int) $active_id, $queue, true ) ) {
			return;
		}

		$processor    = new Job_Processor();
		$batch_count  = 0;
		$terminal     = array( 'completed', 'failed', 'cancelled' );

		do {
			$result = $processor->process_batch( $active_id );

			if ( is_wp_error( $result ) ) {
				self::unmark( $active_id );
				return;
			}

			if ( in_array( $result['status'], $terminal, true ) ) {
				self::unmark( $active_id );
				return;
			}

			// Stop advancing if job paused (errors, manual pause via REST, etc.).
			if ( 'paused' === $result['status'] ) {
				return;
			}

			$batch_count++;
		} while ( $batch_count < self::MAX_BATCHES_PER_TICK );
	}
}
