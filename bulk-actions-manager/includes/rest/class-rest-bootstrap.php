<?php
/**
 * REST API bootstrap.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST_Bootstrap
 */
class REST_Bootstrap {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register all REST routes.
	 */
	public function register_routes() {
		( new Filters_Controller() )->register_routes();
		( new Actions_Controller() )->register_routes();
		( new Preview_Controller() )->register_routes();
		( new Jobs_Controller() )->register_routes();
		( new Logs_Controller() )->register_routes();
		( new Dashboard_Controller() )->register_routes();
		( new Settings_Controller() )->register_routes();
		( new Schedules_Controller() )->register_routes();
		( new Tools_Controller() )->register_routes();
	}
}
