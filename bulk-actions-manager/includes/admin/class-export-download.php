<?php
/**
 * Secure export file download handler.
 *
 * @package BulkActionsManager
 */

namespace BAM\Admin;

use BAM\Database\Repositories\Log_Repository;
use BAM\Utils\Capabilities;
use BAM\Utils\Sanitizer;

defined( 'ABSPATH' ) || exit;

/**
 * Class Export_Download
 */
class Export_Download {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_post_bam_download_export', array( $this, 'handle_download' ) );
	}

	/**
	 * Build download URL for a job export.
	 *
	 * @param int $job_id Job ID.
	 * @return string|null
	 */
	public static function get_url( $job_id ) {
		$log = Log_Repository::find_by_job( $job_id );
		if ( ! $log ) {
			return null;
		}
		$summary = Sanitizer::json_decode( $log->summary );
		if ( empty( $summary['export_file'] ) || ! file_exists( $summary['export_file'] ) ) {
			return null;
		}

		return wp_nonce_url(
			admin_url( 'admin-post.php?action=bam_download_export&job_id=' . absint( $job_id ) ),
			'bam_download_export_' . absint( $job_id )
		);
	}

	/**
	 * Stream export file to browser.
	 */
	public function handle_download() {
		if ( ! Capabilities::current_user_can() ) {
			wp_die( esc_html__( 'Permission denied.', 'bulk-actions-manager' ) );
		}

		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		check_admin_referer( 'bam_download_export_' . $job_id );

		$log = Log_Repository::find_by_job( $job_id );
		if ( ! $log ) {
			wp_die( esc_html__( 'Export not found.', 'bulk-actions-manager' ) );
		}

		$summary = Sanitizer::json_decode( $log->summary );
		$file    = $summary['export_file'] ?? '';

		if ( ! $file || ! file_exists( $file ) ) {
			wp_die( esc_html__( 'Export file not found.', 'bulk-actions-manager' ) );
		}

		$upload_dir = wp_upload_dir();
		$export_dir = wp_normalize_path( trailingslashit( $upload_dir['basedir'] ) . 'bam-exports' );
		$file_path  = wp_normalize_path( $file );

		if ( 0 !== strpos( $file_path, $export_dir ) ) {
			wp_die( esc_html__( 'Invalid export path.', 'bulk-actions-manager' ) );
		}

		$filename = basename( $file_path );
		$mime     = wp_check_filetype( $filename );
		$type     = $mime['type'] ?: 'application/octet-stream';

		nocache_headers();
		header( 'Content-Type: ' . $type );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );
		exit;
	}
}
