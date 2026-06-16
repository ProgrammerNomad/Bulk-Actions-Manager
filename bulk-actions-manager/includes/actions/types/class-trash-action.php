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
		if ( ! $this->get_post( $object_id ) ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		if ( $dry_run ) {
			return new Action_Result( true );
		}
		$result = wp_trash_post( $object_id );
		return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to trash post.', 'bulk-actions-manager' ) );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		unset( $snapshot );
		$result = wp_untrash_post( $object_id );
		return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to untrash post.', 'bulk-actions-manager' ) );
	}
}
