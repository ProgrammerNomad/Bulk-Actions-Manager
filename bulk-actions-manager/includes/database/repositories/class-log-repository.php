<?php
/**
 * Log repository.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database\Repositories;

use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Log_Repository
 */
class Log_Repository {

	const TABLE = 'bam_logs';

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
	 * Find log by ID.
	 *
	 * @param int $id Log ID.
	 * @return object|null
	 */
	public static function find( $id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE id = %d',
				$id
			)
		);
	}

	/**
	 * Find log by job ID.
	 *
	 * @param int $job_id Job ID.
	 * @return object|null
	 */
	public static function find_by_job( $job_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE job_id = %d ORDER BY id DESC LIMIT 1',
				$job_id
			)
		);
	}

	/**
	 * Create a log entry.
	 *
	 * @param array<string, mixed> $data Log data.
	 * @return int|false
	 */
	public static function create( array $data ) {
		global $wpdb;

		$defaults = array(
			'job_id'          => 0,
			'user_id'         => get_current_user_id(),
			'action_type'     => '',
			'filter_payload'  => '',
			'action_payload'  => '',
			'affected_count'  => 0,
			'failed_count'    => 0,
			'undo_status'     => 'none',
			'summary'         => '',
			'errors'          => '',
			'created_at'      => current_time( 'mysql' ),
		);

		$row = wp_parse_args( $data, $defaults );

		foreach ( array( 'filter_payload', 'action_payload', 'summary', 'errors' ) as $field ) {
			if ( is_array( $row[ $field ] ) ) {
				$row[ $field ] = Sanitizer::json_encode( $row[ $field ] );
			}
		}

		$inserted = $wpdb->insert( self::table(), $row );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update log entry.
	 *
	 * @param int                  $id   Log ID.
	 * @param array<string, mixed> $data Fields.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		foreach ( array( 'filter_payload', 'action_payload', 'summary', 'errors' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = Sanitizer::json_encode( $data[ $field ] );
			}
		}

		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * List logs.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function list( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'limit'  => 20,
			'offset' => 0,
		);
		$args = wp_parse_args( $args, $defaults );

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
				(int) $args['limit'],
				(int) $args['offset']
			)
		);
	}

	/**
	 * Count logs with undo available.
	 *
	 * @return int
	 */
	public static function count_undo_available() {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE undo_status = %s',
				'available'
			)
		);
	}
}
