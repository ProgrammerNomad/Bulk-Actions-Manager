<?php
/**
 * Capability management.
 *
 * @package BulkActionsManager
 */

namespace BAM\Utils;

defined( 'ABSPATH' ) || exit;

/**
 * Class Capabilities
 */
class Capabilities {

	/**
	 * Add plugin capability to administrator role.
	 */
	public static function add_to_administrator() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( BAM_CAPABILITY );
		}
	}

	/**
	 * Remove plugin capability from administrator role.
	 */
	public static function remove_from_administrator() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->remove_cap( BAM_CAPABILITY );
		}
	}

	/**
	 * Check if current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can() {
		return current_user_can( BAM_CAPABILITY );
	}
}
