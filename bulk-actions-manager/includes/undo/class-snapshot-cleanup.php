<?php
/**
 * Snapshot cleanup cron handler.
 *
 * @package BulkActionsManager
 */

namespace BAM\Undo;

use BAM\Database\Repositories\Snapshot_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Snapshot_Cleanup
 */
class Snapshot_Cleanup {

	/**
	 * Mark expired undo logs and delete expired snapshots.
	 */
	public static function run() {
		global $wpdb;

		$now = current_time( 'mysql' );

		// Mark logs expired before deleting snapshot rows (subquery needs matching snapshots).
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bam_logs SET undo_status = %s WHERE undo_status = %s AND job_id IN (
					SELECT job_id FROM {$wpdb->prefix}bam_snapshots WHERE expires_at IS NOT NULL AND expires_at < %s
				)",
				'expired',
				'available',
				$now
			)
		);

		Snapshot_Repository::delete_expired();
	}
}
