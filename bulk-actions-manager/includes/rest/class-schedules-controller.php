<?php
/**
 * Schedules REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Cron\Schedule_Runner;
use BAM\Database\Repositories\Schedule_Repository;
use BAM\Utils\Sanitizer;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schedules_Controller
 */
class Schedules_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/schedules',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_schedules' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_schedule' ),
				),
			)
		);

		$this->register_route(
			'/schedules/(?P<id>\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_schedule' ),
				),
			)
		);
	}

	/**
	 * GET /schedules
	 *
	 * @return \WP_REST_Response
	 */
	public function list_schedules() {
		$schedules = Schedule_Repository::list_all();
		$items     = array();

		foreach ( $schedules as $schedule ) {
			$items[] = array(
				'id'              => (int) $schedule->id,
				'name'            => $schedule->name,
				'action_type'     => $schedule->action_type,
				'cron_expression' => $schedule->cron_expression,
				'next_run_at'     => $schedule->next_run_at,
				'is_active'       => (bool) $schedule->is_active,
			);
		}

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /schedules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_schedule( $request ) {
		$params = $request->get_json_params() ?: $request->get_params();

		$cron = sanitize_text_field( $params['cron_expression'] ?? 'daily' );
		$next = Schedule_Runner::calculate_next_run( $cron );

		$id = Schedule_Repository::create(
			array(
				'name'            => sanitize_text_field( $params['name'] ?? __( 'Scheduled Job', 'bulk-actions-manager' ) ),
				'filter_payload'  => $params['filter'] ?? array(),
				'action_type'     => sanitize_text_field( $params['action_type'] ?? '' ),
				'action_payload'  => $params['action_payload'] ?? array(),
				'cron_expression' => $cron,
				'next_run_at'     => $next,
			)
		);

		if ( ! $id ) {
			return new \WP_Error( 'bam_schedule_failed', __( 'Failed to create schedule.', 'bulk-actions-manager' ) );
		}

		return rest_ensure_response( array( 'id' => $id ) );
	}

	/**
	 * DELETE /schedules/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function delete_schedule( $request ) {
		global $wpdb;
		$id = absint( $request['id'] );
		$deleted = $wpdb->delete( Schedule_Repository::table(), array( 'id' => $id ) );
		if ( ! $deleted ) {
			return new \WP_Error( 'bam_not_found', __( 'Schedule not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( array( 'success' => true ) );
	}
}
