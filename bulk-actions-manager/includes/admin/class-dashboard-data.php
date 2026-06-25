<?php
/**
 * Dashboard data for admin page and REST API.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Log_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Database\Repositories\Snapshot_Repository;
use BAM\Jobs\Job_Queue;
use BAM\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard_Data
 */
class Dashboard_Data {

	/**
	 * Get dashboard payload.
	 *
	 * @return array<string, mixed>
	 */
	public static function get() {
		$counts = Job_Repository::count_by_status();

		return array(
			'recent_jobs'   => self::format_jobs( Job_Repository::list( array( 'limit' => 5 ) ) ),
			'running_jobs'  => self::format_jobs( Job_Repository::list_by_statuses( array( 'running', 'queued', 'paused' ), 8 ) ),
			'undoable_jobs' => self::format_jobs( Job_Repository::list_undoable( 8 ) ),
			'counts'        => array(
				'total'     => $counts['total'],
				'running'   => $counts['running'] + $counts['queued'] + $counts['paused'],
				'completed' => $counts['completed'],
				'failed'    => $counts['failed'],
				'scheduled' => Schedule_Repository::count_active(),
				'undo'      => Log_Repository::count_undo_available(),
			),
			'system_status' => array(
				'php_version'        => PHP_VERSION,
				'wordpress_version'  => get_bloginfo( 'version' ),
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'cron_status'        => wp_next_scheduled( 'bam_process_queue' ) ? __( 'Active', 'bulk-actions-manager' ) : __( 'Inactive', 'bulk-actions-manager' ),
				'queue_count'        => count( Job_Queue::get_queue() ),
				'snapshot_count'     => Snapshot_Repository::count_all(),
				'snapshot_retention' => Settings::get( 'snapshot_retention_days', 30 ),
			),
			// Legacy REST keys.
			'statistics'    => array(
				'total'     => $counts['total'],
				'completed' => $counts['completed'],
				'running'   => $counts['running'],
				'failed'    => $counts['failed'],
				'scheduled' => Schedule_Repository::count_active(),
			),
			'system_health' => array(
				'php_version'        => PHP_VERSION,
				'wordpress_version'  => get_bloginfo( 'version' ),
				'memory_limit'       => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'cron_status'        => wp_next_scheduled( 'bam_process_queue' ) ? 'active' : 'inactive',
				'queue_status'       => count( Job_Queue::get_queue() ) . ' queued',
			),
			'undo_summary'  => array(
				'undo_available_jobs' => Log_Repository::count_undo_available(),
				'snapshot_count'      => Snapshot_Repository::count_all(),
				'snapshot_retention'  => Settings::get( 'snapshot_retention_days', 30 ),
			),
		);
	}

	/**
	 * Format job rows for templates.
	 *
	 * @param array<int, object> $jobs Job rows.
	 * @return array<int, object>
	 */
	private static function format_jobs( array $jobs ) {
		return array_map(
			static function ( $job ) {
				return $job;
			},
			$jobs
		);
	}
}
