<?php
/**
 * Content modification actions.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Content_Action
 */
class Content_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Mode: find_replace, append, prepend.
	 *
	 * @var string
	 */
	private $mode;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $id    Action ID.
	 * @param string $mode  Mode.
	 * @param string $label Label.
	 */
	public function __construct( $id, $mode, $label ) {
		$this->id    = $id;
		$this->mode  = $mode;
		$this->label = $label;
	}

	/** @inheritDoc */
	public function get_id() {
		return $this->id;
	}

	/** @inheritDoc */
	public function get_group() {
		return 'content';
	}

	/** @inheritDoc */
	public function get_label() {
		return $this->label;
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
		if ( 'find_replace' === $this->mode && ( ! isset( $payload['find'] ) || ! isset( $payload['replace'] ) ) ) {
			return new \WP_Error( 'bam_missing_find_replace', __( 'Find and replace values are required.', 'bulk-actions-manager' ) );
		}
		if ( in_array( $this->mode, array( 'append', 'prepend' ), true ) && empty( $payload['text'] ) ) {
			return new \WP_Error( 'bam_missing_text', __( 'Text is required.', 'bulk-actions-manager' ) );
		}
		return true;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return array();
		}
		$field = sanitize_key( $payload['field'] ?? 'content' );
		return array(
			'field'  => $field,
			'value'  => 'title' === $field ? $post->post_title : ( 'excerpt' === $field ? $post->post_excerpt : $post->post_content ),
		);
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}

		$field = sanitize_key( $payload['field'] ?? 'content' );
		$update = array( 'ID' => $object_id );

		switch ( $this->mode ) {
			case 'find_replace':
				$current = 'title' === $field ? $post->post_title : ( 'excerpt' === $field ? $post->post_excerpt : $post->post_content );
				$new_value = str_replace( (string) $payload['find'], (string) $payload['replace'], $current );
				break;
			case 'append':
				$current = 'title' === $field ? $post->post_title : ( 'excerpt' === $field ? $post->post_excerpt : $post->post_content );
				$new_value = $current . (string) $payload['text'];
				break;
			case 'prepend':
				$current = 'title' === $field ? $post->post_title : ( 'excerpt' === $field ? $post->post_excerpt : $post->post_content );
				$new_value = (string) $payload['text'] . $current;
				break;
			default:
				return new Action_Result( false, __( 'Unknown content mode.', 'bulk-actions-manager' ) );
		}

		if ( $dry_run ) {
			return new Action_Result( true );
		}

		if ( 'title' === $field ) {
			$update['post_title'] = $new_value;
		} elseif ( 'excerpt' === $field ) {
			$update['post_excerpt'] = $new_value;
		} else {
			$update['post_content'] = $new_value;
		}

		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( empty( $snapshot['field'] ) ) {
			return new Action_Result( false, __( 'Invalid snapshot.', 'bulk-actions-manager' ) );
		}
		$update = array( 'ID' => $object_id );
		$field  = $snapshot['field'];
		if ( 'title' === $field ) {
			$update['post_title'] = $snapshot['value'];
		} elseif ( 'excerpt' === $field ) {
			$update['post_excerpt'] = $snapshot['value'];
		} else {
			$update['post_content'] = $snapshot['value'];
		}
		$result = wp_update_post( $update, true );
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}
}
