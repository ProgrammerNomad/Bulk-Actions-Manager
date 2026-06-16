<?php
/**
 * Schedule repository.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database\Repositories;

use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schedule_Repository
 */
class Schedule_Repository {

	const TABLE = 'bam_schedules';

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
	 * Find schedule by ID.
	 *
	 * @param int $id Schedule ID.
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
	 * Create schedule.
	 *
	 * @param array<string, mixed> $data Schedule data.
	 * @return int|false
	 */
	public static function create( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );
		$defaults = array(
			'name'             => '',
			'filter_payload'   => '',
			'action_type'      => '',
			'action_payload'   => '',
			'cron_expression'  => 'daily',
			'is_active'        => 1,
			'user_id'          => get_current_user_id(),
			'created_at'       => $now,
			'updated_at'       => $now,
		);

		$row = wp_parse_args( $data, $defaults );

		foreach ( array( 'filter_payload', 'action_payload' ) as $field ) {
			if ( is_array( $row[ $field ] ) ) {
				$row[ $field ] = Sanitizer::json_encode( $row[ $field ] );
			}
		}

		$inserted = $wpdb->insert( self::table(), $row );
		return $inserted ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update schedule.
	 *
	 * @param int                  $id   Schedule ID.
	 * @param array<string, mixed> $data Fields.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;

		foreach ( array( 'filter_payload', 'action_payload' ) as $field ) {
			if ( isset( $data[ $field ] ) && is_array( $data[ $field ] ) ) {
				$data[ $field ] = Sanitizer::json_encode( $data[ $field ] );
			}
		}

		$data['updated_at'] = current_time( 'mysql' );

		return false !== $wpdb->update( self::table(), $data, array( 'id' => $id ) );
	}

	/**
	 * Delete schedule.
	 *
	 * @param int $id Schedule ID.
	 * @return bool
	 */
	public static function delete( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Build WHERE clause.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return array{where: string, params: array<int, mixed>}
	 */
	private static function build_where( array $args ) {
		$where  = '1=1';
		$params = array();

		if ( '' !== $args['is_active'] && null !== $args['is_active'] ) {
			$where   .= ' AND is_active = %d';
			$params[] = (int) $args['is_active'];
		}

		if ( ! empty( $args['search'] ) ) {
			global $wpdb;
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
	 * Count schedules.
	 *
	 * @param array<string, mixed> $args Args.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_active' => '',
			'search'    => '',
		);
		$args  = wp_parse_args( $args, $defaults );
		$built = self::build_where( $args );
		$sql   = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . $built['where'];

		if ( empty( $built['params'] ) ) {
			return (int) $wpdb->get_var( $sql );
		}

		return (int) $wpdb->get_var( $wpdb->prepare( $sql, $built['params'] ) );
	}

	/**
	 * Count by active state.
	 *
	 * @return array<string, int>
	 */
	public static function count_by_active() {
		global $wpdb;

		$active   = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_active = %d', 1 ) );
		$inactive = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_active = %d', 0 ) );

		return array(
			'total'    => $active + $inactive,
			'active'   => $active,
			'inactive' => $inactive,
		);
	}

	/**
	 * Get due schedules.
	 *
	 * @return array<int, object>
	 */
	public static function get_due() {
		global $wpdb;

		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE is_active = 1 AND next_run_at <= %s',
				current_time( 'mysql' )
			)
		);
	}

	/**
	 * List schedules.
	 *
	 * @param array<string, mixed> $args Query args.
	 * @return array<int, object>
	 */
	public static function list( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'is_active' => '',
			'search'    => '',
			'limit'     => 20,
			'offset'    => 0,
			'orderby'   => 'created_at',
			'order'     => 'DESC',
		);
		$args  = wp_parse_args( $args, $defaults );
		$built = self::build_where( $args );

		$allowed_orderby = array( 'created_at', 'id', 'name', 'next_run_at', 'last_run_at' );
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
	 * List all schedules (legacy helper).
	 *
	 * @return array<int, object>
	 */
	public static function list_all() {
		return self::list( array( 'limit' => 9999, 'offset' => 0 ) );
	}

	/**
	 * Count active schedules.
	 *
	 * @return int
	 */
	public static function count_active() {
		return self::count( array( 'is_active' => 1 ) );
	}
}
