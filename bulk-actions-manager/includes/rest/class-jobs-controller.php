<?php
/**
 * Jobs REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Database\Repositories\Job_Repository;
use BAM\Jobs\Job_Manager;
use BAM\Jobs\Job_Processor;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Jobs_Controller
 */
class Jobs_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/jobs',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_jobs' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_job' ),
				),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_job' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)/batch',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'process_batch' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)/pause',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'pause_job' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)/resume',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'resume_job' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)/cancel',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'cancel_job' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_job' ),
			)
		);

		$this->register_route(
			'/jobs/(?P<id>\d+)/clone',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'clone_job' ),
			)
		);
	}

	/**
	 * GET /jobs
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_jobs( $request ) {
		$status = sanitize_text_field( $request->get_param( 'status' ) ?? '' );
		$page   = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );
		$limit  = 20;
		$jobs   = Job_Repository::list(
			array(
				'status' => $status,
				'limit'  => $limit,
				'offset' => ( $page - 1 ) * $limit,
			)
		);

		$items = array_map( array( Job_Manager::class, 'format_job' ), $jobs );

		return rest_ensure_response( array( 'items' => $items ) );
	}

	/**
	 * POST /jobs
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function create_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->create( $request->get_json_params() ?: $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * GET /jobs/{id}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_job( $request ) {
		$job = Job_Repository::find( absint( $request['id'] ) );
		if ( ! $job ) {
			return new \WP_Error( 'bam_not_found', __( 'Job not found.', 'bulk-actions-manager' ), array( 'status' => 404 ) );
		}
		return rest_ensure_response( Job_Manager::format_job( $job ) );
	}

	/**
	 * POST /jobs/{id}/batch
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function process_batch( $request ) {
		$processor = new Job_Processor();
		$result    = $processor->process_batch( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST /jobs/{id}/pause
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function pause_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->pause( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /jobs/{id}/resume
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function resume_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->resume( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$processor = new Job_Processor();
		$batch     = $processor->process_batch( absint( $request['id'] ) );
		return rest_ensure_response( is_wp_error( $batch ) ? array( 'success' => true ) : $batch );
	}

	/**
	 * POST /jobs/{id}/cancel
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function cancel_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->cancel( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * PUT /jobs/{id} - update an editable job with strict field rules.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function update_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->update( absint( $request['id'] ), $request->get_json_params() ?: $request->get_params() );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}

	/**
	 * POST /jobs/{id}/clone - clone a job config as a new queued job.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function clone_job( $request ) {
		$manager = new Job_Manager();
		$result  = $manager->clone_job( absint( $request['id'] ) );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return rest_ensure_response( $result );
	}
}
