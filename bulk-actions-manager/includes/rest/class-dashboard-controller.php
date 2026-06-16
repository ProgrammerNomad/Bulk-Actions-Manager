<?php
/**
 * Dashboard REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Admin\Dashboard_Data;
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
		return rest_ensure_response( Dashboard_Data::get() );
	}
}
