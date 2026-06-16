<?php
/**
 * Post meta action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Meta_Action
 */
class Meta_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Mode: add, update, remove.
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
		return 'meta';
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
		if ( empty( $payload['meta_key'] ) ) {
			return new \WP_Error( 'bam_missing_meta_key', __( 'Meta key is required.', 'bulk-actions-manager' ) );
		}
		if ( 'remove' !== $this->mode && ! isset( $payload['meta_value'] ) ) {
			return new \WP_Error( 'bam_missing_meta_value', __( 'Meta value is required.', 'bulk-actions-manager' ) );
		}
		return true;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		$key = sanitize_key( $payload['meta_key'] );
		return array(
			'meta_key'   => $key,
			'meta_value' => get_post_meta( $object_id, $key, true ),
			'existed'    => metadata_exists( 'post', $object_id, $key ),
		);
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		if ( ! $this->get_post( $object_id ) ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		if ( $dry_run ) {
			return new Action_Result( true );
		}

		$key   = sanitize_key( $payload['meta_key'] );
		$value = $payload['meta_value'] ?? '';

		switch ( $this->mode ) {
			case 'add':
				add_post_meta( $object_id, $key, $value );
				break;
			case 'remove':
				delete_post_meta( $object_id, $key );
				break;
			default:
				update_post_meta( $object_id, $key, $value );
				break;
		}

		return new Action_Result( true );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( empty( $snapshot['meta_key'] ) ) {
			return new Action_Result( false, __( 'Invalid snapshot.', 'bulk-actions-manager' ) );
		}
		$key = $snapshot['meta_key'];
		if ( ! empty( $snapshot['existed'] ) ) {
			update_post_meta( $object_id, $key, $snapshot['meta_value'] );
		} else {
			delete_post_meta( $object_id, $key );
		}
		return new Action_Result( true );
	}
}
