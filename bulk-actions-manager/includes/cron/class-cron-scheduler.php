<?php
/**
 * WP Cron scheduler.
 *
 * @package BulkActionsManager
 */

namespace BAM\Cron;

use BAM\Database\Repositories\Job_Item_Repository;
use BAM\Jobs\Job_Queue;
use BAM\Logging\Logger;
use BAM\Undo\Snapshot_Cleanup;

defined( 'ABSPATH' ) || exit;

/**
 * Class Cron_Scheduler
 */
class Cron_Scheduler {

	/**
	 * Constructor - bind cron hooks.
	 */
	public function __construct() {
		add_action( 'bam_process_queue', array( Job_Queue::class, 'process_queue' ) );
		add_action( 'bam_run_schedules', array( Schedule_Runner::class, 'run_due' ) );
		add_action( 'bam_cleanup_snapshots', array( Snapshot_Cleanup::class, 'run' ) );
		add_action( 'bam_cleanup_logs', array( Logger::class, 'cleanup_old_logs' ) );
		add_action( 'bam_cleanup_stale_jobs', array( __CLASS__, 'cleanup_stale_jobs' ) );
	}

	/**
	 * Schedule cron events on activation.
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'bam_process_queue' ) ) {
			wp_schedule_event( time(), 'bam_one_minute', 'bam_process_queue' );
		}
		if ( ! wp_next_scheduled( 'bam_run_schedules' ) ) {
			wp_schedule_event( time(), 'bam_fifteen_minutes', 'bam_run_schedules' );
		}
		if ( ! wp_next_scheduled( 'bam_cleanup_snapshots' ) ) {
			wp_schedule_event( time(), 'daily', 'bam_cleanup_snapshots' );
		}
		if ( ! wp_next_scheduled( 'bam_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'bam_cleanup_logs' );
		}
		if ( ! wp_next_scheduled( 'bam_cleanup_stale_jobs' ) ) {
			wp_schedule_event( time(), 'daily', 'bam_cleanup_stale_jobs' );
		}

		add_filter( 'cron_schedules', array( __CLASS__, 'add_intervals' ) );
	}

	/**
	 * Add custom cron intervals.
	 *
	 * @param array<string, array<string, int|string>> $schedules Schedules.
	 * @return array<string, array<string, int|string>>
	 */
	public static function add_intervals( $schedules ) {
		$schedules['bam_one_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute (BAM)', 'bulk-actions-manager' ),
		);
		$schedules['bam_fifteen_minutes'] = array(
			'interval' => 900,
			'display'  => __( 'Every 15 Minutes (BAM)', 'bulk-actions-manager' ),
		);
		return $schedules;
	}

	/**
	 * Clear cron events on deactivation.
	 */
	public static function clear_events() {
		wp_clear_scheduled_hook( 'bam_process_queue' );
		wp_clear_scheduled_hook( 'bam_run_schedules' );
		wp_clear_scheduled_hook( 'bam_cleanup_snapshots' );
		wp_clear_scheduled_hook( 'bam_cleanup_logs' );
		wp_clear_scheduled_hook( 'bam_cleanup_stale_jobs' );
	}

	/**
	 * Mark stuck running jobs as failed.
	 */
	public static function cleanup_stale_jobs() {
		global $wpdb;

		Job_Item_Repository::reset_stale_processing( 30 );

		$table = $wpdb->prefix . 'bam_jobs';
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, error_message = %s, finished_at = %s
				WHERE status = %s AND started_at < %s",
				'failed',
				__( 'Job timed out.', 'bulk-actions-manager' ),
				current_time( 'mysql' ),
				'running',
				gmdate( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
			)
		);
	}
}
