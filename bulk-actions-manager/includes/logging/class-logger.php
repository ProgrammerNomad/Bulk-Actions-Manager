<?php
/**
 * Audit logger.
 *
 * @package BulkActionsManager
 */

namespace BAM\Logging;

use BAM\Database\Repositories\Log_Repository;
use BAM\Database\Repositories\Job_Repository;

defined( 'ABSPATH' ) || exit;

/**
 * Class Logger
 */
class Logger {

	/**
	 * Create initial log entry for job.
	 *
	 * @param int                  $job_id      Job ID.
	 * @param string               $action_type Action type.
	 * @param array<string, mixed> $filter      Filter payload.
	 * @param array<string, mixed> $payload     Action payload.
	 */
	public static function create_for_job( $job_id, $action_type, array $filter, array $payload ) {
		Log_Repository::create(
			array(
				'job_id'         => $job_id,
				'action_type'    => $action_type,
				'filter_payload' => $filter,
				'action_payload' => $payload,
				'undo_status'    => 'none',
			)
		);
	}

	/**
	 * Complete log when job finishes.
	 *
	 * @param int         $job_id         Job ID.
	 * @param int         $affected       Affected count.
	 * @param int         $failed         Failed count.
	 * @param bool        $undo_available Undo available.
	 * @param string|null $export_file    Export file path.
	 */
	public static function complete_job( $job_id, $affected, $failed, $undo_available, $export_file = null ) {
		$log = Log_Repository::find_by_job( $job_id );
		if ( ! $log ) {
			return;
		}

		$summary = array(
			'affected' => $affected,
			'failed'   => $failed,
		);
		if ( $export_file ) {
			$summary['export_file'] = $export_file;
		}

		Log_Repository::update(
			(int) $log->id,
			array(
				'affected_count' => $affected,
				'failed_count'   => $failed,
				'undo_status'    => $undo_available ? 'available' : 'none',
				'summary'        => $summary,
			)
		);
	}

	/**
	 * Create an immediate-tool audit log entry (export tools, etc.).
	 *
	 * @param string $tool_slug Tool slug.
	 * @param int    $affected  Number of items affected.
	 */
	public static function create_for_tool( $tool_slug, $affected ) {
		Log_Repository::create(
			array(
				'job_id'         => null,
				'action_type'    => 'tool.' . $tool_slug,
				'filter_payload' => array(),
				'action_payload' => array( 'tool_slug' => $tool_slug ),
				'affected_count' => $affected,
				'undo_status'    => 'none',
			)
		);
	}

	/**
	 * Delete old logs based on retention setting.
	 */
	public static function cleanup_old_logs() {
		$days = (int) \BAM\Settings::get( 'log_retention_days', 90 );
		if ( $days <= 0 ) {
			return;
		}

		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . Log_Repository::table() . ' WHERE created_at < %s',
				gmdate( 'Y-m-d H:i:s', strtotime( '-' . $days . ' days' ) )
			)
		);
	}
}
