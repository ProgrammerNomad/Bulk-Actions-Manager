<?php
/**
 * Background job queue.
 *
 * @package BulkActionsManager
 */

namespace BAM\Jobs;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Queue
 */
class Job_Queue {

	const OPTION_KEY = 'bam_queue_jobs';

	/**
	 * Mark job for background processing.
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
	 * Get queued job IDs.
	 *
	 * @return array<int, int>
	 */
	public static function get_queue() {
		$queue = get_option( self::OPTION_KEY, array() );
		return is_array( $queue ) ? array_map( 'intval', $queue ) : array();
	}

	/**
	 * Process all queued jobs (one batch each).
	 */
	public static function process_queue() {
		$processor = new Job_Processor();
		foreach ( self::get_queue() as $job_id ) {
			$result = $processor->process_batch( $job_id );
			if ( is_wp_error( $result ) ) {
				self::unmark( $job_id );
				continue;
			}
			if ( in_array( $result['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
				self::unmark( $job_id );
			}
		}
	}
}
