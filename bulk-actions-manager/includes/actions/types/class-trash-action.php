<?php
/**
 * Move to trash action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Trash_Action
 */
class Trash_Action extends Abstract_Action {

	/** @inheritDoc */
	public function get_id() {
		return 'delete.trash';
	}

	/** @inheritDoc */
	public function get_group() {
		return 'delete';
	}

	/** @inheritDoc */
	public function get_label() {
		return __( 'Move To Trash', 'bulk-actions-manager' );
	}

	/** @inheritDoc */
	public function get_safety_level() {
		return 'recoverable';
	}

	/** @inheritDoc */
	public function supports_undo() {
		return true;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		$post = $this->get_post( $object_id );
		return $post ? array( 'post_status' => $post->post_status ) : array();
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return $this->post_not_found_result( $object_id );
		}

		if ( 'trash' === $post->post_status ) {
			return Action_Result::skipped(
				sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d is already in trash.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'already_trashed'
			);
		}

		if ( $dry_run ) {
			return Action_Result::success();
		}

		if ( ! current_user_can( 'delete_post', $object_id ) ) {
			return Action_Result::failed(
				sprintf(
					/* translators: %d: post ID */
					__( 'You do not have permission to trash post #%d.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'cannot_delete_post'
			);
		}

		$result = wp_trash_post( $object_id );
		if ( ! $result ) {
			return Action_Result::failed(
				sprintf(
					/* translators: %d: post ID */
					__( 'WordPress could not move post #%d to the Trash.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'wp_trash_failed'
			);
		}

		return Action_Result::success( '', 'trashed' );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		unset( $snapshot );
		$result = wp_untrash_post( $object_id );
		return $result ? Action_Result::success() : Action_Result::failed( __( 'Failed to untrash post.', 'bulk-actions-manager' ), 'wp_untrash_failed' );
	}
}
