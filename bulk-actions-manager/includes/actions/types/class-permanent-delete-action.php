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
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return $this->post_not_found_result( $object_id );
		}

		if ( $dry_run ) {
			return Action_Result::success();
		}

		if ( ! current_user_can( 'delete_post', $object_id ) ) {
			return Action_Result::failed(
				sprintf(
					/* translators: %d: post ID */
					__( 'You do not have permission to delete post #%d.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'cannot_delete_post'
			);
		}

		$result = wp_delete_post( $object_id, true );
		if ( ! $result ) {
			return Action_Result::failed(
				sprintf(
					/* translators: %d: post ID */
					__( 'WordPress could not permanently delete post #%d.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'wp_delete_failed'
			);
		}

		return Action_Result::success( '', 'deleted' );
	}
}
