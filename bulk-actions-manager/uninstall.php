<?php
/**
 * Uninstall handler - optionally drop tables when plugin is deleted.
 *
 * @package BulkActionsManager
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$drop_data = get_option( 'bam_drop_data_on_uninstall', false );

if ( $drop_data ) {
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

	delete_option( 'bam_settings' );
	delete_option( 'bam_db_version' );
	delete_option( 'bam_queue_jobs' );
	delete_option( 'bam_drop_data_on_uninstall' );
}

$role = get_role( 'administrator' );
if ( $role ) {
	$role->remove_cap( 'manage_bulk_actions_manager' );
}

wp_clear_scheduled_hook( 'bam_process_queue' );
wp_clear_scheduled_hook( 'bam_run_schedules' );
wp_clear_scheduled_hook( 'bam_cleanup_snapshots' );
wp_clear_scheduled_hook( 'bam_cleanup_logs' );
wp_clear_scheduled_hook( 'bam_cleanup_stale_jobs' );
