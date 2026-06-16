<?php
/**
 * Base REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Utils\Capabilities;
use WP_REST_Controller;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class REST_Controller
 */
abstract class REST_Controller extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = BAM_REST_NAMESPACE;

	/**
	 * Permission check.
	 *
	 * @return bool
	 */
	public function permissions_check() {
		return Capabilities::current_user_can();
	}

	/**
	 * Register a route helper.
	 *
	 * @param string               $route    Route path.
	 * @param array<string, mixed> $args     Route args.
	 */
	protected function register_route( $route, array $args ) {
		register_rest_route(
			$this->namespace,
			$route,
			array_merge(
				array(
					'permission_callback' => array( $this, 'permissions_check' ),
				),
				$args
			)
		);
	}
}
