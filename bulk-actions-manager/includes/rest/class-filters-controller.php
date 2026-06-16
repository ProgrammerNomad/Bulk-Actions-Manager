<?php
/**
 * Filters REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Filters\Filter_Registry;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Filters_Controller
 */
class Filters_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/filters',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_filters' ),
			)
		);
	}

	/**
	 * GET /filters
	 *
	 * @return \WP_REST_Response
	 */
	public function get_filters() {
		$registry = new Filter_Registry();
		return rest_ensure_response(
			array(
				'filters'    => $registry->get_all(),
				'post_types' => Filter_Registry::get_post_types(),
				'statuses'   => get_post_stati(),
				'authors'    => get_users( array( 'fields' => array( 'ID', 'display_name' ) ) ),
			)
		);
	}
}
