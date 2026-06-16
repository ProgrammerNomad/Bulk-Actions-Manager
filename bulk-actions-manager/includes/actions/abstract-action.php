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
	 * Default payload validation.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return true|\WP_Error
	 */
	public function validate_payload( array $payload ) {
		return true;
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
