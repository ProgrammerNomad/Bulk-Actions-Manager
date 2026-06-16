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
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_schedule' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_schedule' ),
				),
				array(
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => array( $this, 'delete_schedule' ),
				),
			)
		);
	}

	/**
	 * Format schedule row for API.
	 *
	 * @param object $schedule Schedule row.
	 * @return array<string, mixed>
	 */
	private function format_schedule( $schedule ) {
		return array(
			'id'              => (int) $schedule->id,
			'name'            => $schedule->name,
			'action_type'     => $schedule->action_type,
			'cron_expression' => $schedule->cron_expression,
			'next_run_at'     => $schedule->next_run_at,
			'last_run_at'     => $schedule->last_run_at,
			'is_active'       => (bool) $schedule->is_active,
			'filter'          => Sanitizer::json_decode( $schedule->filter_payload ),
			'action_payload'  => Sanitizer::json_decode( $schedule->action_payload ),
		);
	}

	/**
	 * GET /schedules
	 *
	 * @return \WP_REST_Response
	 */
	public function list_schedules() {
		$schedules = Schedule_Repository::list_all();
		$items     = array_map( array( $this, 'format_schedule' ), $schedules );

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * GET /schedules/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_schedule( $request ) {
		$schedule = Schedule_Repository::find( absint( $request['id'] ) );
		if ( ! $schedule ) {
			return new \WP_Error( 'bam_not_found', __( 'Schedule not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( $this->format_schedule( $schedule ) );
	}

	/**
	 * POST /schedules
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_schedule( $request ) {
		$params = $request->get_json_params() ?: $request->get_params();
		$data   = $this->sanitize_schedule_params( $params );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$id = Schedule_Repository::create( $data );
		if ( ! $id ) {
			return new \WP_Error( 'bam_schedule_failed', __( 'Failed to create schedule.', 'bulk-actions-manager' ) );
		}

		return rest_ensure_response( array( 'id' => $id ) );
	}

	/**
	 * PUT /schedules/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_schedule( $request ) {
		$id       = absint( $request['id'] );
		$schedule = Schedule_Repository::find( $id );
		if ( ! $schedule ) {
			return new \WP_Error( 'bam_not_found', __( 'Schedule not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}

		$params = $request->get_json_params() ?: $request->get_params();
		$data   = $this->sanitize_schedule_params( $params );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		Schedule_Repository::update( $id, $data );
		return rest_ensure_response( array( 'success' => true, 'id' => $id ) );
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

	/**
	 * Sanitize schedule parameters.
	 *
	 * @param array<string, mixed> $params Raw params.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function sanitize_schedule_params( array $params ) {
		$action_type = sanitize_text_field( $params['action_type'] ?? '' );
		if ( empty( $action_type ) ) {
			return new \WP_Error( 'bam_missing_action', __( 'Action type is required.', 'bulk-actions-manager' ) );
		}

		$cron = sanitize_text_field( $params['cron_expression'] ?? 'daily' );
		$allowed_cron = array( 'hourly', 'daily', 'weekly', 'monthly' );
		if ( ! in_array( $cron, $allowed_cron, true ) ) {
			$cron = 'daily';
		}

		return array(
			'name'            => sanitize_text_field( $params['name'] ?? __( 'Scheduled Job', 'bulk-actions-manager' ) ),
			'filter_payload'  => $params['filter'] ?? array(),
			'action_type'     => $action_type,
			'action_payload'  => $params['action_payload'] ?? array(),
			'cron_expression' => $cron,
			'next_run_at'     => Schedule_Runner::calculate_next_run( $cron ),
			'is_active'       => ( ! isset( $params['is_active'] ) || ! empty( $params['is_active'] ) ) ? 1 : 0,
		);
	}
}
