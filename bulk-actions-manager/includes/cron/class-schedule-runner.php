<?php
/**
 * Scheduled job runner.
 *
 * @package BulkActionsManager
 */

namespace BAM\Cron;

use BAM\Database\Repositories\Schedule_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Jobs\Job_Queue;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schedule_Runner
 *
 * Anti-flood: if the queue already has active work, skip the tick entirely.
 * When idle, create at most one job per tick (oldest due schedule).
 * Timezone: next_run_at uses site local time, not UTC.
 */
class Schedule_Runner {

	/**
	 * Run due schedules - one per tick, only when the queue is idle.
	 */
	public static function run_due() {
		// Anti-flood: do not create new jobs while the queue is busy.
		if ( Job_Queue::has_active_work() ) {
			return;
		}

		$schedules = Schedule_Repository::get_due();
		if ( empty( $schedules ) ) {
			return;
		}

		$manager = new Job_Manager();

		// Process only the first due schedule this tick to avoid flooding.
		$schedule = reset( $schedules );

		$result = $manager->create(
			array(
				'name'            => $schedule->name,
				'filter'          => Sanitizer::json_decode( $schedule->filter_payload ),
				'action_type'     => $schedule->action_type,
				'action_payload'  => Sanitizer::json_decode( $schedule->action_payload ),
				'processing_mode' => 'background',
				'user_id'         => (int) $schedule->user_id,
			)
		);

		// Advance next_run_at only on successful job creation.
		$update = array( 'last_run_at' => current_time( 'mysql' ) );

		if ( ! is_wp_error( $result ) && ! empty( $result['job_id'] ) ) {
			$update['last_job_id'] = (int) $result['job_id'];
			$update['next_run_at'] = self::calculate_next_run( $schedule->cron_expression );
		}

		Schedule_Repository::update( (int) $schedule->id, $update );
	}

	/**
	 * Calculate next run datetime from a cron expression.
	 *
	 * Uses site local time (via current_time) rather than UTC so that daily/weekly
	 * schedules fire at the expected wall-clock time.
	 *
	 * @param string $expression Cron expression (hourly, daily, weekly, monthly).
	 * @return string MySQL datetime in site local time.
	 */
	public static function calculate_next_run( $expression ) {
		$now = current_time( 'timestamp' );
		switch ( $expression ) {
			case 'hourly':
				return date( 'Y-m-d H:i:s', $now + HOUR_IN_SECONDS );
			case 'weekly':
				return date( 'Y-m-d H:i:s', $now + WEEK_IN_SECONDS );
			case 'monthly':
				return date( 'Y-m-d H:i:s', strtotime( '+1 month', $now ) );
			case 'daily':
			default:
				return date( 'Y-m-d H:i:s', $now + DAY_IN_SECONDS );
		}
	}
}
