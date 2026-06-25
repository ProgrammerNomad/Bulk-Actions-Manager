<?php
/**
 * Abstract action base class.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Class Abstract_Action
 */
abstract class Abstract_Action implements Action_Interface {

	/**
	 * Get post or return error result.
	 *
	 * @param int $object_id Post ID.
	 * @return \WP_Post|null
	 */
	protected function get_post( $object_id ) {
		$post = get_post( $object_id );
		return ( $post instanceof \WP_Post ) ? $post : null;
	}

	/**
	 * Standard failed result when a post row no longer exists.
	 *
	 * @param int $object_id Post ID.
	 * @return Action_Result
	 */
	protected function post_not_found_result( $object_id ) {
		return Action_Result::failed(
			sprintf(
				/* translators: %d: post ID */
				__( 'Post #%1$d not found. It may have been deleted before this job ran.', 'bulk-actions-manager' ),
				(int) $object_id
			),
			'post_not_found'
		);
	}

	/**
	 * Default payload validation.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return true|\WP_Error
	 */
	public function validate_payload( array $payload ) {
		return true;
	}

	/**
	 * Default empty description; override in action classes.
	 *
	 * @return string
	 */
	public function get_description() {
		return '';
	}

	/**
	 * Default undo not supported.
	 *
	 * @param int                  $object_id Object ID.
	 * @param array<string, mixed> $snapshot  Snapshot.
	 * @return Action_Result
	 */
	public function undo( $object_id, array $snapshot ) {
		return new Action_Result( false, __( 'Undo not supported for this action.', 'bulk-actions-manager' ) );
	}
}
