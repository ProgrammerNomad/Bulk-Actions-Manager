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
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		if ( $dry_run ) {
			return new Action_Result( true );
		}
		$result = wp_update_post(
			array(
				'ID'          => $object_id,
				'post_author' => absint( $payload['author_id'] ),
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( ! isset( $snapshot['post_author'] ) ) {
			return new Action_Result( false, __( 'Invalid snapshot.', 'bulk-actions-manager' ) );
		}
		$result = wp_update_post(
			array(
				'ID'          => $object_id,
				'post_author' => (int) $snapshot['post_author'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}
}
