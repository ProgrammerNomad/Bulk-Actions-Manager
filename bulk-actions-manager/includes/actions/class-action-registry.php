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
use BAM\Actions\Types\Tool_Action;

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
	 * Constructor - register default actions.
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

		// Tool actions - used by tool-jobs created from the Tools page.
		$this->register( new Tool_Action( 'empty_trash', __( 'Empty Trash', 'bulk-actions-manager' ) ) );
		$this->register( new Tool_Action( 'remove_revisions', __( 'Remove Revisions', 'bulk-actions-manager' ) ) );
		$this->register( new Tool_Action( 'remove_auto_drafts', __( 'Remove Auto Drafts', 'bulk-actions-manager' ) ) );
		$this->register( new Tool_Action( 'orphan_attachments', __( 'Orphan Attachments', 'bulk-actions-manager' ) ) );
		$this->register( new Tool_Action( 'orphan_metadata', __( 'Orphan Metadata', 'bulk-actions-manager' ) ) );
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
			$description = $action->get_description();
			if ( ! $description ) {
				$description = self::default_description( $action->get_id() );
			}
			$groups[ $group ][] = array(
				'id'            => $action->get_id(),
				'label'         => $action->get_label(),
				'safety_level'  => $action->get_safety_level(),
				'supports_undo' => $action->supports_undo(),
				'description'   => $description,
			);
		}
		return $groups;
	}

	/**
	 * Default UI descriptions for built-in actions.
	 *
	 * @param string $id Action ID.
	 * @return string
	 */
	private static function default_description( $id ) {
		$map = array(
			'status.publish'              => __( 'Changes post status to Published. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'status.draft'                => __( 'Changes post status to Draft. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'status.pending'              => __( 'Changes post status to Pending Review. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'status.private'              => __( 'Changes post status to Private. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'delete.trash'                => __( 'Moves posts to trash. Can be restored from the WordPress trash.', 'bulk-actions-manager' ),
			'delete.permanent'            => __( 'Deletes posts permanently. Deletes related metadata. Cannot be undone.', 'bulk-actions-manager' ),
			'author.change'               => __( 'Reassigns posts to a different author. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'category.add'                => __( 'Adds categories to matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'category.remove'             => __( 'Removes categories from matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'category.replace'            => __( 'Replaces categories on matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'tag.add'                     => __( 'Adds tags to matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'tag.remove'                  => __( 'Removes tags from matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'tag.replace'                 => __( 'Replaces tags on matching posts. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'meta.add'                    => __( 'Adds custom meta fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'meta.update'                 => __( 'Updates custom meta fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'meta.remove'                 => __( 'Removes custom meta fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'media.remove_thumbnail'      => __( 'Removes featured image references. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'media.delete_thumbnail_file' => __( 'Deletes featured image files from the media library. Recoverable only if backups exist.', 'bulk-actions-manager' ),
			'media.delete_attached'       => __( 'Deletes attached media files. Cannot be fully undone.', 'bulk-actions-manager' ),
			'content.find_replace'        => __( 'Finds and replaces text in content fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'content.append'              => __( 'Appends text to content fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'content.prepend'             => __( 'Prepends text to content fields. Creates snapshots for undo.', 'bulk-actions-manager' ),
			'export.ids'                  => __( 'Exports matching post IDs. No content changes are made.', 'bulk-actions-manager' ),
			'export.csv'                  => __( 'Exports matching posts as CSV. No content changes are made.', 'bulk-actions-manager' ),
			'export.json'                 => __( 'Exports matching posts as JSON. No content changes are made.', 'bulk-actions-manager' ),
		);

		return $map[ $id ] ?? '';
	}
}
