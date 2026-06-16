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
	 * List all schedules.
	 *
	 * @return array<int, object>
	 */
	public static function list_all() {
		global $wpdb;
		return $wpdb->get_results( 'SELECT * FROM ' . self::table() . ' ORDER BY created_at DESC' );
	}

	/**
	 * Count active schedules.
	 *
	 * @return int
	 */
	public static function count_active() {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . self::table() . ' WHERE is_active = %d',
				1
			)
		);
	}
}
