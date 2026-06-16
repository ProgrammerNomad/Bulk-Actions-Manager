<?php
/**
 * Database schema installer.
 *
 * @package BulkActionsManager
 */

namespace BAM\Database;

defined( 'ABSPATH' ) || exit;

/**
 * Class Schema
 */
class Schema {

	/**
	 * Create or update plugin tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$jobs = "{$prefix}bam_jobs";
		$sql_jobs = "CREATE TABLE {$jobs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			action_type varchar(64) NOT NULL DEFAULT '',
			action_payload longtext NULL,
			filter_payload longtext NULL,
			status varchar(20) NOT NULL DEFAULT 'queued',
			processing_mode varchar(20) NOT NULL DEFAULT 'ajax',
			batch_size smallint(5) unsigned NOT NULL DEFAULT 25,
			is_dry_run tinyint(1) NOT NULL DEFAULT 0,
			total_items int(10) unsigned NOT NULL DEFAULT 0,
			processed_items int(10) unsigned NOT NULL DEFAULT 0,
			failed_items int(10) unsigned NOT NULL DEFAULT 0,
			undo_job_id bigint(20) unsigned NULL,
			parent_job_id bigint(20) unsigned NULL,
			undo_available tinyint(1) NOT NULL DEFAULT 0,
			undo_expires_at datetime NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			started_at datetime NULL,
			finished_at datetime NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			error_message text NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY parent_job_id (parent_job_id)
		) {$charset};";

		$job_items = "{$prefix}bam_job_items";
		$sql_job_items = "CREATE TABLE {$job_items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT 'post',
			object_id bigint(20) unsigned NOT NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			error_message text NULL,
			processed_at datetime NULL,
			PRIMARY KEY (id),
			KEY job_status (job_id, status),
			KEY job_id_id (job_id, id)
		) {$charset};";

		$logs = "{$prefix}bam_logs";
		$sql_logs = "CREATE TABLE {$logs} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			action_type varchar(64) NOT NULL DEFAULT '',
			filter_payload longtext NULL,
			action_payload longtext NULL,
			affected_count int(10) unsigned NOT NULL DEFAULT 0,
			failed_count int(10) unsigned NOT NULL DEFAULT 0,
			undo_status varchar(20) NOT NULL DEFAULT 'none',
			undo_job_id bigint(20) unsigned NULL,
			summary longtext NULL,
			errors longtext NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY job_id (job_id),
			KEY user_id (user_id),
			KEY created_at (created_at),
			KEY undo_status (undo_status)
		) {$charset};";

		$snapshots = "{$prefix}bam_snapshots";
		$sql_snapshots = "CREATE TABLE {$snapshots} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			job_id bigint(20) unsigned NOT NULL,
			object_type varchar(32) NOT NULL DEFAULT 'post',
			object_id bigint(20) unsigned NOT NULL,
			action_type varchar(64) NOT NULL DEFAULT '',
			snapshot_data longtext NULL,
			created_at datetime NOT NULL,
			expires_at datetime NULL,
			PRIMARY KEY (id),
			KEY job_object (job_id, object_id),
			KEY expires_at (expires_at)
		) {$charset};";

		$schedules = "{$prefix}bam_schedules";
		$sql_schedules = "CREATE TABLE {$schedules} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(255) NOT NULL DEFAULT '',
			filter_payload longtext NULL,
			action_type varchar(64) NOT NULL DEFAULT '',
			action_payload longtext NULL,
			cron_expression varchar(64) NOT NULL DEFAULT 'daily',
			next_run_at datetime NULL,
			last_run_at datetime NULL,
			last_job_id bigint(20) unsigned NULL,
			is_active tinyint(1) NOT NULL DEFAULT 1,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY is_active (is_active),
			KEY next_run_at (next_run_at)
		) {$charset};";

		dbDelta( $sql_jobs );
		dbDelta( $sql_job_items );
		dbDelta( $sql_logs );
		dbDelta( $sql_snapshots );
		dbDelta( $sql_schedules );
	}

	/**
	 * Drop all plugin tables.
	 */
	public static function drop_tables() {
		global $wpdb;

		$tables = array(
			'bam_job_items',
			'bam_snapshots',
			'bam_logs',
			'bam_jobs',
			'bam_schedules',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
	}
}
