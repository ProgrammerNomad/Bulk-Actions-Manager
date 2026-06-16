<?php
/**
 * Dashboard REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Log_Repository;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Database\Repositories\Snapshot_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Jobs\Job_Queue;
use BAM\Settings;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Dashboard_Controller
 */
class Dashboard_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/dashboard',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_dashboard' ),
			)
		);
	}

	/**
	 * GET /dashboard
	 *
	 * @return \WP_REST_Response
	 */
	public function get_dashboard() {
		$counts = Job_Repository::count_by_status();
		$recent = Job_Repository::list( array( 'limit' => 5 ) );
		$recent_formatted = array_map(
			function ( $job ) {
				return array(
					'id'          => (int) $job->id,
					'name'        => $job->name,
					'action_type' => $job->action_type,
					'status'      => $job->status,
					'created_at'  => $job->created_at,
				);
			},
			$recent
		);

		return rest_ensure_response(
			array(
				'statistics' => array(
					'total'     => $counts['total'],
					'completed' => $counts['completed'],
					'running'   => $counts['running'],
					'failed'    => $counts['failed'],
					'scheduled' => Schedule_Repository::count_active(),
				),
				'recent_jobs'  => $recent_formatted,
				'system_health' => array(
					'php_version'        => PHP_VERSION,
					'wordpress_version'  => get_bloginfo( 'version' ),
					'memory_limit'       => ini_get( 'memory_limit' ),
					'max_execution_time' => ini_get( 'max_execution_time' ),
					'cron_status'        => wp_next_scheduled( 'bam_process_queue' ) ? 'active' : 'inactive',
					'queue_status'       => count( Job_Queue::get_queue() ) . ' queued',
				),
				'undo_summary' => array(
					'undo_available_jobs' => Log_Repository::count_undo_available(),
					'snapshot_count'      => Snapshot_Repository::count_all(),
					'snapshot_retention'  => Settings::get( 'snapshot_retention_days', 30 ),
				),
			)
		);
	}
}
