<?php
/**
 * Job item repository.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database\Repositories;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Item_Repository
 */
class Job_Item_Repository {

	const TABLE = 'bam_job_items';

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
	 * Bulk insert job items.
	 *
	 * @param int                  $job_id Job ID.
	 * @param array<int, int>      $object_ids Object IDs.
	 * @param string               $object_type Object type.
	 * @return int Number inserted.
	 */
	public static function bulk_insert( $job_id, array $object_ids, $object_type = 'post' ) {
		global $wpdb;

		$inserted = 0;
		$chunks   = array_chunk( $object_ids, 100 );

		foreach ( $chunks as $chunk ) {
			$values = array();
			$placeholders = array();

			foreach ( $chunk as $object_id ) {
				$values[]       = (int) $job_id;
				$values[]       = $object_type;
				$values[]       = (int) $object_id;
				$values[]       = 'pending';
				$placeholders[] = '(%d, %s, %d, %s)';
			}

			if ( empty( $placeholders ) ) {
				continue;
			}

			$sql = 'INSERT INTO ' . self::table() . ' (job_id, object_type, object_id, status) VALUES ' . implode( ', ', $placeholders );
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$result = $wpdb->query( $wpdb->prepare( $sql, $values ) );

			if ( false !== $result ) {
				$inserted += count( $chunk );
			}
		}

		return $inserted;
	}

	/**
	 * Get next pending batch for a job.
	 *
	 * @param int $job_id Job ID.
	 * @param int $limit  Batch size.
	 * @return array<int, object>
	 */
	public static function get_pending_batch( $job_id, $limit ) {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE job_id = %d AND status = %s ORDER BY id ASC LIMIT %d',
				$job_id,
				'pending',
				$limit
			)
		);
	}

	/**
	 * Mark item status.
	 *
	 * @param int    $id     Item ID.
	 * @param string $status New status.
	 * @param string $error  Optional error message.
	 * @return bool
	 */
	public static function update_status( $id, $status, $error = '' ) {
		global $wpdb;

		$data = array(
			'status'       => $status,
			'processed_at' => current_time( 'mysql' ),
		);

		if ( $error ) {
			$data['error_message'] = $error;
		}

		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Count pending items for a job.
	 *
	 * @param int $job_id Job ID.
	 * @return int
	 */
	public static function count_pending( $job_id ) {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE job_id = %d AND status = %s',
				$job_id,
				'pending'
			)
		);
	}
}
