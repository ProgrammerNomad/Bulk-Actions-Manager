<?php
/**
 * Tool action - processes a single item in a tool-job batch.
 *
 * Tool-jobs are normal BAM jobs with action_type prefixed `tool.*`.
 * The payload carries `tool_slug` so the action knows what operation to perform.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Tool_Action
 *
 * Handles per-item execution for tool.* job types.
 * Object IDs are resolved at job-creation time by Tools_Controller.
 */
class Tool_Action extends Abstract_Action {

	/**
	 * Tool slug (e.g. empty_trash, remove_revisions).
	 *
	 * @var string
	 */
	private $slug;

	/**
	 * Human-readable label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $slug  Tool slug.
	 * @param string $label Tool label.
	 */
	public function __construct( $slug, $label ) {
		$this->slug  = $slug;
		$this->label = $label;
	}

	/** @inheritDoc */
	public function get_id() {
		return 'tool.' . $this->slug;
	}

	/** @inheritDoc */
	public function get_group() {
		return 'Tools';
	}

	/** @inheritDoc */
	public function get_label() {
		return $this->label;
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
		if ( $dry_run ) {
			return new Action_Result( true );
		}

		switch ( $this->slug ) {
			case 'empty_trash':
				$post = get_post( $object_id );
				if ( ! $post || 'trash' !== $post->post_status ) {
					return new Action_Result( false, __( 'Post not in trash.', 'bulk-actions-manager' ) );
				}
				$result = wp_delete_post( $object_id, true );
				return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete post.', 'bulk-actions-manager' ) );

			case 'remove_revisions':
				$post = get_post( $object_id );
				if ( ! $post || 'revision' !== $post->post_type ) {
					return new Action_Result( false, __( 'Not a revision.', 'bulk-actions-manager' ) );
				}
				$result = wp_delete_post( $object_id, true );
				return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete revision.', 'bulk-actions-manager' ) );

			case 'remove_auto_drafts':
				$post = get_post( $object_id );
				if ( ! $post || 'auto-draft' !== $post->post_status ) {
					return new Action_Result( false, __( 'Not an auto-draft.', 'bulk-actions-manager' ) );
				}
				$result = wp_delete_post( $object_id, true );
				return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete auto-draft.', 'bulk-actions-manager' ) );

			case 'orphan_attachments':
				$result = wp_delete_attachment( $object_id, true );
				return $result ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete attachment.', 'bulk-actions-manager' ) );

			case 'orphan_metadata':
				global $wpdb;
				$deleted = $wpdb->delete( $wpdb->postmeta, array( 'meta_id' => $object_id ), array( '%d' ) );
				return $deleted ? new Action_Result( true ) : new Action_Result( false, __( 'Failed to delete meta row.', 'bulk-actions-manager' ) );

			default:
				return new Action_Result( false, __( 'Unknown tool slug.', 'bulk-actions-manager' ) );
		}
	}
}
