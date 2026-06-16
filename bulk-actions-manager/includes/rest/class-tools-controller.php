<?php
/**
 * Tools REST controller.
 *
 * @package BulkActionsManager
 */

namespace BAM\REST;

use BAM\Database\Repositories\Job_Repository;
use BAM\Database\Repositories\Log_Repository;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tools_Controller
 */
class Tools_Controller extends REST_Controller {

	/**
	 * Register routes.
	 */
	public function register_routes() {
		$this->register_route(
			'/tools/(?P<tool>[a-z_]+)',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_tool' ),
			)
		);
	}

	/**
	 * POST /tools/{tool}
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function run_tool( $request ) {
		$tool = sanitize_key( $request['tool'] );

		switch ( $tool ) {
			case 'remove_revisions':
				$count = $this->delete_posts_by_type( 'revision' );
				return rest_ensure_response( array( 'message' => sprintf( __( 'Removed %d revisions.', 'bulk-actions-manager' ), $count ) ) );

			case 'remove_auto_drafts':
				$count = $this->delete_posts_by_status( 'auto-draft' );
				return rest_ensure_response( array( 'message' => sprintf( __( 'Removed %d auto-drafts.', 'bulk-actions-manager' ), $count ) ) );

			case 'empty_trash':
				$count = $this->empty_trash();
				return rest_ensure_response( array( 'message' => sprintf( __( 'Emptied trash (%d posts).', 'bulk-actions-manager' ), $count ) ) );

			case 'orphan_attachments':
				$count = $this->clean_orphan_attachments();
				return rest_ensure_response( array( 'message' => sprintf( __( 'Removed %d orphan attachments.', 'bulk-actions-manager' ), $count ) ) );

			case 'orphan_metadata':
				$count = $this->clean_orphan_metadata();
				return rest_ensure_response( array( 'message' => sprintf( __( 'Removed %d orphan meta rows.', 'bulk-actions-manager' ), $count ) ) );

			case 'export_jobs':
				$jobs = Job_Repository::list( array( 'limit' => 1000, 'offset' => 0 ) );
				return rest_ensure_response( array( 'data' => $jobs, 'message' => __( 'Jobs exported.', 'bulk-actions-manager' ) ) );

			case 'export_logs':
				$logs = Log_Repository::list( array( 'limit' => 1000, 'offset' => 0 ) );
				return rest_ensure_response( array( 'data' => $logs, 'message' => __( 'Logs exported.', 'bulk-actions-manager' ) ) );

			default:
				return new \WP_Error( 'bam_unknown_tool', __( 'Unknown tool.', 'bulk-actions-manager' ), array( 'status' => 400 ) );
		}
	}

	/**
	 * Delete posts by type.
	 *
	 * @param string $post_type Post type.
	 * @return int
	 */
	private function delete_posts_by_type( $post_type ) {
		$posts = get_posts(
			array(
				'post_type'      => $post_type,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$count = 0;
		foreach ( $posts as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Delete posts by status.
	 *
	 * @param string $status Post status.
	 * @return int
	 */
	private function delete_posts_by_status( $status ) {
		$posts = get_posts(
			array(
				'post_status'    => $status,
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);
		$count = 0;
		foreach ( $posts as $id ) {
			if ( wp_delete_post( $id, true ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Empty trash for all post types.
	 *
	 * @return int
	 */
	private function empty_trash() {
		return $this->delete_posts_by_status( 'trash' );
	}

	/**
	 * Remove orphan attachments.
	 *
	 * @return int
	 */
	private function clean_orphan_attachments() {
		global $wpdb;
		$ids = $wpdb->get_col(
			"SELECT p.ID FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->posts} parent ON p.post_parent = parent.ID
			WHERE p.post_type = 'attachment' AND p.post_parent > 0 AND parent.ID IS NULL"
		);
		$count = 0;
		foreach ( $ids as $id ) {
			if ( wp_delete_attachment( (int) $id, true ) ) {
				$count++;
			}
		}
		return $count;
	}

	/**
	 * Remove orphan post meta.
	 *
	 * @return int
	 */
	private function clean_orphan_metadata() {
		global $wpdb;
		return (int) $wpdb->query(
			"DELETE pm FROM {$wpdb->postmeta} pm
			LEFT JOIN {$wpdb->posts} p ON pm.post_id = p.ID
			WHERE p.ID IS NULL"
		);
	}
}
