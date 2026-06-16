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
	 * Delete log entry.
	 *
	 * @param int $id Log ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Build WHERE from args.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{where: string, params: array<int, mixed>}
	 */
	private static function build_where( array $args ) {
		global $wpdb;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['undo_status'] ) ) {
			$where   .= ' AND undo_status = %s';
			$params[] = $args['undo_status'];
		}

		if ( ! empty( $args['action_type'] ) ) {
			$where   .= ' AND action_type = %s';
			$params[] = $args['action_type'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where   .= ' AND user_id = %d';
			$params[] = (int) $args['user_id'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (action_type LIKE %s OR CAST(job_id AS CHAR) LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Count logs.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;

		$built = self::build_where( $args );
		$sql   = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $built['where'];

		if ( empty( $built['params'] ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $built['params'] ) );
	}

	/**
	 * Count by undo status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_undo_status() {
		global $wpdb;

		$results = $wpdb->get_results(
			'SELECT undo_status, COUNT(*) as total FROM ' . self::table() . ' GROUP BY undo_status',
			OBJECT_K
		);

		$counts = array(
			'none'      => 0,
			'available' => 0,
			'used'      => 0,
			'expired'   => 0,
			'total'     => 0,
		);

		foreach ( $results as $status => $row ) {
			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = (int) $row->total;
			}
			$counts['total'] += (int) $row->total;
		}

		return $counts;
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
			'undo_status' => '',
			'action_type' => '',
			'user_id'     => 0,
			'search'      => '',
			'limit'       => 20,
			'offset'      => 0,
			'orderby'     => 'created_at',
			'order'       => 'DESC',
		);
		$args  = wp_parse_args( $args, $defaults );
		$built = self::build_where( $args );

		$allowed_orderby = array( 'created_at', 'id', 'affected_count', 'job_id' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$params   = $built['params'];
		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		$sql = 'SELECT * FROM ' . self::table() . ' WHERE ' . $built['where'] . " ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
	}

	/**
	 * Count logs with undo available.
	 *
	 * @return int
	 */
	public static function count_undo_available() {
		return self::count( array( 'undo_status' => 'available' ) );
	}
}
