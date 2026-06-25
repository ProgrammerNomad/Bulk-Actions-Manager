<?php
/**
 * Change author action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Author_Action
 */
class Author_Action extends Abstract_Action {

	/** @inheritDoc */
	public function get_id() {
		return 'author.change';
	}

	/** @inheritDoc */
	public function get_group() {
		return 'author';
	}

	/** @inheritDoc */
	public function get_label() {
		return __( 'Change Author', 'bulk-actions-manager' );
	}

	/** @inheritDoc */
	public function get_safety_level() {
		return 'safe';
	}

	/** @inheritDoc */
	public function supports_undo() {
		return true;
	}

	/** @inheritDoc */
	public function validate_payload( array $payload ) {
		if ( empty( $payload['author_id'] ) ) {
			return new \WP_Error( 'bam_missing_author', __( 'Author ID is required.', 'bulk-actions-manager' ) );
		}
		return true;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		$post = $this->get_post( $object_id );
		return $post ? array( 'post_author' => (int) $post->post_author ) : array();
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return $this->post_not_found_result( $object_id );
		}

		$author_id = absint( $payload['author_id'] );
		if ( (int) $post->post_author === $author_id ) {
			return Action_Result::skipped(
				sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d already has the selected author.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'author_unchanged'
			);
		}

		if ( $dry_run ) {
			return Action_Result::success();
		}

		$result = wp_update_post(
			array(
				'ID'          => $object_id,
				'post_author' => $author_id,
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return Action_Result::failed( $result->get_error_message(), 'wp_update_failed' );
		}
		return Action_Result::success( '', 'author_changed' );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( ! isset( $snapshot['post_author'] ) ) {
			return Action_Result::failed( __( 'Invalid snapshot.', 'bulk-actions-manager' ), 'invalid_snapshot' );
		}
		$result = wp_update_post(
			array(
				'ID'          => $object_id,
				'post_author' => (int) $snapshot['post_author'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return Action_Result::failed( $result->get_error_message(), 'wp_update_failed' );
		}
		return Action_Result::success();
	}
}
