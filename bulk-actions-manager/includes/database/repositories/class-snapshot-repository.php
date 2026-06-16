<?php
/**
 * Snapshot repository.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database\Repositories;

use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Snapshot_Repository
 */
class Snapshot_Repository {

	const TABLE = 'bam_snapshots';

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . self::TABLE;
	}

	/**
	 * Create snapshot.
	 *
	 * @param array<string, mixed> $data Snapshot data.
	 * @return int|false
	 */
	public static function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'job_id'        => 0,
			'object_type'   => 'post',
			'object_id'     => 0,
			'action_type'   => '',
			'snapshot_data' => '',
			'created_at'    => current_time( 'mysql' ),
			'expires_at'    => null,
		);

		$row = wp_parse_args( $data, $defaults );

		if ( is_array( $row['snapshot_data'] ) ) {
			$row['snapshot_data'] = Sanitizer::json_encode( $row['snapshot_data'] );
		}

		$inserted = $wpdb->insert( self::table(), $row );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get snapshots for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return array<int, object>
	 */
	public static function get_by_job( $job_id ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE job_id = %d',
				$job_id
			)
		);
	}

	/**
	 * Get snapshot for specific object in job.
	 *
	 * @param int $job_id    Job ID.
	 * @param int $object_id Object ID.
	 * @return object|null
	 */
	public static function get_for_object( $job_id, $object_id ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE job_id = %d AND object_id = %d LIMIT 1',
				$job_id,
				$object_id
			)
		);
	}

	/**
	 * Delete expired snapshots.
	 *
	 * @return int Rows deleted.
	 */
	public static function delete_expired() {
		global $wpdb;

		return (int) $wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table() . ' WHERE expires_at IS NOT NULL AND expires_at < %s',
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * Get total storage size estimate (row count).
	 *
	 * @return int
	 */
	public static function count_all() {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}
}
