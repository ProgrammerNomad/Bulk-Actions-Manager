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
	 * Supports a single endpoint (associative args with `methods`) or multiple
	 * endpoints (numeric array of route definitions). Each endpoint must have its
	 * own `permission_callback` per WordPress REST API requirements.
	 *
	 * @param string               $route Route path.
	 * @param array<string, mixed> $args  Route args.
	 */
	protected function register_route( $route, array $args ) {
		$permission = array( $this, 'permissions_check' );

		if ( isset( $args['methods'] ) ) {
			if ( ! isset( $args['permission_callback'] ) ) {
				$args['permission_callback'] = $permission;
			}
		} else {
			foreach ( $args as $key => $endpoint ) {
				if ( ! is_array( $endpoint ) ) {
					continue;
				}
				if ( ! isset( $endpoint['permission_callback'] ) ) {
					$args[ $key ] = array_merge(
						array( 'permission_callback' => $permission ),
						$endpoint
					);
				}
			}
		}

		register_rest_route( $this->namespace, $route, $args );
	}
}
