<?php
/**
 * Category taxonomy action.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions\Types;

use BAM\Actions\Abstract_Action;
use BAM\Actions\Action_Result;

defined( 'ABSPATH' ) || exit;

/**
 * Class Category_Action
 */
class Category_Action extends Abstract_Action {

	/**
	 * Action ID.
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Mode: add, remove, replace.
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
		return 'category';
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
		if ( empty( $payload['term_ids'] ) || ! is_array( $payload['term_ids'] ) ) {
			return new \WP_Error( 'bam_missing_terms', __( 'Category IDs are required.', 'bulk-actions-manager' ) );
		}
		return true;
	}

	/** @inheritDoc */
	public function snapshot( $object_id, array $payload ) {
		$terms = wp_get_post_terms( $object_id, 'category', array( 'fields' => 'ids' ) );
		return array( 'term_ids' => is_array( $terms ) ? array_map( 'intval', $terms ) : array() );
	}

	/** @inheritDoc */
	public function execute( $object_id, array $payload, $dry_run ) {
		if ( ! $this->get_post( $object_id ) ) {
			return new Action_Result( false, __( 'Post not found.', 'bulk-actions-manager' ) );
		}
		if ( $dry_run ) {
			return new Action_Result( true );
		}

		$term_ids = array_map( 'absint', (array) $payload['term_ids'] );

		switch ( $this->mode ) {
			case 'add':
				$result = wp_set_post_terms( $object_id, $term_ids, 'category', true );
				break;
			case 'remove':
				$current = wp_get_post_terms( $object_id, 'category', array( 'fields' => 'ids' ) );
				$remaining = array_diff( array_map( 'intval', (array) $current ), $term_ids );
				$result = wp_set_post_terms( $object_id, $remaining, 'category', false );
				break;
			case 'replace':
			default:
				$result = wp_set_post_terms( $object_id, $term_ids, 'category', false );
				break;
		}

		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}

	/** @inheritDoc */
	public function undo( $object_id, array $snapshot ) {
		if ( ! isset( $snapshot['term_ids'] ) ) {
			return new Action_Result( false, __( 'Invalid snapshot.', 'bulk-actions-manager' ) );
		}
		$result = wp_set_post_terms( $object_id, $snapshot['term_ids'], 'category', false );
		if ( is_wp_error( $result ) ) {
			return new Action_Result( false, $result->get_error_message() );
		}
		return new Action_Result( true );
	}
}
