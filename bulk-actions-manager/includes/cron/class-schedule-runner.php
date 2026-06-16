<?php
/**
 * Scheduled job runner.
 *
 * @package BulkActionsManager
 */

namespace BAM\Cron;

use BAM\Database\Repositories\Schedule_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schedule_Runner
 */
class Schedule_Runner {

	/**
	 * Run all due schedules.
	 */
	public static function run_due() {
		$schedules = Schedule_Repository::get_due();
		$manager   = new Job_Manager();

		foreach ( $schedules as $schedule ) {
			$result = $manager->create(
				array(
					'name'             => $schedule->name,
					'filter'           => Sanitizer::json_decode( $schedule->filter_payload ),
					'action_type'      => $schedule->action_type,
					'action_payload'   => Sanitizer::json_decode( $schedule->action_payload ),
					'processing_mode'  => 'background',
				)
			);

			$next = self::calculate_next_run( $schedule->cron_expression );
			$update = array(
				'last_run_at' => current_time( 'mysql' ),
				'next_run_at' => $next,
			);

			if ( ! is_wp_error( $result ) && ! empty( $result['job_id'] ) ) {
				$update['last_job_id'] = (int) $result['job_id'];
			}

			Schedule_Repository::update( (int) $schedule->id, $update );
		}
	}

	/**
	 * Calculate next run datetime from expression.
	 *
	 * @param string $expression Cron expression (hourly, daily, weekly, monthly).
	 * @return string MySQL datetime.
	 */
	public static function calculate_next_run( $expression ) {
		switch ( $expression ) {
			case 'hourly':
				return gmdate( 'Y-m-d H:i:s', strtotime( '+1 hour' ) );
			case 'weekly':
				return gmdate( 'Y-m-d H:i:s', strtotime( '+1 week' ) );
			case 'monthly':
				return gmdate( 'Y-m-d H:i:s', strtotime( '+1 month' ) );
			case 'daily':
			default:
				return gmdate( 'Y-m-d H:i:s', strtotime( '+1 day' ) );
		}
	}
}
