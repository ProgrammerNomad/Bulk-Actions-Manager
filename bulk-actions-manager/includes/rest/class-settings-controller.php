<?php
/**
 * Settings REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Settings;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Settings_Controller
 */
class Settings_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'update_settings' ),
				),
			)
		);
	}

	/**
	 * GET /settings
	 *
	 * @return \WP_REST_Response
	 */
	public function get_settings() {
		return rest_ensure_response( Settings::all() );
	}

	/**
	 * POST /settings
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_settings( $request ) {
		$params = $request->get_json_params() ?: $request->get_params();
		Settings::update( (array) $params );
		return rest_ensure_response( Settings::all() );
	}
}
