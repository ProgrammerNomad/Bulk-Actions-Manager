<?php
/**
 * Permanent delete action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Permanent_Delete_Action
 */
class Permanent_Delete_Action extends Abstract_Action {

	/** @inheritDoc */
	public function get_id() {
		return 'delete.permanent';
	}

	/** @inheritDoc */
	public function get_group() {
		return 'delete';
	}

	/** @inheritDoc */
	public function get_label() {
		return __( 'Permanently Delete', 'bulk-actions-manager' );
	}

	/** @inheritDoc */
	public function get_safety_level() {
		return 'destructive';
	}

	/** @inheritDoc */
	public function supports_undo() {
		return false;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		return array();
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		if ( ! $this->get_post( $object_id ) ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		if ( $dry_run ) {
			return new Action_Result( true );
		}
		$result = wp_delete_post( $object_id, true );
		return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete post.', 'bulk-actions-manager' ) );
	}
}
