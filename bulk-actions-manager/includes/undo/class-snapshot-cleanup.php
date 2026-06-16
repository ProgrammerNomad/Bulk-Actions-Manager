<?php
/**
 * Snapshot cleanup cron handler.
 *
 * @package BulkActionsManager
 */

namespace BAM\Undo;

use BAM\Database\Repositories\Log_Repository;
use BAM\Database\Repositories\Snapshot_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Snapshot_Cleanup
 */
class Snapshot_Cleanup {

	/**
	 * Delete expired snapshots and update log undo status.
	 */
	public static function run() {
		Snapshot_Repository::delete_expired();

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$wpdb->prefix}bam_logs SET undo_status = %s WHERE undo_status = %s AND job_id IN (
					SELECT job_id FROM {$wpdb->prefix}bam_snapshots WHERE expires_at IS NOT NULL AND expires_at < %s
				)",
				'expired',
				'available',
				current_time( 'mysql' )
			)
		);
	}
}
