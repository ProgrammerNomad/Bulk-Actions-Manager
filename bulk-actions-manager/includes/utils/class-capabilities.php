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
	 * Plugin capability slug (filterable).
	 *
	 * @return string
	 */
	public static function get_capability() {
		/**
		 * Filter the capability required to manage Bulk Actions Manager.
		 *
		 * @param string $capability Default capability slug.
		 */
		return apply_filters( 'bam_capability', BAM_CAPABILITY );
	}

	/**
	 * Add plugin capability to administrator role.
	 */
	public static function add_to_administrator() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->add_cap( self::get_capability() );
		}
	}

	/**
	 * Remove plugin capability from administrator role.
	 */
	public static function remove_from_administrator() {
		$role = get_role( 'administrator' );
		if ( $role ) {
			$role->remove_cap( self::get_capability() );
			// Legacy cap if filter changed the slug.
			if ( BAM_CAPABILITY !== self::get_capability() ) {
				$role->remove_cap( BAM_CAPABILITY );
			}
		}
	}

	/**
	 * Check if current user can manage the plugin.
	 *
	 * @return bool
	 */
	public static function current_user_can() {
		return current_user_can( self::get_capability() );
	}
}
