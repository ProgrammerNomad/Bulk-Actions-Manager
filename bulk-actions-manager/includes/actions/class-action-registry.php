<?php
/**
 * Action registry.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions;

use BAM\Actions\Types\Status_Action;
use BAM\Actions\Types\Trash_Action;
use BAM\Actions\Types\Permanent_Delete_Action;
use BAM\Actions\Types\Author_Action;
use BAM\Actions\Types\Category_Action;
use BAM\Actions\Types\Tag_Action;
use BAM\Actions\Types\Meta_Action;
use BAM\Actions\Types\Featured_Image_Action;
use BAM\Actions\Types\Content_Action;
use BAM\Actions\Types\Export_Action;

defined( 'ABSPATH' ) || exit;

/**
 * Class Action_Registry
 */
class Action_Registry {

	/**
	 * Registered actions.
	 *
	 * @var array<string, Action_Interface>
	 */
	private $actions = array();

	/**
	 * Constructor — register default actions.
	 */
	public function __construct() {
		$this->register_defaults();
		/**
		 * Register custom actions.
		 *
		 * @param Action_Registry $registry Registry instance.
		 */
		do_action( 'bam_register_actions', $this );
	}

	/**
	 * Register built-in actions.
	 */
	private function register_defaults() {
		$statuses = array(
			'publish' => __( 'Publish', 'bulk-actions-manager' ),
			'draft'   => __( 'Draft', 'bulk-actions-manager' ),
			'pending' => __( 'Pending Review', 'bulk-actions-manager' ),
			'private' => __( 'Private', 'bulk-actions-manager' ),
		);

		foreach ( $statuses as $status => $label ) {
			$this->register( new Status_Action( 'status.' . $status, $status, $label ) );
		}

		$this->register( new Trash_Action() );
		$this->register( new Permanent_Delete_Action() );
		$this->register( new Author_Action() );
		$this->register( new Category_Action( 'category.add', 'add', __( 'Add Category', 'bulk-actions-manager' ) ) );
		$this->register( new Category_Action( 'category.remove', 'remove', __( 'Remove Category', 'bulk-actions-manager' ) ) );
		$this->register( new Category_Action( 'category.replace', 'replace', __( 'Replace Category', 'bulk-actions-manager' ) ) );
		$this->register( new Tag_Action( 'tag.add', 'add', __( 'Add Tag', 'bulk-actions-manager' ) ) );
		$this->register( new Tag_Action( 'tag.remove', 'remove', __( 'Remove Tag', 'bulk-actions-manager' ) ) );
		$this->register( new Tag_Action( 'tag.replace', 'replace', __( 'Replace Tag', 'bulk-actions-manager' ) ) );
		$this->register( new Meta_Action( 'meta.add', 'add', __( 'Add Meta', 'bulk-actions-manager' ) ) );
		$this->register( new Meta_Action( 'meta.update', 'update', __( 'Update Meta', 'bulk-actions-manager' ) ) );
		$this->register( new Meta_Action( 'meta.remove', 'remove', __( 'Remove Meta', 'bulk-actions-manager' ) ) );
		$this->register( new Featured_Image_Action( 'media.remove_thumbnail', 'remove', __( 'Remove Featured Image', 'bulk-actions-manager' ) ) );
		$this->register( new Featured_Image_Action( 'media.delete_thumbnail_file', 'delete_file', __( 'Delete Featured Image File', 'bulk-actions-manager' ) ) );
		$this->register( new Featured_Image_Action( 'media.delete_attached', 'delete_attached', __( 'Delete Attached Media', 'bulk-actions-manager' ) ) );
		$this->register( new Content_Action( 'content.find_replace', 'find_replace', __( 'Find & Replace', 'bulk-actions-manager' ) ) );
		$this->register( new Content_Action( 'content.append', 'append', __( 'Append Content', 'bulk-actions-manager' ) ) );
		$this->register( new Content_Action( 'content.prepend', 'prepend', __( 'Prepend Content', 'bulk-actions-manager' ) ) );
		$this->register( new Export_Action( 'export.ids', 'ids', __( 'Export IDs', 'bulk-actions-manager' ) ) );
		$this->register( new Export_Action( 'export.csv', 'csv', __( 'Export CSV', 'bulk-actions-manager' ) ) );
		$this->register( new Export_Action( 'export.json', 'json', __( 'Export JSON', 'bulk-actions-manager' ) ) );
	}

	/**
	 * Register an action.
	 *
	 * @param Action_Interface $action Action instance.
	 */
	public function register( Action_Interface $action ) {
		$this->actions[ $action->get_id() ] = $action;
	}

	/**
	 * Get action by ID.
	 *
	 * @param string $id Action ID.
	 * @return Action_Interface|null
	 */
	public function get( $id ) {
		return $this->actions[ $id ] ?? null;
	}

	/**
	 * Get all actions grouped for UI.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public function get_grouped() {
		$groups = array();
		foreach ( $this->actions as $action ) {
			$group = $action->get_group();
			if ( ! isset( $groups[ $group ] ) ) {
				$groups[ $group ] = array();
			}
			$groups[ $group ][] = array(
				'id'            => $action->get_id(),
				'label'         => $action->get_label(),
				'safety_level'  => $action->get_safety_level(),
				'supports_undo' => $action->supports_undo(),
			);
		}
		return $groups;
	}
}
