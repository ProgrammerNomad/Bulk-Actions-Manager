<?php
/**
 * Action interface.
 *
 * @package BulkActionsManager
 */

namespace BAM\Actions;

defined( 'ABSPATH' ) || exit;

/**
 * Interface Action_Interface
 */
interface Action_Interface {

	/**
	 * Unique action ID.
	 *
	 * @return string
	 */
	public function get_id();

	/**
	 * Action group.
	 *
	 * @return string
	 */
	public function get_group();

	/**
	 * Human label.
	 *
	 * @return string
	 */
	public function get_label();

	/**
	 * Safety level: safe, recoverable, destructive.
	 *
	 * @return string
	 */
	public function get_safety_level();

	/**
	 * Whether undo is supported.
	 *
	 * @return bool
	 */
	public function supports_undo();

	/**
	 * Human-readable description lines for the New Job action panel.
	 *
	 * @return string
	 */
	public function get_description();

	/**
	 * Validate action payload.
	 *
	 * @param array<string, mixed> $payload Payload.
	 * @return true|\WP_Error
	 */
	public function validate_payload( array $payload );

	/**
	 * Capture snapshot before mutation.
	 *
	 * @param int                  $object_id Object ID.
	 * @param array<string, mixed> $payload   Action payload.
	 * @return array<string, mixed>
	 */
	public function snapshot( $object_id, array $payload );

	/**
	 * Execute action on object.
	 *
	 * @param int                  $object_id Object ID.
	 * @param array<string, mixed> $payload   Action payload.
	 * @param bool                 $dry_run   Dry run flag.
	 * @return Action_Result
	 */
	public function execute( $object_id, array $payload, $dry_run );

	/**
	 * Undo action using snapshot.
	 *
	 * @param int                  $object_id Object ID.
	 * @param array<string, mixed> $snapshot  Snapshot data.
	 * @return Action_Result
	 */
	public function undo( $object_id, array $snapshot );
}
