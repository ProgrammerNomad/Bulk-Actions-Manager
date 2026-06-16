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

	/**
	 * Table name without prefix.
	 */
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
	 * List jobs with optional status filter.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function list( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'limit'   => 20,
			'offset'  => 0,
			'orderby' => 'created_at',
			'order'   => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$orderby = in_array( $args['orderby'], array( 'created_at', 'id', 'status' ), true ) ? $args['orderby'] : 'created_at';
		$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		$sql = "SELECT * FROM " . self::table() . " WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		if ( ! empty( $params ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $wpdb->get_results( $sql );
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
			$counts[ $status ] = (int) $row->total;
			$counts['total']  += (int) $row->total;
		}

		return $counts;
	}
}
