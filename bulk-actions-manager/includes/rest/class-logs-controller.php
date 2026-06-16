<?php
/**
 * Logs REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Database\Repositories\Log_Repository;
use BAM\Undo\Undo_Manager;
use BAM\Utils\Sanitizer;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logs_Controller
 */
class Logs_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/logs',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_logs' ),
			)
		);

		$this->register_route(
			'/logs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_log' ),
			)
		);

		$this->register_route(
			'/logs/(?P<id>\d+)/undo',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'undo_log' ),
			)
		);
	}

	/**
	 * GET /logs
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_logs( $request ) {
		$page = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
		$logs = Log_Repository::list(
			array(
				'limit'  => 20,
				'offset' => ( $page - 1 ) * 20,
			)
		);

		$items = array();
		foreach ( $logs as $log ) {
			$user = get_userdata( (int) $log->user_id );
			$items[] = array(
				'id'             => (int) $log->id,
				'job_id'         => (int) $log->job_id,
				'user'           => $user ? $user->display_name : '-',
				'action_type'    => $log->action_type,
				'affected_count' => (int) $log->affected_count,
				'created_at'     => $log->created_at,
				'undo_status'    => $log->undo_status,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET /logs/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_log( $request ) {
		$log = Log_Repository::find( absint( $request['id'] ) );
		if ( ! $log ) {
			return new \WP_Error( 'bam_not_found', __( 'Log not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}

		return rest_ensure_response(
			array(
				'id'             => (int) $log->id,
				'job_id'         => (int) $log->job_id,
				'action_type'    => $log->action_type,
				'affected_count' => (int) $log->affected_count,
				'failed_count'   => (int) $log->failed_count,
				'undo_status'    => $log->undo_status,
				'filter_payload' => Sanitizer::json_decode( $log->filter_payload ),
				'action_payload' => Sanitizer::json_decode( $log->action_payload ),
				'summary'        => Sanitizer::json_decode( $log->summary ),
				'errors'         => Sanitizer::json_decode( $log->errors ),
				'created_at'     => $log->created_at,
			)
		);
	}

	/**
	 * POST /logs/{id}/undo
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function undo_log( $request ) {
		$manager = new Undo_Manager();
		$result  = $manager->create_undo_job( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
