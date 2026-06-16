<?php
/**
 * Actions REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Actions\Action_Registry;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Actions_Controller
 */
class Actions_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/actions',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_actions' ),
			)
		);
	}

	/**
	 * GET /actions
	 *
	 * @return \WP_REST_Response
	 */
	public function get_actions() {
		$registry = new Action_Registry();
		return rest_ensure_response( array( 'groups' => $registry->get_grouped() ) );
	}
}
