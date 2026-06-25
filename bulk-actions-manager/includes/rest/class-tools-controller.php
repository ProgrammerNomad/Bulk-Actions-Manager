<?php
/**
 * Tools REST controller.
 *
 * Destructive tools → `tool.*` jobs via Job_Manager (queue-safe, audited).
 * Export tools → immediate execution, browser JSON download.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Log_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Jobs\Job_Queue;
use BAM\Logging\Logger;
use BAM\Settings;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tools_Controller
 */
class Tools_Controller extends REST_Controller {

	/**
	 * Destructive tools that become batched tool-jobs.
	 */
	const TOOL_JOBS = array(
		'empty_trash',
		'remove_revisions',
		'remove_auto_drafts',
		'orphan_attachments',
		'orphan_metadata',
	);

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/tools/(?P<tool>[a-z_]+)',
			array(
				'methods'  => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'run_tool' ),
			)
		);
	}

	/**
	 * POST /tools/{tool}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_tool( $request ) {
		$tool = sanitize_key( $request['tool'] );

		if ( in_array( $tool, self::TOOL_JOBS, true ) ) {
			return $this->create_tool_job( $tool );
		}

		switch ( $tool ) {
			case 'export_jobs':
				return $this->export_jobs();
			case 'export_logs':
				return $this->export_logs();
			default:
				return new \WP_Error( 'bam_unknown_tool', __( 'Unknown tool.', 'bulk-actions-manager' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Create a tool-job for a destructive tool.
	 *
	 * @param string $tool Tool slug.
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function create_tool_job( $tool ) {
		$ids = $this->resolve_ids_for_tool( $tool );

		if ( empty( $ids ) ) {
			return rest_ensure_response( array(
				'message' => __( 'Nothing to process for this tool.', 'bulk-actions-manager' ),
				'total'   => 0,
			) );
		}

		$manager = new Job_Manager();
		$result  = $manager->create_tool_job( $tool, $ids );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Resolve which IDs to process for a given tool.
	 *
	 * @param string $tool Tool slug.
	 * @return array<int, int>
	 */
	private function resolve_ids_for_tool( $tool ) {
		global $wpdb;

		switch ( $tool ) {
			case 'empty_trash':
				return array_map(
					'intval',
					get_posts( array( 'post_status' => 'trash', 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'any' ) )
				);

			case 'remove_revisions':
				return array_map(
					'intval',
					get_posts( array( 'post_type' => 'revision', 'posts_per_page' => -1, 'fields' => 'ids' ) )
				);

			case 'remove_auto_drafts':
				return array_map(
					'intval',
					get_posts( array( 'post_status' => 'auto-draft', 'posts_per_page' => -1, 'fields' => 'ids', 'post_type' => 'any' ) )
				);

			case 'orphan_attachments':
				return array_map(
					'intval',
					(array) $wpdb->get_col(
						"SELECT p.ID FROM {$wpdb->posts} p
						LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
						WHERE p.post_type = 'attachment' AND p.post_parent > 0 AND parent.ID IS NULL"
					)
				);

			case 'orphan_metadata':
				// For orphan meta, we process meta_id values so each row can be deleted atomically.
				return array_map(
					'intval',
					(array) $wpdb->get_col(
						"SELECT pm.meta_id FROM {$wpdb->postmeta} pm
						LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
						WHERE p.ID IS NULL"
					)
				);
		}

		return array();
	}

	/**
	 * Export all jobs as a JSON download.
	 *
	 * @return \WP_REST_Response
	 */
	private function export_jobs() {
		$jobs = Job_Repository::list( array( 'limit' => 5000, 'offset' => 0 ) );
		$data = wp_json_encode( $jobs, JSON_PRETTY_PRINT );

		$this->log_tool_action( 'export_jobs', count( $jobs ) );

		return rest_ensure_response( array(
			'download' => true,
			'filename' => 'bam-jobs-' . gmdate( 'Y-m-d' ) . '.json',
			'data'     => $data,
			'count'    => count( $jobs ),
			'message'  => sprintf(
				/* translators: %d: number of jobs */
				__( 'Exported %d jobs.', 'bulk-actions-manager' ),
				count( $jobs )
			),
		) );
	}

	/**
	 * Export all logs as a JSON download.
	 *
	 * @return \WP_REST_Response
	 */
	private function export_logs() {
		$logs = Log_Repository::list( array( 'limit' => 5000, 'offset' => 0 ) );
		$data = wp_json_encode( $logs, JSON_PRETTY_PRINT );

		$this->log_tool_action( 'export_logs', count( $logs ) );

		return rest_ensure_response( array(
			'download' => true,
			'filename' => 'bam-logs-' . gmdate( 'Y-m-d' ) . '.json',
			'data'     => $data,
			'count'    => count( $logs ),
			'message'  => sprintf(
				/* translators: %d: number of logs */
				__( 'Exported %d log entries.', 'bulk-actions-manager' ),
				count( $logs )
			),
		) );
	}

	/**
	 * Write an immediate-tool audit log entry.
	 *
	 * @param string $tool_slug  Tool slug.
	 * @param int    $affected   Number of items affected.
	 */
	private function log_tool_action( $tool_slug, $affected ) {
		if ( ! Settings::get( 'enable_logs', true ) ) {
			return;
		}

		Logger::create_for_tool( $tool_slug, $affected );
	}
}
