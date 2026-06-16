<?php
/**
 * Preview REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Filters\Filter_Compiler;
use BAM\Filters\Filter_Validator;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Preview_Controller
 */
class Preview_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview' ),
				'args'                => array(
					'filter' => array(
						'required' => true,
						'type'     => 'object',
					),
				),
			)
		);

		$this->register_route(
			'/preview/export',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'export_preview' ),
			)
		);
	}

	/**
	 * POST /preview
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function preview( $request ) {
		$filter = (array) $request->get_param( 'filter' );
		$valid  = Filter_Validator::validate( $filter );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$result = Filter_Compiler::query( $filter, 20 );
		$items  = Filter_Compiler::format_preview_items( $result['ids'] );

		return rest_ensure_response(
			array(
				'total' => $result['total'],
				'items' => $items,
			)
		);
	}

	/**
	 * POST /preview/export — CSV download.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function export_preview( $request ) {
		$filter = (array) $request->get_param( 'filter' );
		$valid  = Filter_Validator::validate( $filter );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$result = Filter_Compiler::query( $filter, 20 );
		$items  = Filter_Compiler::format_preview_items( $result['ids'] );

		$csv = "ID,Title,Type,Status,Author,Date\n";
		foreach ( $items as $item ) {
			$csv .= sprintf(
				"%d,\"%s\",%s,%s,\"%s\",%s\n",
				$item['id'],
				str_replace( '"', '""', $item['title'] ),
				$item['type'],
				$item['status'],
				str_replace( '"', '""', $item['author'] ),
				$item['date']
			);
		}

		return rest_ensure_response(
			array(
				'csv'   => $csv,
				'total' => $result['total'],
			)
		);
	}
}
