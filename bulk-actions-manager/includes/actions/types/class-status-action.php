<?php
/**
 * Status change action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Status_Action
 */
class Status_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Target status.
	 *
	 * @var string
	 */
	private $status;

	/**
	 * Label.
	 *
	 * @var string
	 */
	private $label;

	/**
	 * Constructor.
	 *
	 * @param string $id     Action ID.
	 * @param string $status Target status.
	 * @param string $label  Label.
	 */
	public function __construct( $id, $status, $label ) {
		$this->id     = $id;
		$this->status = $status;
		$this->label  = $label;
	}

	/** @inheritDoc */
	public function get_id() {
		return $this->id;
	}

	/** @inheritDoc */
	public function get_group() {
		return 'status';
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
	public function snapshot( $object_id, array $payload ) {
		$post = $this->get_post( $object_id );
		return $post ? array( 'post_status' => $post->post_status ) : array();
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
				'post_status' => $this->status,
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
		if ( empty( $snapshot['post_status'] ) ) {
			return new Action_Result( false, __( 'Invalid snapshot.', 'bulk-actions-manager' ) );
		}
		$result = wp_update_post(
			array(
				'ID'          => $object_id,
				'post_status' => $snapshot['post_status'],
			),
			true
		);
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}
}
