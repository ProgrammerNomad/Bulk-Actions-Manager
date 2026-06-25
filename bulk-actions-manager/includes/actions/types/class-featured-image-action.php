<?php
/**
 * Featured image / media actions.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Featured_Image_Action
 */
class Featured_Image_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Mode: remove, delete_file, delete_attached.
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
		return 'media';
	}

	/** @inheritDoc */
	public function get_label() {
		return $this->label;
	}

	/** @inheritDoc */
	public function get_safety_level() {
		return in_array( $this->mode, array( 'delete_file', 'delete_attached' ), true ) ? 'destructive' : 'safe';
	}

	/** @inheritDoc */
	public function supports_undo() {
		return 'remove' === $this->mode;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		return array( 'thumbnail_id' => (int) get_post_thumbnail_id( $object_id ) );
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		$post = $this->get_post( $object_id );
		if ( ! $post ) {
			return $this->post_not_found_result( $object_id );
		}

		if ( 'remove' === $this->mode && ! get_post_thumbnail_id( $object_id ) ) {
			return Action_Result::skipped(
				sprintf(
					/* translators: %d: post ID */
					__( 'Post #%d has no featured image.', 'bulk-actions-manager' ),
					(int) $object_id
				),
				'no_thumbnail'
			);
		}

		if ( $dry_run ) {
			return Action_Result::success();
		}

		switch ( $this->mode ) {
			case 'remove':
				delete_post_thumbnail( $object_id );
				break;
			case 'delete_file':
				$thumb_id = get_post_thumbnail_id( $object_id );
				delete_post_thumbnail( $object_id );
				if ( $thumb_id ) {
					wp_delete_attachment( $thumb_id, true );
				}
				break;
			case 'delete_attached':
				$attachments = get_attached_media( '', $object_id );
				foreach ( $attachments as $attachment ) {
					wp_delete_attachment( $attachment->ID, true );
				}
				break;
		}

		return Action_Result::success();
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( empty( $snapshot['thumbnail_id'] ) ) {
			delete_post_thumbnail( $object_id );
			return new Action_Result( true );
		}
		set_post_thumbnail( $object_id, (int) $snapshot['thumbnail_id'] );
		return new Action_Result( true );
	}
}
