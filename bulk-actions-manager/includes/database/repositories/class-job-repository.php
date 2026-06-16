<?php
/**
 * Job repository.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database\Repositories;

use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Job_Repository
 */
class Job_Repository {

	const TABLE = 'bam_jobs';

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
	 * Find job by ID.
	 *
	 * @param int $id Job ID.
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
	 * Create a new job.
	 *
	 * @param array<string, mixed> $data Job data.
	 * @return int|false Job ID or false.
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );
		$defaults = array(
			'name'             => '',
			'action_type'      => '',
			'action_payload'   => '',
			'filter_payload'   => '',
			'status'           => 'queued',
			'processing_mode'  => 'ajax',
			'batch_size'       => 25,
			'is_dry_run'       => 0,
			'total_items'      => 0,
			'processed_items'  => 0,
			'failed_items'     => 0,
			'user_id'          => get_current_user_id(),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$row = wp_parse_args( $data, $defaults );

		if ( is_array( $row['action_payload'] ) ) {
			$row['action_payload'] = Sanitizer::json_encode( $row['action_payload'] );
		}
		if ( is_array( $row['filter_payload'] ) ) {
			$row['filter_payload'] = Sanitizer::json_encode( $row['filter_payload'] );
		}

		$inserted = $wpdb->insert( self::table(), $row );

		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update job fields.
	 *
	 * @param int                  $id   Job ID.
	 * @param array<string, mixed> $data Fields to update.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		if ( isset( $data['action_payload'] ) && is_array( $data['action_payload'] ) ) {
			$data['action_payload'] = Sanitizer::json_encode( $data['action_payload'] );
		}
		if ( isset( $data['filter_payload'] ) && is_array( $data['filter_payload'] ) ) {
			$data['filter_payload'] = Sanitizer::json_encode( $data['filter_payload'] );
		}

		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Delete a job.
	 *
	 * @param int $id Job ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Build WHERE clause from args.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array{where: string, params: array<int, mixed>}
	 */
	private static function build_where( array $args ) {
		global $wpdb;

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND (name LIKE %s OR action_type LIKE %s)';
			$params[] = $like;
			$params[] = $like;
		}

		return array(
			'where'  => $where,
			'params' => $params,
		);
	}

	/**
	 * Count jobs matching args.
	 *
	 * @param array<string, mixed> $args Query args.
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
	 * List jobs.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function list( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'search'  => '',
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);
		$args  = wp_parse_args( $args, $defaults );
		$built = self::build_where( $args );

		$allowed_orderby = array( 'created_at', 'id', 'status', 'name', 'finished_at' );
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
	 * Count jobs by status.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_status() {
		global $wpdb;

		$results = $wpdb->get_results(
			'SELECT status, COUNT(*) as total FROM ' . self::table() . ' GROUP BY status',
			OBJECT_K
		);

		$counts = array(
			'queued'    => 0,
			'running'   => 0,
			'paused'    => 0,
			'completed' => 0,
			'failed'    => 0,
			'cancelled' => 0,
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
}
